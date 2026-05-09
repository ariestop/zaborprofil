<?php

declare(strict_types=1);

namespace App\Module\Admin\Application\Service;

use DateTimeImmutable;

final readonly class DeployInfoService
{
    public function __construct(
        private string $releaseInfoPath,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function info(): array
    {
        $payload = [
            'release' => null,
            'commit' => null,
            'builtAt' => null,
            'deployedAt' => null,
        ];

        if (is_file($this->releaseInfoPath)) {
            $decoded = json_decode((string) file_get_contents($this->releaseInfoPath), true);
            if (\is_array($decoded)) {
                foreach ($payload as $key => $value) {
                    if (array_key_exists($key, $decoded)) {
                        $payload[$key] = $decoded[$key];
                    }
                }
            }
        }

        return [
            ...$payload,
            'releaseInfoPath' => $this->releaseInfoPath,
            'checkedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];
    }
}
