<?php

declare(strict_types=1);

namespace Twig\Extension;

abstract class AbstractExtension implements LastModifiedExtensionInterface
{
    public function getTokenParsers(): array
    {
        return [];
    }

    public function getNodeVisitors(): array
    {
        return [];
    }

    public function getFilters(): array
    {
        return [];
    }

    public function getTests(): array
    {
        return [];
    }

    public function getFunctions(): array
    {
        return [];
    }

    /**
     * @return array{0: array<mixed>, 1: array<mixed>}
     */
    public function getOperators(): array
    {
        return [[], []];
    }

    public function getExpressionParsers(): array
    {
        return [];
    }

    public function getLastModified(): int
    {
        return 0;
    }
}
