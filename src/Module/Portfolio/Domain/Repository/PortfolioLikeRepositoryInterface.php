<?php

declare(strict_types=1);

namespace App\Module\Portfolio\Domain\Repository;

use App\Module\Portfolio\Domain\Entity\PortfolioLike;
use App\Module\Portfolio\Domain\Entity\PortfolioProject;

interface PortfolioLikeRepositoryInterface
{
    public function save(PortfolioLike $like): void;

    public function remove(PortfolioLike $like): void;

    public function findByVisitor(PortfolioProject $project, string $visitorHash): ?PortfolioLike;

    public function countByIp(PortfolioProject $project, string $ipHash): int;

    /**
     * @param list<string> $projectIds ULID работ
     *
     * @return array<string, int> id работы => число лайков (работы без лайков не попадают)
     */
    public function countByProjects(array $projectIds): array;

    /**
     * @param list<string> $projectIds ULID работ
     *
     * @return list<string> id работ из $projectIds, которые лайкнул посетитель
     */
    public function likedByVisitor(array $projectIds, string $visitorHash): array;
}
