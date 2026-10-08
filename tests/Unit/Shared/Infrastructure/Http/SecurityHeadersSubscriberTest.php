<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\Http\SecurityHeadersSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class SecurityHeadersSubscriberTest extends TestCase
{
    public function testEnforcedPolicyAllowsOnlyOwnScripts(): void
    {
        $response = $this->respond(new SecurityHeadersSubscriber('prod', true));

        self::assertFalse($response->headers->has('Content-Security-Policy-Report-Only'));
        $policy = $this->directives((string) $response->headers->get('Content-Security-Policy'));
        self::assertSame("'self'", $policy['script-src']);
        self::assertSame("'self' 'unsafe-inline'", $policy['style-src']);
        self::assertSame("'none'", $policy['object-src']);
        self::assertSame("'none'", $policy['frame-ancestors']);
        self::assertSame("'self'", $policy['connect-src']);
    }

    public function testPolicyIsReportOnlyWhenNotEnforced(): void
    {
        $response = $this->respond(new SecurityHeadersSubscriber('dev', false));

        self::assertFalse($response->headers->has('Content-Security-Policy'));
        $policy = $this->directives((string) $response->headers->get('Content-Security-Policy-Report-Only'));
        self::assertSame("'self'", $policy['script-src']);
    }

    public function testViteDevServerIsAllowedAndPolicyIsNotEnforced(): void
    {
        $response = $this->respond(new SecurityHeadersSubscriber('dev', true, 'http://localhost:5173/'));

        self::assertFalse($response->headers->has('Content-Security-Policy'));
        $policy = $this->directives((string) $response->headers->get('Content-Security-Policy-Report-Only'));
        self::assertSame("'self' http://localhost:5173 'unsafe-inline'", $policy['script-src']);
        self::assertSame("'self' http://localhost:5173 ws://localhost:5173", $policy['connect-src']);
    }

    public function testPolicySetByControllerIsKept(): void
    {
        $response = new Response();
        $response->headers->set('Content-Security-Policy', "default-src 'none'");

        $this->respond(new SecurityHeadersSubscriber('prod', true), $response);

        self::assertSame("default-src 'none'", $response->headers->get('Content-Security-Policy'));
        self::assertFalse($response->headers->has('Content-Security-Policy-Report-Only'));
    }

    private function respond(SecurityHeadersSubscriber $subscriber, Response $response = new Response()): Response
    {
        $event = new ResponseEvent($this->createStub(HttpKernelInterface::class), Request::create('/'), HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onKernelResponse($event);

        return $response;
    }

    /**
     * @return array<string, string>
     */
    private function directives(string $policy): array
    {
        $directives = [];
        foreach (explode(';', $policy) as $directive) {
            [$name, $value] = array_pad(explode(' ', trim($directive), 2), 2, '');
            $directives[$name] = $value;
        }

        return $directives;
    }
}
