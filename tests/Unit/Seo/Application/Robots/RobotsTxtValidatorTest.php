<?php

declare(strict_types=1);

namespace App\Tests\Unit\Seo\Application\Robots;

use App\Module\Seo\Application\Robots\RobotsTxtIssue;
use App\Module\Seo\Application\Robots\RobotsTxtValidator;
use PHPUnit\Framework\TestCase;

final class RobotsTxtValidatorTest extends TestCase
{
    public function testDefaultProductionBodyHasNoIssues(): void
    {
        $body = "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /api/\n\nSitemap: https://zaborprofil.ru/sitemap.xml\n";

        self::assertSame([], (new RobotsTxtValidator())->validate($body));
    }

    public function testReportsSyntaxErrorsWithLineNumbers(): void
    {
        $body = "Disallow: /early/\nUser-agent: *\nnonsense\nDisallow: private\nSitemap: /sitemap.xml\nCrawl-delay: abc\nUser-agent:\n";

        $errors = $this->errors($body);

        self::assertSame(
            [1, 3, 4, 5, 6, 7],
            array_map(static fn (RobotsTxtIssue $issue): ?int => $issue->line, $errors),
        );
    }

    public function testWarnsAboutFullBlockMissingSitemapAndAdmin(): void
    {
        $issues = (new RobotsTxtValidator())->validate("User-agent: *\nDisallow: /\n");

        self::assertSame([], $this->errors("User-agent: *\nDisallow: /\n"));
        $messages = implode("\n", array_map(static fn (RobotsTxtIssue $issue): string => $issue->message, $issues));
        self::assertStringContainsString('закрывает весь сайт', $messages);
        self::assertStringContainsString('Sitemap', $messages);
        self::assertStringContainsString('/admin/', $messages);
    }

    public function testFullBlockWithAllowExceptionIsNotWarned(): void
    {
        $issues = (new RobotsTxtValidator())->validate("User-agent: *\nDisallow: /\nAllow: /public/\nDisallow: /admin/\nSitemap: https://a.test/s.xml\n");

        self::assertSame([], $issues);
    }

    public function testUnknownDirectiveIsOnlyWarning(): void
    {
        $issues = (new RobotsTxtValidator())->validate("User-agent: *\nDisallow: /admin/\nSitemap: https://a.test/s.xml\nNoindex: /x/\n");

        self::assertCount(1, $issues);
        self::assertSame(RobotsTxtIssue::WARNING, $issues[0]->severity);
        self::assertSame(4, $issues[0]->line);
    }

    public function testIgnoresComments(): void
    {
        $body = "# header\nUser-agent: * # all\nDisallow: /admin/ # private\nSitemap: https://a.test/s.xml\n";

        self::assertSame([], (new RobotsTxtValidator())->validate($body));
    }

    /**
     * @return list<RobotsTxtIssue>
     */
    private function errors(string $body): array
    {
        return array_values(array_filter(
            (new RobotsTxtValidator())->validate($body),
            static fn (RobotsTxtIssue $issue): bool => $issue->isError(),
        ));
    }
}
