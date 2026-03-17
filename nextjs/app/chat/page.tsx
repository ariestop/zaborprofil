import Link from 'next/link';
import { ChatWidget } from './ChatWidget';

export default function ChatPage() {
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

      <main className="mx-auto max-w-2xl px-4 py-8">
        <h1 className="mb-6 text-2xl font-bold text-stone-900">Помощник</h1>
        <ChatWidget />
      </main>
    </div>
  );
}
