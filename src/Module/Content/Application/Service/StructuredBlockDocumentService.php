<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use App\Module\Content\Domain\Enum\BlockType;
use InvalidArgumentException;
use Symfony\Component\Uid\Ulid;

final readonly class StructuredBlockDocumentService
{
    private const array RICH_TEXT_KEYS = ['html', 'text', 'answer', 'description'];

    /**
     * Типы, чьи `html`/`text` шаблон выводит как разметку (public/blocks/rich-text.html.twig и его include).
     */
    private const array MARKUP_BLOCK_TYPES = [BlockType::RichText, BlockType::Text, BlockType::SeoText];
    private const array MARKUP_KEYS = ['html', 'text'];
    private const int NAME_MAX_LENGTH = 180;

    public function __construct(
        private BlockSchemaRegistry $blockSchemas,
        private StructuredBlockPayloadValidator $payloadValidator,
        private StructuredRichTextSanitizer $richTextSanitizer,
    ) {
    }

    /**
     * @param array<string, mixed> $blockPayload
     *
     * @return array{id:string, type:BlockType, name:string|null, enabled:bool, position:int, content:array<string, mixed>, settings:array<string, mixed>}
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

        $nameRaw = $blockPayload['name'] ?? null;
        if ($nameRaw !== null && !\is_string($nameRaw)) {
            throw new InvalidArgumentException('Block "name" must be a string.');
        }
        $name = $nameRaw === null ? '' : trim($nameRaw);

        $enabledRaw = $blockPayload['enabled'] ?? true;
        if (!\is_bool($enabledRaw)) {
            throw new InvalidArgumentException('Block "enabled" must be boolean.');
        }

        $contentRaw = $blockPayload['content'] ?? [];
        $content = $this->normalizeObject($contentRaw, 'Block "content" must be an object.');
        $content = $this->sanitizeContent($type, $content);

        $settingsRaw = $blockPayload['settings'] ?? [];
        $settings = $this->normalizeObject($settingsRaw, 'Block "settings" must be an object.');
        $settings = $this->sanitizeRichTextValues($settings);

        $this->blockSchemas->validate($type, $content);
        $this->payloadValidator->validate($type, $content, $settings);

        return [
            'id' => $id,
            'type' => $type,
            'name' => $name === '' ? null : mb_substr($name, 0, self::NAME_MAX_LENGTH),
            'enabled' => $enabledRaw,
            'position' => $position,
            'content' => $content,
            'settings' => $settings,
        ];
    }

    /**
     * @param list<array<string, mixed>> $blocksPayload
     *
     * @return list<array{id:string, type:BlockType, name:string|null, enabled:bool, position:int, content:array<string, mixed>, settings:array<string, mixed>}>
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
     * Очистка содержимого блока перед сохранением — общая для конструктора и API отдельных блоков.
     *
     * @param array<string, mixed> $content
     *
     * @return array<string, mixed>
     */
    public function sanitizeContent(BlockType $type, array $content): array
    {
        $content = $this->sanitizeRichTextValues($content);
        if (!\in_array($type, self::MARKUP_BLOCK_TYPES, true)) {
            return $content;
        }

        foreach (self::MARKUP_KEYS as $key) {
            $value = $content[$key] ?? null;
            if (\is_string($value)) {
                $content[$key] = $this->richTextSanitizer->sanitizeHtml($value);
            }
        }

        return $content;
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
                $sanitized[$key] = $this->richTextSanitizer->sanitizeText($item);
                continue;
            }

            $sanitized[$key] = $item;
        }

        return $sanitized;
    }
}
