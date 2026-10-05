import type { ReactElement } from 'react'
import type { BlockIconName } from './block-kinds'

const paths: Record<BlockIconName, ReactElement> = {
  hero: (
    <>
      <rect x="3.5" y="4.5" width="17" height="15" rx="2" />
      <path d="M7 10h7M7 13.5h4" />
    </>
  ),
  slider: (
    <>
      <rect x="5.5" y="4.5" width="13" height="11" rx="1.5" />
      <path d="M2.5 8v4M21.5 8v4M10 19.5h.01M12 19.5h.01M14 19.5h.01" />
    </>
  ),
  text: <path d="M5 6.5h14M5 10.5h14M5 14.5h9M5 18.5h11" />,
  faq: (
    <>
      <circle cx="12" cy="12" r="8.5" />
      <path d="M9.6 9.5a2.5 2.5 0 0 1 4.8 1c0 1.7-2.4 2-2.4 3.5M12 17h.01" />
    </>
  ),
  features: (
    <>
      <path d="M10 6.5h10M10 12h10M10 17.5h10" />
      <path d="M4 6.5l1.2 1.2L7.5 5.3M4 12l1.2 1.2L7.5 10.8M4 17.5l1.2 1.2 2.3-2.4" />
    </>
  ),
  steps: (
    <>
      <circle cx="5.5" cy="12" r="2.5" />
      <circle cx="18.5" cy="12" r="2.5" />
      <path d="M8 12h8" />
    </>
  ),
  gallery: (
    <>
      <rect x="3.5" y="4.5" width="17" height="15" rx="2" />
      <circle cx="9" cy="10" r="1.8" />
      <path d="M20.5 15.5l-4.5-4.5-8.5 8.5" />
    </>
  ),
  portfolio: (
    <>
      <rect x="3.5" y="7.5" width="17" height="12" rx="2" />
      <path d="M9 7.5V5.5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2M3.5 12.5h17" />
    </>
  ),
  prices: (
    <>
      <rect x="3.5" y="4.5" width="17" height="15" rx="2" />
      <path d="M3.5 9.5h17M3.5 14.5h17M10 9.5v10" />
    </>
  ),
  cta: <path d="M4 12h11M11 7l5 5-5 5M20 5v14" />,
  form: (
    <>
      <rect x="4" y="4.5" width="16" height="15" rx="2" />
      <path d="M7.5 9h9M7.5 12.5h9M7.5 16h5" />
    </>
  ),
  generic: (
    <>
      <rect x="4.5" y="4.5" width="15" height="15" rx="2.5" />
      <path d="M8.5 10h7M8.5 14h4" />
    </>
  ),
}

export function BlockIcon({ name, size = 18 }: { name: BlockIconName, size?: number }) {
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
    >
      {paths[name]}
    </svg>
  )
}
