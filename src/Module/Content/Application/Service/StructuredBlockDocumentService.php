<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use App\Module\Content\Domain\Enum\BlockType;
use InvalidArgumentException;
use Symfony\Component\Uid\Ulid;

final class StructuredBlockDocumentService
{
    private const RICH_TEXT_KEYS = ['html', 'text', 'answer', 'description'];

    public function __construct(
        private readonly BlockSchemaRegistry $blockSchemas,
        private readonly StructuredBlockPayloadValidator $payloadValidator,
        private readonly StructuredRichTextSanitizer $richTextSanitizer,
    ) {
    }

    /**
     * @param array<string, mixed> $blockPayload
     *
     * @return array{id:string, type:BlockType, enabled:bool, position:int, content:array<string, mixed>, settings:array<string, mixed>}
     */
    public function parseBlockPayload(array $blockPayload, int $position): array
    {
        $idRaw = $blockPayload['id'] ?? null;
        $id = \is_string($idRaw) && $idRaw !== '' ? (string) Ulid::fromString($idRaw) : (string) new Ulid();

        $typeRaw = $blockPayload['type'] ?? null;
        if (!\is_string($typeRaw) || $typeRaw === '') {
            throw new InvalidArgumentException('Block "type" is required.');
        }
        $type = BlockType::from($typeRaw);

        $enabledRaw = $blockPayload['enabled'] ?? true;
        if (!\is_bool($enabledRaw)) {
            throw new InvalidArgumentException('Block "enabled" must be boolean.');
        }

        $contentRaw = $blockPayload['content'] ?? [];
        $content = $this->normalizeObject($contentRaw, 'Block "content" must be an object.');
        $content = $this->sanitizeRichTextValues($content);

        $settingsRaw = $blockPayload['settings'] ?? [];
        $settings = $this->normalizeObject($settingsRaw, 'Block "settings" must be an object.');
        $settings = $this->sanitizeRichTextValues($settings);

        $this->blockSchemas->validate($type, $content);
        $this->payloadValidator->validate($type, $content, $settings);

        return [
            'id' => $id,
            'type' => $type,
            'enabled' => $enabledRaw,
            'position' => $position,
            'content' => $content,
            'settings' => $settings,
        ];
    }

    /**
     * @param list<array<string, mixed>> $blocksPayload
     *
     * @return list<array{id:string, type:BlockType, enabled:bool, position:int, content:array<string, mixed>, settings:array<string, mixed>}>
     */
    public function parseBlocks(array $blocksPayload): array
    {
        $parsed = [];
        foreach ($blocksPayload as $position => $blockPayload) {
            $parsed[] = $this->parseBlockPayload($blockPayload, $position);
        }

        return $parsed;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeObject(mixed $value, string $message): array
    {
        if (!\is_array($value)) {
            throw new InvalidArgumentException($message);
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            if (!\is_string($key)) {
                throw new InvalidArgumentException($message);
            }
            $normalized[$key] = $item;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    private function sanitizeRichTextValues(array $value): array
    {
        $sanitized = [];
        foreach ($value as $key => $item) {
            if (\is_array($item)) {
                $sanitized[$key] = array_is_list($item)
                    ? $item
                    : $this->sanitizeRichTextValues($this->normalizeObject($item, 'Nested object must use string keys.'));
                continue;
            }

            if (\is_string($item) && \in_array($key, self::RICH_TEXT_KEYS, true)) {
                $sanitized[$key] = $this->richTextSanitizer->sanitize($item);
                continue;
            }

            $sanitized[$key] = $item;
        }

        return $sanitized;
    }
}
