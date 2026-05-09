import { useNavigate } from 'react-router-dom'
import { useCommandPalette } from '../app/providers/command-palette-provider'
import { adminRoutes } from '../routes/route-config'
import { Dialog } from '../shared/ui/dialog'
import { Button } from '../shared/ui/button'

export function CommandPaletteDialog() {
  const { isOpen, close } = useCommandPalette()
  const navigate = useNavigate()

  return (
    <Dialog
      open={isOpen}
      onOpenChange={(nextOpen) => {
        if (!nextOpen) {
          close()
        }
      }}
      title="Command Palette"
      description="Быстрый переход по разделам админки"
    >
      <div className="space-y-2">
        {adminRoutes
          .filter((route) => !route.path.includes('/:'))
          .map((route) => (
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
    </Dialog>
  )
}
