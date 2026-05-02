<?php

declare(strict_types=1);

namespace App\Module\Seo\Domain\Repository;

use App\Module\Seo\Domain\Entity\Redirect;

interface RedirectRepositoryInterface
{
    public function save(Redirect $redirect): void;

    public function findActiveBySourcePath(string $sourcePath): ?Redirect;

    public function findBySourcePath(string $sourcePath): ?Redirect;

    /**
     * @return list<Redirect>
     */
    public function findAllOrdered(): array;
}
