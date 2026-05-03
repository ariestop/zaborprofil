<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { currentRouteName, navigateTo, routes, type AdminRouteName } from '../router'
import { initializeAuthStore, useAuthStore } from '../stores/auth'
import AuditLogView from '../views/AuditLogView.vue'
import DashboardView from '../views/DashboardView.vue'
import MaintenanceView from '../views/MaintenanceView.vue'
import RedirectsView from '../views/RedirectsView.vue'
import SettingsView from '../views/SettingsView.vue'
import SystemHealthView from '../views/SystemHealthView.vue'

const props = defineProps<{
  userEmail: string
  logoutUrl: string
  logoutToken: string
}>()

initializeAuthStore({
  userEmail: props.userEmail,
  logoutUrl: props.logoutUrl,
  logoutToken: props.logoutToken,
})

const auth = useAuthStore()
const routeName = ref<AdminRouteName>(currentRouteName())

function syncRoute(): void {
  routeName.value = currentRouteName()
}

onMounted(() => {
  window.addEventListener('popstate', syncRoute)
})

onBeforeUnmount(() => {
  window.removeEventListener('popstate', syncRoute)
})

const pageTitle = computed(() => routes.find((route) => route.name === routeName.value)?.title ?? 'Панель управления')

function isActive(name: AdminRouteName): boolean {
  return routeName.value === name
}
</script>

<template>
  <div class="min-h-screen">
    <header class="border-b border-slate-200 bg-white">
      <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
        <div>
          <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">Zaborprofil CMS</p>
          <h1 class="text-xl font-bold text-slate-950">{{ pageTitle }}</h1>
        </div>
        <div class="flex items-center gap-4">
          <span class="text-sm text-slate-600">{{ auth.userEmail }}</span>
          <form method="post" :action="auth.logoutUrl">
            <input type="hidden" name="_csrf_token" :value="auth.logoutToken">
            <button
              type="submit"
              class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
              Выйти
            </button>
          </form>
        </div>
      </div>
    </header>

    <div class="mx-auto grid max-w-7xl grid-cols-[240px_1fr] gap-6 px-6 py-8">
      <aside class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
        <nav class="space-y-1">
          <button
            v-for="route in routes"
            :key="route.name"
            type="button"
            :class="[
              'flex w-full rounded-lg px-3 py-2 text-left text-sm font-medium transition',
              isActive(route.name) ? 'bg-emerald-50 text-emerald-800' : 'text-slate-700 hover:bg-slate-50',
            ]"
            @click="navigateTo(route.path)"
          >
            {{ route.title }}
          </button>
        </nav>
      </aside>

      <main>
        <DashboardView v-if="routeName === 'dashboard'" />
        <SettingsView v-else-if="routeName === 'settings'" />
        <RedirectsView v-else-if="routeName === 'redirects'" />
        <SystemHealthView v-else-if="routeName === 'systemHealth'" />
        <MaintenanceView v-else-if="routeName === 'maintenance'" />
        <AuditLogView v-else-if="routeName === 'auditLog'" />
      </main>
    </div>
  </div>
</template>
