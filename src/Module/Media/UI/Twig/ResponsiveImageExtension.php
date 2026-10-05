<?php

declare(strict_types=1);

namespace App\Module\Media\UI\Twig;

use App\Module\Media\Application\Responsive\PictureHtmlBuilder;
use App\Module\Media\Application\Responsive\ResponsiveImageResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ResponsiveImageExtension extends AbstractExtension
{
    public function __construct(
        private readonly ResponsiveImageResolver $resolver,
        private readonly PictureHtmlBuilder $builder,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('responsive_image', $this->render(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    public function render(mixed $src, array $options = []): string
    {
        if (!\is_string($src)) {
            return '';
        }

        return $this->builder->build($src, $this->resolver->resolve($src), $options);
    }
}
