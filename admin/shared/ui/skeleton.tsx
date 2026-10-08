interface SkeletonProps {
  className?: string
}

export function Skeleton({ className = 'h-5 w-full' }: SkeletonProps) {
  return <div className={`animate-pulse rounded-sm bg-line dark:bg-slate-800 ${className}`} />
}
