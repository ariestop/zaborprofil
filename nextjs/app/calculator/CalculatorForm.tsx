'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { api, type Product } from '@/lib/api/client';

export function CalculatorForm({ products }: { products: Product[] }) {
  const router = useRouter();
  const [productId, setProductId] = useState(products[0]?.id ?? 0);
  const [variantId, setVariantId] = useState(products[0]?.variants[0]?.id ?? 0);
  const [quantity, setQuantity] = useState(10);
  const [height, setHeight] = useState(2000);
  const [width, setWidth] = useState(3000);
  const [installation, setInstallation] = useState(false);
  const [total, setTotal] = useState<number | null>(null);
  const [breakdown, setBreakdown] = useState<{ description: string; quantity: number; total: number }[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const product = products.find((p) => p.id === productId);

  const handleProductChange = (id: number) => {
    setProductId(id);
    const p = products.find((x) => x.id === id);
    setVariantId(p?.variants[0]?.id ?? 0);
    setTotal(null);
  };

  const handleCalculate = async () => {
    setLoading(true);
    setError(null);
    try {
      const result = await api.calculate({
        productId,
        variantId,
        quantity,
        options: { height, width },
        extras: installation ? ['installation'] : [],
      });
      setTotal(result.total);
      setBreakdown(result.breakdown);
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
        productId,
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

  if (products.length === 0) {
    return <p className="text-stone-500">Выберите продукт в каталоге.</p>;
  }

  return (
    <div className="grid gap-8 lg:grid-cols-2">
      <div className="space-y-4 rounded-lg border border-stone-200 bg-white p-6">
        <div>
          <label className="block text-sm font-medium text-stone-700">Продукт</label>
          <select
            value={productId}
            onChange={(e) => handleProductChange(Number(e.target.value))}
            className="mt-1 w-full rounded border border-stone-300 px-3 py-2"
          >
            {products.map((p) => (
              <option key={p.id} value={p.id}>
                {p.name}
              </option>
            ))}
          </select>
        </div>

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

      <div className="rounded-lg border border-stone-200 bg-white p-6">
        <h3 className="font-semibold text-stone-800">Результат</h3>
        {total !== null ? (
          <div className="mt-4">
            <p className="text-2xl font-bold text-stone-900">
              {total.toLocaleString('ru-RU')} ₽
            </p>
            {breakdown.length > 0 && (
              <ul className="mt-4 space-y-2">
                {breakdown.map((line, i) => (
                  <li key={i} className="flex justify-between text-sm text-stone-600">
                    <span>{line.description} × {line.quantity}</span>
                    <span>{line.total.toLocaleString('ru-RU')} ₽</span>
                  </li>
                ))}
              </ul>
            )}
          </div>
        ) : (
          <p className="mt-4 text-stone-500">Нажмите «Рассчитать» для получения сметы.</p>
        )}
      </div>
    </div>
  );
}
