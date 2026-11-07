// Rutas de la aplicación (Vue Router) y protección por rol.
import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import HomeView from '@/views/HomeView.vue'
import CatalogoView from '@/views/CatalogoView.vue'

const routes = [
  { path: '/', name: 'inicio', component: HomeView },
  { path: '/catalogo/:marca', name: 'catalogo', component: CatalogoView, props: true },
  { path: '/producto/:slug', name: 'producto', component: () => import('@/views/ProductoView.vue'), props: true },
  { path: '/contacto', name: 'contacto', component: () => import('@/views/ContactoView.vue') },
  { path: '/login', name: 'login', component: () => import('@/views/LoginView.vue'), meta: { soloInvitados: true } },
  { path: '/recuperar', name: 'recuperar', component: () => import('@/views/RecuperarView.vue'), meta: { soloInvitados: true } },
  { path: '/restablecer', name: 'restablecer', component: () => import('@/views/RestablecerView.vue') },
  { path: '/mi-cuenta', name: 'mi-cuenta', component: () => import('@/views/MiCuentaView.vue'), meta: { requiereSesion: true } },

  // Cliente: compra y pedidos
  {
    path: '/checkout',
    name: 'checkout',
    component: () => import('@/views/CheckoutView.vue'),
    meta: { requiereSesion: true, roles: ['cliente'] },
  },
  {
    path: '/mis-pedidos',
    name: 'mis-pedidos',
    component: () => import('@/views/MisPedidosView.vue'),
    meta: { requiereSesion: true, roles: ['cliente'] },
  },
  {
    path: '/mis-pedidos/:id(\\d+)',
    name: 'mi-pedido',
    component: () => import('@/views/MiPedidoView.vue'),
    props: true,
    meta: { requiereSesion: true, roles: ['cliente'] },
  },
  {
    // Vuelta del widget de Wompi: verifica la transacción ?id=...
    path: '/pago/resultado',
    name: 'pago-resultado',
    component: () => import('@/views/PagoResultadoView.vue'),
    meta: { requiereSesion: true, roles: ['cliente'] },
  },

  {
    path: '/panel',
    component: () => import('@/views/panel/PanelLayout.vue'),
    meta: { requiereSesion: true, roles: ['admin', 'vendedor'] },
    children: [
      { path: '', name: 'panel', component: () => import('@/views/panel/PanelInicio.vue') },
      { path: 'productos', name: 'panel-productos', component: () => import('@/views/panel/ProductosAdmin.vue') },
      {
        path: 'productos/nuevo',
        name: 'panel-producto-nuevo',
        component: () => import('@/views/panel/ProductoEditor.vue'),
        meta: { roles: ['admin'] },
      },
      {
        path: 'productos/:id(\\d+)',
        name: 'panel-producto',
        component: () => import('@/views/panel/ProductoEditor.vue'),
        props: true,
      },
      { path: 'pedidos', name: 'panel-pedidos', component: () => import('@/views/panel/PedidosAdmin.vue') },
      {
        path: 'pedidos/:id(\\d+)',
        name: 'panel-pedido',
        component: () => import('@/views/panel/PedidoAdmin.vue'),
        props: true,
      },
      { path: 'inventario', name: 'panel-inventario', component: () => import('@/views/panel/InventarioAdmin.vue') },
      { path: 'mensajes', name: 'panel-mensajes', component: () => import('@/views/panel/MensajesAdmin.vue') },
      { path: 'reportes', name: 'panel-reportes', component: () => import('@/views/panel/ReportesAdmin.vue') },
      {
        path: 'cupones',
        name: 'panel-cupones',
        component: () => import('@/views/panel/CuponesAdmin.vue'),
        meta: { roles: ['admin'] },
      },
      {
        path: 'usuarios',
        name: 'panel-usuarios',
        component: () => import('@/views/panel/UsuariosAdmin.vue'),
        meta: { roles: ['admin'] },
      },
    ],
  },
  { path: '/:ruta(.*)*', name: 'no-encontrado', component: () => import('@/views/NoEncontradoView.vue') },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior(to, from, posicionGuardada) {
    if (posicionGuardada) return posicionGuardada
    if (to.hash) return { el: to.hash, top: 110, behavior: 'smooth' }
    return { top: 0 }
  },
})

// Guardia global: valida la sesión y los roles antes de entrar a cada página.
router.beforeEach(async (to) => {
  const auth = useAuthStore()
  await auth.inicializar()

  if (to.matched.some((ruta) => ruta.meta.requiereSesion) && !auth.autenticado) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  // Cada nivel de la ruta puede exigir roles (ej. /panel y /panel/productos/nuevo).
  const rolPermitido = to.matched.every((ruta) => !ruta.meta.roles || ruta.meta.roles.includes(auth.rol))
  if (!rolPermitido) {
    return { name: 'inicio' }
  }

  if (to.meta.soloInvitados && auth.autenticado) {
    return auth.rutaInicio
  }

  return true
})

export default router
