/** Native dialogs preserve keyboard focus, Escape dismissal and the existing lead form. */
export function initReferenceHome(): void {
    if (!document.body.classList.contains('ref-page')) return

    const menu = document.querySelector<HTMLElement>('#ref-mobile-menu')
    const toggle = document.querySelector<HTMLButtonElement>('.ref-menu-toggle')
    const closeMenu = (): void => {
        if (menu) menu.hidden = true
        toggle?.setAttribute('aria-expanded', 'false')
    }
    toggle?.addEventListener('click', () => {
        if (!menu) return
        menu.hidden = !menu.hidden
        toggle.setAttribute('aria-expanded', String(!menu.hidden))
    })
    menu?.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeMenu))
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && menu && !menu.hidden) {
            closeMenu()
            toggle?.focus()
        }
    })

    document
        .querySelectorAll<HTMLAnchorElement>('a[href="#lead-form"], a[data-ref-open]')
        .forEach((link) => {
            link.addEventListener('click', (event) => {
                const target = document.getElementById(link.dataset.refOpen ?? 'ref-enquiry')
                if (!(target instanceof HTMLDialogElement)) return
                event.preventDefault()
                document
                    .querySelectorAll<HTMLDialogElement>('.ref-dialog[open]')
                    .forEach((dialog) => dialog.close())
                target.showModal()
            })
        })
    document.querySelectorAll<HTMLDialogElement>('.ref-dialog').forEach((dialog) => {
        dialog.querySelector('[data-ref-close]')?.addEventListener('click', () => dialog.close())
        dialog.addEventListener('click', (event) => {
            const bounds = dialog.getBoundingClientRect()
            if (
                event.target === dialog &&
                (event.clientX < bounds.left ||
                    event.clientX > bounds.right ||
                    event.clientY < bounds.top ||
                    event.clientY > bounds.bottom)
            )
                dialog.close()
        })
    })
}
