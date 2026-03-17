<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Documents\Application\Service\QuotePdfGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class DocumentController extends AbstractController
{
    public function __construct(
        private readonly QuotePdfGenerator $quotePdfGenerator,
    ) {
    }

    #[Route('/documents/quote/{estimateId}', name: 'api_documents_quote', requirements: ['estimateId' => '\d+'], methods: ['GET'])]
    public function quote(int $estimateId): Response
    {
        $pdf = $this->quotePdfGenerator->generate($estimateId);

        if (!$pdf) {
            return new Response('Estimate not found', Response::HTTP_NOT_FOUND);
        }

        $response = new Response($pdf);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', sprintf('inline; filename="quote-%d.pdf"', $estimateId));

        return $response;
    }
}
