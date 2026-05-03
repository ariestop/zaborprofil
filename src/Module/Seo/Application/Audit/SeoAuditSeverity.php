<?php

declare(strict_types=1);

namespace App\Module\Seo\Application\Audit;

enum SeoAuditSeverity: string
{
    case P0 = 'P0';
    case P1 = 'P1';
    case P2 = 'P2';
}
