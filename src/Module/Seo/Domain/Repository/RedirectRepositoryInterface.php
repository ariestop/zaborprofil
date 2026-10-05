<?php

declare(strict_types=1);

namespace App\Module\Seo\Domain\Repository;

use App\Module\Seo\Domain\Entity\Redirect;

interface RedirectRepositoryInterface
{
    public function save(Redirect $redirect): void;

    /**
     * Сохраняет набор правил одной транзакцией.
     *
     * @param list<Redirect> $redirects
     */
    public function saveAll(array $redirects): void;

    public function remove(Redirect $redirect): void;

    public function findById(string $id): ?Redirect;

    public function findActiveBySourcePath(string $sourcePath): ?Redirect;

    public function findBySourcePath(string $sourcePath): ?Redirect;

    /**
     * @return list<Redirect>
     */
    public function findAllOrdered(): array;

    /**
     * @return list<Redirect>
     */
    public function findAllActive(): array;

    public function search(RedirectSearchCriteria $criteria): RedirectSearchResult;

    /**
     * @param list<string> $sourcePaths
     *
     * @return list<string> источники из переданного списка, для которых уже есть активное правило
     */
    public function findActiveSourcePaths(array $sourcePaths): array;

    /**
     * @return array{total: int, active: int}
     */
    public function counts(): array;
}
