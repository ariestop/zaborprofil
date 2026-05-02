import { createApp } from 'vue'
import AdminShell from './components/AdminShell.vue'
import '../shared/styles/app.css'

const root = document.getElementById('admin-app')

if (root !== null) {
  createApp(AdminShell, {
    userEmail: root.dataset.userEmail ?? '',
    logoutUrl: root.dataset.logoutUrl ?? '/admin/logout',
    logoutToken: root.dataset.logoutToken ?? '',
  }).mount(root)
}
