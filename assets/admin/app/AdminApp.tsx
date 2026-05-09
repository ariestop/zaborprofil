import { AppProviders } from './providers/AppProviders'
import { AdminRouter } from '../routes'

export function AdminApp() {
  return (
    <AppProviders>
      <AdminRouter />
    </AppProviders>
  )
}
