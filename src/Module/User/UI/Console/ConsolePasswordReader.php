<?php

declare(strict_types=1);

namespace App\Module\User\UI\Console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ConsolePasswordReader
{
    public const string ENV_NAME = 'ADMIN_PASSWORD';

    /**
     * Порядок источников: опция --password, переменная окружения ADMIN_PASSWORD, интерактивный ввод.
     */
    public static function read(InputInterface $input, SymfonyStyle $io, string $label = 'Пароль'): ?string
    {
        $option = $input->getOption('password');
        if (\is_string($option) && $option !== '') {
            return $option;
        }

        $fromEnv = getenv(self::ENV_NAME);
        if (\is_string($fromEnv) && $fromEnv !== '') {
            return $fromEnv;
        }

        if (!$input->isInteractive()) {
            return null;
        }

        $question = (new Question($label))->setHidden(true)->setHiddenFallback(false);
        $password = $io->askQuestion($question);
        if (!\is_string($password) || $password === '') {
            return null;
        }

        $confirmation = $io->askQuestion((new Question('Повторите пароль'))->setHidden(true)->setHiddenFallback(false));
        if ($confirmation !== $password) {
            $io->error('Пароли не совпадают.');

            return null;
        }

        return $password;
    }
}
