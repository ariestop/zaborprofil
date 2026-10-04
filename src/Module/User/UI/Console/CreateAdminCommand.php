<?php

declare(strict_types=1);

namespace App\Module\User\UI\Console;

use App\Module\User\Application\Service\AdminUserService;
use App\Module\User\Application\Service\EnsureAdminResult;
use App\Module\User\Domain\Exception\UserManagementException;
use App\Module\User\Infrastructure\Repository\AdminUserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:create-admin', description: 'Создать администратора (идемпотентно) или сбросить ему пароль.')]
final class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly AdminUserService $service,
        private readonly AdminUserRepository $users,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email администратора.')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Пароль (небезопасно: виден в истории shell; лучше интерактивный ввод или переменная ADMIN_PASSWORD).')
            ->addOption('super', null, InputOption::VALUE_NONE, 'Выдать ROLE_SUPER_ADMIN вместо ROLE_ADMIN.')
            ->addOption('reset-password', null, InputOption::VALUE_NONE, 'Сменить пароль существующего пользователя.')
            ->setHelp(<<<'HELP'
Создаёт администратора с указанным email. Если пользователь уже существует, команда ничего не меняет
(повторный запуск безопасен); при необходимости она лишь активирует учётную запись и добавляет административную роль.
Пароль существующего пользователя меняется только с --reset-password.

Пароль берётся из --password, переменной окружения ADMIN_PASSWORD или запрашивается интерактивно (ввод скрыт).
Минимальная длина пароля — 12 символов.
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $emailArgument = $input->getArgument('email');
        $email = \is_string($emailArgument) ? $emailArgument : '';
        $reset = $input->getOption('reset-password') === true;
        $super = $input->getOption('super') === true;

        $exists = $this->users->findOneByEmail($email) !== null;
        $password = null;
        if (!$exists || $reset) {
            $password = ConsolePasswordReader::read($input, $io);
            if ($password === null) {
                $io->error('Пароль не задан. Передайте --password, переменную ADMIN_PASSWORD или запустите команду интерактивно.');

                return Command::FAILURE;
            }
        }

        try {
            $result = $this->service->ensureAdmin($email, $password, $reset, $super);
        } catch (UserManagementException $exception) {
            $io->error($this->formatError($exception));

            return Command::FAILURE;
        }

        match ($result) {
            EnsureAdminResult::Created => $io->success(\sprintf('Администратор %s создан.', mb_strtolower(trim($email)))),
            EnsureAdminResult::Updated => $io->success(\sprintf('Администратор %s обновлён.', mb_strtolower(trim($email)))),
            EnsureAdminResult::Unchanged => $io->note(\sprintf('Администратор %s уже существует, изменений нет.', mb_strtolower(trim($email)))),
        };

        return Command::SUCCESS;
    }

    private function formatError(UserManagementException $exception): string
    {
        $messages = array_map(static fn (array $detail): string => $detail['message'], $exception->details);

        return $messages === [] ? $exception->getMessage() : implode("\n", $messages);
    }
}
