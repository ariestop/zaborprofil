<?php

declare(strict_types=1);

namespace App\Module\Seo\Infrastructure\Doctrine;

use App\Module\Content\Domain\Entity\Page;
use App\Module\Seo\Domain\Entity\Redirect;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::onFlush)]
final class PagePathChangeListener
{
    public function onFlush(OnFlushEventArgs $event): void
    {
        $entityManager = $event->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();
        $redirectMetadata = $entityManager->getClassMetadata(Redirect::class);
        $repository = $entityManager->getRepository(Redirect::class);

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof Page) {
                continue;
            }

            $changeSet = $unitOfWork->getEntityChangeSet($entity);
            if (!isset($changeSet['path'])) {
                continue;
            }

            $pathChange = $changeSet['path'];
            if (!\is_array($pathChange)) {
                continue;
            }

            $oldPath = $pathChange[0];
            $newPath = $pathChange[1];
            if (!\is_string($oldPath) || !\is_string($newPath) || $oldPath === $newPath) {
                continue;
            }

            if ($repository->findOneBy(['sourcePath' => $oldPath]) !== null) {
                continue;
            }

            $redirect = new Redirect($oldPath, $newPath, 301, true);
            $entityManager->persist($redirect);
            $unitOfWork->computeChangeSet($redirectMetadata, $redirect);
        }
    }
}
