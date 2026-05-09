<?php

declare(strict_types=1);

use App\Kernel;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use Doctrine\Persistence\ManagerRegistry;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$environment = getenv('APP_ENV') ?: 'test';
$debug = filter_var(getenv('APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOL);
$email = (string) (getenv('E2E_ADMIN_EMAIL') ?: 'e2e-admin@example.test');
$password = (string) (getenv('E2E_ADMIN_PASSWORD') ?: 'e2e-admin-password');

$kernel = new Kernel($environment, $debug);
$kernel->boot();

$container = $kernel->getContainer();

/** @var ManagerRegistry $doctrine */
$doctrine = $container->get('doctrine');
$entityManager = $doctrine->getManagerForClass(AdminUser::class);
if ($entityManager === null) {
    throw new RuntimeException('Could not resolve entity manager for AdminUser.');
}

$repository = $entityManager->getRepository(AdminUser::class);
$existing = $repository->findOneBy(['email' => mb_strtolower(trim($email))]);
if ($existing instanceof AdminUser) {
    $entityManager->remove($existing);
    $entityManager->flush();
}

$hash = password_hash($password, PASSWORD_BCRYPT);
if (!is_string($hash)) {
    throw new RuntimeException('Could not hash E2E admin password.');
}
$seedUser = new AdminUser($email, $hash, ['ROLE_SUPER_ADMIN']);
$entityManager->persist($seedUser);
$entityManager->flush();

fwrite(STDOUT, sprintf("Seeded E2E admin user: %s\n", $seedUser->getUserIdentifier()));

$kernel->shutdown();
