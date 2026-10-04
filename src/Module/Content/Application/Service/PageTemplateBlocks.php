<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Entity\PageBlock;
use InvalidArgumentException;

/**
 * Превращает описание блоков шаблона в валидные блоки страницы.
 *
 * Все блоки проверяются теми же правилами, что и сохранение в Page Builder,
 * поэтому шаблон нельзя сохранить или применить с невалидным содержимым.
 */
final readonly class PageTemplateBlocks
{
    private const int MAX_BLOCKS = 60;

    public function __construct(private StructuredBlockDocumentService $documentService)
    {
    }

    /**
     * @param array<mixed> $blocks
     *
     * @return list<array<string, mixed>> нормализованная схема блоков для хранения в шаблоне
     */
    public function normalize(array $blocks): array
    {
        if ($blocks === []) {
            throw new InvalidArgumentException('Template must contain at least one block.');
        }

        if (\count($blocks) > self::MAX_BLOCKS) {
            throw new InvalidArgumentException(\sprintf('Template cannot contain more than %d blocks.', self::MAX_BLOCKS));
        }

        $schema = [];
        foreach (array_values($blocks) as $position => $block) {
            if (!\is_array($block)) {
                throw new InvalidArgumentException('Each template block must be an object.');
            }

            $parsed = $this->documentService->parseBlockPayload($this->stringKeyed($this->withEnabledFlag($block)), $position);
            $item = [
                'type' => $parsed['type']->value,
                'name' => $this->blockName($block, $parsed['type']->value),
                'position' => $position,
                'content' => $parsed['content'],
                'settings' => $parsed['settings'],
                'isEnabled' => $parsed['enabled'],
            ];

            $hint = $block['hint'] ?? null;
            if (\is_string($hint) && trim($hint) !== '') {
                $item['hint'] = trim($hint);
            }

            $schema[] = $item;
        }

        return $schema;
    }

    /**
     * Добавляет блоки шаблона к странице и возвращает их количество.
     *
     * @param list<array<string, mixed>> $blocksSchema
     */
    public function applyTo(Page $page, array $blocksSchema, int $startPosition = 0): int
    {
        $created = 0;
        foreach ($this->normalize($blocksSchema) as $item) {
            $parsed = $this->documentService->parseBlockPayload($this->stringKeyed($this->withEnabledFlag($item)), $startPosition + $created);
            new PageBlock(
                $page,
                $parsed['type'],
                \is_string($item['name']) ? $item['name'] : $parsed['type']->value,
                $startPosition + $created,
                $parsed['content'],
                $parsed['settings'],
                $parsed['enabled'],
            );
            ++$created;
        }

        return $created;
    }

    /**
     * @param array<mixed> $block
     *
     * @return array<mixed>
     */
    private function withEnabledFlag(array $block): array
    {
        $enabled = $block['enabled'] ?? $block['isEnabled'] ?? true;
        $block['enabled'] = $enabled;
        unset($block['id']);

        return $block;
    }

    /**
     * @param array<mixed> $block
     *
     * @return array<string, mixed>
     */
    private function stringKeyed(array $block): array
    {
        $result = [];
        foreach ($block as $key => $value) {
            if (!\is_string($key)) {
                throw new InvalidArgumentException('Each template block must be an object.');
            }
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @param array<mixed> $block
     */
    private function blockName(array $block, string $type): string
    {
        $name = $block['name'] ?? null;
        if (\is_string($name) && trim($name) !== '') {
            return mb_substr(trim($name), 0, 180);
        }

        return $type;
    }
}
