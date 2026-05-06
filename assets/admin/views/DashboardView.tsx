export default function DashboardView() {
  return (
    <section className="grid gap-4 lg:grid-cols-3">
      <article className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
        <p className="text-sm font-semibold uppercase tracking-wide text-emerald-700">Zaborprofil Admin</p>
        <h2 className="mt-2 text-2xl font-bold text-slate-950">Фундамент админки готов</h2>
        <p className="mt-3 text-slate-600">
          Этот интерфейс станет базой для системных разделов, SEO, медиа и заявок.
        </p>
      </article>

      <article className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 className="text-base font-semibold text-slate-950">Быстрый статус</h3>
        <dl className="mt-4 space-y-3 text-sm">
          <div className="flex justify-between">
            <dt className="text-slate-500">API-клиент</dt>
            <dd className="font-medium text-emerald-700">готов</dd>
          </div>
          <div className="flex justify-between">
            <dt className="text-slate-500">CSRF</dt>
            <dd className="font-medium text-emerald-700">включен</dd>
          </div>
          <div className="flex justify-between">
            <dt className="text-slate-500">SPA routing</dt>
            <dd className="font-medium text-emerald-700">готов</dd>
          </div>
        </dl>
      </article>
    </section>
  )
}
