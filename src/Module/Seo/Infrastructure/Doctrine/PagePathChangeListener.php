<?php

declare(strict_types=1);

namespace App\Module\Seo\Infrastructure\Doctrine;

use App\Module\Content\Domain\Entity\Page;
use App\Module\Seo\Domain\Entity\Redirect;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Автоматические 301-редиректы при смене пути страницы.
 *
 * Редиректы проверяются раньше маршрутизации ({@see \App\Module\Seo\Infrastructure\Http\RedirectKernelSubscriber}),
 * поэтому активный редирект с адреса, где теперь живёт страница, делает её недоступной. Раньше так получалась петля:
 * при переименовании `/a/` → `/b/` → `/a/` оставались активными `/a/ → /b/` и `/b/ → /a/`, а новая страница
 * по старому адресу переименованной перекрывалась редиректом. Поэтому:
 * - редирект с нового адреса страницы (и с адреса новой страницы) выключается;
 * - редирект со старого адреса создаётся, а если правило для этого адреса уже есть — перенаправляется на новый адрес.
 */
#[AsDoctrineListener(event: Events::onFlush)]
final class PagePathChangeListener
{
    public function onFlush(OnFlushEventArgs $event): void
    {
        $entityManager = $event->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();
        $redirectMetadata = $entityManager->getClassMetadata(Redirect::class);

        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof Page) {
                $this->releasePath($entityManager, $redirectMetadata, $entity->path());
            }
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof Page) {
                continue;
            }

            $pathChange = $unitOfWork->getEntityChangeSet($entity)['path'] ?? null;
            if (!\is_array($pathChange)) {
                continue;
            }

            [$oldPath, $newPath] = $pathChange;
            if (!\is_string($oldPath) || !\is_string($newPath) || $oldPath === $newPath) {
                continue;
            }

            $this->releasePath($entityManager, $redirectMetadata, $newPath);
            $this->redirectOldPath($entityManager, $redirectMetadata, $oldPath, $newPath);
        }
    }

    /**
     * По этому адресу теперь живёт страница: активный редирект с него выключается.
     *
     * @param ClassMetadata<Redirect> $metadata
     */
    private function releasePath(EntityManagerInterface $entityManager, ClassMetadata $metadata, string $path): void
    {
        $redirect = $this->findBySource($entityManager, $path);
        if ($redirect === null || !$redirect->isActive()) {
            return;
        }

        $redirect->update($redirect->targetPath(), $redirect->statusCode(), false);
        $entityManager->getUnitOfWork()->recomputeSingleEntityChangeSet($metadata, $redirect);
    }

    /**
     * @param ClassMetadata<Redirect> $metadata
     */
    private function redirectOldPath(EntityManagerInterface $entityManager, ClassMetadata $metadata, string $oldPath, string $newPath): void
    {
        $unitOfWork = $entityManager->getUnitOfWork();
        $existing = $this->findBySource($entityManager, $oldPath);
        if ($existing !== null) {
            $existing->update($newPath, 301, true);
            $unitOfWork->recomputeSingleEntityChangeSet($metadata, $existing);

            return;
        }

        $redirect = new Redirect($oldPath, $newPath, 301, true);
        $entityManager->persist($redirect);
        $unitOfWork->computeChangeSet($metadata, $redirect);
    }

    private function findBySource(EntityManagerInterface $entityManager, string $path): ?Redirect
    {
        return $entityManager->getRepository(Redirect::class)->findOneBy(['sourcePath' => Redirect::normalizeSourcePath($path)]);
    }
}
