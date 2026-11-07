import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { alExpirar } from './services/api'
import { useAuthStore } from './stores/auth'
import './assets/css/base.css'

const app = createApp(App)
app.use(createPinia())
app.use(router)

// Si la API rechaza el token (vencido o cuenta desactivada), se cierra la sesión.
// Solo se envía al login si la página actual exige sesión; en la tienda se sigue navegando.
alExpirar(() => {
  useAuthStore().cerrarSesion()
  const actual = router.currentRoute.value
  if (actual.matched.some((ruta) => ruta.meta.requiereSesion)) {
    router.push({ name: 'login', query: { redirect: actual.fullPath, expirada: '1' } })
  }
})

app.mount('#app')
