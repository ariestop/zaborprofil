export const ADMIN_ROUTES = {
  login: '/admin/login',
  dashboard: '/admin/dashboard',
  pages: '/admin/pages',
} as const

export const ADMIN_LOGIN_SELECTORS = {
  usernameInput: '#username',
  passwordInput: '#password',
  submitButtonName: 'Войти',
} as const

export const BUILDER_SELECTORS = {
  openBuilderLinkName: 'Перейти в Builder',
  saveNowButtonName: 'Save now',
  previewLinkName: 'Preview',
  dndSectionTitle: 'DnD blocks (backend reorder)',
  richTextAriaLabel: 'Main content area, start typing to enter text.',
  saveRichTextButtonName: 'Сохранить rich text в block settings',
  runtimeText: 'Builder snapshot editor',
} as const
