<?php

declare(strict_types=1);

namespace App\Tests\Unit\Seo\Application\Redirect;

use App\Module\Seo\Application\Redirect\RedirectGraph;
use PHPUnit\Framework\TestCase;

final class RedirectGraphTest extends TestCase
{
    public function testDetectsLoopClosedByNewEdge(): void
    {
        $graph = new RedirectGraph(['/b/' => '/c/', '/c/' => '/a/']);

        self::assertSame(['/a/', '/b/', '/c/', '/a/'], $graph->loopFor('/a/', '/b/'));
    }

    public function testNoLoopForPlainTarget(): void
    {
        $graph = new RedirectGraph(['/b/' => '/c/']);

        self::assertNull($graph->loopFor('/a/', '/b/'));
        self::assertNull($graph->loopFor('/a/', '/elsewhere/'));
    }

    public function testDetectsPreexistingLoopOnTheWay(): void
    {
        $graph = new RedirectGraph(['/b/' => '/c/', '/c/' => '/b/']);

        self::assertNotNull($graph->loopFor('/a/', '/b/'));
    }

    public function testChainForFollowsExistingEdges(): void
    {
        $graph = new RedirectGraph(['/b/' => '/c/', '/c/' => '/d/']);

        self::assertSame(['/a/', '/b/', '/c/', '/d/'], $graph->chainFor('/a/', '/b/'));
        self::assertSame(['/a/', '/z/'], $graph->chainFor('/a/', '/z/'));
    }

    public function testIncomingTo(): void
    {
        $graph = new RedirectGraph(['/x/' => '/a/', '/y/' => '/a/', '/z/' => '/q/']);

        self::assertSame(['/x/', '/y/'], $graph->incomingTo('/a/'));
    }

    public function testCyclesAndChainsOverWholeGraph(): void
    {
        $graph = new RedirectGraph([
            '/a/' => '/b/',
            '/b/' => '/a/',
            '/h/' => '/m/',
            '/m/' => '/t/',
            '/single/' => '/ok/',
        ]);

        self::assertSame([['/a/', '/b/', '/a/']], $graph->cycles());
        self::assertSame([['/h/', '/m/', '/t/']], $graph->chains());
    }

    public function testSetNullRemovesEdge(): void
    {
        $graph = new RedirectGraph(['/a/' => '/b/', '/b/' => '/c/']);
        $graph->set('/b/', null);

        self::assertSame([], $graph->chains());
    }
}
