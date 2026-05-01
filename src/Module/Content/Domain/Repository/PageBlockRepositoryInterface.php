<?php

declare(strict_types=1);

namespace App\Module\Content\Domain\Repository;

use App\Module\Content\Domain\Entity\PageBlock;
use Symfony\Component\Uid\Ulid;

interface PageBlockRepositoryInterface
{
    public function save(PageBlock $block): void;

    public function remove(PageBlock $block): void;

    public function get(Ulid $id): PageBlock;

    public function findById(Ulid $id): ?PageBlock;

    /**
     * @return list<PageBlock>
     */
    public function findByPage(Ulid $pageId): array;
}
