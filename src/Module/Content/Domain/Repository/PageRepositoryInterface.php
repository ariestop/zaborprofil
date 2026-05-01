<?php

declare(strict_types=1);

namespace App\Module\Content\Domain\Repository;

use App\Module\Content\Domain\Entity\Page;
use Symfony\Component\Uid\Ulid;

interface PageRepositoryInterface
{
    public function save(Page $page): void;

    public function get(Ulid $id): Page;

    public function findById(Ulid $id): ?Page;

    public function findOneByPath(string $path): ?Page;

    public function findPublishedByPath(string $path): ?Page;

    public function existsByPath(string $path, ?Ulid $excludeId = null): bool;
}
