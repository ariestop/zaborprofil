import { create } from 'zustand'

const STORAGE_KEY = 'admin.pageEditor.advancedMode'

function readStored(): boolean {
  try {
    return window.localStorage.getItem(STORAGE_KEY) === '1'
  } catch {
    return false
  }
}

function writeStored(value: boolean): void {
  try {
    window.localStorage.setItem(STORAGE_KEY, value ? '1' : '0')
  } catch {
    // Хранилище может быть недоступно (приватный режим): режим просто не запоминается.
  }
}

interface AdvancedModeState {
  enabled: boolean
  setEnabled: (enabled: boolean) => void
}

/** Расширенный режим: показывает JSON-редакторы блоков и JSON-LD. По умолчанию выключен. */
export const useAdvancedMode = create<AdvancedModeState>((set) => ({
  enabled: readStored(),
  setEnabled: (enabled) => {
    writeStored(enabled)
    set({ enabled })
  },
}))
