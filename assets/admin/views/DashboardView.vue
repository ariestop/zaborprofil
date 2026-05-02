<template>
  <section class="grid gap-4 lg:grid-cols-3">
    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
      <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">Zaborprofil Admin</p>
      <h2 class="mt-2 text-2xl font-bold text-slate-950">Фундамент админки готов</h2>
      <p class="mt-3 text-slate-600">
        Этот интерфейс станет базой для системных разделов, SEO, медиа и заявок.
      </p>
    </article>

    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <h3 class="text-base font-semibold text-slate-950">Быстрый статус</h3>
      <dl class="mt-4 space-y-3 text-sm">
        <div class="flex justify-between">
          <dt class="text-slate-500">API-клиент</dt>
          <dd class="font-medium text-emerald-700">готов</dd>
        </div>
        <div class="flex justify-between">
          <dt class="text-slate-500">CSRF</dt>
          <dd class="font-medium text-emerald-700">включен</dd>
        </div>
        <div class="flex justify-between">
          <dt class="text-slate-500">SPA routing</dt>
          <dd class="font-medium text-emerald-700">готов</dd>
        </div>
      </dl>
    </article>
  </section>
</template>
