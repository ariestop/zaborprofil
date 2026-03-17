'use client';

import { useState } from 'react';
import { ChatWidget } from '@/app/chat/ChatWidget';

export function ChatButton() {
  const [open, setOpen] = useState(false);

  return (
    <>
      <button
        onClick={() => setOpen(true)}
        className="fixed bottom-6 right-6 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-stone-800 text-white shadow-lg hover:bg-stone-700"
        aria-label="Открыть чат"
      >
        ?
      </button>
      {open && (
        <div className="fixed inset-0 z-40 flex items-end justify-end p-4 sm:p-6">
          <div
            className="absolute inset-0 bg-black/20"
            onClick={() => setOpen(false)}
          />
          <div className="relative w-full max-w-md rounded-lg bg-white shadow-xl">
            <div className="flex items-center justify-between border-b p-4">
              <span className="font-semibold">Помощник</span>
              <button
                onClick={() => setOpen(false)}
                className="text-stone-500 hover:text-stone-700"
              >
                ×
              </button>
            </div>
            <ChatWidget />
          </div>
        </div>
      )}
    </>
  );
}
