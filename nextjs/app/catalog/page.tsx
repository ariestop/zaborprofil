import Link from 'next/link';
import { api, type Product } from '@/lib/api/client';

export const dynamic = 'force-dynamic';

export default async function CatalogPage() {
  const { products, categories } = await api.getCatalog();

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
        <h1 className="mb-6 text-2xl font-bold text-stone-900">Каталог</h1>

        {categories.length > 0 && (
          <div className="mb-6 flex flex-wrap gap-2">
            {categories.map((cat) => (
              <span
                key={cat.id}
                className="rounded-full bg-stone-200 px-3 py-1 text-sm text-stone-700"
              >
                {cat.name}
              </span>
            ))}
          </div>
        )}

        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {products.map((product) => (
            <ProductCard key={product.id} product={product} />
          ))}
        </div>

        {products.length === 0 && (
          <p className="text-center text-stone-500">Продукты не найдены.</p>
        )}
      </main>
    </div>
  );
}

function ProductCard({ product }: { product: Product }) {
  return (
    <Link
      href={`/product/${product.slug}`}
      className="block rounded-lg border border-stone-200 bg-white p-4 shadow-sm transition hover:shadow-md"
    >
      <h2 className="font-semibold text-stone-900">{product.name}</h2>
      <p className="mt-1 text-sm text-stone-500">{product.category.name}</p>
      {product.description && (
        <p className="mt-2 line-clamp-2 text-sm text-stone-600">{product.description}</p>
      )}
    </Link>
  );
}
