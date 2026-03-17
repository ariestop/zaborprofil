<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Catalog\Infrastructure\Persistence\Entity\ProductVariant;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ProductVariantCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ProductVariant::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Вариант товара')
            ->setEntityLabelInPlural('Варианты товаров')
            ->setSearchFields(['sku']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('product', 'Товар');
        yield TextField::new('sku', 'Артикул (SKU)');
        yield DateTimeField::new('createdAt', 'Создан')->hideOnForm();
    }
}
