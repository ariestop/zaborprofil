import Link from 'next/link';
import { notFound } from 'next/navigation';
import { api } from '@/lib/api/client';
import { CalculatorWidget } from './CalculatorWidget';

export const dynamic = 'force-dynamic';

export default async function ProductPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  const result = await api.getProduct(slug);

  if ('error' in result) {
    notFound();
  }

  const product = result;

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
          </nav>
        </div>
      </header>

      <main className="mx-auto max-w-6xl px-4 py-8">
        <Link href="/catalog" className="mb-4 inline-block text-sm text-stone-500 hover:text-stone-700">
          ← Назад в каталог
        </Link>

        <div className="grid gap-8 lg:grid-cols-2">
          <div>
            <h1 className="text-3xl font-bold text-stone-900">{product.name}</h1>
            <p className="mt-2 text-stone-600">{product.category.name}</p>
            {product.description && (
              <p className="mt-4 text-stone-700">{product.description}</p>
            )}
            {product.variants.length > 0 && (
              <div className="mt-4">
                <h3 className="font-medium text-stone-800">Варианты</h3>
                <ul className="mt-2 space-y-1">
                  {product.variants.map((v) => (
                    <li key={v.id} className="text-sm text-stone-600">
                      {v.sku} {JSON.stringify(v.attributes)}
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </div>

          <div className="rounded-lg border border-stone-200 bg-white p-6">
            <CalculatorWidget product={product} />
          </div>
        </div>
      </main>
    </div>
  );
}
