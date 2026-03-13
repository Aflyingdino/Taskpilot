import './assets/main.css'

import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import { checkSession } from './stores/authStore'
import { fetchProjects } from './stores/projectStore'

;(async () => {
  const loggedIn = await checkSession()
  if (loggedIn) await fetchProjects()

  const app = createApp(App)
  app.use(router)
  app.mount('#app')
})()
