<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Upload;

use App\Shared\Domain\Exception\ClientSafeExceptionInterface;
use RuntimeException;

final class UploadSecurityException extends RuntimeException implements ClientSafeExceptionInterface
{
}
