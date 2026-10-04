<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\Http\StagingAccessSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class StagingAccessSubscriberTest extends TestCase
{
    private const string USER = 'dev';
    private const string PASSWORD = 'correct horse';

    public function testPassesThroughOutsideStaging(): void
    {
        $event = $this->requestEvent($this->subscriber(environment: 'prod'), $this->request());

        self::assertFalse($event->hasResponse());
    }

    public function testPassesThroughWhenAuthDisabled(): void
    {
        $event = $this->requestEvent($this->subscriber(authEnabled: false), $this->request());

        self::assertFalse($event->hasResponse());
    }

    public function testRejectsRequestWithoutCredentials(): void
    {
        $event = $this->requestEvent($this->subscriber(), $this->request());

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertStringStartsWith('Basic realm="', (string) $response->headers->get('WWW-Authenticate'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function testAcceptsCorrectCredentials(): void
    {
        $event = $this->requestEvent($this->subscriber(), $this->request(self::USER, self::PASSWORD));

        self::assertFalse($event->hasResponse());
    }

    public function testAcceptsCredentialsFromAuthorizationHeader(): void
    {
        $request = Request::create('https://dev.zaborprofil.ru/', server: [
            'HTTP_AUTHORIZATION' => 'Basic '.base64_encode(self::USER.':'.self::PASSWORD),
        ]);

        self::assertFalse($this->requestEvent($this->subscriber(), $request)->hasResponse());
    }

    public function testAcceptsCredentialsFromRedirectAuthorizationHeader(): void
    {
        $request = Request::create('https://dev.zaborprofil.ru/', server: [
            'REDIRECT_HTTP_AUTHORIZATION' => 'Basic '.base64_encode(self::USER.':'.self::PASSWORD),
        ]);

        self::assertFalse($this->requestEvent($this->subscriber(), $request)->hasResponse());
    }

    public function testRejectsWrongPassword(): void
    {
        $event = $this->requestEvent($this->subscriber(), $this->request(self::USER, 'wrong'));

        self::assertSame(Response::HTTP_UNAUTHORIZED, $event->getResponse()?->getStatusCode());
    }

    public function testRejectsWrongUser(): void
    {
        $event = $this->requestEvent($this->subscriber(), $this->request('admin', self::PASSWORD));

        self::assertSame(Response::HTTP_UNAUTHORIZED, $event->getResponse()?->getStatusCode());
    }

    public function testStaysClosedWhenHashIsInvalid(): void
    {
        $event = $this->requestEvent(
            $this->subscriber(passwordHash: 'not-a-bcrypt-hash'),
            $this->request(self::USER, 'not-a-bcrypt-hash'),
        );

        self::assertSame(Response::HTTP_UNAUTHORIZED, $event->getResponse()?->getStatusCode());
    }

    public function testStaysClosedWhenCredentialsAreNotConfigured(): void
    {
        $event = $this->requestEvent(
            $this->subscriber(user: '', passwordHash: ''),
            $this->request('', ''),
        );

        self::assertSame(Response::HTTP_UNAUTHORIZED, $event->getResponse()?->getStatusCode());
    }

    public function testSubRequestsAreNotChecked(): void
    {
        $subscriber = $this->subscriber();
        $event = new RequestEvent($this->kernel(), $this->request(), HttpKernelInterface::SUB_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    public function testAddsNoIndexHeaderOnStaging(): void
    {
        $response = $this->responseFor($this->subscriber(), new Response());

        self::assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
    }

    public function testDoesNotAddNoIndexHeaderOutsideStaging(): void
    {
        $response = $this->responseFor($this->subscriber(environment: 'prod'), new Response());

        self::assertFalse($response->headers->has('X-Robots-Tag'));
    }

    public function testSubscribesBeforeSecurityFirewall(): void
    {
        $events = StagingAccessSubscriber::getSubscribedEvents();

        self::assertGreaterThan(8, $events['kernel.request'][1]);
    }

    private function subscriber(
        string $environment = 'staging',
        bool $authEnabled = true,
        string $user = self::USER,
        ?string $passwordHash = null,
    ): StagingAccessSubscriber {
        return new StagingAccessSubscriber(
            $environment,
            $authEnabled,
            $user,
            $passwordHash ?? password_hash(self::PASSWORD, \PASSWORD_BCRYPT, ['cost' => 4]),
        );
    }

    private function request(?string $user = null, ?string $password = null): Request
    {
        $server = [];
        if ($user !== null) {
            $server['PHP_AUTH_USER'] = $user;
            $server['PHP_AUTH_PW'] = $password ?? '';
        }

        return Request::create('https://dev.zaborprofil.ru/', server: $server);
    }

    private function requestEvent(StagingAccessSubscriber $subscriber, Request $request): RequestEvent
    {
        $event = new RequestEvent($this->kernel(), $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);

        return $event;
    }

    private function responseFor(StagingAccessSubscriber $subscriber, Response $response): Response
    {
        $event = new ResponseEvent($this->kernel(), $this->request(), HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onKernelResponse($event);

        return $event->getResponse();
    }

    private function kernel(): HttpKernelInterface
    {
        return $this->createStub(HttpKernelInterface::class);
    }
}
