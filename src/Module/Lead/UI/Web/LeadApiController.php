<?php

declare(strict_types=1);

namespace App\Module\Lead\UI\Web;

use App\Module\Lead\Application\AntiSpam\LeadAntiSpamChecker;
use App\Module\Lead\Application\Notification\LeadNotifier;
use App\Module\Lead\Domain\Entity\Lead;
use App\Module\Lead\Domain\Repository\LeadRepositoryInterface;
use App\Module\Lead\Domain\ValueObject\LeadUtm;
use App\Module\Lead\Domain\ValueObject\PhoneNumber;
use App\Shared\Application\Logging\BusinessEventLogger;
use App\Shared\UI\Http\AdminApiErrorResponder;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final readonly class LeadApiController
{
    public function __construct(
        private LeadRepositoryInterface $leads,
        private LeadAntiSpamChecker $antiSpam,
        private LeadNotifier $notifier,
        private BusinessEventLogger $businessEvents,
        private AdminApiErrorResponder $errors,
    ) {
    }

    #[Route('/api/leads', name: 'api_leads_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            /** @var array<string, mixed> $payload */
            $payload = $request->toArray();
            $spamCheck = $this->antiSpam->check($payload, $request);

            if (($payload['consent'] ?? false) !== true) {
                throw new InvalidArgumentException('Consent is required.');
            }

            $lead = new Lead(
                $this->string($payload, 'source', 'public_form'),
                $this->string($payload, 'name'),
                $this->string($payload, 'phone'),
                $this->nullableString($payload, 'email'),
                $this->nullableString($payload, 'message'),
                [
                    'consent' => true,
                    'text' => $this->nullableString($payload, 'consentText') ?? 'Пользователь согласился на обработку персональных данных.',
                    'policyUrl' => $this->nullableString($payload, 'policyUrl') ?? '/privacy/',
                    'pageUrl' => $this->nullableString($payload, 'pageUrl'),
                    'ip' => $request->getClientIp(),
                    'userAgent' => $request->headers->get('User-Agent'),
                    'capturedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                ],
                $this->nullableString($payload, 'pageUrl'),
                LeadUtm::sanitize($payload['utm'] ?? null),
            );
            if ($spamCheck->isSpam()) {
                $lead->markSpam($spamCheck->score, $spamCheck->reasons);
            }

            $this->leads->save($lead);
            $this->businessEvents->log('lead.created', [
                'leadId' => (string) $lead->id(),
                'source' => $lead->toArray()['source'],
                'status' => $lead->status(),
                'maskedContact' => PhoneNumber::mask($lead->phone()),
            ]);
            if (!$spamCheck->isSpam()) {
                $this->notifier->notify($lead);
            }

            return new JsonResponse(['status' => $spamCheck->isSpam() ? 'accepted' : 'created', 'id' => $lead->toArray()['id']], $spamCheck->isSpam() ? 202 : 201);
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Public Lead API');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function string(array $payload, string $key, ?string $default = null): string
    {
        $value = $payload[$key] ?? $default;

        if (!\is_string($value)) {
            throw new InvalidArgumentException(\sprintf('Field "%s" must be a string.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function nullableString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        if ($value !== null && !\is_string($value)) {
            throw new InvalidArgumentException(\sprintf('Field "%s" must be a string or null.', $key));
        }

        return $value;
    }
}
