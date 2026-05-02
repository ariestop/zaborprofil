<?php

declare(strict_types=1);

namespace App\Module\Settings\UI\Twig;

use App\Module\Settings\Application\Service\SettingsService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class SettingsTwigExtension extends AbstractExtension
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('setting', $this->setting(...)),
        ];
    }

    public function setting(string $scope, string $key, mixed $default = null): mixed
    {
        return $this->settings->get($scope, $key, $default);
    }
}
