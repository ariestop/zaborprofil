import PagesPage from './PagesPage'

/** `/admin/pages/new`: окно «Новая страница» поверх списка страниц, закрытие возвращает к списку. */
export default function PageCreatePage() {
  return <PagesPage creating />
}
