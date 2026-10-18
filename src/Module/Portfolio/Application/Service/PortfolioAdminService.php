<?php

declare(strict_types=1);

namespace App\Module\Portfolio\Application\Service;

use App\Module\Content\Application\Service\PublicHttpCachePurgerInterface;
use App\Module\Portfolio\Application\Command\PortfolioCategoryInput;
use App\Module\Portfolio\Application\Command\PortfolioMediaInput;
use App\Module\Portfolio\Application\Command\PortfolioProjectInput;
use App\Module\Portfolio\Domain\Entity\PortfolioCategory;
use App\Module\Portfolio\Domain\Entity\PortfolioProject;
use App\Module\Portfolio\Domain\Enum\PortfolioMediaKind;
use App\Module\Portfolio\Domain\Repository\PortfolioCategoryRepositoryInterface;
use App\Module\Portfolio\Domain\Repository\PortfolioProjectRepositoryInterface;
use App\Module\Portfolio\Domain\ValueObject\PortfolioMediaDraft;
use App\Shared\Application\Transaction\TransactionRunnerInterface;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/**
 * Портфолио в админке: категории и работы, их порядок, медиа и публикация.
 * Каждая операция — одна транзакция; HTTP-кэш сайта сбрасывается после фиксации (работы видны в блоках на страницах).
 */
final readonly class PortfolioAdminService
{
    private const int SORT_STEP = 10;

    public function __construct(
        private PortfolioCategoryRepositoryInterface $categories,
        private PortfolioProjectRepositoryInterface $projects,
        private TransactionRunnerInterface $transactions,
        private PublicHttpCachePurgerInterface $httpCachePurger,
        #[Target('app.rich_text_sanitizer')]
        private HtmlSanitizerInterface $sanitizer,
    ) {
    }

    public function createCategory(PortfolioCategoryInput $input): PortfolioCategory
    {
        return $this->transactions->run(function () use ($input): PortfolioCategory {
            $this->assertCategorySlugFree($input->slug, null);
            $last = array_reverse($this->categories->findAllOrdered())[0] ?? null;
            $category = new PortfolioCategory($input->title, $input->slug, $input->shortTitle, $input->description, ($last?->sortOrder() ?? 0) + self::SORT_STEP);
            $this->categories->save($category);
            $this->purgeAfterCommit();

            return $category;
        });
    }

    public function updateCategory(string $id, PortfolioCategoryInput $input): PortfolioCategory
    {
        return $this->transactions->run(function () use ($id, $input): PortfolioCategory {
            $category = $this->categories->get($id);
            $this->assertCategorySlugFree($input->slug, $category);
            $category->edit($input->title, $input->slug, $input->shortTitle, $input->description);
            $this->categories->save($category);
            $this->purgeAfterCommit();

            return $category;
        });
    }

    public function deleteCategory(string $id): void
    {
        $this->transactions->run(function () use ($id): void {
            $category = $this->categories->get($id);
            if ($this->projects->countByCategory($category) > 0) {
                throw new InvalidArgumentException('В категории есть работы: сначала перенесите их в другую категорию или удалите.');
            }
            $this->categories->remove($category);
            $this->purgeAfterCommit();
        });
    }

    /**
     * @param list<string> $ids все категории в новом порядке
     *
     * @return list<PortfolioCategory>
     */
    public function reorderCategories(array $ids): array
    {
        return $this->transactions->run(function () use ($ids): array {
            $all = $this->categories->findAllOrdered();
            $byId = [];
            foreach ($all as $category) {
                $byId[(string) $category->id()] = $category;
            }
            self::assertSameSet($ids, array_keys($byId));
            foreach ($ids as $index => $id) {
                $byId[$id]->moveTo(($index + 1) * self::SORT_STEP);
                $this->categories->save($byId[$id]);
            }
            $this->purgeAfterCommit();

            return $this->categories->findAllOrdered();
        });
    }

    public function createProject(PortfolioProjectInput $input): PortfolioProject
    {
        return $this->transactions->run(function () use ($input): PortfolioProject {
            $category = $this->categories->get($input->categoryId);
            $siblings = $this->projects->findByCategory($category);
            $last = $siblings === [] ? 0 : max(array_map(static fn (PortfolioProject $project): int => $project->sortOrder(), $siblings));
            $project = new PortfolioProject($category, $input->title, $input->slug, $last + self::SORT_STEP);
            $this->fill($project, $input);
            $this->projects->save($project);
            $this->purgeAfterCommit();

            return $project;
        });
    }

    public function updateProject(string $id, PortfolioProjectInput $input): PortfolioProject
    {
        return $this->transactions->run(function () use ($id, $input): PortfolioProject {
            $project = $this->projects->get($id);
            $previousCategory = $project->category();
            $this->fill($project, $input);
            if ($previousCategory !== $project->category()) {
                $siblings = $this->projects->findByCategory($project->category());
                $project->moveTo(($siblings === [] ? 0 : max(array_map(static fn (PortfolioProject $p): int => $p->sortOrder(), $siblings))) + self::SORT_STEP);
            }
            $this->projects->save($project);
            $this->purgeAfterCommit();

            return $project;
        });
    }

    public function deleteProject(string $id): void
    {
        $this->transactions->run(function () use ($id): void {
            $this->projects->remove($this->projects->get($id));
            $this->purgeAfterCommit();
        });
    }

    public function setPublished(string $id, bool $published): PortfolioProject
    {
        return $this->transactions->run(function () use ($id, $published): PortfolioProject {
            $project = $this->projects->get($id);
            $published ? $project->publish() : $project->unpublish();
            $this->projects->save($project);
            $this->purgeAfterCommit();

            return $project;
        });
    }

    /**
     * @param list<string> $ids все работы категории в новом порядке
     *
     * @return list<PortfolioProject>
     */
    public function reorderProjects(string $categoryId, array $ids): array
    {
        return $this->transactions->run(function () use ($categoryId, $ids): array {
            $category = $this->categories->get($categoryId);
            $byId = [];
            foreach ($this->projects->findByCategory($category) as $project) {
                $byId[(string) $project->id()] = $project;
            }
            self::assertSameSet($ids, array_keys($byId));
            foreach ($ids as $index => $id) {
                $byId[$id]->moveTo(($index + 1) * self::SORT_STEP);
                $this->projects->save($byId[$id]);
            }
            $this->purgeAfterCommit();

            return $this->projects->findByCategory($category);
        });
    }

    private function fill(PortfolioProject $project, PortfolioProjectInput $input): void
    {
        $category = $this->categories->get($input->categoryId);
        if ($this->projects->slugTaken($category, trim($input->slug, '/ '), (string) $project->id())) {
            throw new InvalidArgumentException('В этой категории уже есть работа с таким адресом.');
        }
        $project->edit(
            $category,
            $input->title,
            $input->slug,
            $input->place,
            $input->year,
            $input->summary,
            $this->sanitizer->sanitize($input->description),
            $input->specs,
            $input->seoTitle,
            $input->seoDescription,
        );
        $project->replaceMedia(array_map(
            static fn (PortfolioMediaInput $media): PortfolioMediaDraft => new PortfolioMediaDraft(
                PortfolioMediaKind::tryFrom($media->kind) ?? throw new InvalidArgumentException('Неизвестный вид медиа: photo, video или embed.'),
                $media->src,
                $media->poster,
                $media->caption,
                $media->cover,
            ),
            $input->media,
        ));
    }

    private function assertCategorySlugFree(string $slug, ?PortfolioCategory $current): void
    {
        $existing = $this->categories->findBySlug(strtolower(trim($slug, '/ ')));
        if ($existing !== null && $existing !== $current) {
            throw new InvalidArgumentException('Категория с таким адресом уже есть.');
        }
    }

    /**
     * @param list<string> $requested
     * @param list<string> $existing
     */
    private static function assertSameSet(array $requested, array $existing): void
    {
        if (\count($requested) !== \count(array_unique($requested)) || array_diff($requested, $existing) !== [] || array_diff($existing, $requested) !== []) {
            throw new InvalidArgumentException('Список изменился в другой вкладке или у другого пользователя. Обновите страницу и повторите.');
        }
    }

    private function purgeAfterCommit(): void
    {
        $this->transactions->afterCommit(fn () => $this->httpCachePurger->purgeAll());
    }
}
