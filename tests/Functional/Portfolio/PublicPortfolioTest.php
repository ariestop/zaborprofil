<?php

declare(strict_types=1);

namespace App\Tests\Functional\Portfolio;

use App\Module\Content\Application\Service\PageBlockView;
use App\Module\Content\UI\Web\TwigBlockRenderer;
use App\Module\Portfolio\Application\Command\PortfolioCategoryInput;
use App\Module\Portfolio\Application\Command\PortfolioMediaInput;
use App\Module\Portfolio\Application\Command\PortfolioProjectInput;
use App\Module\Portfolio\Application\Service\PortfolioAdminService;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PublicPortfolioTest extends WebTestCase
{
    private KernelBrowser $client;

    /**
     * @var array<string, string> slug работы => id
     */
    private array $projects = [];

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        SchemaTestHelper::recreateSchema($entityManager);

        $service = self::getContainer()->get(PortfolioAdminService::class);
        self::assertInstanceOf(PortfolioAdminService::class, $service);
        $jaluzi = (string) $service->createCategory(new PortfolioCategoryInput('Заборы Жалюзи', 'zabor-jaluzi', 'Жалюзи', 'Продуваемые заборы.'))->id();
        $profnastil = (string) $service->createCategory(new PortfolioCategoryInput('Заборы из профнастила', 'profnastil', 'Профнастил'))->id();
        $service->createCategory(new PortfolioCategoryInput('Ворота', 'vorota'));

        foreach ([
            ['grafit', $jaluzi, 'Жалюзи графит', true],
            ['kalitka', $jaluzi, 'Жалюзи с калиткой', true],
            ['chernovik', $jaluzi, 'Черновик жалюзи', false],
            ['sad', $profnastil, 'Профнастил вдоль сада', true],
        ] as [$slug, $category, $title, $published]) {
            $project = $service->createProject(new PortfolioProjectInput(
                $category,
                $title,
                $slug,
                'Саратов',
                2025,
                'Коротко о работе '.$slug,
                '<p>🏡 Описание работы <strong>'.$slug.'</strong></p>',
                [['label' => 'Длина', 'value' => '42 м'], ['label' => 'Высота', 'value' => '1,8 м']],
                [
                    new PortfolioMediaInput('photo', '/uploads/media/'.$slug.'-1.jpg', null, 'Вид с улицы'),
                    new PortfolioMediaInput('video', '/uploads/media/'.$slug.'.mp4', '/uploads/media/'.$slug.'-1.jpg', 'Монтаж'),
                    new PortfolioMediaInput('embed', 'https://vk.com/video-1_2', null, 'Обзор'),
                    new PortfolioMediaInput('photo', '/uploads/media/'.$slug.'-2.jpg', null, null, true),
                ],
            ));
            $this->projects[$slug] = (string) $project->id();
            if ($published) {
                $service->setPublished((string) $project->id(), true);
            }
        }
    }

    public function testHubShowsNonEmptySectionsAndLatestWorks(): void
    {
        $html = $this->html('/portfolio/');

        self::assertStringContainsString('<h1 class="zp-pf-h1">Портфолио</h1>', $html);
        self::assertStringContainsString('href="/portfolio/zabor-jaluzi/"', $html);
        self::assertStringContainsString('2 работы', $html, 'Черновик в счёт не идёт.');
        self::assertStringContainsString('href="/portfolio/profnastil/"', $html);
        self::assertStringNotContainsString('href="/portfolio/vorota/"', $html, 'Пустой раздел не показывается.');
        self::assertStringContainsString('Всего 3 работы', $html);
        self::assertStringNotContainsString('Черновик жалюзи', $html);
        self::assertStringContainsString('"@type":"BreadcrumbList"', $html);
    }

    public function testSectionListsItsPublishedWorksAndSwitcher(): void
    {
        $html = $this->html('/portfolio/zabor-jaluzi/');

        self::assertStringContainsString('Заборы Жалюзи', $html);
        self::assertStringContainsString('Продуваемые заборы.', $html);
        self::assertStringContainsString('href="/portfolio/zabor-jaluzi/grafit/"', $html);
        self::assertStringContainsString('href="/portfolio/zabor-jaluzi/kalitka/"', $html);
        self::assertStringNotContainsString('chernovik', $html);
        self::assertMatchesRegularExpression('#href="/portfolio/zabor-jaluzi/"\s+aria-current="page"#', $html);
        self::assertStringContainsString('<link rel="canonical" href="https://zaborprofil.test/portfolio/zabor-jaluzi/">', $html);
    }

    public function testProjectPageShowsGalleryWithPlayerEmbedAndNeighbours(): void
    {
        $html = $this->html('/portfolio/zabor-jaluzi/grafit/');

        self::assertStringContainsString('<h1 class="zp-pf-h1">Жалюзи графит</h1>', $html);
        self::assertStringContainsString('<dd>42 м</dd>', $html);
        self::assertStringContainsString('🏡 Описание работы <strong>grafit</strong>', $html);
        self::assertStringContainsString('data-zp-player', $html, 'Загруженное видео — в плеере.');
        self::assertStringContainsString('src="/uploads/media/grafit.mp4"', $html);
        self::assertStringContainsString('data-video-embed="https://vkvideo.ru/video_ext.php?oid=-1&amp;id=2&amp;hd=2"', $html);
        self::assertStringContainsString('2 фото · 2 видео', $html);
        self::assertStringContainsString('href="/portfolio/zabor-jaluzi/kalitka/"', $html, 'Соседняя работа раздела.');
        self::assertStringContainsString('<meta property="og:image" content="https://zaborprofil.test/uploads/media/grafit-2.jpg">', $html, 'Обложка — фото со звёздочкой.');
        self::assertStringContainsString('"@type":"CreativeWork"', $html);
        self::assertStringContainsString('id="lead-form"', $html);
    }

    public function testFeedRendersPhotosAsImagesNotText(): void
    {
        $service = self::getContainer()->get(PortfolioAdminService::class);
        self::assertInstanceOf(PortfolioAdminService::class, $service);
        $category = (string) $service->createCategory(new PortfolioCategoryInput('Ранчо', 'rancho'))->id();
        $single = $service->createProject(new PortfolioProjectInput($category, 'Одно фото', 'odno-foto', media: [
            new PortfolioMediaInput('photo', '/uploads/media/odno-foto.jpg', null, null),
        ]));
        $service->setPublished((string) $single->id(), true);

        foreach (['/portfolio/', '/portfolio/rancho/', '/portfolio/zabor-jaluzi/'] as $path) {
            $html = $this->html($path);
            self::assertStringNotContainsString('&lt;img', $html, $path.': разметка фото выведена текстом.');
            self::assertStringNotContainsString('&lt;picture', $html, $path);
            self::assertStringContainsString('data-pf-post=', $html, $path.': карточки — посты ленты.');
        }
        self::assertMatchesRegularExpression('#<a class="zp-pf-post__slide" href="/portfolio/rancho/odno-foto/"[^>]*>\s*<(img|picture)#', $this->html('/portfolio/rancho/'), 'Единственное фото — ссылка на работу.');
        self::assertStringContainsString('data-pf-track', $this->html('/portfolio/zabor-jaluzi/'), 'Несколько фото — лента со свайпом.');
    }

    public function testShareLinksAndCanonicalIgnoreRequestHost(): void
    {
        $this->client->request('GET', '/portfolio/zabor-jaluzi/grafit/', server: ['HTTP_HOST' => 'zaborprofil.test:4443']);
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString('data-pf-share="https://zaborprofil.test/portfolio/zabor-jaluzi/grafit/"', $html);
        self::assertStringContainsString('<link rel="canonical" href="https://zaborprofil.test/portfolio/zabor-jaluzi/grafit/">', $html);
        self::assertStringNotContainsString(':4443', $html);
    }

    public function testDraftsUnknownAndEmptySectionsAreNotFound(): void
    {
        foreach (['/portfolio/zabor-jaluzi/chernovik/', '/portfolio/vorota/', '/portfolio/nope/', '/portfolio/profnastil/grafit/'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseStatusCodeSame(404, $path);
        }
    }

    public function testSitemapListsPortfolio(): void
    {
        $this->client->request('GET', '/sitemap.xml');
        $index = (string) $this->client->getResponse()->getContent();
        $xml = $index;
        if (str_contains($index, '<sitemapindex')) {
            preg_match_all('#<loc>https?://[^/]+(/sitemap-pages-\d+\.xml)</loc>#', $index, $chunks);
            $xml = '';
            foreach ($chunks[1] as $chunk) {
                $this->client->request('GET', $chunk);
                $xml .= (string) $this->client->getResponse()->getContent();
            }
        }

        foreach (['/portfolio/', '/portfolio/zabor-jaluzi/', '/portfolio/profnastil/', '/portfolio/zabor-jaluzi/grafit/', '/portfolio/profnastil/sad/'] as $path) {
            self::assertStringContainsString($path.'</loc>', $xml, $path);
        }
        self::assertStringNotContainsString('chernovik', $xml);
        self::assertStringNotContainsString('/portfolio/vorota/', $xml);
    }

    public function testPageBlockShowsChosenOrLatestWorksInARow(): void
    {
        $chosen = $this->block(['title' => 'Похожие работы', 'projectIds' => [$this->projects['sad'], $this->projects['chernovik'], $this->projects['grafit']], 'limit' => 6]);
        self::assertStringContainsString('data-pf-row', $chosen);
        self::assertStringContainsString('Похожие работы', $chosen);
        self::assertLessThan(strpos($chosen, '/portfolio/zabor-jaluzi/grafit/'), strpos($chosen, '/portfolio/profnastil/sad/'), 'Порядок как в блоке.');
        self::assertStringNotContainsString('chernovik', $chosen, 'Черновик в блоке не показывается.');
        self::assertStringContainsString('href="/portfolio/"', $chosen);

        $section = $this->block(['projectIds' => [], 'category' => 'zabor-jaluzi', 'limit' => 1]);
        self::assertSame(1, substr_count($section, 'class="zp-pf-card"'));
        self::assertStringContainsString('href="/portfolio/zabor-jaluzi/">Все работы', $section);

        self::assertSame('', trim($this->block(['category' => 'vorota'])), 'Пустой блок не выводится.');
    }

    private function html(string $path): string
    {
        $this->client->request('GET', $path);
        self::assertResponseIsSuccessful($path);

        return (string) $this->client->getResponse()->getContent();
    }

    /**
     * @param array<string, mixed> $content
     */
    private function block(array $content): string
    {
        $renderer = self::getContainer()->get(TwigBlockRenderer::class);
        self::assertInstanceOf(TwigBlockRenderer::class, $renderer);

        return $renderer->render(new PageBlockView('b1', 'portfolio-projects', 'Портфолио', 0, $content, []));
    }
}
