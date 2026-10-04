<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Ulid;

final class Version20261014153700 extends AbstractMigration
{
    private const array LEGACY_TEMPLATE_LAYOUTS = [
        'home_default' => ['Home default', ['hero', 'feature_grid', 'portfolio_grid', 'steps', 'faq', 'cta_form', 'seo_text']],
        'service_landing' => ['Service landing', ['hero', 'text_image', 'feature_grid', 'gallery', 'price_cards', 'steps', 'portfolio_grid', 'faq', 'cta_form', 'seo_text']],
        'material_landing' => ['Material landing', ['hero', 'text', 'gallery', 'feature_grid', 'table', 'price_cards', 'faq', 'cta_form', 'seo_text']],
        'portfolio_index' => ['Portfolio index', ['hero', 'portfolio_grid', 'cta_form', 'seo_text']],
        'contacts' => ['Contacts', ['hero', 'contacts', 'map', 'cta_form']],
        'prices' => ['Prices', ['hero', 'table', 'price_cards', 'cta_form', 'seo_text']],
        'text_page' => ['Text page', ['hero', 'text']],
        'seo_landing' => ['SEO landing', ['hero', 'text_image', 'feature_grid', 'faq', 'cta_form', 'seo_text']],
    ];

    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription(): string
    {
        return 'Page templates: kind column (page/section), thematic system templates with structured blocks and editor hints.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE content_page_templates ADD kind VARCHAR(16) DEFAULT 'page' NOT NULL");

        $now = '2026-10-14 15:37:00';
        foreach ($this->systemTemplates() as $code => $template) {
            $this->addSql(
                'UPDATE content_page_templates SET name = ?, description = ?, blocks_schema = ?, updated_at = ? WHERE code = ?',
                [$template['name'], $template['description'], $this->json($template['blocks']), $now, $code],
            );
        }

        foreach ($this->newTemplates() as $code => $template) {
            $this->addSql(
                'INSERT INTO content_page_templates (id, code, name, description, page_type, blocks_schema, default_seo, default_settings, is_system, is_active, kind, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1, ?, ?, ?)',
                [
                    Ulid::fromString($template['id'])->toBinary(),
                    $code,
                    $template['name'],
                    $template['description'],
                    $template['page_type'],
                    $this->json($template['blocks']),
                    '{}',
                    '{}',
                    'page',
                    $now,
                    $now,
                ],
                [ParameterType::BINARY],
            );
        }
    }

    public function down(Schema $schema): void
    {
        foreach (array_keys($this->newTemplates()) as $code) {
            $this->addSql('DELETE FROM content_page_templates WHERE code = ?', [$code]);
        }

        $now = '2026-10-14 15:37:00';
        foreach (self::LEGACY_TEMPLATE_LAYOUTS as $code => [$name, $types]) {
            $blocks = array_map(static fn (string $type, int $position): array => [
                'type' => $type,
                'name' => str_replace('_', ' ', ucfirst($type)),
                'position' => $position,
                'content' => [],
                'settings' => [],
                'isEnabled' => true,
            ], $types, array_keys($types));

            $this->addSql(
                'UPDATE content_page_templates SET name = ?, blocks_schema = ?, updated_at = ? WHERE code = ?',
                [$name, $this->json($blocks), $now, $code],
            );
        }

        $this->addSql('ALTER TABLE content_page_templates DROP kind');
    }

    /**
     * @return array<string, array{name: string, description: string, blocks: list<array<string, mixed>>}>
     */
    private function systemTemplates(): array
    {
        return [
            'home_default' => [
                'name' => 'Главная страница',
                'description' => 'Первый экран, преимущества, работы, этапы, вопросы и форма заявки.',
                'blocks' => $this->blocks([
                    $this->hero('Заборы и ворота под ключ', 'Производство, доставка и монтаж. Гарантия на работы.'),
                    $this->advantages(),
                    $this->portfolio(),
                    $this->steps(),
                    $this->faq(),
                    $this->contactForm(),
                    $this->seoText(),
                ]),
            ],
            'service_landing' => [
                'name' => 'Страница услуги',
                'description' => 'Описание услуги, преимущества, галерея, цены, этапы, работы и вопросы.',
                'blocks' => $this->blocks([
                    $this->hero('Название услуги', 'Что получает клиент и за какое время.'),
                    $this->text(),
                    $this->advantages(),
                    $this->gallery(),
                    $this->priceTable(),
                    $this->steps(),
                    $this->portfolio(),
                    $this->faq(),
                    $this->contactForm(),
                    $this->seoText(),
                ]),
            ],
            'material_landing' => [
                'name' => 'Страница материала',
                'description' => 'Материал: описание, галерея, характеристики, цены и вопросы.',
                'blocks' => $this->blocks([
                    $this->hero('Название материала', 'Для каких участков подходит и чем отличается.'),
                    $this->text(),
                    $this->gallery(),
                    $this->advantages(),
                    $this->priceTable(),
                    $this->faq(),
                    $this->contactForm(),
                    $this->seoText(),
                ]),
            ],
            'portfolio_index' => [
                'name' => 'Портфолио',
                'description' => 'Список выполненных работ с призывом оставить заявку.',
                'blocks' => $this->blocks([
                    $this->hero('Наши работы', 'Реальные объекты: фото, материалы, сроки.'),
                    $this->portfolio(),
                    $this->gallery(),
                    $this->contactForm('Хотите такой же забор?'),
                    $this->seoText(),
                ]),
            ],
            'contacts' => [
                'name' => 'Контакты',
                'description' => 'Телефон, адрес, режим работы, схема проезда и форма обратной связи.',
                'blocks' => $this->blocks([
                    $this->hero('Контакты', 'Как с нами связаться и как нас найти.'),
                    $this->contacts(),
                    $this->contactForm('Напишите нам'),
                ]),
            ],
            'prices' => [
                'name' => 'Прайс',
                'description' => 'Таблица цен, варианты комплектации и форма расчёта стоимости.',
                'blocks' => $this->blocks([
                    $this->hero('Цены на заборы и ворота', 'Ориентировочные цены «под ключ». Точную стоимость назовём после замера.'),
                    $this->priceTable(),
                    $this->advantages('Что входит в стоимость'),
                    $this->faq(),
                    $this->contactForm('Рассчитать стоимость'),
                    $this->seoText(),
                ]),
            ],
            'text_page' => [
                'name' => 'Текстовая страница',
                'description' => 'Заголовок и текст: для статей, условий, политик.',
                'blocks' => $this->blocks([
                    $this->hero('Заголовок страницы', ''),
                    $this->text(),
                ]),
            ],
            'seo_landing' => [
                'name' => 'SEO-посадочная',
                'description' => 'Страница под поисковый запрос: текст, преимущества, вопросы и заявка.',
                'blocks' => $this->blocks([
                    $this->hero('Заголовок под поисковый запрос', 'Ответ на запрос в одном предложении.'),
                    $this->text(),
                    $this->advantages(),
                    $this->faq(),
                    $this->contactForm(),
                    $this->seoText(),
                ]),
            ],
        ];
    }

    /**
     * @return array<string, array{id: string, name: string, description: string, page_type: string, blocks: list<array<string, mixed>>}>
     */
    private function newTemplates(): array
    {
        return [
            'fence_profnastil' => [
                'id' => '01H0000000000000000000000J',
                'name' => 'Забор из профнастила',
                'description' => 'Услуга «забор из профнастила»: описание, преимущества, цены, фото, работы, вопросы и заявка.',
                'page_type' => 'service',
                'blocks' => $this->blocks([
                    $this->hero('Забор из профнастила под ключ', 'Замер, изготовление и монтаж за 3–7 дней. Гарантия на работы.', 'Укажите в заголовке город и главное преимущество, в подзаголовке — сроки и гарантию.'),
                    $this->text('<h2>Забор из профнастила: что нужно знать</h2><p>Опишите, из чего состоит забор (профнастил, столбы, лаги), для каких участков он подходит и чем лучше других вариантов.</p><p>Добавьте 2–3 абзаца с ключевыми словами, написанными естественным текстом.</p>'),
                    $this->advantages('Почему профнастил', [
                        ['title' => 'Долговечность', 'text' => 'Оцинкованная сталь с полимерным покрытием служит 20+ лет.'],
                        ['title' => 'Быстрый монтаж', 'text' => 'Установка забора на типовом участке занимает 1–2 дня.'],
                        ['title' => 'Приватность', 'text' => 'Сплошное полотно закрывает участок от посторонних глаз.'],
                        ['title' => 'Доступная цена', 'text' => 'Один из самых выгодных вариантов по соотношению цены и срока службы.'],
                    ], 'Оставьте 3–6 реальных преимуществ с конкретными цифрами и условиями.'),
                    $this->priceTable([
                        'columns' => ['Вариант', 'Высота', 'Цена, ₽/м.п.'],
                        'rows' => [['Эконом (С8, столбы 60×60)', '1,5 м', 'от 1 500'], ['Стандарт (С8, лаги 40×20)', '1,8 м', 'от 1 900'], ['Премиум (С21, двусторонний)', '2,0 м', 'от 2 600']],
                    ], 'Укажите актуальные цены «от» за погонный метр. Обновляйте при каждом изменении прайса.'),
                    $this->gallery('Добавьте 6–12 фотографий готовых заборов из медиатеки и заполните alt-тексты.'),
                    $this->portfolio('Выберите 3–6 лучших объектов и укажите ссылки на их страницы.'),
                    $this->faq([
                        ['question' => 'Сколько стоит забор из профнастила?', 'answer' => 'Стоимость зависит от высоты, типа профнастила и длины забора. Ориентиры — в таблице цен, точную сумму назовём после замера.'],
                        ['question' => 'Как быстро вы установите забор?', 'answer' => 'Типовой участок — 1–2 дня после изготовления комплектующих.'],
                        ['question' => 'Даёте ли вы гарантию?', 'answer' => 'Да. Укажите срок гарантии на материалы и монтаж.'],
                    ], 'Замените ответы на реальные: вопросы показываются в поиске, если включена разметка FAQ.'),
                    $this->cta('Рассчитайте стоимость забора', 'Бесплатный замер и расчёт в день обращения.', 'Заказать замер'),
                    $this->contactForm('Оставьте заявку на расчёт'),
                ]),
            ],
            'fence_jalousie' => [
                'id' => '01H0000000000000000000000K',
                'name' => 'Забор-жалюзи',
                'description' => 'Услуга «забор-жалюзи»: описание, преимущества, цены, фото, работы, вопросы и заявка.',
                'page_type' => 'service',
                'blocks' => $this->blocks([
                    $this->hero('Забор-жалюзи под ключ', 'Современный вид, продуваемость и приватность. Монтаж с гарантией.', 'Укажите город и отличие вашего забора-жалюзи (цвета, двусторонняя покраска, срок).'),
                    $this->text('<h2>Забор-жалюзи: особенности</h2><p>Расскажите, как устроен забор-жалюзи (горизонтальные или вертикальные ламели), какие цвета и профили доступны.</p><p>Опишите, чем он лучше сплошного профнастила и штакетника.</p>'),
                    $this->advantages('Преимущества жалюзи', [
                        ['title' => 'Продуваемость', 'text' => 'Ветер проходит через ламели — нагрузка на столбы меньше.'],
                        ['title' => 'Современный дизайн', 'text' => 'Ровные линии и широкая палитра цветов RAL.'],
                        ['title' => 'Обзор закрыт', 'text' => 'Ламели скрывают участок под углом, при этом свет проходит.'],
                        ['title' => 'Долгий срок службы', 'text' => 'Оцинкованная сталь с полимерным покрытием.'],
                    ], 'Оставьте 3–6 реальных преимуществ.'),
                    $this->priceTable([
                        'columns' => ['Вариант', 'Высота', 'Цена, ₽/м.п.'],
                        'rows' => [['Жалюзи односторонние', '1,8 м', 'от 3 200'], ['Жалюзи двусторонние', '1,8 м', 'от 3 900'], ['Жалюзи с каркасом из трубы', '2,0 м', 'от 4 500']],
                    ], 'Укажите актуальные цены «от» за погонный метр.'),
                    $this->gallery('Добавьте 6–12 фотографий заборов-жалюзи из медиатеки и заполните alt-тексты.'),
                    $this->portfolio('Выберите 3–6 лучших объектов и укажите ссылки на их страницы.'),
                    $this->faq([
                        ['question' => 'Чем забор-жалюзи отличается от профнастила?', 'answer' => 'Жалюзи пропускают воздух и свет, но закрывают обзор под углом. Профнастил — сплошной.'],
                        ['question' => 'Какие цвета доступны?', 'answer' => 'Укажите палитру RAL и доступные варианты покрытия.'],
                        ['question' => 'Сколько занимает установка?', 'answer' => 'Укажите реальные сроки изготовления и монтажа.'],
                    ], 'Замените ответы на реальные.'),
                    $this->cta('Закажите забор-жалюзи', 'Бесплатный замер и расчёт в день обращения.', 'Заказать замер'),
                    $this->contactForm('Оставьте заявку на расчёт'),
                ]),
            ],
            'gates_wickets' => [
                'id' => '01H0000000000000000000000M',
                'name' => 'Ворота и калитки',
                'description' => 'Услуга «ворота и калитки»: типы, преимущества, цены, фото, работы, вопросы и заявка.',
                'page_type' => 'service',
                'blocks' => $this->blocks([
                    $this->hero('Ворота и калитки на заказ', 'Откатные, распашные и секционные. Изготовление и установка с автоматикой.', 'Укажите город и типы ворот, которые вы делаете.'),
                    $this->text('<h2>Какие ворота выбрать</h2><p>Сравните откатные, распашные и секционные ворота: когда подходит каждый тип, сколько нужно места, как работает автоматика.</p>'),
                    $this->advantages('Почему выбирают нас', [
                        ['title' => 'Изготовление по размерам', 'text' => 'Делаем под проём на вашем участке.'],
                        ['title' => 'Автоматика', 'text' => 'Устанавливаем и настраиваем привод, пульты и калитки.'],
                        ['title' => 'Единый стиль', 'text' => 'Ворота, калитка и забор в одном цвете и дизайне.'],
                        ['title' => 'Гарантия', 'text' => 'Гарантия на конструкцию и монтаж.'],
                    ], 'Оставьте 3–6 реальных преимуществ.'),
                    $this->priceTable([
                        'columns' => ['Тип', 'Размер', 'Цена'],
                        'rows' => [['Распашные ворота', '3,5 × 2,0 м', 'от 18 000 ₽'], ['Откатные ворота', '4,0 × 2,0 м', 'от 45 000 ₽'], ['Калитка', '1,0 × 2,0 м', 'от 7 000 ₽'], ['Автоматика для ворот', 'комплект', 'от 25 000 ₽']],
                    ], 'Укажите актуальные цены «от» по каждому типу.'),
                    $this->gallery('Добавьте 6–12 фотографий установленных ворот и калиток из медиатеки.'),
                    $this->portfolio('Выберите 3–6 лучших объектов и укажите ссылки на их страницы.'),
                    $this->faq([
                        ['question' => 'Какие ворота лучше выбрать?', 'answer' => 'Зависит от свободного места у въезда и бюджета. Опишите правила выбора.'],
                        ['question' => 'Можно ли добавить автоматику позже?', 'answer' => 'Да, если ворота рассчитаны на привод. Уточните условия.'],
                        ['question' => 'Сколько стоит калитка?', 'answer' => 'Укажите диапазон цен и от чего он зависит.'],
                    ], 'Замените ответы на реальные.'),
                    $this->cta('Рассчитайте ворота и калитку', 'Бесплатный замер и расчёт в день обращения.', 'Заказать замер'),
                    $this->contactForm('Оставьте заявку на расчёт'),
                ]),
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $blocks
     *
     * @return list<array<string, mixed>>
     */
    private function blocks(array $blocks): array
    {
        return array_map(static fn (array $block, int $position): array => [...$block, 'position' => $position], $blocks, array_keys($blocks));
    }

    /**
     * @param array<string, mixed> $content
     *
     * @return array<string, mixed>
     */
    private function block(string $type, string $name, array $content, string $hint): array
    {
        return [
            'type' => $type,
            'name' => $name,
            'position' => 0,
            'content' => $content,
            'settings' => new \stdClass(),
            'isEnabled' => true,
            'hint' => $hint,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function hero(string $title, string $subtitle, string $hint = 'Заголовок страницы и короткое предложение. Это первый экран: сформулируйте выгоду клиенту.'): array
    {
        return $this->block('hero.classic', 'Первый экран', [
            'title' => $title,
            'subtitle' => $subtitle,
            'text' => '',
            'cta' => ['label' => 'Рассчитать стоимость', 'href' => '#lead'],
        ], $hint);
    }

    /**
     * @return array<string, mixed>
     */
    private function text(string $html = '<h2>Подзаголовок</h2><p>Основной текст страницы. Опишите предложение простыми словами, 2–3 абзаца.</p>'): array
    {
        return $this->block('rich-text', 'Текст', ['html' => $html], 'Основной текст: 2–3 абзаца с подзаголовком. Замените текст-заготовку на свой.');
    }

    /**
     * @return array<string, mixed>
     */
    private function seoText(): array
    {
        return $this->block('rich-text', 'SEO-текст', [
            'html' => '<h2>Подробнее</h2><p>Текст для поискового продвижения: ответы на частые вопросы, ключевые слова естественным языком. Замените заготовку на свой текст.</p>',
        ], 'Дополнительный текст внизу страницы для поисковых запросов. Не дублируйте текст из других разделов.');
    }

    /**
     * @param list<array{title: string, text: string}>|null $items
     *
     * @return array<string, mixed>
     */
    private function advantages(string $name = 'Преимущества', ?array $items = null, string $hint = 'Оставьте 3–6 реальных преимуществ с цифрами и условиями.'): array
    {
        return $this->block('features', $name, [
            'items' => $items ?? [
                ['title' => 'Собственное производство', 'text' => 'Без посредников, контролируем качество и сроки.'],
                ['title' => 'Монтаж под ключ', 'text' => 'Замер, изготовление, доставка и установка.'],
                ['title' => 'Гарантия', 'text' => 'Гарантия на материалы и монтаж.'],
            ],
        ], $hint);
    }

    /**
     * @return array<string, mixed>
     */
    private function steps(): array
    {
        return $this->block('steps', 'Этапы работы', [
            'items' => [
                ['title' => 'Заявка и замер', 'text' => 'Выезжаем на участок и считаем стоимость бесплатно.'],
                ['title' => 'Договор и оплата', 'text' => 'Фиксируем цену и сроки в договоре.'],
                ['title' => 'Изготовление', 'text' => 'Готовим комплектующие на производстве.'],
                ['title' => 'Монтаж', 'text' => 'Устанавливаем и принимаем работу вместе с вами.'],
            ],
        ], 'Опишите реальный порядок работы компании, 3–5 шагов.');
    }

    /**
     * @param list<array{question: string, answer: string}>|null $items
     *
     * @return array<string, mixed>
     */
    private function faq(?array $items = null, string $hint = 'Замените вопросы и ответы на реальные. Пустые вопросы удалите.'): array
    {
        return $this->block('faq', 'Вопросы и ответы', [
            'items' => $items ?? [
                ['question' => 'Сколько стоит работа?', 'answer' => 'Цена зависит от материалов и объёма. Точную стоимость назовём после замера.'],
                ['question' => 'Какие сроки?', 'answer' => 'Укажите реальные сроки изготовления и монтажа.'],
            ],
        ], $hint);
    }

    /**
     * @return array<string, mixed>
     */
    private function gallery(string $hint = 'Добавьте 6–12 фотографий из медиатеки и заполните alt-тексты.'): array
    {
        return $this->block('gallery', 'Фотогалерея', ['items' => []], $hint);
    }

    /**
     * @return array<string, mixed>
     */
    private function portfolio(string $hint = 'Выберите 3–6 лучших объектов: фото, название и ссылка на страницу работы.'): array
    {
        return $this->block('portfolio', 'Наши работы', ['items' => []], $hint);
    }

    /**
     * @param array{columns: list<string>, rows: list<list<string>>}|null $table
     *
     * @return array<string, mixed>
     */
    private function priceTable(?array $table = null, string $hint = 'Укажите актуальные цены «от». Обновляйте при каждом изменении прайса.'): array
    {
        return $this->block('price-table', 'Цены', $table ?? [
            'columns' => ['Позиция', 'Единица', 'Цена, ₽'],
            'rows' => [['Позиция 1', 'м.п.', 'от 1 000'], ['Позиция 2', 'шт.', 'от 5 000']],
        ], $hint);
    }

    /**
     * @return array<string, mixed>
     */
    private function cta(string $title, string $subtitle, string $label): array
    {
        return $this->block('cta', 'Призыв к действию', [
            'title' => $title,
            'subtitle' => $subtitle,
            'text' => '',
            'cta' => ['label' => $label, 'href' => '#lead'],
        ], 'Короткий призыв с одной кнопкой. Ссылка #lead ведёт к форме заявки на странице.');
    }

    /**
     * @return array<string, mixed>
     */
    private function contactForm(string $title = 'Оставьте заявку'): array
    {
        return $this->block('contact-form', 'Форма заявки', ['title' => $title], 'Форма заявки. Заявки попадают в раздел «Заявки».');
    }

    /**
     * @return array<string, mixed>
     */
    private function contacts(): array
    {
        return $this->block('rich-text', 'Контактные данные', [
            'html' => '<h2>Как связаться</h2><p>Телефон: укажите номер.</p><p>Email: укажите адрес.</p><p>Адрес офиса и производства: укажите адрес.</p><p>Режим работы: укажите дни и часы.</p>',
        ], 'Телефон, email, адрес и режим работы. Данные должны совпадать с настройками сайта и разметкой LocalBusiness.');
    }

    /**
     * @param list<array<string, mixed>> $blocks
     */
    private function json(array $blocks): string
    {
        return json_encode($blocks, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
    }
}
