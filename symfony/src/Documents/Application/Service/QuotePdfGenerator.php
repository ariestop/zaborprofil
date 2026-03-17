<?php

declare(strict_types=1);

namespace App\Documents\Application\Service;

use App\Estimation\Application\UseCase\GetEstimateUseCase;
use Dompdf\Dompdf;
use Dompdf\Options;

final readonly class QuotePdfGenerator
{
    public function __construct(
        private GetEstimateUseCase $getEstimateUseCase,
    ) {
    }

    public function generate(int $estimateId): ?string
    {
        $estimate = $this->getEstimateUseCase->execute($estimateId);
        if (!$estimate) {
            return null;
        }

        $html = $this->renderHtml($estimate);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * @param array{id: int, totalPrice: float, status: string, lines: array} $estimate
     */
    private function renderHtml(array $estimate): string
    {
        $linesHtml = '';
        $date = $this->escape(date('d.m.Y'));
        $totalPrice = number_format($estimate['totalPrice'], 2);

        foreach ($estimate['lines'] as $line) {
            $linesHtml .= sprintf(
                '<tr><td>%s</td><td>%d</td><td>%.2f</td><td>%.2f</td></tr>',
                htmlspecialchars($line['description']),
                $line['quantity'],
                $line['price'] / max(1, $line['quantity']),
                $line['price']
            );
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; padding: 40px; }
        h1 { font-size: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #333; padding: 8px; text-align: left; }
        th { background: #f0f0f0; }
        .total { font-weight: bold; font-size: 18px; margin-top: 20px; }
    </style>
</head>
<body>
    <h1>Коммерческое предложение #{$estimate['id']}</h1>
    <p>Дата: {$date}</p>
    <table>
        <thead>
            <tr><th>Наименование</th><th>Кол-во</th><th>Цена за ед.</th><th>Сумма</th></tr>
        </thead>
        <tbody>
            {$linesHtml}
        </tbody>
    </table>
    <p class="total">Итого: {$totalPrice} руб.</p>
</body>
</html>
HTML;
    }

    private function escape(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
