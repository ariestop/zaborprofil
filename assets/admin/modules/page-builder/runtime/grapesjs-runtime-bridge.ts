import { isPageBuilderEnabled } from './feature-flags'

type GrapesJsModule = typeof import('grapesjs')
type GrapesJsInitOptions = Parameters<GrapesJsModule['default']['init']>[0]
export type GrapesJsEditor = ReturnType<GrapesJsModule['default']['init']>

interface GrapesJsRuntime {
  init: (options: GrapesJsInitOptions) => GrapesJsEditor
}

let runtimePromise: Promise<GrapesJsRuntime> | null = null

async function importGrapesJsRuntime(): Promise<GrapesJsRuntime> {
  const [{ default: grapesjs }] = await Promise.all([
    import('grapesjs'),
    import('grapesjs/dist/css/grapes.min.css'),
  ])

  return {
    init: (options) => grapesjs.init(options),
  }
}

export async function loadGrapesJsRuntime(): Promise<GrapesJsRuntime | null> {
  if (!isPageBuilderEnabled()) {
    return null
  }

  runtimePromise ??= importGrapesJsRuntime()
  return runtimePromise
}

export function warmupGrapesJsRuntimeOnIntent(): void {
  if (!isPageBuilderEnabled() || runtimePromise !== null) {
    return
  }

  void loadGrapesJsRuntime()
}
