<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Command;

final readonly class SavePageBuilderDocumentCommand
{
    /**
     * @param array<mixed> $blocks     блоки документа конструктора в порядке вывода (как пришли в запросе)
     * @param string|null  $baseVersion версия документа, от которой редактор начинал правку; null — без проверки конфликта
     */
    public function __construct(
        public string $pageId,
        public array $blocks,
        public ?string $baseVersion,
    ) {
    }
}
