<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine;

use App\Shared\Domain\Contract\TimestampedEntityInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
final class TimestampListener
{
    public function prePersist(PrePersistEventArgs $event): void
    {
        $entity = $event->getObject();
        if ($entity instanceof TimestampedEntityInterface) {
            $entity->initializeTimestamps();
        }
    }

    public function preUpdate(PreUpdateEventArgs $event): void
    {
        $entity = $event->getObject();
        if (!$entity instanceof TimestampedEntityInterface) {
            return;
        }

        $entity->touch();
        $event->getObjectManager()->getUnitOfWork()->recomputeSingleEntityChangeSet(
            $event->getObjectManager()->getClassMetadata($entity::class),
            $entity,
        );
    }

}
