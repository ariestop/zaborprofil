import { Link } from 'react-router-dom'
import { useDocumentTitle } from '../shared/hooks/use-document-title'

export default function ForbiddenPage() {
    useDocumentTitle('Нет доступа — ЗаборПрофиль')

    return (
        <div role="alert" className="mx-auto max-w-lg py-16 text-center">
            <p className="text-5xl font-bold text-slate-300 dark:text-slate-700" aria-hidden="true">
                403
            </p>
            <h2 className="mt-4 text-xl font-semibold">Нет доступа к этому разделу</h2>
            <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">
                Ваша роль не позволяет открыть эту страницу. Если доступ нужен для работы,
                обратитесь к администратору сайта.
            </p>
            <Link
                to="/admin/dashboard"
                className="mt-6 inline-flex h-10 items-center rounded-lg border border-slate-300 px-4 text-sm font-medium text-slate-700 hover:bg-slate-50 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 dark:border-slate-700 dark:text-slate-100 dark:hover:bg-slate-800"
            >
                На сводку
            </Link>
        </div>
    )
}
