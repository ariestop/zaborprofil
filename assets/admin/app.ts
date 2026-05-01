import { createApp } from 'vue'
import AdminShell from './components/AdminShell.vue'
import '../site/styles/app.css'

const root = document.getElementById('admin-app')

if (root !== null) {
  createApp(AdminShell).mount(root)
}
