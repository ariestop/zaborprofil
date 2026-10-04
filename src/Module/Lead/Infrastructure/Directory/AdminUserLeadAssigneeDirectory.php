<?php

declare(strict_types=1);

namespace App\Module\Lead\Infrastructure\Directory;

use App\Module\Lead\Domain\Repository\LeadAssigneeDirectoryInterface;
use App\Module\Lead\Domain\ValueObject\LeadAssignee;
use App\Module\User\Domain\AdminRole;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Module\User\Infrastructure\Repository\AdminUserRepository;
use Doctrine\DBAL\ArrayParameterType;
use Symfony\Component\Uid\Ulid;

final readonly class AdminUserLeadAssigneeDirectory implements LeadAssigneeDirectoryInterface
{
    /**
     * Роли, которым доступна работа с заявками (`leads.view`).
     *
     * @var list<string>
     */
    private const array LEAD_ROLES = [AdminRole::MANAGER, AdminRole::ADMIN, AdminRole::SUPER_ADMIN];

    public function __construct(private AdminUserRepository $users)
    {
    }

    public function findAssignable(string $id): ?LeadAssignee
    {
        if (!Ulid::isValid($id)) {
            return null;
        }

        $user = $this->users->find(Ulid::fromString($id));

        return $user instanceof AdminUser && $this->isAssignable($user) ? self::toAssignee($user) : null;
    }

    public function findMany(array $ids): array
    {
        $ulids = [];
        foreach ($ids as $id) {
            if (Ulid::isValid($id)) {
                $ulids[] = Ulid::fromString($id)->toBinary();
            }
        }

        if ($ulids === []) {
            return [];
        }

        /** @var list<AdminUser> $users */
        $users = $this->users->createQueryBuilder('user')
            ->where('user.id IN (:ids)')
            ->setParameter('ids', $ulids, ArrayParameterType::BINARY)
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($users as $user) {
            $assignee = self::toAssignee($user);
            $result[$assignee->id] = $assignee;
        }

        return $result;
    }

    public function assignable(): array
    {
        $assignees = [];
        foreach ($this->users->findAllActive() as $user) {
            if ($this->isAssignable($user)) {
                $assignees[] = self::toAssignee($user);
            }
        }

        usort($assignees, static fn (LeadAssignee $a, LeadAssignee $b): int => $a->email <=> $b->email);

        return $assignees;
    }

    private function isAssignable(AdminUser $user): bool
    {
        return $user->isActive() && array_any(
            $user->storedRoles(),
            static fn (string $role): bool => \in_array($role, self::LEAD_ROLES, true),
        );
    }

    private static function toAssignee(AdminUser $user): LeadAssignee
    {
        return new LeadAssignee((string) $user->id(), $user->email());
    }
}
