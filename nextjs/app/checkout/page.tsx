import Link from 'next/link';
import { Suspense } from 'react';
import { CheckoutForm } from './CheckoutForm';

export default function CheckoutPage({
  searchParams,
}: {
  searchParams: Promise<{ estimateId?: string }>;
}) {
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
        <h1 className="mb-6 text-2xl font-bold text-stone-900">Оформление заказа</h1>
        <Suspense fallback={<p>Загрузка…</p>}>
          <CheckoutForm searchParams={searchParams} />
        </Suspense>
      </main>
    </div>
  );
}
