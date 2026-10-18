<?php

declare(strict_types=1);

namespace App\Tests\Unit\Portfolio;

use App\Module\Portfolio\Domain\Entity\PortfolioCategory;
use App\Module\Portfolio\Domain\Entity\PortfolioMedia;
use App\Module\Portfolio\Domain\Entity\PortfolioProject;
use App\Module\Portfolio\Domain\Enum\PortfolioMediaKind;
use App\Module\Portfolio\Domain\ValueObject\PortfolioMediaDraft;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PortfolioProjectMediaTest extends TestCase
{
    public function testFirstPhotoBecomesCoverAndOrderIsKept(): void
    {
        $project = $this->project();
        $project->replaceMedia([
            new PortfolioMediaDraft(PortfolioMediaKind::Embed, 'https://vkvideo.ru/video-1_2'),
            new PortfolioMediaDraft(PortfolioMediaKind::Photo, '/uploads/media/a.jpg', caption: ' Вид с улицы '),
            new PortfolioMediaDraft(PortfolioMediaKind::Video, '/uploads/media/b.mp4', poster: '/uploads/media/a.jpg'),
            new PortfolioMediaDraft(PortfolioMediaKind::Photo, '/uploads/media/c.webp'),
        ]);

        self::assertSame(['embed', 'photo', 'video', 'photo'], array_map(static fn (PortfolioMedia $m): string => $m->kind()->value, $project->media()));
        self::assertSame('/uploads/media/a.jpg', $project->cover()?->src());
        self::assertSame('Вид с улицы', $project->media()[1]->caption());
        self::assertCount(2, $project->photos());
        self::assertCount(2, $project->videos());
    }

    public function testChosenPhotoIsCover(): void
    {
        $project = $this->project();
        $project->replaceMedia([
            new PortfolioMediaDraft(PortfolioMediaKind::Photo, '/uploads/media/a.jpg'),
            new PortfolioMediaDraft(PortfolioMediaKind::Photo, '/uploads/media/c.jpg', cover: true),
        ]);

        self::assertSame('/uploads/media/c.jpg', $project->cover()?->src());
    }

    public function testFeedStartsWithTheCoverAndSkipsVideos(): void
    {
        $project = $this->project();
        $project->replaceMedia([
            new PortfolioMediaDraft(PortfolioMediaKind::Photo, '/uploads/media/a.jpg'),
            new PortfolioMediaDraft(PortfolioMediaKind::Video, '/uploads/media/b.mp4'),
            new PortfolioMediaDraft(PortfolioMediaKind::Photo, '/uploads/media/c.jpg', cover: true),
            new PortfolioMediaDraft(PortfolioMediaKind::Photo, '/uploads/media/d.jpg'),
        ]);

        $src = static fn (PortfolioMedia $m): string => $m->src();
        self::assertSame(['/uploads/media/c.jpg', '/uploads/media/a.jpg', '/uploads/media/d.jpg'], array_map($src, $project->feedPhotos(10)));
        self::assertSame(['/uploads/media/c.jpg', '/uploads/media/a.jpg'], array_map($src, $project->feedPhotos(2)));
    }

    public function testOnlyOnePhotoCanBeCover(): void
    {
        $this->expectExceptionMessage('Главное фото может быть только одно.');
        $this->project()->replaceMedia([
            new PortfolioMediaDraft(PortfolioMediaKind::Photo, '/uploads/media/a.jpg', cover: true),
            new PortfolioMediaDraft(PortfolioMediaKind::Photo, '/uploads/media/c.jpg', cover: true),
        ]);
    }

    public function testVideoCannotBeCover(): void
    {
        $this->expectExceptionMessage('Главным может быть только фото.');
        new PortfolioMediaDraft(PortfolioMediaKind::Video, '/uploads/media/b.mp4', cover: true);
    }

    public function testMediaMustComeFromTheLibrary(): void
    {
        foreach ([
            [PortfolioMediaKind::Photo, 'https://evil.example/a.jpg'],
            [PortfolioMediaKind::Photo, '/uploads/media/a.mp4'],
            [PortfolioMediaKind::Video, '/uploads/media/b.jpg'],
            [PortfolioMediaKind::Video, '/uploads/media/../../etc/passwd.mp4x'],
        ] as [$kind, $src]) {
            try {
                new PortfolioMediaDraft($kind, $src);
                self::fail('Ожидалась ошибка для '.$src);
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testPublishingNeedsAPhoto(): void
    {
        $project = $this->project();
        $project->replaceMedia([new PortfolioMediaDraft(PortfolioMediaKind::Embed, 'https://vkvideo.ru/video-1_2')]);

        $this->expectExceptionMessage('Добавьте хотя бы одно фото');
        $project->publish();
    }

    public function testPublishedProjectKeepsAtLeastOnePhoto(): void
    {
        $project = $this->project();
        $project->replaceMedia([new PortfolioMediaDraft(PortfolioMediaKind::Photo, '/uploads/media/a.jpg')]);
        $project->publish();

        self::assertTrue($project->isPublished());
        self::assertNotNull($project->publishedAt());
        $this->expectExceptionMessage('хотя бы одно фото');
        $project->replaceMedia([]);
    }

    public function testSpecsNeedLabelAndValue(): void
    {
        $project = $this->project();
        $project->edit($project->category(), 'Работа', 'rabota', null, 2025, null, '', [['label' => 'Длина', 'value' => '42 м'], ['label' => '', 'value' => '']], null, null);
        self::assertSame([['label' => 'Длина', 'value' => '42 м']], $project->specs());

        $this->expectExceptionMessage('и название, и значение');
        $project->edit($project->category(), 'Работа', 'rabota', null, 2025, null, '', [['label' => 'Длина', 'value' => '']], null, null);
    }

    public function testPathIncludesCategory(): void
    {
        self::assertSame('/portfolio/zabor-zhalyuzi/rabota-1/', $this->project()->path());
    }

    private function project(): PortfolioProject
    {
        return new PortfolioProject(new PortfolioCategory('Заборы Жалюзи', 'zabor-zhalyuzi', 'Жалюзи'), 'Работа 1', 'rabota-1');
    }
}
