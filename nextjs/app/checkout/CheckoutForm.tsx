'use client';

import { use, useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { api } from '@/lib/api/client';

export function CheckoutForm({
  searchParams,
}: {
  searchParams: Promise<{ estimateId?: string }>;
}) {
  const router = useRouter();
  const params = use(searchParams);
  const estimateId = params.estimateId ? Number(params.estimateId) : null;

  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!estimateId) return;
    setLoading(true);
    setError(null);
    try {
      await api.createOrder(estimateId, { name, email, phone });
      setDone(true);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Ошибка');
    } finally {
      setLoading(false);
    }
  };

  if (!estimateId) {
    return (
      <p className="text-stone-600">
        Выберите смету в <Link href="/cart" className="underline">корзине</Link>.
      </p>
    );
  }

  if (done) {
    return (
      <div className="rounded-lg border border-green-200 bg-green-50 p-8 text-center">
        <h2 className="text-xl font-semibold text-green-800">Заказ оформлен</h2>
        <p className="mt-2 text-green-700">Менеджер свяжется с вами в ближайшее время.</p>
        <Link href="/" className="mt-4 inline-block text-green-800 underline">
          На главную
        </Link>
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="max-w-md space-y-4">
      <div>
        <label className="block text-sm font-medium text-stone-700">Имя</label>
        <input
          type="text"
          required
          value={name}
          onChange={(e) => setName(e.target.value)}
          className="mt-1 w-full rounded border border-stone-300 px-3 py-2"
        />
      </div>
      <div>
        <label className="block text-sm font-medium text-stone-700">Email</label>
        <input
          type="email"
          required
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          className="mt-1 w-full rounded border border-stone-300 px-3 py-2"
        />
      </div>
      <div>
        <label className="block text-sm font-medium text-stone-700">Телефон</label>
        <input
          type="tel"
          required
          value={phone}
          onChange={(e) => setPhone(e.target.value)}
          className="mt-1 w-full rounded border border-stone-300 px-3 py-2"
        />
      </div>
      {error && <p className="text-sm text-red-600">{error}</p>}
      <button
        type="submit"
        disabled={loading}
        className="rounded bg-stone-800 px-4 py-2 text-white hover:bg-stone-700 disabled:opacity-50"
      >
        {loading ? '…' : 'Отправить заказ'}
      </button>
    </form>
  );
}
