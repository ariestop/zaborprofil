<?php

declare(strict_types=1);

namespace App\Module\Lead\UI\Admin;

use App\Module\Lead\Domain\Repository\LeadSearchCriteria;
use App\Module\Lead\Domain\ValueObject\LeadActor;
use App\Module\Lead\Domain\ValueObject\LeadStatus;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;

final readonly class LeadCriteriaParser
{
    public const int MAX_PER_PAGE = 100;

    public function parse(Request $request, LeadActor $actor): LeadSearchCriteria
    {
        $query = $request->query;
        $sort = $query->getString('sort', LeadSearchCriteria::SORT_CREATED);
        $status = trim($query->getString('status'));
        $assignee = trim($query->getString('assignee'));

        return new LeadSearchCriteria(
            self::nullIfEmpty($query->getString('q')),
            $status === '' || $status === 'all' ? null : LeadStatus::normalize($status),
            self::nullIfEmpty($query->getString('source')),
            self::date($query->getString('from'), 'from'),
            self::date($query->getString('to'), 'to')?->modify('+1 day'),
            match ($assignee) {
                '', 'all' => null,
                'none' => LeadSearchCriteria::ASSIGNEE_NONE,
                'me' => $actor->id ?? throw new InvalidArgumentException('Current user is not available.'),
                default => $assignee,
            },
            \in_array($sort, LeadSearchCriteria::SORTS, true) ? $sort : LeadSearchCriteria::SORT_CREATED,
            $query->getString('direction', 'desc') !== 'asc',
            max(1, $query->getInt('page', 1)),
            min(self::MAX_PER_PAGE, max(1, $query->getInt('perPage', 25))),
        );
    }

    private static function nullIfEmpty(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private static function date(string $value, string $field): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException(\sprintf('Parameter "%s" must be a date in YYYY-MM-DD format.', $field));
        }

        return $date;
    }
}
