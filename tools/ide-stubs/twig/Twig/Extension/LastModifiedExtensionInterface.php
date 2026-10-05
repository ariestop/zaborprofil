<?php

declare(strict_types=1);

namespace Twig\Extension;

interface LastModifiedExtensionInterface extends ExtensionInterface
{
    public function getLastModified(): int;
}
