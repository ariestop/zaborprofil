<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use App\Module\Content\Domain\Enum\BlockType;
use JsonException;
use RuntimeException;

/**
 * Единый реестр типов блоков. Источник правды — config/content/block-types.json,
 * его же использует frontend админки (admin/modules/page-builder/types.ts).
 */
final class BlockTypeCatalog
{
    /**
     * @var list<string>
     */
    private array $structured;

    /**
     * @var array<string, string|null>
     */
    private array $legacy;

    public function __construct(string $path)
    {
        try {
            $decoded = json_decode((string) file_get_contents($path), true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(\sprintf('Block type catalog "%s" is not valid JSON.', $path), 0, $exception);
        }

        if (!\is_array($decoded)) {
            throw new RuntimeException(\sprintf('Block type catalog "%s" must be a JSON object.', $path));
        }

        $structured = $decoded['structured'] ?? null;
        $legacy = $decoded['legacy'] ?? null;
        if (!\is_array($structured) || !\is_array($legacy)) {
            throw new RuntimeException(\sprintf('Block type catalog "%s" must contain "structured" and "legacy".', $path));
        }

        $this->structured = [];
        foreach ($structured as $type) {
            if (!\is_string($type)) {
                throw new RuntimeException('Block type catalog "structured" must contain only strings.');
            }
            $this->structured[] = $type;
        }

        $this->legacy = [];
        foreach ($legacy as $type => $canonical) {
            if (!\is_string($type) || ($canonical !== null && !\is_string($canonical))) {
                throw new RuntimeException('Block type catalog "legacy" must map type names to canonical types or null.');
            }
            $this->legacy[$type] = $canonical;
        }
    }

    /**
     * @return list<string>
     */
    public function structuredTypes(): array
    {
        return $this->structured;
    }

    /**
     * @return array<string, string|null>
     */
    public function legacyAliases(): array
    {
        return $this->legacy;
    }

    /**
     * @return list<string>
     */
    public function allTypes(): array
    {
        return [...$this->structured, ...array_keys($this->legacy)];
    }

    public function isLegacy(BlockType $type): bool
    {
        return \array_key_exists($type->value, $this->legacy);
    }

    public function canonicalFor(BlockType $type): ?string
    {
        return $this->legacy[$type->value] ?? null;
    }
}
