<?php

declare(strict_types=1);

namespace App\Shared\UI\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\RouterInterface;

#[AsCommand(name: 'app:smoke:test', description: 'Runs a small release-readiness smoke test suite.')]
final class SmokeTestCommand extends Command
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $checks = [
            'route:health_check' => $this->routeExists('health_check'),
            'route:admin_login' => $this->routeExists('admin_login'),
            'route:admin_spa' => $this->routeExists('admin_spa'),
            'route:api_leads_create' => $this->routeExists('api_leads_create'),
            'route:admin_api_media_assets_index' => $this->routeExists('admin_api_media_assets_index'),
            'route:admin_api_menu_item_index' => $this->routeExists('admin_api_menu_item_index'),
            'route:admin_api_leads_index' => $this->routeExists('admin_api_leads_index'),
            'route:admin_api_catalog_products_index' => $this->routeExists('admin_api_catalog_products_index'),
            'route:public_sitemap_xml' => $this->routeExists('public_sitemap_xml'),
            'route:public_robots_txt' => $this->routeExists('public_robots_txt'),
            'env:app_secret' => $this->envExists('APP_SECRET'),
            'env:database_url' => $this->envExists('DATABASE_URL'),
            'build:manifest' => $this->buildManifestExists(),
            'uploads:writable' => $this->uploadsWritable(),
        ];

        foreach ($checks as $name => $ok) {
            $ok ? $io->success($name) : $io->error($name);
        }

        if (\in_array(false, $checks, true)) {
            $io->error('Smoke test failed.');

            return Command::FAILURE;
        }

        $io->success('Smoke test passed.');

        return Command::SUCCESS;
    }

    private function routeExists(string $name): bool
    {
        return $this->router->getRouteCollection()->get($name) !== null;
    }

    private function envExists(string $name): bool
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? null;

        return \is_string($value) && trim($value) !== '';
    }

    private function buildManifestExists(): bool
    {
        return is_file($this->projectDir.'/public_html/build/.vite/manifest.json');
    }

    private function uploadsWritable(): bool
    {
        $directory = $this->projectDir.'/public_html/uploads';

        if (!is_dir($directory)) {
            return is_writable(\dirname($directory));
        }

        return is_writable($directory);
    }
}
