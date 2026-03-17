'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { api, type Product } from '@/lib/api/client';

export function CalculatorWidget({ product }: { product: Product }) {
  const router = useRouter();
  const [quantity, setQuantity] = useState(10);
  const [height, setHeight] = useState(2000);
  const [width, setWidth] = useState(3000);
  const [installation, setInstallation] = useState(false);
  const [total, setTotal] = useState<number | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const variantId = product.variants[0]?.id ?? 0;

  const handleCalculate = async () => {
    setLoading(true);
    setError(null);
    try {
      const result = await api.calculate({
        productId: product.id,
        variantId,
        quantity,
        options: { height, width },
        extras: installation ? ['installation'] : [],
      });
      setTotal(result.total);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Ошибка расчёта');
    } finally {
      setLoading(false);
    }
  };

  const handleAddToCart = async () => {
    setLoading(true);
    setError(null);
    try {
      const result = await api.createEstimate({
        productId: product.id,
        variantId,
        quantity,
        options: { height, width },
        extras: installation ? ['installation'] : [],
      });
      router.push(`/cart?estimateId=${result.estimateId}`);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Ошибка');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="space-y-4">
      <h3 className="text-lg font-semibold text-stone-800">Рассчитать стоимость</h3>

      <div>
        <label className="block text-sm font-medium text-stone-700">Количество, шт</label>
        <input
          type="number"
          min={1}
          value={quantity}
          onChange={(e) => setQuantity(Number(e.target.value))}
          className="mt-1 w-full rounded border border-stone-300 px-3 py-2"
        />
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-stone-700">Высота, мм</label>
          <input
            type="number"
            min={500}
            value={height}
            onChange={(e) => setHeight(Number(e.target.value))}
            className="mt-1 w-full rounded border border-stone-300 px-3 py-2"
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-stone-700">Ширина, мм</label>
          <input
            type="number"
            min={500}
            value={width}
            onChange={(e) => setWidth(Number(e.target.value))}
            className="mt-1 w-full rounded border border-stone-300 px-3 py-2"
          />
        </div>
      </div>

      <label className="flex items-center gap-2">
        <input
          type="checkbox"
          checked={installation}
          onChange={(e) => setInstallation(e.target.checked)}
        />
        <span className="text-sm text-stone-700">Монтаж</span>
      </label>

      {error && <p className="text-sm text-red-600">{error}</p>}

      {total !== null && (
        <div className="rounded bg-stone-100 p-4">
          <p className="text-2xl font-bold text-stone-900">
            {total.toLocaleString('ru-RU')} ₽
          </p>
        </div>
      )}

      <div className="flex gap-2">
        <button
          onClick={handleCalculate}
          disabled={loading}
          className="rounded bg-stone-800 px-4 py-2 text-white hover:bg-stone-700 disabled:opacity-50"
        >
          {loading ? '…' : 'Рассчитать'}
        </button>
        <button
          onClick={handleAddToCart}
          disabled={loading}
          className="rounded border border-stone-300 px-4 py-2 hover:bg-stone-100 disabled:opacity-50"
        >
          В корзину
        </button>
      </div>
    </div>
  );
}
