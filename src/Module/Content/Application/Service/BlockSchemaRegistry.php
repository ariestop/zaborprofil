<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use App\Module\Content\Domain\Enum\BlockType;
use InvalidArgumentException;

final readonly class BlockSchemaRegistry
{
    /**
     * @return list<BlockSchema>
     */
    public function all(): array
    {
        return [
            new BlockSchema(BlockType::Hero, 'Hero', 'Первый экран страницы.', ['title'], ['home', 'landing', 'service', 'seo_landing'], ['title' => '', 'text' => '', 'cta' => []], ['layout' => 'default'], 'MVP', 'high'),
            new BlockSchema(BlockType::Text, 'Text', 'Текстовый блок.', ['text'], ['text_page', 'material_landing'], ['title' => '', 'text' => ''], ['width' => 'prose'], 'MVP', 'medium'),
            new BlockSchema(BlockType::TextImage, 'Text + Image', 'Текст с изображением.', ['title', 'text'], ['service', 'material_landing', 'seo_landing'], ['title' => '', 'text' => '', 'image' => null, 'alt' => ''], ['imageSide' => 'right'], 'MVP', 'high'),
            new BlockSchema(BlockType::Image, 'Image', 'Одиночное изображение.', ['image', 'alt'], ['portfolio_item', 'material_landing'], ['image' => null, 'alt' => '', 'caption' => ''], ['lazy' => true], 'MVP+', 'medium'),
            new BlockSchema(BlockType::Gallery, 'Gallery', 'Галерея изображений.', ['items'], ['service', 'material_landing', 'portfolio_item'], ['items' => []], ['columns' => 3], 'MVP+', 'high'),
            new BlockSchema(BlockType::Video, 'Video', 'Видеоблок.', ['url'], ['portfolio_item', 'landing'], ['url' => '', 'title' => ''], ['lazy' => true], 'Later', 'low'),
            new BlockSchema(BlockType::FeatureGrid, 'Feature grid', 'Сетка преимуществ.', ['items'], ['home', 'landing', 'service'], ['items' => []], ['columns' => 3], 'MVP', 'medium'),
            new BlockSchema(BlockType::PriceCards, 'Price cards', 'Карточки цен.', ['items'], ['service', 'prices', 'material_landing'], ['items' => []], ['currency' => 'RUB'], 'MVP', 'high'),
            new BlockSchema(BlockType::Steps, 'Steps', 'Этапы работ.', ['items'], ['home', 'service', 'landing'], ['items' => []], ['columns' => 4], 'MVP', 'medium'),
            new BlockSchema(BlockType::Faq, 'FAQ', 'Вопросы и ответы.', ['items'], ['landing', 'service', 'seo_landing'], ['items' => []], ['schemaOrg' => true], 'MVP', 'high'),
            new BlockSchema(BlockType::CtaForm, 'CTA form', 'Форма заявки.', ['title'], ['home', 'landing', 'service', 'contacts'], ['title' => '', 'text' => '', 'button' => 'Оставить заявку'], ['source' => 'page_engine'], 'MVP', 'high'),
            new BlockSchema(BlockType::TelegramCta, 'Telegram CTA', 'Переход в Telegram.', ['url'], ['landing', 'contacts'], ['title' => '', 'text' => '', 'url' => ''], ['style' => 'card'], 'MVP+', 'low'),
            new BlockSchema(BlockType::Contacts, 'Contacts', 'Контактные данные.', ['items'], ['contacts'], ['items' => []], ['layout' => 'cards'], 'MVP', 'high'),
            new BlockSchema(BlockType::Map, 'Map', 'Карта.', ['address'], ['contacts'], ['address' => '', 'embedUrl' => ''], ['height' => 420], 'MVP+', 'medium'),
            new BlockSchema(BlockType::PortfolioGrid, 'Portfolio grid', 'Сетка работ.', [], ['home', 'portfolio_index', 'service'], ['items' => []], ['limit' => 6], 'MVP+', 'high'),
            new BlockSchema(BlockType::SeoText, 'SEO text', 'SEO-текст.', ['text'], ['landing', 'service', 'seo_landing'], ['title' => '', 'text' => ''], ['collapsed' => false], 'MVP', 'high'),
            new BlockSchema(BlockType::HtmlEmbed, 'HTML embed', 'Безопасный HTML/embed.', ['html'], ['text_page'], ['html' => ''], ['sandbox' => true], 'Later', 'medium'),
            new BlockSchema(BlockType::Table, 'Table', 'Таблица.', ['columns', 'rows'], ['prices', 'material_landing'], ['columns' => [], 'rows' => []], ['responsive' => 'scroll'], 'MVP', 'medium'),
            new BlockSchema(BlockType::Accordion, 'Accordion', 'Аккордеон.', ['items'], ['prices', 'text_page'], ['items' => []], ['multiple' => false], 'MVP+', 'medium'),
            new BlockSchema(BlockType::CalculatorPlaceholder, 'Calculator placeholder', 'Место под будущий калькулятор.', [], ['landing', 'service'], ['title' => '', 'text' => ''], ['calculator' => null], 'Later', 'low'),
            new BlockSchema(BlockType::BeforeAfter, 'Before / After', 'До/после.', ['before', 'after'], ['portfolio_item'], ['before' => null, 'after' => null], ['mode' => 'slider'], 'Later', 'high'),
            new BlockSchema(BlockType::ReviewCards, 'Review cards', 'Отзывы.', ['items'], ['home', 'landing'], ['items' => []], ['columns' => 3], 'Later', 'medium'),
            new BlockSchema(BlockType::Documents, 'Documents', 'Документы и сертификаты.', ['items'], ['material_landing', 'text_page'], ['items' => []], ['layout' => 'list'], 'Later', 'medium'),
        ];
    }

    public function get(BlockType $type): BlockSchema
    {
        foreach ($this->all() as $schema) {
            if ($schema->type === $type) {
                return $schema;
            }
        }

        throw new InvalidArgumentException(\sprintf('Block type "%s" is not supported.', $type->value));
    }

    /**
     * @param array<string, mixed> $content
     */
    public function validate(BlockType $type, array $content): void
    {
        $this->get($type);

        if ($type === BlockType::HtmlEmbed) {
            $htmlValue = $content['html'] ?? '';
            $html = \is_string($htmlValue) ? $htmlValue : '';
            if (preg_match('/<\s*script\b/i', $html) === 1) {
                throw new InvalidArgumentException('HTML embed cannot contain script tags.');
            }
        }
    }
}
