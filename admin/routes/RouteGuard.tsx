import type { ReactNode } from 'react'
import ForbiddenPage from '../pages/ForbiddenPage'
import { useAuthStore } from '../stores/auth'
import { findRoute, isRouteAllowed } from './route-config'

interface RouteGuardProps {
  /** Ключ маршрута из `route-config`: право берётся оттуда, чтобы меню и доступ не расходились. */
  routeKey: string
  children: ReactNode
}

export function RouteGuard({ routeKey, children }: RouteGuardProps) {
  const permissions = useAuthStore((state) => state.permissions)
  const route = findRoute(routeKey)

  if (route === undefined || !isRouteAllowed(route, permissions)) {
    return <ForbiddenPage />
  }

  return <>{children}</>
}
