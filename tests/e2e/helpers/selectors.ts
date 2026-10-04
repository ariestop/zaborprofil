export const ADMIN_ROUTES = {
  login: '/admin/login',
  dashboard: '/admin/dashboard',
  pages: '/admin/pages',
  media: '/admin/media',
} as const

export const ADMIN_LOGIN_SELECTORS = {
  usernameInput: '#username',
  passwordInput: '#password',
  submitButtonName: 'Войти',
} as const

export const PAGES_SELECTORS = {
  headingName: 'Редактор страниц',
} as const

export const BUILDER_SELECTORS = {
  openBuilderLinkName: 'Перейти в Builder',
  saveNowButtonName: 'Save now',
  previewButtonName: 'Preview',
  runtimeText: 'Structured Visual CMS Builder',
  blocksSectionTitle: 'Блоки страницы',
  previewSectionTitle: 'Page preview',
  previewPlaceholderText: 'Нажмите «Preview», чтобы получить HTML предпросмотра от backend.',
} as const

const ONE_PIXEL_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC'

export const MEDIA_SELECTORS = {
  headingName: 'Медиатека',
  pngBuffer: Buffer.from(ONE_PIXEL_PNG_BASE64, 'base64'),
} as const
