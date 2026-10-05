<?php

declare(strict_types=1);

namespace App\Tests\Unit\Admin\Infrastructure\Http;

use App\Module\Admin\Infrastructure\Http\AdminApiExceptionSubscriber;
use App\Shared\UI\Http\AdminApiErrorResponder;
use App\Tests\Support\Logging\RecordingLogger;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

final class AdminApiExceptionSubscriberTest extends TestCase
{
    public function testSubscribesToKernelException(): void
    {
        self::assertArrayHasKey(KernelEvents::EXCEPTION, AdminApiExceptionSubscriber::getSubscribedEvents());
    }

    public function testUnexpectedExceptionBecomesGenericJsonAndIsLogged(): void
    {
        $logger = new RecordingLogger();
        $event = $this->event('/admin/api/pages', new RuntimeException('SQLSTATE[23000]: Duplicate entry in /var/www/app'), $logger);

        $response = $event['event']->getResponse();

        self::assertNotNull($response);
        self::assertSame(500, $response->getStatusCode());
        self::assertSame('{"error":"Internal server error","code":"INTERNAL"}', $response->getContent());
        self::assertCount(1, $logger->records);
    }

    public function testNotFoundRouteDoesNotEchoRoutingMessage(): void
    {
        $event = $this->event('/admin/api/unknown', new NotFoundHttpException('No route found for "GET /admin/api/unknown"'));

        $response = $event['event']->getResponse();

        self::assertNotNull($response);
        self::assertSame(404, $response->getStatusCode());
        self::assertSame('{"error":"Not Found","code":"NOT_FOUND"}', $response->getContent());
    }

    public function testApplicationAccessDeniedMessageIsKept(): void
    {
        $event = $this->event('/admin/api/settings/site/name', new AccessDeniedHttpException('Invalid CSRF token.'));

        $response = $event['event']->getResponse();

        self::assertNotNull($response);
        self::assertSame(403, $response->getStatusCode());
        self::assertSame('{"error":"Invalid CSRF token.","code":"ACCESS_DENIED"}', $response->getContent());
    }

    public function testServerHttpExceptionIsGenericAndKeepsHeaders(): void
    {
        $event = $this->event('/admin/api/system/overview', new ServiceUnavailableHttpException(30, 'Database host db.internal is down'));

        $response = $event['event']->getResponse();

        self::assertNotNull($response);
        self::assertSame(503, $response->getStatusCode());
        self::assertSame('30', $response->headers->get('Retry-After'));
        self::assertSame('{"error":"Internal server error","code":"INTERNAL"}', $response->getContent());
    }

    public function testNonAdminApiPathsAreIgnored(): void
    {
        foreach (['/', '/api/leads', '/admin/dashboard', '/admin/apiary'] as $path) {
            $event = $this->event($path, new RuntimeException('boom'));

            self::assertNull($event['event']->getResponse(), $path);
        }
    }

    public function testExistingResponseIsNotOverridden(): void
    {
        $logger = new RecordingLogger();
        $subscriber = new AdminApiExceptionSubscriber(new AdminApiErrorResponder($logger));
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ExceptionEvent($kernel, Request::create('/admin/api/pages'), HttpKernelInterface::MAIN_REQUEST, new RuntimeException('boom'));
        $event->setResponse(new Response('redirect', 302));

        $subscriber->onKernelException($event);

        self::assertSame(302, $event->getResponse()?->getStatusCode());
        self::assertSame([], $logger->records);
    }

    /**
     * @return array{event: ExceptionEvent}
     */
    private function event(string $path, Throwable $exception, ?RecordingLogger $logger = null): array
    {
        $subscriber = new AdminApiExceptionSubscriber(new AdminApiErrorResponder($logger ?? new RecordingLogger()));
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ExceptionEvent($kernel, Request::create($path), HttpKernelInterface::MAIN_REQUEST, $exception);

        $subscriber->onKernelException($event);

        return ['event' => $event];
    }
}
