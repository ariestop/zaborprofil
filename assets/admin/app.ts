import { createApp } from 'vue'
import AdminShell from './components/AdminShell.vue'
import '../shared/styles/app.css'

const root = document.getElementById('admin-app')

if (root !== null) {
  createApp(AdminShell).mount(root)
}
