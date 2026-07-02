import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { useThemeStore } from './stores/theme'
import './style.css'

const pinia = createPinia()
const app = createApp(App)

app.use(pinia).use(router)

const theme = useThemeStore(pinia)
theme.init()

app.mount('#app')
