import { Component, type ReactNode } from 'react'
import { ErrorState } from './error-state'

interface ErrorBoundaryState {
  hasError: boolean
}

interface ErrorBoundaryProps {
  children: ReactNode
}

export class ErrorBoundary extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
  public state: ErrorBoundaryState = {
    hasError: false,
  }

  public static getDerivedStateFromError(): ErrorBoundaryState {
    return { hasError: true }
  }

  public componentDidCatch(): void {}

  public render() {
    if (this.state.hasError) {
      return (
        <div className="mx-auto mt-6 max-w-3xl px-4">
          <ErrorState
            title="Произошла непредвиденная ошибка shell"
            description="Перезагрузите страницу. Если проблема повторяется, проверьте логи backend и frontend."
          />
        </div>
      )
    }

    return this.props.children
  }
}
