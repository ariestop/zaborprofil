import { buildSnippetUrl, resolveSeoTitle, truncateForSnippet } from './snippet'

const TITLE_DISPLAY_LIMIT = 60
const DESCRIPTION_DISPLAY_LIMIT = 155

interface SnippetPreviewProps {
  metaTitle: string
  title: string
  h1: string
  path: string
  canonicalUrl: string
  metaDescription: string
  titleTemplate: string
  siteName: string
  origin?: string
}

export function SnippetPreview({
  metaTitle,
  title,
  h1,
  path,
  canonicalUrl,
  metaDescription,
  titleTemplate,
  siteName,
  origin = window.location.origin,
}: SnippetPreviewProps) {
  const seoTitle = resolveSeoTitle({ metaTitle, title, h1, template: titleTemplate, siteName })
  const url = buildSnippetUrl(origin, path, canonicalUrl)
  const description = metaDescription.trim()
  const titleSource = metaTitle.trim() !== '' ? 'SEO-title' : titleTemplate.trim() !== '' ? 'шаблон по умолчанию' : 'название страницы'

  return (
    <figure className="rounded-lg border border-line bg-white p-4" aria-label="Превью сниппета в поиске">
      <figcaption className="mb-3 text-xs font-medium uppercase tracking-wide text-graphite">
        Превью в поиске (источник заголовка: {titleSource})
      </figcaption>
      <div className="max-w-[600px] font-sans" data-testid="snippet-preview">
        <div className="truncate text-sm text-ink" data-testid="snippet-url">
          {url.host}
          {url.crumbs.map((crumb) => (
            <span key={crumb}> › {crumb}</span>
          ))}
        </div>
        <div className="mt-1 text-xl leading-snug text-[#1a0dab]" data-testid="snippet-title">
          {truncateForSnippet(seoTitle, TITLE_DISPLAY_LIMIT) || 'Заголовок страницы'}
        </div>
        <div className="mt-1 text-sm leading-snug text-graphite" data-testid="snippet-description">
          {description === ''
            ? 'Описание не задано — поисковик подставит фрагмент текста страницы.'
            : truncateForSnippet(description, DESCRIPTION_DISPLAY_LIMIT)}
        </div>
      </div>
    </figure>
  )
}
