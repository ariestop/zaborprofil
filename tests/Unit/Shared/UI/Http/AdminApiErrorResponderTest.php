<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\UI\Http;

use App\Module\Content\Domain\Exception\ContentNotFoundException;
use App\Shared\Infrastructure\Upload\UploadSecurityException;
use App\Shared\UI\Http\AdminApiErrorResponder;
use App\Tests\Support\Logging\RecordingLogger;
use Doctrine\DBAL\Driver\AbstractException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\ORMInvalidArgumentException;
use InvalidArgumentException;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Throwable;
use ValueError;

final class AdminApiErrorResponderTest extends TestCase
{
    public function testNotFoundExceptionKeepsDomainMessage(): void
    {
        $response = $this->responder()->fromThrowable(new ContentNotFoundException('Page not found.'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(['error' => 'Page not found.', 'code' => 'NOT_FOUND'], $this->payload($response));
    }

    /**
     * @return iterable<string, array{Throwable}>
     */
    public static function validationExceptions(): iterable
    {
        yield 'invalid argument' => [new InvalidArgumentException('Field "label" must be a string.')];
        yield 'value error' => [new ValueError('Field "label" must be a string.')];
        yield 'upload security' => [new UploadSecurityException('Field "label" must be a string.')];
    }

    #[DataProvider('validationExceptions')]
    public function testClientSafeExceptionsBecomeValidationErrors(Throwable $exception): void
    {
        $response = $this->responder()->fromThrowable($exception);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(['error' => 'Field "label" must be a string.', 'code' => 'VALIDATION'], $this->payload($response));
    }

    public function testMalformedRequestBodyBecomesBadRequestWithoutParserDetails(): void
    {
        $logger = new RecordingLogger();
        $responder = new AdminApiErrorResponder($logger);

        $response = $responder->fromThrowable(new BadRequestException('Could not decode request body: Syntax error near "secret"'));
        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['error' => 'Request body is invalid.', 'code' => 'BAD_REQUEST'], $this->payload($response));

        $response = $responder->fromThrowable(new JsonException('Syntax error'));
        self::assertSame(400, $response->getStatusCode());
        self::assertSame([], $logger->records);
    }

    public function testUnexpectedExceptionIsLoggedAndHidden(): void
    {
        $logger = new RecordingLogger();
        $exception = new RuntimeException('SQLSTATE[HY000]: /var/www/zaborprofil/secret.php');

        $response = (new AdminApiErrorResponder($logger))->fromThrowable($exception, 'Admin Menu API');

        self::assertSame(500, $response->getStatusCode());
        self::assertSame(['error' => 'Internal server error', 'code' => 'INTERNAL'], $this->payload($response));
        self::assertStringNotContainsString('SQLSTATE', (string) $response->getContent());
        self::assertStringNotContainsString('secret', (string) $response->getContent());

        self::assertCount(1, $logger->records);
        self::assertSame('error', $logger->records[0]['level']);
        self::assertStringContainsString('Admin Menu API', $logger->records[0]['message']);
        self::assertSame($exception, $logger->records[0]['context']['exception'] ?? null);
    }

    public function testDoctrineInvalidArgumentIsTreatedAsInternalError(): void
    {
        $logger = new RecordingLogger();
        $exception = new ORMInvalidArgumentException('A new entity was found through the relationship App\Module\Content\Domain\Entity\Page#parent.');

        $response = (new AdminApiErrorResponder($logger))->fromThrowable($exception);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame(['error' => 'Internal server error', 'code' => 'INTERNAL'], $this->payload($response));
        self::assertCount(1, $logger->records);
        self::assertSame($exception, $logger->records[0]['context']['exception'] ?? null);
    }

    public function testUniqueConstraintViolationBecomesConflictWithoutSqlDetails(): void
    {
        $logger = new RecordingLogger();
        $driverException = new class ("SQLSTATE[23000]: Duplicate entry 'x-3' for key 'uniq_content_page_revisions_page_version'", '23000', 1062) extends AbstractException {
        };
        $exception = new UniqueConstraintViolationException($driverException, null);

        $response = (new AdminApiErrorResponder($logger))->fromThrowable($exception, 'Admin Content API');

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('CONFLICT', $this->payload($response)['code'] ?? null);
        self::assertStringNotContainsString('Duplicate', (string) $response->getContent());
        self::assertCount(1, $logger->records);
        self::assertSame('warning', $logger->records[0]['level']);
        self::assertSame($exception, $logger->records[0]['context']['exception'] ?? null);
    }

    private function responder(): AdminApiErrorResponder
    {
        return new AdminApiErrorResponder(new RecordingLogger());
    }

    /**
     * @return array<mixed>
     */
    private function payload(JsonResponse $response): array
    {
        $decoded = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return \is_array($decoded) ? $decoded : [];
    }
}
