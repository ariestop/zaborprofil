<?php

declare(strict_types=1);

namespace App\Module\Seo\Application\Redirect;

use App\Module\Seo\Domain\Entity\Redirect;

final class RedirectCsvExporter
{
    /**
     * @param iterable<Redirect> $redirects
     */
    public function export(iterable $redirects): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        fputcsv($handle, ['source', 'target', 'status', 'active'], ',', '"', '', "\n");
        foreach ($redirects as $redirect) {
            fputcsv($handle, [
                $redirect->sourcePath(),
                $redirect->targetPath(),
                $redirect->statusCode(),
                $redirect->isActive() ? '1' : '0',
            ], ',', '"', '', "\n");
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }
}
