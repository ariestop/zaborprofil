import Link from 'next/link';
import { api } from '@/lib/api/client';
import { LeadForm } from '@/components/LeadForm';

export const dynamic = 'force-dynamic';

export default async function HomePage() {
  const { products, categories } = await api.getCatalog().catch(() => ({
    products: [],
    categories: [],
    total: 0,
    page: 1,
    limit: 20,
  }));

  return (
    <div className="min-h-screen bg-stone-50">
      <header className="border-b bg-white">
        <div className="mx-auto max-w-6xl px-4 py-4">
          <Link href="/" className="text-xl font-semibold text-stone-800">
            Zaborprofil
          </Link>
          <nav className="mt-2 flex gap-4">
            <Link href="/catalog" className="text-stone-600 hover:text-stone-900">
              Каталог
            </Link>
            <Link href="/calculator" className="text-stone-600 hover:text-stone-900">
              Калькулятор
            </Link>
            <Link href="/cart" className="text-stone-600 hover:text-stone-900">
              Корзина
            </Link>
            <Link href="/chat" className="text-stone-600 hover:text-stone-900">
              Помощник
            </Link>
          </nav>
        </div>
      </header>

      <main className="mx-auto max-w-6xl px-4 py-12">
        <section className="mb-16 text-center">
          <h1 className="text-4xl font-bold text-stone-900">Заборы из профнастила</h1>
          <p className="mt-4 text-lg text-stone-600">
            Рассчитайте стоимость и оформите заказ онлайн
          </p>
          <Link
            href="/calculator"
            className="mt-6 inline-block rounded bg-stone-800 px-6 py-3 text-white hover:bg-stone-700"
          >
            Калькулятор
          </Link>
        </section>

        {products.length > 0 && (
          <section className="mb-16">
            <h2 className="mb-6 text-2xl font-bold text-stone-900">Продукты</h2>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {products.slice(0, 6).map((p) => (
                <Link
                  key={p.id}
                  href={`/product/${p.slug}`}
                  className="rounded-lg border border-stone-200 bg-white p-4 hover:shadow-md"
                >
                  <h3 className="font-semibold text-stone-900">{p.name}</h3>
                  <p className="text-sm text-stone-500">{p.category.name}</p>
                </Link>
              ))}
            </div>
            <Link href="/catalog" className="mt-4 inline-block text-stone-600 hover:text-stone-800">
              Весь каталог →
            </Link>
          </section>
        )}

        <section className="rounded-lg border border-stone-200 bg-white p-8">
          <h2 className="mb-4 text-xl font-bold text-stone-900">Оставить заявку</h2>
          <LeadForm />
        </section>
      </main>
    </div>
  );
}
