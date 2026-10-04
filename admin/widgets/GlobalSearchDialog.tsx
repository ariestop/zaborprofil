import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useGlobalSearch } from '../app/providers/global-search-provider'
import { adminRoutes } from '../routes/route-config'
import { Dialog } from '../shared/ui/dialog'
import { Input } from '../shared/ui/input'
import { Button } from '../shared/ui/button'

export function GlobalSearchDialog() {
  const navigate = useNavigate()
  const [query, setQuery] = useState('')
  const { isOpen, close } = useGlobalSearch()

  const results = useMemo(() => {
    const normalizedQuery = query.trim().toLowerCase()
    const navigable = adminRoutes.filter((route) => !route.path.includes('/:'))
    if (normalizedQuery === '') {
      return navigable
    }

    return navigable.filter((route) => route.title.toLowerCase().includes(normalizedQuery))
  }, [query])

  return (
    <Dialog
      open={isOpen}
      onOpenChange={(nextOpen) => {
        if (!nextOpen) {
          close()
          setQuery('')
        }
      }}
      title="Global Search"
      description="Foundation для поиска по разделам и сущностям"
    >
      <div className="space-y-3">
        <Input
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          placeholder="Найти раздел..."
          autoFocus
        />
        <div className="max-h-72 space-y-2 overflow-y-auto">
          {results.map((route) => (
            <Button
              key={route.key}
              type="button"
              variant="outline"
              className="w-full justify-start"
              onClick={() => {
                navigate(route.path)
                close()
              }}
            >
              {route.title}
            </Button>
          ))}
        </div>
      </div>
    </Dialog>
  )
}
