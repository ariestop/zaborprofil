<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use App\Module\Content\Domain\Enum\BlockType;
use InvalidArgumentException;

final readonly class StructuredBlockPayloadValidator
{
    /**
     * @param array<string, mixed> $content
     * @param array<string, mixed> $settings
     */
    public function validate(BlockType $type, array $content, array $settings): void
    {
        unset($settings);

        match ($type) {
            BlockType::HeroClassic => $this->requireString($content, 'title'),
            BlockType::RichText => $this->requireString($content, 'html'),
            BlockType::Features => $this->requireObjectList($content, 'items'),
            BlockType::Faq, BlockType::SchemaFaq => $this->requireFaqItems($content),
            BlockType::Gallery, BlockType::Portfolio => $this->requireObjectList($content, 'items'),
            BlockType::Cta => $this->requireString($content, 'title'),
            BlockType::ContactForm => $this->requireString($content, 'title'),
            BlockType::PriceTable => $this->requirePriceTable($content),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $content
     */
    private function requireString(array $content, string $field): void
    {
        $value = $content[$field] ?? null;
        if (!\is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(\sprintf('Block content field "%s" must be a non-empty string.', $field));
        }
    }

    /**
     * @param array<string, mixed> $content
     */
    private function requireObjectList(array $content, string $field): void
    {
        $value = $content[$field] ?? null;
        if (!\is_array($value)) {
            throw new InvalidArgumentException(\sprintf('Block content field "%s" must be an array.', $field));
        }

        foreach ($value as $item) {
            if (!\is_array($item)) {
                throw new InvalidArgumentException(\sprintf('Block content field "%s" must contain only objects.', $field));
            }
        }
    }

    /**
     * @param array<string, mixed> $content
     */
    private function requireFaqItems(array $content): void
    {
        $this->requireObjectList($content, 'items');
        /** @var array<int, mixed> $items */
        $items = $content['items'];
        foreach ($items as $item) {
            if (!\is_array($item)) {
                continue;
            }

            $question = $item['question'] ?? null;
            $answer = $item['answer'] ?? null;
            if (!\is_string($question) || trim($question) === '') {
                throw new InvalidArgumentException('FAQ item question must be a non-empty string.');
            }
            if (!\is_string($answer) || trim($answer) === '') {
                throw new InvalidArgumentException('FAQ item answer must be a non-empty string.');
            }
        }
    }

    /**
     * @param array<string, mixed> $content
     */
    private function requirePriceTable(array $content): void
    {
        $columns = $content['columns'] ?? null;
        $rows = $content['rows'] ?? null;

        if (!\is_array($columns) || !\is_array($rows)) {
            throw new InvalidArgumentException('Price table must contain array fields "columns" and "rows".');
        }
    }
}
