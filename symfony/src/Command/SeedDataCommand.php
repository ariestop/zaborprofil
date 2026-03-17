<?php

declare(strict_types=1);

namespace App\Command;

use App\Catalog\Infrastructure\Persistence\Entity\Category;
use App\Catalog\Infrastructure\Persistence\Entity\Product;
use App\Catalog\Infrastructure\Persistence\Entity\ProductVariant;
use App\Pricing\Infrastructure\Persistence\Entity\PriceList;
use App\Pricing\Infrastructure\Persistence\Entity\PriceProfile;
use App\Pricing\Infrastructure\Persistence\Entity\PriceRule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed-data', description: 'Seed initial catalog and pricing data')]
final class SeedDataCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $profile = $this->entityManager->getRepository(PriceProfile::class)->findOneBy(['type' => 'default']);
        if ($profile) {
            $io->info('Data already seeded.');
            return Command::SUCCESS;
        }

        $profile = new PriceProfile();
        $profile->setName('Розница');
        $profile->setType('default');
        $profile->setSlug('default');
        $this->entityManager->persist($profile);

        $priceList = new PriceList();
        $priceList->setPriceProfile($profile);
        $priceList->setName('Основной прайс');
        $priceList->setSlug('main');
        $priceList->setCurrency('RUB');
        $this->entityManager->persist($priceList);
        $this->entityManager->flush();

        $category = new Category();
        $category->setName('Заборы');
        $category->setSlug('zabory');
        $this->entityManager->persist($category);

        $product = new Product();
        $product->setCategory($category);
        $product->setName('Профнастил С8');
        $product->setSlug('profnastil-s8');
        $product->setDescription('Забор из профнастила С8');
        $this->entityManager->persist($product);

        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->setSku('PN-S8-001');
        $variant->setAttributes(['height' => 2000, 'width' => 1000]);
        $product->addVariant($variant);

        $this->entityManager->flush();

        $priceRule = new PriceRule();
        $priceRule->setProductId($product->getId());
        $priceRule->setPriceList($priceList);
        $priceRule->setBasePrice('8000.00');
        $this->entityManager->persist($priceRule);
        $this->entityManager->flush();

        $io->success('Seed data created.');

        return Command::SUCCESS;
    }
}
