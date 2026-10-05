<?php

declare(strict_types=1);

namespace App\Tests\Unit\Seo\Application\Redirect;

use App\Module\Seo\Application\Redirect\RedirectRuleValidator;
use App\Module\Seo\Application\Redirect\RedirectValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RedirectRuleValidatorTest extends TestCase
{
    private RedirectRuleValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new RedirectRuleValidator('https://zaborprofil.test');
    }

    public function testNormalizesAndEncodesRule(): void
    {
        $rule = $this->validator->validate(' старая/ ', 'новая/страница/', 301);

        self::assertSame('/%D1%81%D1%82%D0%B0%D1%80%D0%B0%D1%8F/', $rule->sourcePath);
        self::assertSame('/%D0%BD%D0%BE%D0%B2%D0%B0%D1%8F/%D1%81%D1%82%D1%80%D0%B0%D0%BD%D0%B8%D1%86%D0%B0/', $rule->targetPath);
        self::assertSame(301, $rule->statusCode);
    }

    public function testAllowsExternalHttpsTarget(): void
    {
        $rule = $this->validator->validate('/old/', 'https://example.com/new/?utm=1', 302);

        self::assertSame('https://example.com/new/?utm=1', $rule->targetPath);
        self::assertNull($this->validator->internalPath($rule->targetPath));
    }

    public function testSameHostAbsoluteTargetIsInternal(): void
    {
        self::assertSame('/new/', $this->validator->internalPath('https://zaborprofil.test/new/?a=1'));
    }

    /**
     * @return iterable<string, array{string, string, int, string}>
     */
    public static function invalidRules(): iterable
    {
        yield 'empty source' => ['  ', '/new/', 301, 'sourcePath'];
        yield 'source with query' => ['/old/?a=1', '/new/', 301, 'sourcePath'];
        yield 'source with fragment' => ['/old/#x', '/new/', 301, 'sourcePath'];
        yield 'source with domain' => ['https://zaborprofil.test/old/', '/new/', 301, 'sourcePath'];
        yield 'source double slash' => ['/old//page/', '/new/', 301, 'sourcePath'];
        yield 'source in admin zone' => ['/admin/pages', '/new/', 301, 'sourcePath'];
        yield 'source in uploads' => ['/uploads/a.jpg', '/new/', 301, 'sourcePath'];
        yield 'source too long' => ['/'.'a/' . str_repeat('b', 520), '/new/', 301, 'sourcePath'];
        yield 'empty target' => ['/old/', ' ', 301, 'targetPath'];
        yield 'self redirect' => ['/old/', '/old/', 301, 'targetPath'];
        yield 'self redirect with query' => ['/old/', '/old/?a=1', 301, 'targetPath'];
        yield 'self redirect absolute' => ['/old/', 'https://zaborprofil.test/old/', 301, 'targetPath'];
        yield 'target admin' => ['/old/', '/admin/dashboard', 301, 'targetPath'];
        yield 'target admin absolute' => ['/old/', 'https://zaborprofil.test/admin', 301, 'targetPath'];
        yield 'protocol relative target' => ['/old/', '//evil.example/path', 301, 'targetPath'];
        yield 'javascript target' => ['/old/', 'javascript:alert(1)', 301, 'targetPath'];
        yield 'ftp target' => ['/old/', 'ftp://example.com/file', 301, 'targetPath'];
        yield 'target with backslash' => ['/old/', '/new\\evil', 301, 'targetPath'];
        yield 'bad status' => ['/old/', '/new/', 200, 'statusCode'];
    }

    #[DataProvider('invalidRules')]
    public function testRejectsInvalidRule(string $source, string $target, int $status, string $field): void
    {
        try {
            $this->validator->validate($source, $target, $status);
            self::fail('Validation exception expected.');
        } catch (RedirectValidationException $exception) {
            self::assertSame($field, $exception->field);
        }
    }
}
