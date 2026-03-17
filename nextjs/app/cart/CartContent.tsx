'use client';

import { use, useEffect, useState } from 'react';
import Link from 'next/link';
import { api, type EstimateDetail } from '@/lib/api/client';

export function CartContent({
  searchParams,
}: {
  searchParams: Promise<{ estimateId?: string }>;
}) {
  const params = use(searchParams);
  const estimateId = params.estimateId ? Number(params.estimateId) : null;
  const [estimate, setEstimate] = useState<EstimateDetail | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (estimateId) {
      api.getEstimate(estimateId).then(setEstimate).finally(() => setLoading(false));
    } else {
      setLoading(false);
    }
  }, [estimateId]);

  if (loading) return <p>Загрузка…</p>;
  if (!estimateId) {
    return (
      <div className="rounded-lg border border-stone-200 bg-white p-8 text-center">
        <p className="text-stone-600">Корзина пуста.</p>
        <Link href="/catalog" className="mt-4 inline-block text-stone-800 underline">
          Перейти в каталог
        </Link>
      </div>
    );
  }
  if (!estimate) {
    return <p className="text-red-600">Смета не найдена.</p>;
  }

  return (
    <div className="grid gap-8 lg:grid-cols-2">
      <div className="rounded-lg border border-stone-200 bg-white p-6">
        <h2 className="font-semibold text-stone-800">Позиции</h2>
        <ul className="mt-4 space-y-2">
          {estimate.lines.map((line, i) => (
            <li key={i} className="flex justify-between text-sm">
              <span>{line.description} × {line.quantity}</span>
              <span>{line.price.toLocaleString('ru-RU')} ₽</span>
            </li>
          ))}
        </ul>
        <p className="mt-4 font-bold text-stone-900">
          Итого: {estimate.totalPrice.toLocaleString('ru-RU')} ₽
        </p>
        <Link
          href={`/checkout?estimateId=${estimateId}`}
          className="mt-4 inline-block rounded bg-stone-800 px-4 py-2 text-white hover:bg-stone-700"
        >
          Оформить заказ
        </Link>
      </div>

      <div>
        <a
          href={`${process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8080'}/api/documents/quote/${estimateId}`}
          target="_blank"
          rel="noopener noreferrer"
          className="inline-block rounded border border-stone-300 px-4 py-2 hover:bg-stone-100"
        >
          Скачать PDF
        </a>
      </div>
    </div>
  );
}
