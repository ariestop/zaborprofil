<?php

declare(strict_types=1);

namespace Twig\Extension;

/**
 * Минимальный стаб для Intelephense: реальный `vendor/twig` в Docker лежит в томе `php_vendor`
 * и недоступен на хосте.
 */
interface ExtensionInterface
{
    public function getTokenParsers(): array;

    public function getNodeVisitors(): array;

    public function getFilters(): array;

    public function getTests(): array;

    public function getFunctions(): array;

    /**
     * @return array{0: array<mixed>, 1: array<mixed>}
     */
    public function getOperators(): array;
}
