<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content;

use App\Module\Content\Application\Service\Diff\PageRevisionDiffer;
use App\Module\Content\Application\Service\Diff\TextDiffer;
use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Entity\PageRevision;
use App\Module\Content\Domain\Enum\PageType;
use PHPUnit\Framework\TestCase;

final class PageRevisionDifferTest extends TestCase
{
    public function testIdenticalRevisionsHaveNoChangesEvenWithDifferentBlockIdsAndStatuses(): void
    {
        $from = $this->revision(blocks: [$this->block('id-1', 'hero', 'Hero', 0, ['title' => 'Забор'])], status: 'draft');
        $to = $this->revision(blocks: [$this->block('id-2', 'hero', 'Hero', 0, ['title' => 'Забор'])], status: 'published');

        $diff = $this->differ()->diff($from, $to);

        self::assertFalse($diff['hasChanges']);
        self::assertSame('unchanged', $diff['blocks'][0]['status']);
    }

    public function testFieldAndSeoChangesAreReportedWithTextDiff(): void
    {
        $from = $this->revision(title: 'Забор из профнастила', seo: ['metaDescription' => 'Старое описание', 'isIndexable' => true]);
        $to = $this->revision(title: 'Забор из штакетника', seo: ['metaDescription' => 'Новое описание', 'isIndexable' => false]);

        $diff = $this->differ()->diff($from, $to);

        self::assertTrue($diff['hasChanges']);
        $titleChange = $diff['fields'][0];
        self::assertSame('title', $titleChange['field']);
        self::assertArrayHasKey('textDiff', $titleChange);
        self::assertSame('delete', $titleChange['textDiff'][1]['op']);
        self::assertSame(['metaDescription', 'isIndexable'], array_column($diff['seo'], 'field'));
        self::assertArrayNotHasKey('textDiff', $diff['seo'][1]);
        self::assertSame(2, $diff['summary']['seo']);
    }

    public function testBlockAddedRemovedAndChangedAreMatchedByTypeAndNameWhenIdsDiffer(): void
    {
        $from = $this->revision(blocks: [
            $this->block('a', 'hero', 'Hero', 0, ['title' => 'Старый', 'items' => [['text' => 'один']]]),
            $this->block('b', 'faq', 'FAQ', 1, ['q' => 'Вопрос']),
        ]);
        $to = $this->revision(blocks: [
            $this->block('x', 'hero', 'Hero', 0, ['title' => 'Новый', 'items' => [['text' => 'один']]]),
            $this->block('y', 'cta', 'CTA', 1, ['title' => 'Заказать']),
        ]);

        $diff = $this->differ()->diff($from, $to);

        self::assertSame(['changed', 'added', 'removed'], array_column($diff['blocks'], 'status'));
        self::assertSame(['content.title'], array_column($diff['blocks'][0]['changes'], 'field'));
        self::assertSame(['blocksAdded' => 1, 'blocksRemoved' => 1, 'blocksChanged' => 1], [
            'blocksAdded' => $diff['summary']['blocksAdded'],
            'blocksRemoved' => $diff['summary']['blocksRemoved'],
            'blocksChanged' => $diff['summary']['blocksChanged'],
        ]);
    }

    public function testReorderingBlocksIsReportedAsOrderChange(): void
    {
        $first = $this->block('a', 'hero', 'Hero', 0, ['title' => 'A']);
        $second = $this->block('b', 'faq', 'FAQ', 1, ['q' => 'B']);

        $diff = $this->differ()->diff(
            $this->revision(blocks: [$first, $second]),
            $this->revision(blocks: [$second + ['position' => 0], $first + ['position' => 1]]),
        );

        self::assertTrue($diff['hasChanges']);
        self::assertSame(['changed', 'changed'], array_column($diff['blocks'], 'status'));
        self::assertSame('order', $diff['blocks'][0]['changes'][0]['field']);
    }

    public function testScheduleDatesAndStatusAreNotTreatedAsContentChanges(): void
    {
        $from = $this->revision(settings: ['visibility' => 'public', 'sortOrder' => 0, 'scheduledPublishAt' => null]);
        $to = $this->revision(settings: ['visibility' => 'public', 'sortOrder' => 0, 'scheduledPublishAt' => '2030-01-01T00:00:00+00:00']);

        self::assertFalse($this->differ()->diff($from, $to)['hasChanges']);
    }

    private function differ(): PageRevisionDiffer
    {
        return new PageRevisionDiffer(new TextDiffer());
    }

    /**
     * @param array<string, mixed>       $seo
     * @param list<array<string, mixed>> $blocks
     * @param array<string, mixed>       $settings
     */
    private function revision(string $title = 'Заборы', array $seo = [], array $blocks = [], array $settings = [], string $status = 'draft'): PageRevision
    {
        return new PageRevision(
            new Page(PageType::Landing, $title, 'zabory', '/zabory/', $title),
            1,
            $title,
            $title,
            'zabory',
            '/zabory/',
            'landing',
            'default',
            $status,
            $seo,
            $blocks,
            $settings,
        );
    }

    /**
     * @param array<string, mixed> $content
     *
     * @return array<string, mixed>
     */
    private function block(string $id, string $type, string $name, int $position, array $content): array
    {
        return [
            'id' => $id,
            'type' => $type,
            'name' => $name,
            'position' => $position,
            'isEnabled' => true,
            'visibility' => 'public',
            'content' => $content,
            'settings' => [],
        ];
    }
}
