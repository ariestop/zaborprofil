<?php

declare(strict_types=1);

// CLI-only: secrets are read from the process environment and never printed.
function bootstrapError(string $message): never
{
    fwrite(STDERR, $message."\n");
    exit(1);
}

$database = getenv('DEV2_DATABASE_URL') ?: '';
$auth = getenv('DEV2_BASIC_AUTH') ?: '';
$destination = $argv[1] ?? '';
try {
    $parts = @parse_url($database);
} catch (ValueError) {
    bootstrapError('Некорректный DEV2_DATABASE_URL.');
}
if ($destination === '' || !is_array($parts) || ($parts['scheme'] ?? '') !== 'mysql'
    || empty($parts['host']) || empty($parts['user']) || empty($parts['path'])
    || preg_match("/['\\r\\n]/", $database) === 1) {
    bootstrapError('DEV2_DATABASE_URL должен содержать корректный MySQL URL; спецсимволы кодируются в URL.');
}
$credentials = explode(':', $auth, 2);
if (count($credentials) !== 2 || $credentials[0] === '' || $credentials[1] === ''
    || preg_match("/['\\r\\n]/", $credentials[0]) === 1) {
    bootstrapError('Для первого запуска нужен DEV2_STAGING_BASIC_AUTH или существующий STAGING_BASIC_AUTH.');
}
$hash = password_hash($credentials[1], PASSWORD_BCRYPT);
$secret = bin2hex(random_bytes(32));
$config = [
    'APP_ENV=staging',
    'APP_DEBUG=0',
    'APP_SECRET='.$secret,
    'APP_SHARE_DIR=var/share',
    "DATABASE_URL='".$database."'",
    'MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0',
    'MAILER_DSN=null://null',
    'LEAD_NOTIFICATION_EMAIL=',
    'LEAD_TELEGRAM_BOT_TOKEN=',
    'LEAD_TELEGRAM_CHAT_ID=',
    'TELEGRAM_BOT_TOKEN=',
    'TELEGRAM_CHAT_ID=',
    'SENTRY_DSN=',
    'SITE_URL=https://dev2.zaborprofil.ru',
    'DEFAULT_URI=https://dev2.zaborprofil.ru',
    'STAGING_AUTH_ENABLED=1',
    "STAGING_AUTH_USER='".$credentials[0]."'",
    "STAGING_AUTH_HASH='".$hash."'",
    'ADMIN_WEB_MIGRATIONS_ENABLED=0',
];
umask(0077);
if (file_put_contents($destination, implode("\n", $config)."\n") === false) {
    bootstrapError('Не удалось подготовить конфигурацию dev2.');
}
chmod($destination, 0600);
