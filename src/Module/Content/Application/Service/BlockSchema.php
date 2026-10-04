<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use App\Module\Content\Domain\Enum\BlockType;

final readonly class BlockSchema
{
    /**
     * @param list<string> $requiredContentFields
     * @param list<string> $recommendedPageTypes
     * @param array<string, mixed> $defaultContent
     * @param array<string, mixed> $defaultSettings
     */
    public function __construct(
        public BlockType $type,
        public string $label,
        public string $description,
        public array $requiredContentFields = [],
        public array $recommendedPageTypes = [],
        public array $defaultContent = [],
        public array $defaultSettings = [],
        public string $priority = 'MVP',
        public string $seoImpact = 'medium',
        public bool $isLegacy = false,
        public ?string $canonicalType = null,
    ) {
    }

    public function withCanonicalType(?string $canonicalType): self
    {
        return new self(
            $this->type,
            $this->label,
            $this->description,
            $this->requiredContentFields,
            $this->recommendedPageTypes,
            $this->defaultContent,
            $this->defaultSettings,
            $this->priority,
            $this->seoImpact,
            $this->isLegacy,
            $canonicalType,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'label' => $this->label,
            'description' => $this->description,
            'requiredContentFields' => $this->requiredContentFields,
            'recommendedPageTypes' => $this->recommendedPageTypes,
            'defaultContent' => $this->defaultContent,
            'defaultSettings' => $this->defaultSettings,
            'priority' => $this->priority,
            'seoImpact' => $this->seoImpact,
            'isLegacy' => $this->isLegacy,
            'canonicalType' => $this->canonicalType,
        ];
    }
}
