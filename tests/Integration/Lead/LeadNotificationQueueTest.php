<?php

declare(strict_types=1);

namespace App\Tests\Integration\Lead;

use App\Module\Lead\Application\Notification\NotifyLeadMessage;
use App\Module\Lead\Application\Notification\NotifyLeadMessageHandler;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Uid\Ulid;

/**
 * Уведомления о заявке уходят через асинхронную очередь (воркер с повторами), а не в запросе посетителя.
 */
final class LeadNotificationQueueTest extends KernelTestCase
{
    public function testNotificationIsRoutedToAsyncTransport(): void
    {
        self::bootKernel();
        $bus = self::getContainer()->get(MessageBusInterface::class);
        self::assertInstanceOf(MessageBusInterface::class, $bus);
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        $bus->dispatch(new NotifyLeadMessage((string) new Ulid(), 'email'));

        $sent = $transport->getSent();
        self::assertCount(1, $sent);
        self::assertInstanceOf(NotifyLeadMessage::class, $sent[0]->getMessage());
    }

    public function testNotificationForRemovedLeadIsNotRetried(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        SchemaTestHelper::recreateSchema($entityManager);
        $handler = self::getContainer()->get(NotifyLeadMessageHandler::class);
        self::assertInstanceOf(NotifyLeadMessageHandler::class, $handler);

        $this->expectException(UnrecoverableMessageHandlingException::class);

        $handler(new NotifyLeadMessage((string) new Ulid(), 'email'));
    }
}
