import type { ReactElement, SVGProps } from 'react'

export type NavIconName =
  | 'dashboard'
  | 'leads'
  | 'pages'
  | 'media'
  | 'seo'
  | 'users'
  | 'settings'
  | 'audit'
  | 'server'
  | 'search'
  | 'sidebar'
  | 'menu'
  | 'close'
  | 'external'
  | 'moon'
  | 'sun'
  | 'logout'
  | 'chevron'
  | 'phone'
  | 'mail'
  | 'plus'
  | 'send'
  | 'calculator'
  | 'bell'
  | 'redirect'

const paths: Record<NavIconName, ReactElement> = {
  dashboard: (
    <>
      <rect x="3.5" y="3.5" width="7" height="7" rx="1.5" />
      <rect x="13.5" y="3.5" width="7" height="7" rx="1.5" />
      <rect x="3.5" y="13.5" width="7" height="7" rx="1.5" />
      <rect x="13.5" y="13.5" width="7" height="7" rx="1.5" />
    </>
  ),
  leads: (
    <>
      <path d="M4 13.5h4.5l1.5 2.5h4l1.5-2.5H20" />
      <path d="M6.5 5h11l2.5 8.5V19a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-5.5z" />
    </>
  ),
  pages: (
    <>
      <path d="M14 3.5H7a1.5 1.5 0 0 0-1.5 1.5v14A1.5 1.5 0 0 0 7 20.5h10a1.5 1.5 0 0 0 1.5-1.5V8z" />
      <path d="M14 3.5V8h4.5" />
      <path d="M9 13h6M9 16.5h4" />
    </>
  ),
  media: (
    <>
      <rect x="3.5" y="4.5" width="17" height="15" rx="2" />
      <circle cx="9" cy="10" r="1.8" />
      <path d="M20.5 15.5l-4.5-4.5-8.5 8.5" />
    </>
  ),
  seo: (
    <>
      <circle cx="12" cy="12" r="8.5" />
      <path d="M8.5 12.2l2.4 2.4 4.6-5" />
    </>
  ),
  users: <path d="M12 3.5l7 2.5v5.5c0 4.5-3 7.6-7 9-4-1.4-7-4.5-7-9V6z" />,
  settings: (
    <>
      <circle cx="12" cy="12" r="3" />
      <path d="M12 3.5v2.5M12 18v2.5M3.5 12H6M18 12h2.5M6 6l1.8 1.8M16.2 16.2L18 18M6 18l1.8-1.8M16.2 7.8L18 6" />
    </>
  ),
  audit: (
    <>
      <path d="M9 6.5h11M9 12h11M9 17.5h11" />
      <path d="M4.5 6.5h.01M4.5 12h.01M4.5 17.5h.01" />
    </>
  ),
  server: <path d="M3.5 12h4l2-5 4 10 2-5h5" />,
  search: (
    <>
      <circle cx="11" cy="11" r="6.5" />
      <path d="M20 20l-4.2-4.2" />
    </>
  ),
  sidebar: (
    <>
      <rect x="3.5" y="4.5" width="17" height="15" rx="2" />
      <path d="M9.5 4.5v15" />
    </>
  ),
  menu: <path d="M4 7h16M4 12h16M4 17h16" />,
  close: <path d="M6.5 6.5l11 11M17.5 6.5l-11 11" />,
  external: (
    <>
      <path d="M14 4.5h5.5V10" />
      <path d="M19.5 4.5L11 13" />
      <path d="M17.5 14v4.5a1 1 0 0 1-1 1h-11a1 1 0 0 1-1-1v-11a1 1 0 0 1 1-1H10" />
    </>
  ),
  moon: <path d="M19.5 14.5A8 8 0 0 1 9.5 4.5a8 8 0 1 0 10 10z" />,
  sun: (
    <>
      <circle cx="12" cy="12" r="4" />
      <path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.3 5.3l1.4 1.4M17.3 17.3l1.4 1.4M5.3 18.7l1.4-1.4M17.3 6.7l1.4-1.4" />
    </>
  ),
  logout: (
    <>
      <path d="M14 4.5h4.5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H14" />
      <path d="M10 16.5L5.5 12 10 7.5" />
      <path d="M5.5 12H15" />
    </>
  ),
  chevron: <path d="M6 9.5l6 6 6-6" />,
  phone: (
    <path d="M5.5 4h3.5l1.8 4.5-2.3 1.4a10.5 10.5 0 0 0 5.6 5.6l1.4-2.3L20 15v3.5a2 2 0 0 1-2 2A14.5 14.5 0 0 1 3.5 6a2 2 0 0 1 2-2z" />
  ),
  plus: <path d="M12 5v14M5 12h14" />,
  bell: (
    <>
      <path d="M6.5 16.5V11a5.5 5.5 0 0 1 11 0v5.5l1.5 1.5H5z" />
      <path d="M10 20.5h4" />
    </>
  ),
  redirect: (
    <>
      <path d="M4.5 19.5v-7a4 4 0 0 1 4-4h11" />
      <path d="M15.5 4.5l4 4-4 4" />
    </>
  ),
  send: (
    <>
      <path d="M20.5 3.5L3.5 10.5l7 2.5 2.5 7z" />
      <path d="M10.5 13L20.5 3.5" />
    </>
  ),
  calculator: (
    <>
      <rect x="5" y="3.5" width="14" height="17" rx="2" />
      <path d="M8.5 7.5h7" />
      <path d="M8.5 12h.01M12 12h.01M15.5 12h.01M8.5 16h.01M12 16h.01M15.5 16h.01" />
    </>
  ),
  mail: (
    <>
      <rect x="3.5" y="5.5" width="17" height="13" rx="2" />
      <path d="M4 7l8 6 8-6" />
    </>
  ),
}

interface NavIconProps extends Omit<SVGProps<SVGSVGElement>, 'name'> {
  name: NavIconName
  size?: number
}

/** Лёгкие контурные иконки админки без внешних зависимостей. */
export function NavIcon({ name, size = 18, ...props }: NavIconProps) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth={1.8}
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      focusable="false"
      {...props}
    >
      {paths[name]}
    </svg>
  )
}
