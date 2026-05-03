<?php

declare(strict_types=1);

namespace App\Module\Lead\UI\Web;

use App\Module\Lead\Domain\Entity\Lead;
use App\Module\Lead\Domain\Repository\LeadRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final readonly class LeadApiController
{
    public function __construct(
        private LeadRepositoryInterface $leads,
        private MailerInterface $mailer,
        private string $leadNotificationEmail,
    ) {
    }

    #[Route('/api/leads', name: 'api_leads_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            /** @var array<string, mixed> $payload */
            $payload = $request->toArray();
            if (($payload['website'] ?? '') !== '') {
                return new JsonResponse(['status' => 'accepted'], 202);
            }

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
                    'capturedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                ],
            );
            $this->leads->save($lead);
            $this->notify($lead);

            return new JsonResponse(['status' => 'created', 'id' => $lead->toArray()['id']], 201);
        } catch (Throwable $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
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

    private function notify(Lead $lead): void
    {
        if ($this->leadNotificationEmail === '') {
            return;
        }

        $payload = $lead->toArray();
        $id = $payload['id'];
        if (!\is_string($id)) {
            return;
        }

        $this->mailer->send((new Email())
            ->to($this->leadNotificationEmail)
            ->subject('Новая заявка с сайта zaborprofil.ru')
            ->text('Поступила новая заявка: '.$id));
    }
}
