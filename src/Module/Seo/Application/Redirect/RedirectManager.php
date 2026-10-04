<?php

declare(strict_types=1);

namespace App\Module\Seo\Application\Redirect;

use App\Module\Content\Application\Service\PublicPageCacheInvalidator;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Module\Seo\Domain\Entity\Redirect;
use App\Module\Seo\Domain\Repository\RedirectRepositoryInterface;

final readonly class RedirectManager
{
    public function __construct(
        private RedirectRepositoryInterface $redirects,
        private RedirectRuleValidator $validator,
        private PageRepositoryInterface $pages,
        private PublicPageCacheInvalidator $publicPageCache,
    ) {
    }

    public function create(string $sourcePath, string $targetPath, int $statusCode, bool $active): RedirectChange
    {
        $rule = $this->validator->validate($sourcePath, $targetPath, $statusCode);
        if ($this->redirects->findBySourcePath($rule->sourcePath) !== null) {
            throw new RedirectValidationException('Редирект с таким исходным URL уже существует.', 'sourcePath', RedirectValidationException::CODE_DUPLICATE);
        }

        $warnings = $this->guardAgainstLoops($rule, $active, null);
        $redirect = new Redirect($rule->sourcePath, $rule->targetPath, $rule->statusCode, $active);
        $this->redirects->save($redirect);
        $this->publicPageCache->invalidateAll();

        return new RedirectChange($redirect, $warnings);
    }

    public function update(Redirect $redirect, string $sourcePath, string $targetPath, int $statusCode, bool $active): RedirectChange
    {
        $rule = $this->validator->validate($sourcePath, $targetPath, $statusCode);
        if ($rule->sourcePath !== $redirect->sourcePath()) {
            $existing = $this->redirects->findBySourcePath($rule->sourcePath);
            if ($existing !== null && (string) $existing->id() !== (string) $redirect->id()) {
                throw new RedirectValidationException('Редирект с таким исходным URL уже существует.', 'sourcePath', RedirectValidationException::CODE_DUPLICATE);
            }
        }

        $warnings = $this->guardAgainstLoops($rule, $active, $redirect);
        if ($rule->sourcePath !== $redirect->sourcePath()) {
            $redirect->changeSourcePath($rule->sourcePath);
        }
        $redirect->update($rule->targetPath, $rule->statusCode, $active);
        $this->redirects->save($redirect);
        $this->publicPageCache->invalidateAll();

        return new RedirectChange($redirect, $warnings);
    }

    public function remove(Redirect $redirect): void
    {
        $this->redirects->remove($redirect);
        $this->publicPageCache->invalidateAll();
    }

    /**
     * @return list<RedirectWarning>
     */
    private function guardAgainstLoops(RedirectRule $rule, bool $active, ?Redirect $current): array
    {
        $warnings = [];
        $decodedSource = rawurldecode($rule->sourcePath);
        if ($this->pages->findPublishedByPath($decodedSource) !== null) {
            $warnings[] = new RedirectWarning('source_is_published_page', 'Исходный URL совпадает с опубликованной страницей: редирект перекроет её на сайте.');
        }

        $internalTarget = $this->validator->internalPath($rule->targetPath);
        if ($internalTarget !== null && $internalTarget !== '/' && $this->pages->findPublishedByPath(rawurldecode($internalTarget)) === null) {
            $warnings[] = new RedirectWarning('target_page_not_found', 'Целевой URL не совпадает с опубликованной страницей CMS. Убедитесь, что он открывается (например, это раздел каталога или файл).');
        }

        if (!$active || $internalTarget === null) {
            return $warnings;
        }

        $graph = $this->activeGraph($current);
        $loop = $graph->loopFor($rule->sourcePath, $internalTarget);
        if ($loop !== null) {
            throw new RedirectValidationException(
                'Редирект создаёт цикл: '.implode(' → ', $loop).'.',
                'targetPath',
                RedirectValidationException::CODE_LOOP,
            );
        }

        $chain = $graph->chainFor($rule->sourcePath, $internalTarget);
        if (\count($chain) > 2) {
            $warnings[] = new RedirectWarning('chain', 'Цепочка редиректов: '.implode(' → ', $chain).'. Лучше сразу указать конечный URL.');
        }

        $incoming = $graph->incomingTo($rule->sourcePath);
        if ($incoming !== []) {
            $warnings[] = new RedirectWarning(
                'incoming_chain',
                \sprintf('На исходный URL уже ведут редиректы (%s): получится цепочка.', implode(', ', \array_slice($incoming, 0, 3))),
            );
        }

        return $warnings;
    }

    private function activeGraph(?Redirect $excluding): RedirectGraph
    {
        $graph = new RedirectGraph();
        foreach ($this->redirects->findAllActive() as $redirect) {
            if ($excluding !== null && (string) $redirect->id() === (string) $excluding->id()) {
                continue;
            }

            $internal = $this->validator->internalPath($redirect->targetPath());
            if ($internal !== null) {
                $graph->set($redirect->sourcePath(), $internal);
            }
        }

        return $graph;
    }
}
