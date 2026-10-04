<?php

declare(strict_types=1);

namespace App\Module\User\UI\Console;

use App\Module\User\Application\Service\AdminUserService;
use App\Module\User\Domain\Exception\UserManagementException;
use App\Module\User\Infrastructure\Repository\AdminUserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:change-password', description: 'Сменить (сбросить) пароль пользователя админки.')]
final class ChangePasswordCommand extends Command
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
            ->addArgument('email', InputArgument::REQUIRED, 'Email пользователя.')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Новый пароль (лучше интерактивный ввод или переменная ADMIN_PASSWORD).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $emailArgument = $input->getArgument('email');
        $email = \is_string($emailArgument) ? $emailArgument : '';

        $user = $this->users->findOneByEmail($email);
        if ($user === null) {
            $io->error(\sprintf('Пользователь %s не найден.', $email));

            return Command::FAILURE;
        }

        $password = ConsolePasswordReader::read($input, $io, 'Новый пароль');
        if ($password === null) {
            $io->error('Пароль не задан. Передайте --password, переменную ADMIN_PASSWORD или запустите команду интерактивно.');

            return Command::FAILURE;
        }

        try {
            $this->service->changePassword($user, $password);
        } catch (UserManagementException $exception) {
            $messages = array_map(static fn (array $detail): string => $detail['message'], $exception->details);
            $io->error($messages === [] ? $exception->getMessage() : implode("\n", $messages));

            return Command::FAILURE;
        }

        $io->success(\sprintf('Пароль пользователя %s изменён.', $user->email()));

        return Command::SUCCESS;
    }
}
