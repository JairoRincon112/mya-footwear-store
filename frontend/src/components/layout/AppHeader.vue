<script setup>
// Menú principal fijo (igual al diseño original), con las marcas cargadas desde la API
// y las opciones de sesión según el rol.
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useCatalogoStore } from '@/stores/catalogo'
import { useCarritoStore } from '@/stores/carrito'
import logo from '@/assets/img/logo.png'

const auth = useAuthStore()
const catalogo = useCatalogoStore()
const carrito = useCarritoStore()
const router = useRouter()

// Mientras cargan las marcas se muestran las del diseño original.
const MARCAS_INICIALES = [
  { slug: 'adidas', nombre: 'Adidas' },
  { slug: 'nike', nombre: 'Nike' },
  { slug: 'puma', nombre: 'Puma' },
]
const marcas = computed(() => (catalogo.marcas.length ? catalogo.marcas : MARCAS_INICIALES))
const primerNombre = computed(() => auth.usuario?.nombre.split(/\s+/)[0] ?? '')

// El menú es fijo y su alto cambia (celular, sesión iniciada, más marcas):
// se mide y se guarda en --alto-header para que el contenido empiece justo debajo,
// sin franjas vacías ni partes tapadas.
const cabecera = ref(null)
let observador = null

onMounted(() => {
  catalogo.cargar().catch(() => {})
  if (window.ResizeObserver && cabecera.value) {
    observador = new ResizeObserver(([entrada]) => {
      const alto = Math.ceil(entrada.target.getBoundingClientRect().height)
      document.documentElement.style.setProperty('--alto-header', `${alto}px`)
    })
    observador.observe(cabecera.value)
  }
})
onBeforeUnmount(() => observador?.disconnect())

function salir() {
  auth.cerrarSesion()
  carrito.vaciar() // El carrito no pasa al siguiente usuario del equipo.
  router.push({ name: 'inicio' })
}
</script>

<template>
  <header ref="cabecera" class="menu-principal">
    <RouterLink to="/" class="logo">
      <img :src="logo" alt="">
      <h1 class="clase_empresa">MyA FOOTWEAR STORE</h1>
    </RouterLink>

    <nav :class="{ 'con-sesion': auth.autenticado }" aria-label="Menú principal">
      <RouterLink to="/" class="nav_link">HOME</RouterLink>
      {{ ' ' }}
      <template v-for="marca in marcas" :key="marca.slug">
        <RouterLink :to="{ name: 'catalogo', params: { marca: marca.slug } }" class="nav_link">
          {{ marca.nombre.toUpperCase() }}
        </RouterLink>
        {{ ' ' }}
      </template>
      <RouterLink to="/contacto" class="nav_link">CONTACTO</RouterLink>
      {{ ' ' }}

      <template v-if="auth.autenticado">
        <RouterLink v-if="auth.esPersonal" :to="{ name: 'panel' }" class="nav_link">PANEL</RouterLink>
        <RouterLink v-else :to="{ name: 'mis-pedidos' }" class="nav_link">MIS PEDIDOS</RouterLink>
        {{ ' ' }}
        <RouterLink :to="{ name: 'mi-cuenta' }" class="saludo" title="Mi cuenta">Hola, {{ primerNombre }}</RouterLink>
        {{ ' ' }}
        <a href="#" class="nav_link" @click.prevent="salir">SALIR</a>
      </template>
      <RouterLink v-else :to="{ name: 'login' }" class="nav_link">INICIAR SESIÓN</RouterLink>
    </nav>
  </header>
</template>
