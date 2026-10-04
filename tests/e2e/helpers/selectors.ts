export const ADMIN_ROUTES = {
  login: '/admin/login',
  dashboard: '/admin/dashboard',
  pages: '/admin/pages',
  seo: '/admin/seo',
  media: '/admin/media',
} as const

export const ADMIN_LOGIN_SELECTORS = {
  usernameInput: '#username',
  passwordInput: '#password',
  submitButtonName: 'Войти',
} as const

export const PAGES_SELECTORS = {
  headingName: 'Страницы',
  createLinkName: 'Создать страницу',
} as const

export const PAGE_EDITOR_SELECTORS = {
  tabContent: 'Контент и блоки',
  tabSeo: 'SEO',
  tabSettings: 'Настройки',
  tabRevisions: 'Ревизии',
  saveButtonName: 'Сохранить',
  previewButtonName: 'Предпросмотр',
  publishButtonName: 'Опубликовать',
  leaveDialogTitle: 'Есть несохранённые изменения',
  createTitleLabel: 'Название страницы',
  createSubmitName: 'Создать страницу',
  seoTitleLabel: 'SEO-заголовок (title)',
  seoDescriptionLabel: 'Описание (meta description)',
} as const

export const BUILDER_SELECTORS = {
  blocksSectionTitle: 'Блоки страницы',
  previewButtonName: 'Быстрый предпросмотр блоков',
  previewSectionTitle: 'Быстрый предпросмотр блоков',
  previewPlaceholderText: 'Нажмите «Быстрый предпросмотр блоков», чтобы получить HTML от backend без сохранения страницы.',
} as const

export const SEO_SELECTORS = {
  headingName: 'SEO-панель',
  addRedirectButtonName: 'Добавить редирект',
  sourceInputLabel: 'Старый URL',
  targetInputLabel: 'Новый URL',
  saveButtonName: 'Сохранить',
  redirectSearchLabel: 'Поиск редиректов',
  robotsTabName: 'robots.txt',
  robotsEditorLabel: 'Содержимое robots.txt',
  robotsIssuesLabel: 'Замечания к robots.txt',
  notFoundTabName: 'Журнал 404',
  notFoundSearchLabel: 'Поиск по журналу 404',
  createRedirectButtonName: 'Создать редирект',
  collapseAssetWidgetName: 'Свернуть настройки сборки',
} as const

const ONE_PIXEL_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC'

export const MEDIA_SELECTORS = {
  headingName: 'Медиатека',
  pngBuffer: Buffer.from(ONE_PIXEL_PNG_BASE64, 'base64'),
} as const
