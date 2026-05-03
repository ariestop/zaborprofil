<?php

declare(strict_types=1);

namespace App\Module\User\Infrastructure\Repository;

use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @extends ServiceEntityRepository<AdminUser>
 * @implements UserProviderInterface<AdminUser>
 */
final class AdminUserRepository extends ServiceEntityRepository implements UserProviderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminUser::class);
    }

    public function save(AdminUser $user): void
    {
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->findOneBy(['email' => mb_strtolower(trim($identifier))]);

        if (!$user instanceof AdminUser) {
            $exception = new UserNotFoundException(\sprintf('Admin user "%s" was not found.', $identifier));
            $exception->setUserIdentifier($identifier);

            throw $exception;
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof AdminUser) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', get_debug_type($user)));
        }

        $refreshedUser = $this->findOneBy(['email' => $user->email()]);

        if (!$refreshedUser instanceof AdminUser) {
            $exception = new UserNotFoundException(\sprintf('Admin user "%s" was not found.', $user->email()));
            $exception->setUserIdentifier($user->email());

            throw $exception;
        }

        return $refreshedUser;
    }

    public function supportsClass(string $class): bool
    {
        return $class === AdminUser::class || is_subclass_of($class, AdminUser::class);
    }
}
