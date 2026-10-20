/**
 * Главная: ссылка раскрывает компактный подбор, нативный details остаётся рабочим без JS.
 * Отправка заявок и контекст «Хочу похожий» используют существующие leadForm / initLeadPlans.
 */
export function initHomeChoice(root: ParentNode = document): void {
    const panel = root.querySelector<HTMLDetailsElement>('[data-home-choice-panel]')
    if (panel === null) {
        return
    }

    const open = (): void => {
        panel.open = true
        panel.querySelector<HTMLElement>('summary')?.focus({ preventScroll: true })
    }

    root.querySelectorAll<HTMLAnchorElement>('a[data-home-choice]').forEach((link) => {
        link.addEventListener('click', open)
    })

    if (window.location.hash === '#home-choice') {
        open()
    }
}
