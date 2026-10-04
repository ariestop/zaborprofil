<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lead\Application\Export;

use App\Module\Lead\Application\Export\LeadCsvExporter;
use App\Module\Lead\Domain\Entity\Lead;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class LeadCsvExporterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function cells(): iterable
    {
        yield 'plain text' => ['Иван Петров', 'Иван Петров'];
        yield 'empty' => ['', ''];
        yield 'formula' => ['=SUM(A1:A2)', "'=SUM(A1:A2)"];
        yield 'plus formula' => ['+cmd|calc', "'+cmd|calc"];
        yield 'phone with plus' => ['+7 (900) 123-45-67', '+7 (900) 123-45-67'];
        yield 'plus with payload is not a phone' => ['+7+HYPERLINK("x")', "'+7+HYPERLINK(\"x\")"];
        yield 'minus formula' => ['-2+3', "'-2+3"];
        yield 'at sign' => ['@SUM(1)', "'@SUM(1)"];
        yield 'tab prefix' => ["\t=1+1", "'\t=1+1"];
        yield 'carriage return prefix' => ["\r=1+1", "'\r=1+1"];
        yield 'leading spaces before formula' => ['  =1+1', "'  =1+1"];
        yield 'equals inside text is harmless' => ['цена = 100', 'цена = 100'];
    }

    #[DataProvider('cells')]
    public function testEscapesCsvInjection(string $input, string $expected): void
    {
        self::assertSame($expected, LeadCsvExporter::escapeCell($input));
    }

    public function testExportsHeaderBomAndAssigneeLabelsWithoutConsentSnapshot(): void
    {
        $lead = new Lead('callback', 'Иван, "Сантехник"', '8 900 000-00-00', 'ivan@example.test', "Первая\nвторая", ['ip' => '203.0.113.7', 'userAgent' => 'UA-secret']);
        $lead->assignTo(Ulid::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV'));

        $csv = (new LeadCsvExporter())->export([$lead], ['01ARZ3NDEKTSV4RRFFQ69G5FAV' => 'manager@example.test']);

        self::assertStringStartsWith("\xEF\xBB\xBFid,created_at,name,phone,email,source,status,assignee,spam_score,message\n", $csv);
        self::assertStringContainsString('"Иван, ""Сантехник"""', $csv);
        self::assertStringContainsString(',manager@example.test,0,"Первая'."\n".'вторая"', $csv);
        self::assertStringNotContainsString('203.0.113.7', $csv);
        self::assertStringNotContainsString('UA-secret', $csv);
    }
}
