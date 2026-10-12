<?php

declare(strict_types=1);

namespace App\Tests\Integration\Lead;

use App\Module\Admin\Application\Service\AdminAuditLogger;
use App\Module\AuditLog\Domain\Entity\AuditLogEntry;
use App\Module\AuditLog\Domain\Repository\AuditLogRepositoryInterface;
use App\Module\Lead\Application\Workflow\LeadWorkflow;
use App\Module\Lead\Domain\Entity\Lead;
use App\Module\Lead\Domain\Repository\LeadAssigneeDirectoryInterface;
use App\Module\Lead\Domain\Repository\LeadEventRepositoryInterface;
use App\Module\Lead\Domain\Repository\LeadRepositoryInterface;
use App\Module\Lead\Domain\ValueObject\LeadActor;
use App\Shared\Application\Logging\BusinessEventLogger;
use App\Shared\Application\Transaction\TransactionRunnerInterface;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Действие менеджера над заявкой сохраняется целиком или никак: заявка, событие хронологии и запись аудита
 * пишутся одной транзакцией (docs/17-doctrine-and-database.md §7.1).
 */
final class LeadWorkflowTransactionTest extends KernelTestCase
{
    private Lead $lead;

    protected function setUp(): void
    {
        self::bootKernel();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $this->lead = new Lead('public_page_form', 'Иван', '+79990000000', null, null, ['consent' => true]);
        $this->service(LeadRepositoryInterface::class)->save($this->lead);
    }

    public function testFailedAuditRollsBackStatusChangeAndItsHistoryEvent(): void
    {
        $this->expectFailure(fn (LeadWorkflow $workflow): Lead => $workflow->changeStatus($this->lead, 'done', LeadActor::system()));

        $reloaded = $this->service(LeadRepositoryInterface::class)->get((string) $this->lead->id());
        self::assertSame('new', $reloaded->status());
        self::assertSame([], $this->service(LeadEventRepositoryInterface::class)->findByLead($reloaded));
    }

    public function testFailedAuditRollsBackNote(): void
    {
        $this->expectFailure(fn (LeadWorkflow $workflow): mixed => $workflow->addNote($this->lead, 'Перезвонить в среду', LeadActor::system()));

        $reloaded = $this->service(LeadRepositoryInterface::class)->get((string) $this->lead->id());
        self::assertSame([], $this->service(LeadEventRepositoryInterface::class)->findByLead($reloaded));
    }

    /**
     * @param callable(LeadWorkflow): mixed $action
     */
    private function expectFailure(callable $action): void
    {
        $failingAudit = new class () implements AuditLogRepositoryInterface {
            public function save(AuditLogEntry $entry): void
            {
                throw new RuntimeException('Audit storage is unavailable.');
            }

            public function findLatest(int $limit = 50): array
            {
                return [];
            }

            public function findByEntity(string $entityType, string $entityId, string $actionPrefix, int $limit = 50): array
            {
                return [];
            }
        };
        $workflow = new LeadWorkflow(
            $this->service(LeadRepositoryInterface::class),
            $this->service(LeadEventRepositoryInterface::class),
            $this->service(LeadAssigneeDirectoryInterface::class),
            new AdminAuditLogger($failingAudit, $this->service(Security::class), $this->service(RequestStack::class)),
            $this->service(BusinessEventLogger::class),
            $this->service(TransactionRunnerInterface::class),
        );

        try {
            $action($workflow);
            self::fail('The action must fail when the audit entry cannot be written.');
        } catch (RuntimeException $exception) {
            self::assertSame('Audit storage is unavailable.', $exception->getMessage());
        }

        $this->entityManager()->clear();
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($id, $service);

        return $service;
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->service(EntityManagerInterface::class);
    }
}
