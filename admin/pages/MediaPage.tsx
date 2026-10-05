import { MediaLibrary } from '../features/media/MediaLibrary'
import { PageHeader } from '../shared/ui'

export default function MediaPage() {
  return (
    <div>
      <PageHeader
        title="Медиатека"
        description="Загружайте изображения и PDF, добавляйте alt и title, выбирайте файлы в блоках страниц и SEO-полях."
      />
      <MediaLibrary mode="manage" />
    </div>
  )
}
