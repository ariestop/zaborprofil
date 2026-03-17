'use client';

import { useState, useRef, useEffect } from 'react';
import { api } from '@/lib/api/client';

type Message = { role: 'user' | 'assistant'; content: string };

export function ChatWidget() {
  const [messages, setMessages] = useState<Message[]>([
    { role: 'assistant', content: 'Здравствуйте! Чем могу помочь? Спросите о калькуляторе, ценах или оформлении заявки.' },
  ]);
  const [input, setInput] = useState('');
  const [loading, setLoading] = useState(false);
  const bottomRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!input.trim() || loading) return;

    const userMessage = input.trim();
    setInput('');
    setMessages((m) => [...m, { role: 'user', content: userMessage }]);
    setLoading(true);

    try {
      const result = await api.chat(userMessage);
      setMessages((m) => [...m, { role: 'assistant', content: result.response }]);
    } catch {
      setMessages((m) => [...m, { role: 'assistant', content: 'Произошла ошибка. Попробуйте позже.' }]);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex flex-col rounded-lg border border-stone-200 bg-white">
      <div className="max-h-96 overflow-y-auto p-4 space-y-4">
        {messages.map((msg, i) => (
          <div
            key={i}
            className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}
          >
            <div
              className={`max-w-[80%] rounded-lg px-4 py-2 ${
                msg.role === 'user'
                  ? 'bg-stone-800 text-white'
                  : 'bg-stone-100 text-stone-800'
              }`}
            >
              {msg.content}
            </div>
          </div>
        ))}
        {loading && (
          <div className="flex justify-start">
            <div className="rounded-lg bg-stone-100 px-4 py-2 text-stone-500">
              …
            </div>
          </div>
        )}
        <div ref={bottomRef} />
      </div>
      <form onSubmit={handleSubmit} className="border-t p-4">
        <div className="flex gap-2">
          <input
            type="text"
            value={input}
            onChange={(e) => setInput(e.target.value)}
            placeholder="Ваш вопрос..."
            className="flex-1 rounded border border-stone-300 px-3 py-2"
            disabled={loading}
          />
          <button
            type="submit"
            disabled={loading}
            className="rounded bg-stone-800 px-4 py-2 text-white hover:bg-stone-700 disabled:opacity-50"
          >
            Отправить
          </button>
        </div>
      </form>
    </div>
  );
}
