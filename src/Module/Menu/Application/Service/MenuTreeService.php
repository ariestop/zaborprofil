<?php

declare(strict_types=1);

namespace App\Module\Menu\Application\Service;

use App\Module\Menu\Application\Command\MenuItemInput;
use App\Module\Menu\Application\Command\MenuOrderEntry;
use App\Module\Menu\Domain\Entity\MenuItem;
use App\Module\Menu\Domain\Repository\MenuItemRepositoryInterface;
use App\Module\Menu\Domain\ValueObject\MenuPosition;
use App\Shared\Application\Transaction\TransactionRunnerInterface;
use InvalidArgumentException;
use Symfony\Component\Uid\Ulid;

/**
 * Управление деревом меню из админки. Меню двухуровневое: раздел и его подпункты в той же позиции.
 * Каждая операция — одна транзакция; кэш меню и HTTP-кэш сайта сбрасываются после фиксации.
 */
final readonly class MenuTreeService
{
    private const int SORT_STEP = 10;

    public function __construct(
        private MenuItemRepositoryInterface $items,
        private TransactionRunnerInterface $transactions,
        private MenuProvider $menus,
    ) {
    }

    public function create(MenuItemInput $input): MenuItem
    {
        return $this->transactions->run(function () use ($input): MenuItem {
            $position = MenuPosition::normalize($input->position);
            $parent = $this->parent($input->parentId, $position, null);
            $item = new MenuItem(
                $position,
                $input->label,
                $input->url,
                $input->sortOrder ?? $this->nextSortOrder($position, $parent),
                $input->active,
                $parent,
                $input->hint,
            );
            $this->items->save($item);
            $this->invalidateAfterCommit([$position]);

            return $item;
        });
    }

    public function update(string $id, MenuItemInput $input): MenuItem
    {
        return $this->transactions->run(function () use ($id, $input): MenuItem {
            $item = $this->items->get($id);
            $previousPosition = $item->position();
            $position = MenuPosition::normalize($input->position);
            $parent = $this->parent($input->parentId, $position, $item);

            $item->edit($input->label, $input->url, $input->hint, $input->active);

            $moved = $position !== $previousPosition || !self::sameUlid($parent, $item->parentId());
            if ($moved || $input->sortOrder !== null) {
                $children = $this->childrenOf($item);
                if ($parent !== null && $children !== []) {
                    throw new InvalidArgumentException('Пункт с подпунктами нельзя вложить в другой пункт: сначала перенесите подпункты.');
                }

                $item->place($position, $parent, $input->sortOrder ?? ($moved ? $this->nextSortOrder($position, $parent) : $item->sortOrder()));
                // Раздел переносится в другую позицию вместе с подпунктами.
                foreach ($children as $child) {
                    $child->place($position, $item->id(), $child->sortOrder());
                    $this->items->save($child);
                }
            }

            $this->items->save($item);
            $this->invalidateAfterCommit([$previousPosition, $position]);

            return $item;
        });
    }

    /**
     * Удаляет пункт вместе с подпунктами. Возвращает число удалённых пунктов.
     */
    public function delete(string $id): int
    {
        return $this->transactions->run(function () use ($id): int {
            $item = $this->items->get($id);
            $removed = [...$this->childrenOf($item), $item];
            foreach ($removed as $menuItem) {
                $this->items->remove($menuItem);
            }
            $this->invalidateAfterCommit([$item->position()]);

            return \count($removed);
        });
    }

    /**
     * Новый порядок всей позиции: список должен содержать каждый пункт позиции ровно один раз.
     * Порядок строк — порядок на сайте; parentId — раздел, в который вложен пункт (null — верхний уровень).
     *
     * @param list<MenuOrderEntry> $entries
     *
     * @return list<MenuItem>
     */
    public function reorder(string $position, array $entries): array
    {
        return $this->transactions->run(function () use ($position, $entries): array {
            $position = MenuPosition::normalize($position);
            $items = [];
            foreach ($this->items->findByPosition($position) as $item) {
                $items[(string) $item->id()] = $item;
            }

            $requested = array_map(static fn (MenuOrderEntry $entry): string => $entry->id, $entries);
            if (\count($requested) !== \count(array_unique($requested)) || array_diff($requested, array_keys($items)) !== [] || array_diff(array_keys($items), $requested) !== []) {
                throw new InvalidArgumentException('Меню изменилось в другой вкладке или у другого пользователя. Обновите страницу и повторите.');
            }

            $parentOf = [];
            foreach ($entries as $entry) {
                $parentOf[$entry->id] = $entry->parentId;
            }

            $counters = [];
            $ordered = [];
            foreach ($entries as $entry) {
                $parentId = $entry->parentId;
                if ($parentId !== null) {
                    if ($parentId === $entry->id || !\array_key_exists($parentId, $parentOf)) {
                        throw new InvalidArgumentException('Подпункт можно вложить только в пункт этого же меню.');
                    }
                    if ($parentOf[$parentId] !== null) {
                        throw new InvalidArgumentException('Меню двухуровневое: подпункт нельзя вложить в другой подпункт.');
                    }
                }

                $key = $parentId ?? '';
                $counters[$key] = ($counters[$key] ?? 0) + self::SORT_STEP;
                $item = $items[$entry->id];
                $item->place($position, $parentId === null ? null : Ulid::fromString($parentId), $counters[$key]);
                $this->items->save($item);
                $ordered[] = $item;
            }
            $this->invalidateAfterCommit([$position]);

            return $ordered;
        });
    }

    private function parent(?string $parentId, string $position, ?MenuItem $self): ?Ulid
    {
        if ($parentId === null || trim($parentId) === '') {
            return null;
        }

        $parent = $this->items->get($parentId);
        if ($self !== null && $parent->id()->equals($self->id())) {
            throw new InvalidArgumentException('Пункт меню не может быть вложен сам в себя.');
        }
        if ($parent->position() !== $position) {
            throw new InvalidArgumentException('Подпункт можно вложить только в пункт этого же меню.');
        }
        if ($parent->parentId() !== null) {
            throw new InvalidArgumentException('Меню двухуровневое: подпункт нельзя вложить в другой подпункт.');
        }

        return $parent->id();
    }

    private function nextSortOrder(string $position, ?Ulid $parent): int
    {
        $max = 0;
        foreach ($this->items->findByPosition($position) as $item) {
            if (self::sameUlid($item->parentId(), $parent)) {
                $max = max($max, $item->sortOrder());
            }
        }

        return $max + self::SORT_STEP;
    }

    /**
     * @return list<MenuItem>
     */
    private function childrenOf(MenuItem $item): array
    {
        return array_values(array_filter(
            $this->items->findByPosition($item->position()),
            static fn (MenuItem $candidate): bool => $candidate->parentId()?->equals($item->id()) ?? false,
        ));
    }

    /**
     * @param list<string> $positions
     */
    private function invalidateAfterCommit(array $positions): void
    {
        $this->transactions->afterCommit(function () use ($positions): void {
            foreach (array_unique($positions) as $position) {
                $this->menus->invalidate($position);
            }
        });
    }

    private static function sameUlid(?Ulid $left, ?Ulid $right): bool
    {
        return $left === null ? $right === null : ($right !== null && $left->equals($right));
    }
}
