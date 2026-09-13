import { createPinia } from 'pinia'
import { createApp } from 'vue'

import App from './App.vue'
import { router } from './router'
import { startSyncLoop } from './offline/sync'
import './composables/useTheme' // applies the saved/default theme before first paint
import './chart-setup'
import './style.css'

const app = createApp(App)

app.use(createPinia())
app.use(router)

app.mount('#app')

startSyncLoop()
