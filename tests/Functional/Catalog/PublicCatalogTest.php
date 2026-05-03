<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog;

use App\Module\Catalog\Domain\Entity\Category;
use App\Module\Catalog\Domain\Entity\Product;
use App\Module\Catalog\Domain\Entity\Variant;
use App\Module\Catalog\Domain\Enum\ProductStatus;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PublicCatalogTest extends WebTestCase
{
    protected function setUp(): void
    {
        self::ensureKernelShutdown();
    }

    public function testPublicCatalogCategoryAndProductPagesRenderSeoAndSchema(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $this->createPublishedProduct();

        $client->request('GET', '/catalog/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Каталог продукции');
        self::assertStringContainsString('/catalog/zabory/', (string) $client->getResponse()->getContent());

        $client->request('GET', '/catalog/zabory/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Заборы');
        self::assertStringContainsString('Забор жалюзи', (string) $client->getResponse()->getContent());

        $client->request('GET', '/catalog/zabory/zabor-jaluzi/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Забор жалюзи');
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('<meta name="description" content="SEO описание товара жалюзи.">', $html);
        self::assertStringContainsString('<link rel="canonical" href="https://zaborprofil.test/catalog/zabory/zabor-jaluzi/">', $html);
        self::assertStringContainsString('<meta property="og:type" content="product">', $html);
        self::assertStringContainsString('"@type":"Product"', $html);
        self::assertStringContainsString('"price":"12500.00"', $html);
        self::assertStringContainsString('"@type":"BreadcrumbList"', $html);
    }

    public function testSitemapContainsPublishedIndexableProductsOnly(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $this->createPublishedProduct();

        $draft = new Product('Черновик', 'draft-product', '/catalog/draft-product/', ProductStatus::Draft);
        $this->entityManager()->persist($draft);
        $this->entityManager()->flush();

        $client->request('GET', '/sitemap.xml');
        self::assertResponseIsSuccessful();
        $xml = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('https://zaborprofil.test/catalog/zabory/zabor-jaluzi/', $xml);
        self::assertStringNotContainsString('/catalog/draft-product/', $xml);
    }

    public function testMissingCatalogPageRendersSiteNotFoundPlaceholder(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());

        $client->request('GET', '/catalog/missing-item/');

        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('h1', 'Страница не найдена');
        self::assertStringContainsString('<meta name="robots" content="noindex, nofollow">', (string) $client->getResponse()->getContent());
    }

    private function createPublishedProduct(): Product
    {
        $entityManager = $this->entityManager();
        $category = new Category('Заборы', 'zabory', '/catalog/zabory/', 'Категория заборов');
        $product = new Product(
            'Забор жалюзи',
            'zabor-jaluzi',
            '/catalog/zabory/zabor-jaluzi/',
            ProductStatus::Published,
            'Краткое описание товара.',
            'Подробное описание товара.',
            true,
            $category,
        );
        $product->updateSeoMetadata(
            'SEO описание товара жалюзи.',
            'https://zaborprofil.test/catalog/zabory/zabor-jaluzi/',
            'OG Забор жалюзи',
            'OG описание товара.',
            null,
        );
        new Variant($product, 'ZBR-JAL-200', 'Высота 2м', 1250000);

        $entityManager->persist($category);
        $entityManager->persist($product);
        $entityManager->flush();

        return $product;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        if (!$entityManager instanceof EntityManagerInterface) {
            throw new LogicException('Entity manager service is not available.');
        }

        return $entityManager;
    }
}
