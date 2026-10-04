<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use App\Module\Content\Application\DTO\BuilderBlockOutput;

/**
 * Версия документа Builder для оптимистичной блокировки: хэш содержимого блоков страницы.
 *
 * Не зависит от порядка ключей JSON (MySQL перестраивает ключи JSON-колонок) и от служебных
 * меток времени, поэтому одинакова для одного и того же состояния блоков при любом способе чтения.
 */
final class BuilderDocumentVersion
{
    /**
     * @param list<BuilderBlockOutput> $blocks
     */
    public function fromBlocks(array $blocks): string
    {
        $normalized = array_map(static fn (BuilderBlockOutput $block): array => [
            'id' => $block->id,
            'type' => $block->type,
            'enabled' => $block->enabled,
            'position' => $block->position,
            'content' => $block->content,
            'settings' => $block->settings,
        ], $blocks);

        usort($normalized, static fn (array $a, array $b): int => [$a['position'], $a['id']] <=> [$b['position'], $b['id']]);

        return substr(hash('sha256', json_encode(self::sortKeys($normalized), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 0, 32);
    }

    private static function sortKeys(mixed $value): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }

        $sorted = array_map(self::sortKeys(...), $value);
        if (!array_is_list($sorted)) {
            ksort($sorted);
        }

        return $sorted;
    }
}
