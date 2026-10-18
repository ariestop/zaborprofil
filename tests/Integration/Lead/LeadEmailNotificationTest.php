<?php

declare(strict_types=1);

namespace App\Tests\Integration\Lead;

use App\Module\Lead\Application\Notification\LeadNotifier;
use App\Module\Lead\Domain\Entity\Lead;
use App\Shared\Infrastructure\Notification\TelegramMessageSenderInterface;
use App\Tests\Support\Logging\RecordingLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Письмо о заявке проходит через настоящий Mailer: без адреса отправителя Symfony его не отправляет
 * («An email must have a From or a Sender header»), а unit-тест с подменой Mailer этого не видит.
 */
final class LeadEmailNotificationTest extends KernelTestCase
{
    public function testLeadEmailIsSentWithConfiguredSender(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $mailer = $container->get(MailerInterface::class);
        self::assertInstanceOf(MailerInterface::class, $mailer);
        $telegram = $container->get(TelegramMessageSenderInterface::class);
        self::assertInstanceOf(TelegramMessageSenderInterface::class, $telegram);
        $bus = $container->get(MessageBusInterface::class);
        self::assertInstanceOf(MessageBusInterface::class, $bus);

        $notifier = new LeadNotifier($mailer, $telegram, $bus, new RecordingLogger(), 'sales@example.test', '', '');
        $notifier->send(new Lead('public_form', 'Иван', '+79990000000', null, null, ['consent' => true]), LeadNotifier::CHANNEL_EMAIL);

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame(['sales@example.test'], array_map(static fn (Address $address): string => $address->getAddress(), $email->getTo()));
        $from = $email->getFrom();
        self::assertCount(1, $from, 'У письма есть отправитель из MAILER_FROM.');
        self::assertSame('noreply@zaborprofil.ru', $from[0]->getAddress());
    }
}
