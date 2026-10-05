<?php

declare(strict_types=1);

namespace App\Module\User\Application\Service;

use App\Module\User\Domain\AdminRole;
use App\Module\User\Domain\Exception\UserManagementException;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Module\User\Infrastructure\Repository\AdminUserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class AdminUserService
{
    public const int PASSWORD_MIN_LENGTH = 12;
    public const int PASSWORD_MAX_LENGTH = 128;
    public const int NAME_MAX_LENGTH = 120;

    public function __construct(
        private AdminUserRepository $users,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * @param list<string> $roles
     */
    public function create(string $email, string $plainPassword, array $roles, ?string $name = null): AdminUser
    {
        $email = mb_strtolower(trim($email));
        $roles = $this->normalizeRoles($roles);

        $this->assertValid([
            ...$this->emailErrors($email),
            ...$this->passwordErrors('password', $plainPassword, $email),
            ...$this->nameErrors($name),
        ]);

        if ($this->users->findOneByEmail($email) instanceof AdminUser) {
            throw UserManagementException::conflict($email);
        }

        $user = new AdminUser($email, '', $roles);
        $user->changePasswordHash($this->passwordHasher->hashPassword($user, $plainPassword));
        if ($name !== null) {
            $user->rename($name);
        }
        $this->users->save($user);

        return $user;
    }

    /**
     * Пустое имя очищает поле.
     */
    public function rename(AdminUser $user, ?string $name): void
    {
        $this->assertValid($this->nameErrors($name));

        $user->rename($name);
        $this->users->save($user);
    }

    /**
     * Идемпотентно гарантирует наличие активного администратора.
     * Существующему пользователю пароль меняется только при $resetPassword.
     */
    public function ensureAdmin(string $email, ?string $plainPassword, bool $resetPassword = false, bool $superAdmin = false): EnsureAdminResult
    {
        $email = mb_strtolower(trim($email));
        $user = $this->users->findOneByEmail($email);

        if (!$user instanceof AdminUser) {
            if ($plainPassword === null) {
                throw UserManagementException::validation('Для нового пользователя нужен пароль.', [
                    ['field' => 'password', 'message' => 'Пароль обязателен для нового пользователя.'],
                ]);
            }

            $this->create($email, $plainPassword, $superAdmin ? [AdminRole::SUPER_ADMIN] : [AdminRole::ADMIN]);

            return EnsureAdminResult::Created;
        }

        $changed = false;
        $storedRoles = $user->storedRoles();
        $wanted = $superAdmin ? AdminRole::SUPER_ADMIN : AdminRole::ADMIN;

        if ($superAdmin && !\in_array(AdminRole::SUPER_ADMIN, $storedRoles, true)) {
            $user->updateRoles([...$storedRoles, $wanted]);
            $changed = true;
        } elseif (!AdminRole::hasAdministrative($storedRoles)) {
            $user->updateRoles([...$storedRoles, $wanted]);
            $changed = true;
        }

        if (!$user->isActive()) {
            $user->activate();
            $changed = true;
        }

        if ($resetPassword) {
            if ($plainPassword === null) {
                throw UserManagementException::validation('Для сброса нужен новый пароль.', [
                    ['field' => 'password', 'message' => 'Укажите новый пароль.'],
                ]);
            }

            $this->applyPassword($user, $plainPassword);
            $changed = true;
        }

        if ($changed) {
            $this->users->save($user);
        }

        return $changed ? EnsureAdminResult::Updated : EnsureAdminResult::Unchanged;
    }

    public function changePassword(AdminUser $user, string $plainPassword): void
    {
        $this->applyPassword($user, $plainPassword);
        $this->users->save($user);
    }

    public function changeOwnPassword(AdminUser $user, string $currentPassword, string $newPassword): void
    {
        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            throw UserManagementException::invalidCurrentPassword();
        }

        if ($currentPassword === $newPassword) {
            throw UserManagementException::validation('Новый пароль совпадает с текущим.', [
                ['field' => 'password', 'message' => 'Новый пароль должен отличаться от текущего.'],
            ]);
        }

        $this->changePassword($user, $newPassword);
    }

    /**
     * @param list<string> $roles
     */
    public function updateRoles(AdminUser $actor, AdminUser $target, array $roles): void
    {
        $roles = $this->normalizeRoles($roles);
        $currentRoles = $target->storedRoles();
        $isSelf = $this->isSame($actor, $target);

        $removedAdministrative = array_diff(
            array_intersect($currentRoles, AdminRole::administrative()),
            $roles,
        );
        if ($isSelf && $removedAdministrative !== []) {
            throw UserManagementException::selfLockout('Нельзя снять с себя административную роль.');
        }

        $changedSuperAdmin = \in_array(AdminRole::SUPER_ADMIN, $currentRoles, true) !== \in_array(AdminRole::SUPER_ADMIN, $roles, true);
        if ($changedSuperAdmin && !\in_array(AdminRole::SUPER_ADMIN, $actor->storedRoles(), true)) {
            throw UserManagementException::roleNotAllowed('Назначать и снимать ROLE_SUPER_ADMIN может только суперадминистратор.');
        }

        $this->assertAdministratorsRemain($target, $roles, $target->isActive());

        $target->updateRoles($roles);
        $this->users->save($target);
    }

    public function setActive(AdminUser $actor, AdminUser $target, bool $active): void
    {
        if (!$active) {
            if ($this->isSame($actor, $target)) {
                throw UserManagementException::selfLockout('Нельзя деактивировать собственную учётную запись.');
            }

            $this->assertAdministratorsRemain($target, $target->storedRoles(), false);
        }

        $active ? $target->activate() : $target->deactivate();
        $this->users->save($target);
    }

    public function delete(AdminUser $actor, AdminUser $target): void
    {
        if ($this->isSame($actor, $target)) {
            throw UserManagementException::selfLockout('Нельзя удалить собственную учётную запись.');
        }

        $this->assertAdministratorsRemain($target, [], false);

        $this->users->remove($target);
    }

    /**
     * @param list<string> $newRoles
     */
    private function assertAdministratorsRemain(AdminUser $target, array $newRoles, bool $newActive): void
    {
        $others = array_filter(
            $this->users->findAllActive(),
            fn (AdminUser $user): bool => !$this->isSame($user, $target),
        );

        $administrators = array_filter($others, static fn (AdminUser $user): bool => AdminRole::hasAdministrative($user->storedRoles()));
        $superAdmins = array_filter($others, static fn (AdminUser $user): bool => \in_array(AdminRole::SUPER_ADMIN, $user->storedRoles(), true));

        $targetStaysAdministrator = $newActive && AdminRole::hasAdministrative($newRoles);
        $targetStaysSuperAdmin = $newActive && \in_array(AdminRole::SUPER_ADMIN, $newRoles, true);

        if (!$targetStaysAdministrator && $administrators === []) {
            throw UserManagementException::lastAdmin('Нельзя остаться без активного администратора.');
        }

        $targetWasSuperAdmin = $target->isActive() && \in_array(AdminRole::SUPER_ADMIN, $target->storedRoles(), true);
        if ($targetWasSuperAdmin && !$targetStaysSuperAdmin && $superAdmins === []) {
            throw UserManagementException::lastAdmin('Нельзя остаться без активного суперадминистратора.');
        }
    }

    private function applyPassword(AdminUser $user, string $plainPassword): void
    {
        $this->assertValid($this->passwordErrors('password', $plainPassword, $user->email()));
        $user->changePasswordHash($this->passwordHasher->hashPassword($user, $plainPassword));
    }

    /**
     * @param list<string> $roles
     *
     * @return non-empty-list<string>
     */
    private function normalizeRoles(array $roles): array
    {
        $normalized = array_values(array_unique(array_map(trim(...), $roles)));
        $normalized = array_values(array_filter($normalized, static fn (string $role): bool => $role !== ''));

        if ($normalized === []) {
            throw UserManagementException::validation('Нужно выбрать хотя бы одну роль.', [
                ['field' => 'roles', 'message' => 'Нужно выбрать хотя бы одну роль.'],
            ]);
        }

        $unknown = array_values(array_filter($normalized, static fn (string $role): bool => !AdminRole::isKnown($role)));
        if ($unknown !== []) {
            $message = \sprintf('Неизвестные роли: %s. Допустимые: %s.', implode(', ', $unknown), implode(', ', AdminRole::all()));

            throw UserManagementException::validation($message, [['field' => 'roles', 'message' => $message]]);
        }

        return $normalized;
    }

    private function isSame(AdminUser $left, AdminUser $right): bool
    {
        return $left->id()->equals($right->id());
    }

    /**
     * @return list<array{field: string, message: string}>
     */
    private function emailErrors(string $email): array
    {
        return $this->violations('email', $email, [
            new Assert\NotBlank(message: 'Укажите email.'),
            new Assert\Email(message: 'Укажите корректный email.'),
            new Assert\Length(max: 180, maxMessage: 'Email не должен быть длиннее {{ limit }} символов.'),
        ]);
    }

    /**
     * @return list<array{field: string, message: string}>
     */
    private function nameErrors(?string $name): array
    {
        if ($name === null) {
            return [];
        }

        return $this->violations('name', trim($name), [
            new Assert\Length(max: self::NAME_MAX_LENGTH, maxMessage: 'Имя не должно быть длиннее {{ limit }} символов.'),
        ]);
    }

    /**
     * @return list<array{field: string, message: string}>
     */
    private function passwordErrors(string $field, string $password, string $email): array
    {
        $errors = $this->violations($field, $password, [
            new Assert\NotBlank(message: 'Укажите пароль.'),
            new Assert\Length(
                min: self::PASSWORD_MIN_LENGTH,
                max: self::PASSWORD_MAX_LENGTH,
                minMessage: 'Пароль должен содержать не менее {{ limit }} символов.',
                maxMessage: 'Пароль не должен быть длиннее {{ limit }} символов.',
            ),
        ]);

        if ($errors === [] && mb_strtolower($password) === $email) {
            $errors[] = ['field' => $field, 'message' => 'Пароль не должен совпадать с email.'];
        }

        return $errors;
    }

    /**
     * @param list<Constraint> $constraints
     *
     * @return list<array{field: string, message: string}>
     */
    private function violations(string $field, string $value, array $constraints): array
    {
        $errors = [];
        foreach ($this->validator->validate($value, $constraints) as $violation) {
            $errors[] = ['field' => $field, 'message' => (string) $violation->getMessage()];
        }

        return $errors;
    }

    /**
     * @param list<array{field: string, message: string}> $errors
     */
    private function assertValid(array $errors): void
    {
        if ($errors !== []) {
            throw UserManagementException::validation('Validation failed.', $errors);
        }
    }
}
