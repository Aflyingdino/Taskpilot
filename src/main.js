import './assets/main.css'

import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import { api } from './utils/api'
import { checkSession } from './stores/authStore'
import { fetchProjects } from './stores/projectStore'

;(async () => {
  await api.initSecurity().catch(() => {})
  const loggedIn = await checkSession()
  if (loggedIn) await fetchProjects()

  const app = createApp(App)
  app.use(router)
  app.mount('#app')
})()
