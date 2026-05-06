import { createElement, StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import AdminShell from './components/AdminShell'
import './components/styles/_variables.scss'
import './components/styles/_keyframe-animations.scss'
import '../shared/styles/app.css'
import { initializeAuthStore } from './stores/auth'

const root = document.getElementById('admin-app')

if (root !== null) {
  initializeAuthStore({
    userEmail: root.dataset.userEmail ?? '',
    logoutUrl: root.dataset.logoutUrl ?? '/admin/logout',
    logoutToken: root.dataset.logoutToken ?? '',
  })

  createRoot(root).render(
    createElement(
      StrictMode,
      null,
      createElement(AdminShell),
    ),
  )
}
