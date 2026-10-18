<?php

declare(strict_types=1);

namespace App\Tests\Unit\Menu\Application\Service;

use App\Module\Menu\Application\Service\MenuIconResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MenuIconResolverTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function items(): iterable
    {
        yield 'основное меню: жалюзи' => ['Забор Жалюзи', '/zabor-jaluzi/', 'i-zp-fence'];
        yield 'основное меню: каталог' => ['Каталог', '/#catalog', 'i-zp-catalog'];
        yield 'основное меню: как работаем' => ['Как мы работаем', '/#process', 'i-zp-steps'];
        yield 'основное меню: контакты' => ['Контакты', '#contacts', 'i-zp-pin'];
        yield 'каталог заборов — каталог, а не забор' => ['Каталог заборов', '/zabory/', 'i-zp-catalog'];
        yield 'раздел по названию, а не по ссылке на каталог' => ['Заборы', '/#catalog', 'i-zp-fence'];
        yield 'раздел материалов' => ['Материалы', '/fasad/profnastil/', 'i-zp-catalog'];
        yield 'раздел комплектующих' => ['Комплектующие', '/krepezh-3d-zabor/', 'i-zp-catalog'];
        yield 'по ссылке, если название ни о чём не говорит' => ['Подробнее', '/zabor-3d-setka/', 'i-zp-fence'];
        yield 'доставка' => ['Доставка и оплата', '/dostavka/', 'i-zp-truck'];
        yield 'замер по ссылке' => ['Вызвать специалиста', '/zamer/', 'i-zp-measure'];
        yield 'гарантии' => ['Гарантии', '/garantii/', 'i-zp-shield'];
        yield 'портфолио' => ['Портфолио', '/portfolio/', 'i-zp-photo'];
        yield 'наши работы — портфолио, даже про заборы' => ['Наши работы: заборы', '/portfolio/zabor-jaluzi/', 'i-zp-photo'];
        yield 'регистр не важен' => ['КОНТАКТЫ', '/', 'i-zp-pin'];
        yield 'неизвестный пункт — нейтральная иконка' => ['Новости', '/news/', MenuIconResolver::DEFAULT];
    }

    #[DataProvider('items')]
    public function testChoosesIconByLabelAndUrl(string $label, string $url, string $expected): void
    {
        self::assertSame($expected, (new MenuIconResolver())->symbolFor($label, $url));
    }
}
