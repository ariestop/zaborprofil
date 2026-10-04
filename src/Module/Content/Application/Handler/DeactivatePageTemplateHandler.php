<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Handler;

use App\Module\Content\Domain\Repository\PageTemplateRepositoryInterface;
use InvalidArgumentException;

final readonly class DeactivatePageTemplateHandler
{
    public function __construct(private PageTemplateRepositoryInterface $templates)
    {
    }

    public function __invoke(string $code): void
    {
        $template = $this->templates->getByCode($code);
        if ($template->isSystem()) {
            throw new InvalidArgumentException('System page templates cannot be removed.');
        }

        $template->deactivate();
        $this->templates->save($template);
    }
}
