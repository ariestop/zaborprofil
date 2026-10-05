<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Мягкая блокировка «сейчас редактирует X»: не запрещает сохранение, а только предупреждает.
 *
 * Состояние эфемерно и живёт в файловом кэше. Блокировка протухает, если клиент не присылал heartbeat
 * дольше {@see self::TTL_SECONDS}, поэтому закрытая вкладка или упавший браузер не оставляют «вечный» замок.
 */
final readonly class PageEditLockService
{
    public const int TTL_SECONDS = 90;
    public const int HEARTBEAT_SECONDS = 30;

    public function __construct(
        #[Autowire(service: 'cache.page_edit_lock')]
        private CacheItemPoolInterface $cache,
        private ClockInterface $clock,
    ) {
    }

    public function acquire(string $pageId, string $userId, string $userLabel, string $sessionId, bool $takeOver = false): PageEditLockStatus
    {
        $now = $this->clock->now()->getTimestamp();
        $current = $this->read($pageId, $now);

        if ($current !== null && $current->sessionId !== $sessionId && !$takeOver) {
            return new PageEditLockStatus($current, false, $userId);
        }

        $holder = new PageEditLockHolder(
            $userId,
            $userLabel,
            $sessionId,
            $current !== null && $current->sessionId === $sessionId ? $current->acquiredAt : $now,
            $now,
        );
        $this->write($pageId, $holder);

        return new PageEditLockStatus($holder, true, $userId);
    }

    public function status(string $pageId, string $userId, string $sessionId): PageEditLockStatus
    {
        $current = $this->read($pageId, $this->clock->now()->getTimestamp());

        return new PageEditLockStatus($current, $current !== null && $current->sessionId === $sessionId, $userId);
    }

    public function release(string $pageId, string $sessionId): void
    {
        $current = $this->read($pageId, $this->clock->now()->getTimestamp());
        if ($current !== null && $current->sessionId === $sessionId) {
            $this->cache->deleteItem($this->key($pageId));
        }
    }

    private function read(string $pageId, int $now): ?PageEditLockHolder
    {
        $item = $this->cache->getItem($this->key($pageId));
        if (!$item->isHit()) {
            return null;
        }

        $data = $item->get();
        if (
            !\is_array($data)
            || !\is_string($data['userId'] ?? null)
            || !\is_string($data['label'] ?? null)
            || !\is_string($data['sessionId'] ?? null)
            || !\is_int($data['acquiredAt'] ?? null)
            || !\is_int($data['heartbeatAt'] ?? null)
        ) {
            return null;
        }

        if ($data['heartbeatAt'] + self::TTL_SECONDS <= $now) {
            return null;
        }

        return new PageEditLockHolder($data['userId'], $data['label'], $data['sessionId'], $data['acquiredAt'], $data['heartbeatAt']);
    }

    private function write(string $pageId, PageEditLockHolder $holder): void
    {
        $item = $this->cache->getItem($this->key($pageId));
        $item->set([
            'userId' => $holder->userId,
            'label' => $holder->label,
            'sessionId' => $holder->sessionId,
            'acquiredAt' => $holder->acquiredAt,
            'heartbeatAt' => $holder->heartbeatAt,
        ]);
        $item->expiresAfter(self::TTL_SECONDS * 2);
        $this->cache->save($item);
    }

    private function key(string $pageId): string
    {
        return 'page_edit_lock.'.preg_replace('/[^A-Za-z0-9_.]/', '_', $pageId);
    }
}
