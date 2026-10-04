export const ADMIN_ROUTES = {
  login: '/admin/login',
  dashboard: '/admin/dashboard',
  pages: '/admin/pages',
  seo: '/admin/seo',
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
