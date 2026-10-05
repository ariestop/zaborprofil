<?php

declare(strict_types=1);

namespace App\Module\Content\Infrastructure\Doctrine;

use App\Module\Content\Application\Service\CurrentAdminActor;
use App\Module\Content\Domain\Entity\Page;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

/**
 * Запоминает автора последней правки страницы (`content_pages.updated_by`).
 *
 * Правкой считается любое сохранение, при котором у страницы сдвинулось `updatedAt`:
 * поля и SEO, смена статуса, а также изменения блоков — они вызывают `Page::touch()`.
 * Так автор не теряется ни в одном из обработчиков, которые меняют страницу.
 */
#[AsDoctrineListener(event: Events::onFlush)]
final readonly class PageEditorStampListener
{
    public function __construct(private CurrentAdminActor $actor)
    {
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        $entityManager = $event->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();
        $editor = $this->actor->id();

        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            if (!$entity instanceof Page || $editor === null || $entity->updatedBy() === $editor) {
                continue;
            }

            $entity->recordEditor($editor);
            $unitOfWork->recomputeSingleEntityChangeSet($entityManager->getClassMetadata(Page::class), $entity);
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof Page || $entity->updatedBy() === $editor) {
                continue;
            }

            if (!\array_key_exists('updatedAt', $unitOfWork->getEntityChangeSet($entity))) {
                continue;
            }

            $entity->recordEditor($editor);
            $unitOfWork->recomputeSingleEntityChangeSet($entityManager->getClassMetadata(Page::class), $entity);
        }
    }
}
