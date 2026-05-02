<?php

declare(strict_types=1);

namespace App\Module\Settings\Infrastructure\Doctrine\Repository;

use App\Module\Settings\Domain\Entity\Setting;
use App\Module\Settings\Domain\Repository\SettingRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Setting>
 */
final class DoctrineSettingRepository extends ServiceEntityRepository implements SettingRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Setting::class);
    }

    public function save(Setting $setting): void
    {
        $this->getEntityManager()->persist($setting);
        $this->getEntityManager()->flush();
    }

    public function remove(Setting $setting): void
    {
        $this->getEntityManager()->remove($setting);
        $this->getEntityManager()->flush();
    }

    public function findOne(string $scope, string $key): ?Setting
    {
        return $this->findOneBy([
            'scope' => $scope,
            'key' => $key,
        ]);
    }

    public function findByScope(?string $scope = null): array
    {
        $builder = $this->createQueryBuilder('setting')
            ->orderBy('setting.scope', 'ASC')
            ->addOrderBy('setting.key', 'ASC');

        if ($scope !== null) {
            $builder
                ->andWhere('setting.scope = :scope')
                ->setParameter('scope', $scope);
        }

        /** @var list<Setting> $result */
        $result = $builder->getQuery()->getResult();

        return $result;
    }
}
