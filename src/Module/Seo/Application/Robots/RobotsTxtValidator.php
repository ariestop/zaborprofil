<?php

declare(strict_types=1);

namespace App\Module\Seo\Application\Robots;

/**
 * Проверяет синтаксис robots.txt: группы User-agent, директивы Allow/Disallow, Sitemap, Crawl-delay.
 * Ошибки блокируют сохранение, предупреждения только показываются редактору.
 */
final class RobotsTxtValidator
{
    private const int MAX_LINE_LENGTH = 1024;

    /**
     * @var list<string>
     */
    private const array KNOWN_DIRECTIVES = ['user-agent', 'allow', 'disallow', 'sitemap', 'crawl-delay', 'host', 'clean-param'];

    /**
     * @return list<RobotsTxtIssue>
     */
    public function validate(string $body): array
    {
        $issues = [];
        $hasGroup = false;
        $hasSitemap = false;
        $hasAdminRule = false;
        $currentGroupAgents = [];
        $groupRules = [];
        $groups = [];
        $collectingAgents = false;

        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $body));
        foreach ($lines as $index => $rawLine) {
            $number = $index + 1;
            $line = trim((string) preg_replace('/\s+#.*$|^#.*$/', '', $rawLine));
            if ($line === '') {
                continue;
            }

            if (mb_strlen($rawLine) > self::MAX_LINE_LENGTH) {
                $issues[] = new RobotsTxtIssue(RobotsTxtIssue::ERROR, $number, \sprintf('Строка длиннее %d символов.', self::MAX_LINE_LENGTH));
                continue;
            }

            $separator = strpos($line, ':');
            if ($separator === false) {
                $issues[] = new RobotsTxtIssue(RobotsTxtIssue::ERROR, $number, 'Ожидается формат «Директива: значение».');
                continue;
            }

            $directive = strtolower(trim(substr($line, 0, $separator)));
            $value = trim(substr($line, $separator + 1));

            if (!\in_array($directive, self::KNOWN_DIRECTIVES, true)) {
                $issues[] = new RobotsTxtIssue(RobotsTxtIssue::WARNING, $number, \sprintf('Неизвестная директива «%s»: поисковые роботы её проигнорируют.', trim(substr($line, 0, $separator))));
                continue;
            }

            switch ($directive) {
                case 'user-agent':
                    if ($value === '') {
                        $issues[] = new RobotsTxtIssue(RobotsTxtIssue::ERROR, $number, 'User-agent не может быть пустым.');
                        break;
                    }

                    if (!$collectingAgents) {
                        if ($currentGroupAgents !== []) {
                            $groups[] = ['agents' => $currentGroupAgents, 'rules' => $groupRules];
                        }
                        $currentGroupAgents = [];
                        $groupRules = [];
                    }

                    $currentGroupAgents[] = $value;
                    $collectingAgents = true;
                    $hasGroup = true;
                    break;

                case 'allow':
                case 'disallow':
                    $collectingAgents = false;
                    if (!$hasGroup) {
                        $issues[] = new RobotsTxtIssue(RobotsTxtIssue::ERROR, $number, \sprintf('Директива %s должна идти после строки User-agent.', ucfirst($directive)));
                        break;
                    }

                    if ($value === '') {
                        if ($directive === 'allow') {
                            $issues[] = new RobotsTxtIssue(RobotsTxtIssue::WARNING, $number, 'Пустой Allow игнорируется роботами.');
                        }
                        break;
                    }

                    if (!str_starts_with($value, '/') && !str_starts_with($value, '*')) {
                        $issues[] = new RobotsTxtIssue(RobotsTxtIssue::ERROR, $number, \sprintf('Путь в %s должен начинаться с «/» или «*».', ucfirst($directive)));
                        break;
                    }

                    $groupRules[] = [$directive, $value, $number];
                    if ($directive === 'disallow' && ($value === '/admin' || $value === '/admin/')) {
                        $hasAdminRule = true;
                    }
                    break;

                case 'sitemap':
                    if (preg_match('/^https?:\/\/[^\s\/]+\S*$/i', $value) !== 1) {
                        $issues[] = new RobotsTxtIssue(RobotsTxtIssue::ERROR, $number, 'Sitemap должен быть абсолютным URL с http:// или https://.');
                        break;
                    }

                    $hasSitemap = true;
                    break;

                case 'crawl-delay':
                    if (!is_numeric($value) || (float) $value < 0) {
                        $issues[] = new RobotsTxtIssue(RobotsTxtIssue::ERROR, $number, 'Crawl-delay должен быть неотрицательным числом.');
                    }
                    break;

                default:
                    if ($value === '') {
                        $issues[] = new RobotsTxtIssue(RobotsTxtIssue::ERROR, $number, \sprintf('У директивы %s не указано значение.', $directive));
                    }
                    break;
            }
        }

        if ($currentGroupAgents !== []) {
            $groups[] = ['agents' => $currentGroupAgents, 'rules' => $groupRules];
        }

        foreach ($groups as $group) {
            if (!\in_array('*', $group['agents'], true)) {
                continue;
            }

            foreach ($group['rules'] as [$directive, $value, $number]) {
                if ($directive === 'disallow' && $value === '/') {
                    $allowsSomething = array_any($group['rules'], static fn (array $rule): bool => $rule[0] === 'allow');
                    if (!$allowsSomething) {
                        $issues[] = new RobotsTxtIssue(RobotsTxtIssue::WARNING, $number, 'Disallow: / для всех роботов закрывает весь сайт от индексации.');
                    }
                }
            }
        }

        if ($hasGroup && !$hasSitemap) {
            $issues[] = new RobotsTxtIssue(RobotsTxtIssue::WARNING, null, 'Не указан Sitemap: добавьте строку «Sitemap: https://домен/sitemap.xml».');
        }

        if ($hasGroup && !$hasAdminRule) {
            $issues[] = new RobotsTxtIssue(RobotsTxtIssue::WARNING, null, 'Нет правила «Disallow: /admin/» — админ-панель не закрыта от роботов в robots.txt.');
        }

        usort($issues, static fn (RobotsTxtIssue $a, RobotsTxtIssue $b): int => ($a->line ?? PHP_INT_MAX) <=> ($b->line ?? PHP_INT_MAX));

        return $issues;
    }
}
