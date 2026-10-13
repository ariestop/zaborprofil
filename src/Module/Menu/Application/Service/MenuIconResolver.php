<?php

declare(strict_types=1);

namespace App\Module\Menu\Application\Service;

/**
 * Подбирает фирменную иконку (id символа в templates/public/partials/icons.html.twig) пункту меню
 * по названию и адресу. Пункты вводятся в админке, поэтому иконка выбирается автоматически;
 * неизвестные пункты получают нейтральную иконку «страница».
 */
final readonly class MenuIconResolver
{
    public const string DEFAULT = 'i-zp-page';

    /**
     * Порядок важен: «Каталог заборов» — это каталог, а не забор.
     *
     * @var array<string, list<string>>
     */
    private const array KEYWORDS = [
        'i-zp-catalog' => ['каталог', 'catalog', 'katalog'],
        'i-zp-steps' => ['работаем', 'этап', 'process', 'kak-my'],
        'i-zp-pin' => ['контакт', 'contact', 'адрес', 'как проехать'],
        'i-zp-truck' => ['доставк', 'dostavk', 'delivery'],
        'i-zp-measure' => ['замер', 'zamer', 'расч', 'калькулятор', 'цены', 'цена', 'price'],
        'i-zp-shield' => ['гарант', 'о компании', 'о нас', 'about', 'сертификат', 'отзыв', 'review'],
        'i-zp-fence' => ['забор', 'zabor', 'жалюзи', 'jaluzi', 'ограждени', 'профнастил', 'штакетник', 'ворота', 'калитк'],
    ];

    public function symbolFor(string $label, string $url): string
    {
        $haystack = mb_strtolower($label.' '.$url);
        foreach (self::KEYWORDS as $symbol => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    return $symbol;
                }
            }
        }

        return self::DEFAULT;
    }
}
