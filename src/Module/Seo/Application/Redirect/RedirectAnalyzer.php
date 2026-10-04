<?php

declare(strict_types=1);

namespace App\Module\Seo\Application\Redirect;

use App\Module\Seo\Domain\Entity\Redirect;
use App\Module\Seo\Domain\Repository\RedirectRepositoryInterface;

/**
 * Находит циклы и цепочки среди всех активных редиректов.
 */
final readonly class RedirectAnalyzer
{
    public function __construct(
        private RedirectRepositoryInterface $redirects,
        private RedirectRuleValidator $validator,
    ) {
    }

    /**
     * @return array{
     *     loops: list<array{path: list<string>, rules: list<array{id: string, sourcePath: string, targetPath: string, statusCode: int}>}>,
     *     chains: list<array{path: list<string>, finalTarget: string, rules: list<array{id: string, sourcePath: string, targetPath: string, statusCode: int}>}>,
     *     activeRules: int
     * }
     */
    public function analyze(): array
    {
        $active = $this->redirects->findAllActive();
        $bySource = [];
        $graph = new RedirectGraph();
        foreach ($active as $redirect) {
            $bySource[$redirect->sourcePath()] = $redirect;
            $internal = $this->validator->internalPath($redirect->targetPath());
            if ($internal !== null) {
                $graph->set($redirect->sourcePath(), $internal);
            }
        }

        $loops = [];
        foreach ($graph->cycles() as $cycle) {
            $loops[] = [
                'path' => $cycle,
                'rules' => $this->rulesFor(\array_slice($cycle, 0, -1), $bySource),
            ];
        }

        $chains = [];
        foreach ($graph->chains() as $chain) {
            $rules = $this->rulesFor(\array_slice($chain, 0, -1), $bySource);
            $chains[] = [
                'path' => $chain,
                'finalTarget' => $rules === [] ? $chain[\count($chain) - 1] : $rules[\count($rules) - 1]['targetPath'],
                'rules' => $rules,
            ];
        }

        return ['loops' => $loops, 'chains' => $chains, 'activeRules' => \count($active)];
    }

    /**
     * @param list<string>            $sources
     * @param array<string, Redirect> $bySource
     *
     * @return list<array{id: string, sourcePath: string, targetPath: string, statusCode: int}>
     */
    private function rulesFor(array $sources, array $bySource): array
    {
        $rules = [];
        foreach ($sources as $source) {
            $redirect = $bySource[$source] ?? null;
            if ($redirect === null) {
                continue;
            }

            $rules[] = [
                'id' => (string) $redirect->id(),
                'sourcePath' => $redirect->sourcePath(),
                'targetPath' => $redirect->targetPath(),
                'statusCode' => $redirect->statusCode(),
            ];
        }

        return $rules;
    }
}
