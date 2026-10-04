<?php

declare(strict_types=1);

namespace App\Module\Media\Application\Usage;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class MediaUsageFinder
{
    /**
     * @param iterable<MediaUsageProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(MediaUsageProviderInterface::TAG)]
        private iterable $providers,
    ) {
    }

    public function index(): MediaUsageIndex
    {
        $references = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->references() as $reference) {
                $references[] = $reference;
            }
        }

        return new MediaUsageIndex($references);
    }
}
