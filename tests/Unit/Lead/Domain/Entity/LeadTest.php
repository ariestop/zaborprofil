<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lead\Domain\Entity;

use App\Module\Lead\Domain\Entity\Lead;
use App\Module\Lead\Domain\ValueObject\LeadStatus;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LeadTest extends TestCase
{
    public function testCanMarkLeadAsSpamWithReasons(): void
    {
        $lead = new Lead('public_form', 'Иван', '+79990000000', null, null, ['consent' => true]);

        $lead->markSpam(120, ['honeypot_filled']);
        $payload = $lead->toArray();

        self::assertSame(LeadStatus::SPAM, $payload['status']);
        self::assertSame(120, $payload['spamScore']);
        self::assertSame(['honeypot_filled'], $payload['spamReasons']);
    }

    public function testStoresPageUrlUtmAndDetectsCompanyLeads(): void
    {
        $lead = new Lead(
            'public_page_form',
            'ООО «СтройДвор»',
            '+79990000000',
            null,
            null,
            ['consent' => true],
            'https://zaborprofil.ru/zabory/proflist?utm_source=yandex',
            ['source' => 'yandex', 'medium' => 'cpc', 'campaign' => " zabor\n", 'unknown' => 'x', 'term' => 5],
        );
        $payload = $lead->toArray();

        self::assertSame('https://zaborprofil.ru/zabory/proflist?utm_source=yandex', $payload['pageUrl']);
        self::assertSame(['source' => 'yandex', 'medium' => 'cpc', 'campaign' => 'zabor'], $payload['utm']);
        self::assertTrue($payload['b2b']);
        self::assertNull($payload['readAt']);

        $lead->markRead();
        self::assertNotNull($lead->toArray()['readAt']);

        $person = new Lead('public_form', 'Андрей Смирнов', '+79990000001', null, null, ['consent' => true]);
        self::assertFalse($person->toArray()['b2b']);
        self::assertSame([], $person->toArray()['utm']);
    }

    public function testAcceptsValuesAtColumnLimits(): void
    {
        $lead = new Lead(
            str_repeat('s', Lead::SOURCE_MAX_LENGTH),
            str_repeat('я', Lead::NAME_MAX_LENGTH),
            '+7 '.str_repeat('9', Lead::PHONE_MAX_LENGTH - 3),
            str_repeat('e', Lead::EMAIL_MAX_LENGTH),
            str_repeat('я', Lead::MESSAGE_MAX_LENGTH),
            ['consent' => true],
        );

        $name = $lead->toArray()['name'];
        self::assertIsString($name);
        self::assertSame(Lead::NAME_MAX_LENGTH, mb_strlen($name));
    }

    /**
     * @return iterable<string, array{array{string, string, string, ?string, ?string}, string}>
     */
    public static function invalidFields(): iterable
    {
        $tooLong = static fn (int $length): string => str_repeat('я', $length + 1);

        yield 'source' => [[$tooLong(Lead::SOURCE_MAX_LENGTH), 'Иван', '+79990000000', null, null], 'Lead source must not exceed 120 characters.'];
        yield 'name' => [['public_form', $tooLong(Lead::NAME_MAX_LENGTH), '+79990000000', null, null], 'Lead name must not exceed 180 characters.'];
        yield 'phone length' => [['public_form', 'Иван', '+7 '.str_repeat('9', Lead::PHONE_MAX_LENGTH), null, null], 'Lead phone must not exceed 40 characters.'];
        yield 'phone digits' => [['public_form', 'Иван', '12-34', null, null], 'Lead phone must contain at least 6 digits.'];
        yield 'email' => [['public_form', 'Иван', '+79990000000', $tooLong(Lead::EMAIL_MAX_LENGTH), null], 'Lead email must not exceed 180 characters.'];
        yield 'message' => [['public_form', 'Иван', '+79990000000', null, $tooLong(Lead::MESSAGE_MAX_LENGTH)], 'Lead message must not exceed 5000 characters.'];
    }

    /**
     * @param array{string, string, string, ?string, ?string} $fields
     */
    #[DataProvider('invalidFields')]
    public function testRejectsValuesThatDoNotFitColumns(array $fields, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        new Lead($fields[0], $fields[1], $fields[2], $fields[3], $fields[4], ['consent' => true]);
    }
}
