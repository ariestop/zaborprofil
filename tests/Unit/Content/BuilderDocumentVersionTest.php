<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content;

use App\Module\Content\Application\DTO\BuilderBlockOutput;
use App\Module\Content\Application\Service\BuilderDocumentVersion;
use PHPUnit\Framework\TestCase;

final class BuilderDocumentVersionTest extends TestCase
{
    public function testVersionIgnoresKeyOrderAndMetadata(): void
    {
        $service = new BuilderDocumentVersion();

        $a = $this->block('b1', 0, ['title' => 'A', 'text' => 'B'], ['createdAt' => '2026-01-01']);
        $b = $this->block('b1', 0, ['text' => 'B', 'title' => 'A'], ['createdAt' => '2027-02-02']);

        self::assertSame($service->fromBlocks([$a]), $service->fromBlocks([$b]));
    }

    public function testVersionChangesWhenContentOrderOrEnabledFlagChanges(): void
    {
        $service = new BuilderDocumentVersion();
        $base = $service->fromBlocks([$this->block('b1', 0, ['title' => 'A']), $this->block('b2', 1, ['title' => 'B'])]);

        self::assertNotSame($base, $service->fromBlocks([$this->block('b1', 0, ['title' => 'A2']), $this->block('b2', 1, ['title' => 'B'])]));
        self::assertNotSame($base, $service->fromBlocks([$this->block('b2', 0, ['title' => 'B']), $this->block('b1', 1, ['title' => 'A'])]));
        self::assertNotSame($base, $service->fromBlocks([$this->block('b1', 0, ['title' => 'A'], enabled: false), $this->block('b2', 1, ['title' => 'B'])]));
        self::assertNotSame($base, $service->fromBlocks([$this->block('b1', 0, ['title' => 'A'])]));
        self::assertSame($service->fromBlocks([]), $service->fromBlocks([]));
    }

    /**
     * @param array<string, mixed> $content
     * @param array<string, string> $metadata
     */
    private function block(string $id, int $position, array $content, array $metadata = [], bool $enabled = true): BuilderBlockOutput
    {
        return new BuilderBlockOutput($id, 'hero.classic', $enabled, $position, $content, [], $metadata);
    }
}
