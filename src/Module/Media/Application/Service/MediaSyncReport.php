<?php

declare(strict_types=1);

namespace App\Module\Media\Application\Service;

/**
 * Итог синхронизации медиатеки: что занесено, кому сделаны превью и что пропущено.
 */
final class MediaSyncReport
{
    /** @var list<string> */
    public array $imported = [];

    /** @var list<string> */
    public array $variantsCreated = [];

    /** @var list<string> файлы из контента, которых нет на диске */
    public array $missingFiles = [];

    /** @var list<string> файлы с тем же содержимым, что у ассета под другим путём */
    public array $duplicates = [];

    /** @var list<string> не картинки или форматы, которые PHP не умеет уменьшать */
    public array $skipped = [];

    /** @var list<string> картинки больше лимитов загрузки (размер файла или разрешение): GD их не обрабатывает */
    public array $tooLarge = [];

    /**
     * Файлы, на которых обработка упала; остальные файлы синхронизация продолжает.
     *
     * @var array<string, string> путь => класс и текст исключения
     */
    public array $failed = [];
}
