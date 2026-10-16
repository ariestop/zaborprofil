<?php

declare(strict_types=1);

namespace App\Module\Portfolio\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Uid\Ulid;

/**
 * Лайк работы от посетителя сайта. Посетитель — случайный токен в cookie; в БД только HMAC токена и IP, без исходных значений.
 * Один посетитель — один лайк на работу (уникальный индекс); лимит лайков с одного IP проверяет PortfolioLikeService.
 */
#[ORM\Entity(repositoryClass: \App\Module\Portfolio\Infrastructure\Doctrine\Repository\DoctrinePortfolioLikeRepository::class)]
#[ORM\Table(name: 'portfolio_likes')]
#[ORM\UniqueConstraint(name: 'uniq_portfolio_likes_project_visitor', columns: ['project_id', 'visitor_hash'])]
#[ORM\Index(name: 'idx_portfolio_likes_project_ip', columns: ['project_id', 'ip_hash'])]
final class PortfolioLike
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: PortfolioProject::class)]
    #[ORM\JoinColumn(name: 'project_id', nullable: false, onDelete: 'CASCADE')]
    private PortfolioProject $project;

    #[ORM\Column(name: 'visitor_hash', length: 64, options: ['fixed' => true])]
    private string $visitorHash;

    #[ORM\Column(name: 'ip_hash', length: 64, options: ['fixed' => true])]
    private string $ipHash;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(PortfolioProject $project, string $visitorHash, string $ipHash)
    {
        foreach ([$visitorHash, $ipHash] as $hash) {
            if (preg_match('/^[0-9a-f]{64}$/', $hash) !== 1) {
                throw new InvalidArgumentException('Лайк хранит только хэши посетителя и IP.');
            }
        }
        $this->id = new Ulid();
        $this->project = $project;
        $this->visitorHash = $visitorHash;
        $this->ipHash = $ipHash;
        $this->createdAt = new DateTimeImmutable();
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function project(): PortfolioProject
    {
        return $this->project;
    }

    public function visitorHash(): string
    {
        return $this->visitorHash;
    }

    public function ipHash(): string
    {
        return $this->ipHash;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
