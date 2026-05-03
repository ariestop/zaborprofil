<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content\UI\Admin;

use App\Module\Content\Domain\Exception\ContentNotFoundException;
use App\Module\Content\UI\Admin\ContentApiResponder;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class ContentApiResponderTest extends TestCase
{
    public function testReturns404WithDomainMessageForNotFound(): void
    {
        $responder = new ContentApiResponder();

        $response = $responder->error(new ContentNotFoundException('Page not found'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(
            ['error' => 'Page not found', 'code' => 'NOT_FOUND'],
            json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR),
        );
    }

    public function testReturns422WithDomainMessageForValidationErrors(): void
    {
        $responder = new ContentApiResponder();

        $response = $responder->error(new \InvalidArgumentException('slug is required'));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(
            ['error' => 'slug is required', 'code' => 'VALIDATION'],
            json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR),
        );
    }

    public function testReturnsGeneric500AndDoesNotLeakExceptionMessage(): void
    {
        $logger = new class () extends AbstractLogger {
            /**
             * @var list<array{level: string, context: array<string, mixed>}>
             */
            public array $records = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                if (!\is_string($level)) {
                    throw new \InvalidArgumentException('Log level must be a string.');
                }

                $stringKeyContext = [];
                foreach ($context as $key => $value) {
                    if (\is_string($key)) {
                        $stringKeyContext[$key] = $value;
                    }
                }

                $this->records[] = [
                    'level' => $level,
                    'context' => $stringKeyContext,
                ];
            }
        };

        $responder = new ContentApiResponder($logger);

        $response = $responder->error(new \RuntimeException('SQLSTATE[42P01]: secret table name'));

        self::assertSame(500, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(['error' => 'Internal server error', 'code' => 'INTERNAL'], $payload);
        self::assertStringNotContainsString('SQLSTATE', (string) $response->getContent());

        self::assertCount(1, $logger->records);
        self::assertSame('error', $logger->records[0]['level']);
        self::assertInstanceOf(\RuntimeException::class, $logger->records[0]['context']['exception']);
    }
}
