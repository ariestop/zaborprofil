<?php

declare(strict_types=1);

namespace App\Tests\Unit\Seo\Application\Redirect;

use App\Module\Seo\Application\Redirect\RedirectCsvParser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RedirectCsvParserTest extends TestCase
{
    public function testParsesHeaderlessCommaCsv(): void
    {
        $result = (new RedirectCsvParser())->parse("/old/,/new/\n/old2/,/new2/,302,0\n");

        self::assertSame([], $result['errors']);
        self::assertSame(
            [
                ['line' => 1, 'source' => '/old/', 'target' => '/new/', 'status' => '', 'active' => ''],
                ['line' => 2, 'source' => '/old2/', 'target' => '/new2/', 'status' => '302', 'active' => '0'],
            ],
            $result['rows'],
        );
    }

    public function testParsesSemicolonCsvWithBomAndHeader(): void
    {
        $csv = "\xEF\xBB\xBFtarget;source;status\r\n/new/;/old/;308\r\n\r\n# comment\r\n/n2/;/o2/;\r\n";

        $result = (new RedirectCsvParser())->parse($csv);

        self::assertSame(
            [
                ['line' => 2, 'source' => '/old/', 'target' => '/new/', 'status' => '308', 'active' => ''],
                ['line' => 5, 'source' => '/o2/', 'target' => '/n2/', 'status' => '', 'active' => ''],
            ],
            $result['rows'],
        );
    }

    public function testParsesQuotedFieldsWithDelimiters(): void
    {
        $result = (new RedirectCsvParser())->parse("source,target\n\"/old/\",\"/new/?a=1,2\"\n");

        self::assertSame('/new/?a=1,2', $result['rows'][0]['target']);
    }

    public function testReportsRowsWithoutTarget(): void
    {
        $result = (new RedirectCsvParser())->parse("/old/,/new/\n/lonely/\n");

        self::assertCount(1, $result['rows']);
        self::assertSame(2, $result['errors'][0]['line']);
        self::assertSame('/lonely/', $result['errors'][0]['source']);
    }

    public function testRejectsHeaderWithoutTargetColumn(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new RedirectCsvParser())->parse("source,code\n/a/,301\n");
    }

    public function testRejectsEmptyFile(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new RedirectCsvParser())->parse("\n  \n# only comment\n");
    }

    public function testRejectsTooManyRows(): void
    {
        $csv = '';
        for ($i = 0; $i <= RedirectCsvParser::MAX_ROWS; ++$i) {
            $csv .= \sprintf("/old-%d/,/new-%d/\n", $i, $i);
        }

        $this->expectException(InvalidArgumentException::class);

        (new RedirectCsvParser())->parse($csv);
    }

    public function testRejectsNonUtf8(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new RedirectCsvParser())->parse("/old/,/\xC0\xC1/\n");
    }
}
