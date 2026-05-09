<?php

declare(strict_types=1);

namespace Twig;

/**
 * @phpstan-type TwigCallable callable|array{class-string, string}|null
 */
final class TwigFunction
{
    /**
     * @param TwigCallable          $callable
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly string $name,
        private mixed $callable = null,
        private array $options = [],
    ) {
    }
}
