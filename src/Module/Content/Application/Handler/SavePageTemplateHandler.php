<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Handler;

use App\Module\Content\Application\Command\SavePageTemplateCommand;
use App\Module\Content\Application\DTO\PageTemplateOutput;
use App\Module\Content\Application\Service\PageTemplateBlocks;
use App\Module\Content\Domain\Entity\PageTemplate;
use App\Module\Content\Domain\Enum\PageType;
use App\Module\Content\Domain\Repository\PageTemplateRepositoryInterface;
use Symfony\Component\Uid\Ulid;

final readonly class SavePageTemplateHandler
{
    public function __construct(
        private PageTemplateRepositoryInterface $templates,
        private PageTemplateBlocks $templateBlocks,
    ) {
    }

    public function __invoke(SavePageTemplateCommand $command): PageTemplateOutput
    {
        $template = new PageTemplate(
            'custom_' . strtolower((string) new Ulid()),
            $command->name,
            PageType::from($command->pageType),
            $this->templateBlocks->normalize($command->blocks),
            $command->description,
            kind: $command->kind,
        );

        $this->templates->save($template);

        return PageTemplateOutput::fromTemplate($template);
    }
}
