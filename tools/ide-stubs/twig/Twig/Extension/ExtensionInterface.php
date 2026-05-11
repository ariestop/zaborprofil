<?php

declare(strict_types=1);

namespace Twig\Extension;

/**
 * Минимальный стаб для Intelephense, когда в рабочей копии ещё нет `vendor/` после `composer install`.
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
