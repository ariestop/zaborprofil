/**
 * Страница отдаётся из HTTP-кэша, поэтому серверный HTML не содержит ни времени загрузки формы,
 * ни адреса запроса: антиспам-проверка «слишком быстрая отправка» и источник заявки заполняются в браузере.
 */
export function initLeadFormContext(form: HTMLFormElement, now: Date = new Date(), pageUrl: string = window.location.href): void {
  const loadedAt = form.elements.namedItem('formLoadedAt')
  const page = form.elements.namedItem('pageUrl')

  if (loadedAt instanceof HTMLInputElement) {
    loadedAt.value = now.toISOString()
  }

  if (page instanceof HTMLInputElement) {
    page.value = pageUrl
  }
}
