<?php

declare(strict_types=1);

namespace Twig;

class Markup implements \Countable, \JsonSerializable, \Stringable
{
    public function __construct(
        private string $content,
        private ?string $charset,
    ) {
    }

    public function __toString(): string
    {
        return $this->content;
    }

    public function count(): int
    {
        return \strlen($this->content);
    }

    public function jsonSerialize(): mixed
    {
        return $this->content;
    }

    public function getCharset(): string
    {
        return $this->charset ?? 'UTF-8';
    }
}
