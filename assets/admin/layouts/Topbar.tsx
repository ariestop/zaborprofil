import { useAuthStore } from '../stores/auth'
import { useTheme } from '../app/providers/theme-provider'
import { useCommandPalette } from '../app/providers/command-palette-provider'
import { useGlobalSearch } from '../app/providers/global-search-provider'
import { Button } from '../shared/ui/button'

export function Topbar() {
  const userEmail = useAuthStore((state) => state.userEmail)
  const logoutUrl = useAuthStore((state) => state.logoutUrl)
  const logoutToken = useAuthStore((state) => state.logoutToken)
  const { theme, toggleTheme } = useTheme()
  const { open: openCommandPalette } = useCommandPalette()
  const { open: openGlobalSearch } = useGlobalSearch()

  return (
    <header className="border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
      <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 lg:px-6">
        <div className="min-w-0">
          <p className="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-400">Zaborprofil CMS</p>
          <h1 className="truncate text-lg font-semibold">Admin Shell</h1>
        </div>

        <div className="flex items-center gap-2">
          <Button type="button" variant="outline" size="sm" onClick={openGlobalSearch}>
            Поиск
          </Button>
          <Button type="button" variant="outline" size="sm" onClick={openCommandPalette}>
            Команды
          </Button>
          <Button type="button" variant="ghost" size="sm" onClick={toggleTheme}>
            {theme === 'dark' ? 'Светлая тема' : 'Тёмная тема'}
          </Button>
          <span className="hidden text-sm text-slate-600 dark:text-slate-300 md:inline">{userEmail}</span>
          <form method="post" action={logoutUrl}>
            <input type="hidden" name="_csrf_token" value={logoutToken} />
            <Button type="submit" size="sm">Выйти</Button>
          </form>
        </div>
      </div>
    </header>
  )
}
