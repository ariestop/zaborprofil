import { Skeleton } from './skeleton'

export function PageLoadingState() {
  return (
    <div className="space-y-4">
      <Skeleton className="h-7 w-64" />
      <Skeleton className="h-5 w-full" />
      <Skeleton className="h-5 w-4/5" />
      <Skeleton className="h-32 w-full" />
    </div>
  )
}
