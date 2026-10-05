<?php

declare(strict_types=1);

namespace App\Tests\Functional\Staging;

use LogicException;
use Symfony\Bridge\Twig\AppVariable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * Открытый staging (STAGING_AUTH_ENABLED=0): <meta name="robots"> закрывает индексацию независимо от Basic Auth
 * и от флага индексации страницы. Заголовок X-Robots-Tag покрыт StagingAccessSubscriberTest,
 * robots.txt — RobotsControllerTest.
 */
final class OpenStagingIndexingTest extends KernelTestCase
{
    public function testStagingForcesNoIndexMetaEvenForIndexablePage(): void
    {
        $html = $this->renderMetaRobots('staging', 'index, follow');

        self::assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $html);
        self::assertStringNotContainsString('content="index, follow"', $html);
    }

    public function testStagingNoIndexMetaWithoutPageRobotsValue(): void
    {
        $html = $this->renderMetaRobots('staging', null);

        self::assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $html);
    }

    public function testProdKeepsPageRobotsValue(): void
    {
        self::assertStringContainsString(
            '<meta name="robots" content="index, follow">',
            $this->renderMetaRobots('prod', 'index, follow'),
        );
        self::assertStringContainsString(
            '<meta name="robots" content="noindex, nofollow">',
            $this->renderMetaRobots('prod', 'noindex, nofollow'),
        );
        self::assertStringContainsString(
            '<meta name="robots" content="index, follow">',
            $this->renderMetaRobots('prod', null),
        );
    }

    private function renderMetaRobots(string $environment, ?string $metaRobots): string
    {
        self::bootKernel();

        $twig = self::getContainer()->get('twig');
        if (!$twig instanceof Environment) {
            throw new LogicException('Twig service is not available.');
        }

        $app = new AppVariable();
        $app->setEnvironment($environment);
        $app->setDebug(false);

        $context = ['app' => $app];
        if ($metaRobots !== null) {
            $context['meta_robots'] = $metaRobots;
        }

        return $twig->load('base.html.twig')->renderBlock('meta_robots', $context);
    }
}
