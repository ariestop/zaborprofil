import '../shared/styles/app.css'

function formPayload(form: HTMLFormElement): Record<string, unknown> {
  const data = new FormData(form)

  return {
    source: String(data.get('source') ?? 'public_page_form'),
    name: String(data.get('name') ?? ''),
    phone: String(data.get('phone') ?? ''),
    email: String(data.get('email') ?? '') || null,
    message: String(data.get('message') ?? '') || null,
    consent: data.get('consent') === 'on',
    consentText: String(data.get('consentText') ?? ''),
    website: String(data.get('website') ?? ''),
    formLoadedAt: String(data.get('formLoadedAt') ?? ''),
    pageUrl: String(data.get('pageUrl') ?? window.location.href),
    policyUrl: String(data.get('policyUrl') ?? '/privacy/'),
  }
}

document.querySelectorAll<HTMLFormElement>('.js-lead-form').forEach((form) => {
  const status = form.querySelector<HTMLElement>('.js-lead-form-status')

  form.addEventListener('submit', async (event) => {
    event.preventDefault()
    status!.textContent = 'Отправляем...'

    const response = await fetch(form.action, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(formPayload(form)),
    })

    if (!response.ok) {
      status!.textContent = 'Не удалось отправить заявку. Проверьте поля и попробуйте снова.'
      return
    }

    form.reset()
    status!.textContent = 'Спасибо! Заявка отправлена.'
  })
})
