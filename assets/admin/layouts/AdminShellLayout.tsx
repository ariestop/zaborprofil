import { Outlet } from 'react-router-dom'
import { SidebarNav } from './SidebarNav'
import { Topbar } from './Topbar'
import { Breadcrumbs } from './Breadcrumbs'
import { CommandPaletteDialog } from '../widgets/CommandPaletteDialog'
import { GlobalSearchDialog } from '../widgets/GlobalSearchDialog'

export function AdminShellLayout() {
  return (
    <div className="min-h-screen bg-slate-100 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
      <Topbar />
      <div className="mx-auto grid max-w-7xl grid-cols-[260px_1fr] gap-6 px-4 py-6 lg:px-6">
        <SidebarNav />
        <main className="space-y-4">
          <Breadcrumbs />
          <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <Outlet />
          </section>
        </main>
      </div>
      <CommandPaletteDialog />
      <GlobalSearchDialog />
    </div>
  )
}
