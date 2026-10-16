<?php

declare(strict_types=1);

namespace App\Module\Portfolio\Application\Service;

use App\Module\Portfolio\Domain\Entity\PortfolioLike;
use App\Module\Portfolio\Domain\Entity\PortfolioProject;
use App\Module\Portfolio\Domain\Exception\PortfolioLikeLimitException;
use App\Module\Portfolio\Domain\Exception\PortfolioNotFoundException;
use App\Module\Portfolio\Domain\Repository\PortfolioLikeRepositoryInterface;
use App\Module\Portfolio\Domain\Repository\PortfolioProjectRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Лайки работ портфолио на сайте.
 *
 * Защита от накрутки:
 *  - посетитель — случайный токен из cookie, один лайк на работу (уникальный индекс в БД, повторное нажатие лайк снимает);
 *  - новые cookie не помогают: с одного IP на работу засчитывается не больше MAX_LIKES_PER_IP лайков (семья или офис за одним IP);
 *  - частоту запросов с IP ограничивает rate limiter в контроллере, чужие сайты отсекает проверка Origin;
 *  - лайкнуть можно только опубликованную работу.
 * Токен и IP хранятся только как HMAC с секретом приложения.
 */
final readonly class PortfolioLikeService
{
    public const int MAX_LIKES_PER_IP = 5;
    public const int MAX_PROJECTS_PER_REQUEST = 60;

    public function __construct(
        private PortfolioProjectRepositoryInterface $projects,
        private PortfolioLikeRepositoryInterface $likes,
        #[Autowire('%kernel.secret%')]
        private string $secret,
    ) {
    }

    public static function newVisitorToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function isVisitorToken(?string $token): bool
    {
        return $token !== null && preg_match('/^[0-9a-f]{32}$/', $token) === 1;
    }

    /**
     * Счётчики и отметка «я лайкнул» для работ на странице. Неизвестные id и черновики пропускаются.
     *
     * @param list<string> $projectIds
     *
     * @return array<string, array{count: int, liked: bool}>
     */
    public function states(array $projectIds, ?string $visitorToken): array
    {
        $ids = [];
        foreach ($this->projects->findPublishedByIds(\array_slice(array_values(array_unique($projectIds)), 0, self::MAX_PROJECTS_PER_REQUEST)) as $project) {
            $ids[] = (string) $project->id();
        }
        if ($ids === []) {
            return [];
        }

        $counts = $this->likes->countByProjects($ids);
        $liked = self::isVisitorToken($visitorToken) ? array_flip($this->likes->likedByVisitor($ids, $this->hash('visitor', (string) $visitorToken))) : [];

        $states = [];
        foreach ($ids as $id) {
            $states[$id] = ['count' => $counts[$id] ?? 0, 'liked' => isset($liked[$id])];
        }

        return $states;
    }

    /**
     * Ставит или снимает лайк посетителя. Повторная постановка и снятие несуществующего лайка ничего не меняют.
     *
     * @return array{count: int, liked: bool}
     *
     * @throws PortfolioNotFoundException  работы нет или она не опубликована
     * @throws PortfolioLikeLimitException с этого IP на работу уже MAX_LIKES_PER_IP лайков
     */
    public function setLiked(string $projectId, string $visitorToken, string $clientIp, bool $liked): array
    {
        if (!self::isVisitorToken($visitorToken)) {
            throw new \InvalidArgumentException('Некорректный токен посетителя.');
        }
        $project = $this->projects->findPublishedByIds([$projectId])[0] ?? throw new PortfolioNotFoundException('Работа портфолио не найдена.');
        $visitorHash = $this->hash('visitor', $visitorToken);
        $existing = $this->likes->findByVisitor($project, $visitorHash);

        if ($liked && !$existing instanceof PortfolioLike) {
            $ipHash = $this->hash('ip', $clientIp);
            if ($this->likes->countByIp($project, $ipHash) >= self::MAX_LIKES_PER_IP) {
                throw new PortfolioLikeLimitException('С этой сети уже поставлено много лайков этой работе.');
            }
            $this->likes->save(new PortfolioLike($project, $visitorHash, $ipHash));
        } elseif (!$liked && $existing instanceof PortfolioLike) {
            $this->likes->remove($existing);
        }

        return ['count' => $this->count($project), 'liked' => $liked];
    }

    private function count(PortfolioProject $project): int
    {
        $id = (string) $project->id();

        return $this->likes->countByProjects([$id])[$id] ?? 0;
    }

    private function hash(string $purpose, string $value): string
    {
        return hash_hmac('sha256', $purpose.':'.$value, $this->secret);
    }
}
