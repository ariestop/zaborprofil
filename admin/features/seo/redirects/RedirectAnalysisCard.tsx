import { useState } from 'react'
import { Badge, Button, Card } from '../../../shared/ui'
import { useToast } from '../../../app/providers/toast-provider'
import { useRedirectAnalysisQuery, useUpdateRedirectMutation } from '../../../entities/seo/api'
import type { RedirectAnalysis, RedirectRuleRef } from '../../../entities/seo/model'
import { decodeForDisplay, describeApiError } from './redirect-rules'

export function RedirectAnalysisCard() {
    const { push } = useToast()
    const [requested, setRequested] = useState(false)
    const analysisQuery = useRedirectAnalysisQuery(requested)
    const updateMutation = useUpdateRedirectMutation()

    const analysis: RedirectAnalysis | undefined = analysisQuery.data
    const busy = analysisQuery.isFetching || updateMutation.isPending

    const run = () => {
        if (requested) {
            void analysisQuery.refetch()
        } else {
            setRequested(true)
        }
    }

    const fixChain = async (rules: RedirectRuleRef[], finalTarget: string) => {
        try {
            for (const rule of rules) {
                if (rule.targetPath !== finalTarget) {
                    await updateMutation.mutateAsync({
                        id: rule.id,
                        payload: {
                            targetPath: finalTarget,
                            statusCode: rule.statusCode,
                            isActive: true,
                        },
                    })
                }
            }
            push({
                title: 'Цепочка сокращена',
                description: `Все правила ведут напрямую на ${decodeForDisplay(finalTarget)}.`,
            })
            await analysisQuery.refetch()
        } catch (error) {
            push({
                title: 'Не удалось исправить цепочку',
                description: describeApiError(error, 'Повторите попытку.'),
            })
        }
    }

    const disableRule = async (rule: RedirectRuleRef) => {
        try {
            await updateMutation.mutateAsync({
                id: rule.id,
                payload: {
                    targetPath: rule.targetPath,
                    statusCode: rule.statusCode,
                    isActive: false,
                },
            })
            push({ title: 'Правило отключено', description: decodeForDisplay(rule.sourcePath) })
            await analysisQuery.refetch()
        } catch (error) {
            push({
                title: 'Не удалось отключить правило',
                description: describeApiError(error, 'Повторите попытку.'),
            })
        }
    }

    return (
        <Card
            title="Проверка циклов и цепочек"
            description="Цикл — редиректы замыкаются друг на друга, страница не открывается. Цепочка — несколько переходов подряд: это замедляет сайт и теряет вес ссылок."
        >
            <div className="grid gap-3">
                <div>
                    <Button type="button" variant="outline" disabled={busy} onClick={run}>
                        {analysisQuery.isFetching
                            ? 'Проверка...'
                            : 'Проверить все активные редиректы'}
                    </Button>
                </div>

                {analysisQuery.isError ? (
                    <p className="text-sm text-red-600">Не удалось выполнить проверку.</p>
                ) : null}

                {analysis !== undefined ? (
                    <div className="grid gap-3" aria-live="polite">
                        <p className="text-sm text-slate-600 dark:text-slate-300">
                            Проверено активных правил: {analysis.activeRules}. Циклов:{' '}
                            {analysis.loops.length}. Цепочек: {analysis.chains.length}.
                        </p>
                        {analysis.loops.length === 0 && analysis.chains.length === 0 ? (
                            <p className="text-sm text-brand-700 dark:text-brand-400">
                                Проблем не найдено.
                            </p>
                        ) : null}

                        {analysis.loops.map((loop) => (
                            <div
                                key={loop.path.join('>')}
                                className="rounded-lg border border-red-300 bg-red-50 p-3 dark:border-red-900 dark:bg-red-950/30"
                            >
                                <p className="mb-1 text-sm font-semibold text-red-800 dark:text-red-300">
                                    <Badge tone="warning">Цикл</Badge>{' '}
                                    <span className="font-mono">
                                        {loop.path.map(decodeForDisplay).join(' → ')}
                                    </span>
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    {loop.rules.map((rule) => (
                                        <Button
                                            key={rule.id}
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            disabled={busy}
                                            onClick={() => void disableRule(rule)}
                                        >
                                            Отключить {decodeForDisplay(rule.sourcePath)}
                                        </Button>
                                    ))}
                                </div>
                            </div>
                        ))}

                        {analysis.chains.map((chain) => (
                            <div
                                key={chain.path.join('>')}
                                className="rounded-lg border border-amber-300 bg-amber-50 p-3 dark:border-amber-900 dark:bg-amber-950/30"
                            >
                                <p className="mb-1 text-sm font-semibold text-amber-800 dark:text-amber-300">
                                    <Badge tone="warning">Цепочка</Badge>{' '}
                                    <span className="font-mono">
                                        {chain.path.map(decodeForDisplay).join(' → ')}
                                    </span>
                                </p>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    disabled={busy}
                                    onClick={() => void fixChain(chain.rules, chain.finalTarget)}
                                >
                                    Вести напрямую на {decodeForDisplay(chain.finalTarget)}
                                </Button>
                            </div>
                        ))}
                    </div>
                ) : null}
            </div>
        </Card>
    )
}
