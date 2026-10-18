<?php

declare(strict_types=1);

namespace App\Module\Media\Application\Upload;

use App\Module\Media\Domain\Entity\MediaAsset;

final readonly class MediaUploadResult
{
    /**
     * @param bool $duplicate файл с таким содержимым уже есть в медиатеке: возвращена существующая запись, новый файл не сохранён
     */
    public function __construct(
        public MediaAsset $asset,
        public bool $duplicate,
    ) {
    }
}
