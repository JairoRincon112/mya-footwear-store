<script setup>
// Inicio de sesión y registro de clientes.
// "Ingresar como" (desplegable): la persona elige si entra como cliente, vendedor o
// administrador. El rol viaja a la API, que solo entrega el token si la cuenta
// tiene ese rol. Al entrar, cada rol va directo a su zona (tienda o panel).
// El último rol elegido se recuerda en este navegador.
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { REGEX_EMAIL, almacenamiento } from '@/utils/formato'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const ROLES = [
  {
    valor: 'cliente',
    nombre: 'Cliente',
    icono: 'fa-bag-shopping',
    descripcion: 'Compra, revisa tus pedidos y tu cuenta',
    titulo: 'Tu próximo par te espera',
    beneficios: ['Compra rápida con tu carrito guardado', 'Sigue el estado de tus pedidos', 'Usa cupones y ofertas'],
    boton: 'Entrar a la tienda',
  },
  {
    valor: 'vendedor',
    nombre: 'Vendedor',
    icono: 'fa-store',
    descripcion: 'Gestiona pedidos, inventario y mensajes',
    titulo: 'Listo para vender',
    beneficios: ['Atiende y actualiza pedidos', 'Controla el stock por talla y color', 'Responde los mensajes de clientes'],
    boton: 'Entrar al panel',
  },
  {
    valor: 'admin',
    nombre: 'Administrador',
    icono: 'fa-user-shield',
    descripcion: 'Control total: productos, usuarios y reportes',
    titulo: 'Panel de control',
    beneficios: ['Administra productos, ofertas y cupones', 'Gestiona usuarios y roles', 'Revisa reportes de ventas'],
    boton: 'Entrar al panel',
  },
]
const CLAVE_ROL = 'mya_rol_login'

const modo = ref('login') // 'login' | 'registro'
const rolElegido = ref(rolInicial())
const desplegado = ref(false)
const indiceResaltado = ref(0)
const selector = ref(null)
const verPassword = ref(false)
const datosLogin = reactive({ email: '', password: '' })
const datosRegistro = reactive({ nombre: '', email: '', password: '', confirmacion: '' })
const errores = ref({})
const errorGeneral = ref('')
const enviando = ref(false)

const rol = computed(() => ROLES.find((r) => r.valor === rolElegido.value) ?? ROLES[0])
const sesionExpirada = computed(() => route.query.expirada === '1')

/** Rol con el que abre el login: si viene de una página del panel, personal; si no, el último usado. */
function rolInicial() {
  const destino = String(route.query.redirect ?? '')
  const guardado = almacenamiento.leer(CLAVE_ROL)
  if (destino.startsWith('/checkout') || destino.startsWith('/mi-')) return 'cliente'
  if (destino.startsWith('/panel') && guardado !== 'admin') return 'vendedor'
  return ROLES.some((r) => r.valor === guardado) ? guardado : 'cliente'
}

function elegirRol(valor) {
  rolElegido.value = valor
  almacenamiento.guardar(CLAVE_ROL, valor)
  desplegado.value = false
  errorGeneral.value = ''
  if (valor !== 'cliente') modo.value = 'login' // solo los clientes pueden crear cuenta
  nextTick(() => selector.value?.querySelector('.selector-boton')?.focus())
}

function abrirSelector() {
  desplegado.value = !desplegado.value
  indiceResaltado.value = ROLES.findIndex((r) => r.valor === rolElegido.value)
}

/** Teclado del desplegable: flechas para moverse, Enter para elegir, Escape para cerrar. */
function teclaSelector(evento) {
  if (!desplegado.value) {
    if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(evento.key)) {
      evento.preventDefault()
      abrirSelector()
    }
    return
  }
  if (evento.key === 'ArrowDown') indiceResaltado.value = (indiceResaltado.value + 1) % ROLES.length
  else if (evento.key === 'ArrowUp') indiceResaltado.value = (indiceResaltado.value + ROLES.length - 1) % ROLES.length
  else if (evento.key === 'Enter' || evento.key === ' ') elegirRol(ROLES[indiceResaltado.value].valor)
  else if (evento.key === 'Escape') desplegado.value = false
  else return
  evento.preventDefault()
}

function clicFuera(evento) {
  if (selector.value && !selector.value.contains(evento.target)) desplegado.value = false
}
onMounted(() => document.addEventListener('pointerdown', clicFuera))
onBeforeUnmount(() => document.removeEventListener('pointerdown', clicFuera))

function cambiarModo(nuevo) {
  modo.value = nuevo
  errores.value = {}
  errorGeneral.value = ''
}

/** Vuelve a la página que pidió el login (solo rutas internas permitidas para su rol) o a la de su rol. */
function irDespuesDelLogin() {
  const destino = typeof route.query.redirect === 'string' ? route.query.redirect : ''
  const interno = destino.startsWith('/') && !destino.startsWith('//') && !destino.startsWith('/login')
  const delPanel = destino.startsWith('/panel')
  if (interno && delPanel === auth.esPersonal) router.replace(destino)
  else router.replace(auth.rutaInicio)
}

async function enviar(accion, validaciones) {
  errorGeneral.value = ''
  errores.value = validaciones
  if (Object.keys(validaciones).length) return

  enviando.value = true
  try {
    await accion()
    irDespuesDelLogin()
  } catch (e) {
    errores.value = e.errores ?? {}
    errorGeneral.value = e.message
  } finally {
    enviando.value = false
  }
}

function iniciarSesion() {
  const validaciones = {}
  if (!REGEX_EMAIL.test(datosLogin.email.trim())) validaciones.email = 'Escribe un correo electrónico válido.'
  if (!datosLogin.password) validaciones.password = 'Escribe tu contraseña.'
  enviar(() => auth.login(datosLogin.email.trim(), datosLogin.password, rolElegido.value), validaciones)
}

function registrarse() {
  const validaciones = {}
  if (datosRegistro.nombre.trim().length < 2) validaciones.nombre = 'Escribe tu nombre.'
  if (!REGEX_EMAIL.test(datosRegistro.email.trim())) validaciones.email = 'Escribe un correo electrónico válido.'
  if (datosRegistro.password.length < 8) validaciones.password = 'La contraseña debe tener al menos 8 caracteres.'
  else if (datosRegistro.password.length > 72) validaciones.password = 'La contraseña es demasiado larga.'
  if (datosRegistro.confirmacion !== datosRegistro.password) validaciones.confirmacion = 'Las contraseñas no coinciden.'
  enviar(
    () => auth.registro({ nombre: datosRegistro.nombre.trim(), email: datosRegistro.email.trim(), password: datosRegistro.password }),
    validaciones,
  )
}
</script>

<template>
  <main class="pagina-login">
    <div class="contenedor">
      <!-- Panel lateral: cambia según el rol elegido -->
      <aside class="lateral" :data-rol="rol.valor">
        <span class="eco" aria-hidden="true">MyA</span>
        <Transition name="desliza" mode="out-in">
          <div :key="rol.valor" class="lateral-contenido">
            <span class="lateral-icono"><i class="fa-solid" :class="rol.icono"></i></span>
            <p class="antetitulo">{{ rol.nombre }}</p>
            <h2>{{ modo === 'registro' ? 'Únete a MyA' : rol.titulo }}<span class="punto">.</span></h2>
            <ul>
              <li v-for="b in rol.beneficios" :key="b"><i class="fa-solid fa-check"></i>{{ b }}</li>
            </ul>
          </div>
        </Transition>
      </aside>

      <!-- Formulario -->
      <section class="tarjeta">
        <h1>{{ modo === 'login' ? 'Iniciar sesión' : 'Crear cuenta' }}</h1>
        <p class="bajada">{{ modo === 'login' ? 'Elige cómo vas a ingresar y escribe tus datos.' : 'Crea tu cuenta de cliente en segundos.' }}</p>

        <p v-if="sesionExpirada && modo === 'login'" class="alerta alerta-error">Tu sesión expiró. Inicia sesión de nuevo.</p>

        <!-- Desplegable "Ingresar como" -->
        <div v-if="modo === 'login'" ref="selector" class="selector" :class="{ abierto: desplegado }">
          <span id="etiqueta-rol" class="selector-etiqueta">Ingresar como</span>
          <button
            type="button"
            class="selector-boton"
            aria-haspopup="listbox"
            :aria-expanded="desplegado"
            aria-labelledby="etiqueta-rol"
            aria-controls="lista-roles"
            @click="abrirSelector"
            @keydown="teclaSelector"
          >
            <span class="rol-icono"><i class="fa-solid" :class="rol.icono"></i></span>
            <span class="rol-texto"><strong>{{ rol.nombre }}</strong>{{ rol.descripcion }}</span>
            <i class="fa-solid fa-chevron-down chevron"></i>
          </button>

          <Transition name="despliega">
            <ul v-show="desplegado" id="lista-roles" class="selector-lista" role="listbox" aria-labelledby="etiqueta-rol" :aria-activedescendant="`rol-${ROLES[indiceResaltado].valor}`">
              <li
                v-for="(r, i) in ROLES"
                :id="`rol-${r.valor}`"
                :key="r.valor"
                role="option"
                class="opcion"
                :class="{ resaltada: i === indiceResaltado }"
                :aria-selected="r.valor === rolElegido"
                @click="elegirRol(r.valor)"
                @mouseenter="indiceResaltado = i"
              >
                <span class="rol-icono"><i class="fa-solid" :class="r.icono"></i></span>
                <span class="rol-texto"><strong>{{ r.nombre }}</strong>{{ r.descripcion }}</span>
                <i v-if="r.valor === rolElegido" class="fa-solid fa-check marca"></i>
              </li>
            </ul>
          </Transition>
        </div>

        <!-- Inicio de sesión -->
        <form v-if="modo === 'login'" class="formulario" novalidate @submit.prevent="iniciarSesion">
          <label class="campo" :class="{ 'con-error': errores.email }">
            Correo electrónico
            <span class="con-icono">
              <i class="fa-solid fa-envelope"></i>
              <input v-model="datosLogin.email" type="email" autocomplete="username" maxlength="150" placeholder="tucorreo@ejemplo.com">
            </span>
            <span v-if="errores.email" class="error-campo">{{ errores.email }}</span>
          </label>
          <label class="campo" :class="{ 'con-error': errores.password }">
            Contraseña
            <span class="con-icono">
              <i class="fa-solid fa-lock"></i>
              <input v-model="datosLogin.password" :type="verPassword ? 'text' : 'password'" autocomplete="current-password" maxlength="72" placeholder="Tu contraseña">
              <button type="button" class="ver" :aria-label="verPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'" @click="verPassword = !verPassword">
                <i class="fa-solid" :class="verPassword ? 'fa-eye-slash' : 'fa-eye'"></i>
              </button>
            </span>
            <span v-if="errores.password" class="error-campo">{{ errores.password }}</span>
          </label>
          <RouterLink :to="{ name: 'recuperar' }" class="olvido">¿Olvidaste tu contraseña?</RouterLink>

          <p v-if="errorGeneral" class="mensaje-error" role="alert"><i class="fa-solid fa-circle-exclamation"></i> {{ errorGeneral }}</p>

          <button type="submit" class="entrar" :disabled="enviando">
            <template v-if="enviando"><i class="fa-solid fa-spinner fa-spin"></i> Entrando...</template>
            <template v-else>{{ rol.boton }} <i class="fa-solid fa-arrow-right"></i></template>
          </button>

          <p v-if="rolElegido === 'cliente'" class="cambio-modo">
            ¿Aún no tienes cuenta? <button type="button" @click="cambiarModo('registro')">Créala gratis</button>
          </p>
          <p v-else class="cambio-modo nota"><i class="fa-solid fa-circle-info"></i> Las cuentas de personal las crea el administrador.</p>
        </form>

        <!-- Registro (solo clientes) -->
        <form v-else class="formulario" novalidate @submit.prevent="registrarse">
          <label class="campo" :class="{ 'con-error': errores.nombre }">
            Nombre
            <span class="con-icono">
              <i class="fa-solid fa-user"></i>
              <input v-model="datosRegistro.nombre" type="text" autocomplete="name" maxlength="120" placeholder="Tu nombre">
            </span>
            <span v-if="errores.nombre" class="error-campo">{{ errores.nombre }}</span>
          </label>
          <label class="campo" :class="{ 'con-error': errores.email }">
            Correo electrónico
            <span class="con-icono">
              <i class="fa-solid fa-envelope"></i>
              <input v-model="datosRegistro.email" type="email" autocomplete="username" maxlength="150" placeholder="tucorreo@ejemplo.com">
            </span>
            <span v-if="errores.email" class="error-campo">{{ errores.email }}</span>
          </label>
          <div class="fila">
            <label class="campo" :class="{ 'con-error': errores.password }">
              Contraseña
              <span class="con-icono">
                <i class="fa-solid fa-lock"></i>
                <input v-model="datosRegistro.password" :type="verPassword ? 'text' : 'password'" autocomplete="new-password" maxlength="72" placeholder="Mínimo 8">
              </span>
              <span v-if="errores.password" class="error-campo">{{ errores.password }}</span>
            </label>
            <label class="campo" :class="{ 'con-error': errores.confirmacion }">
              Confirmar
              <span class="con-icono">
                <i class="fa-solid fa-lock"></i>
                <input v-model="datosRegistro.confirmacion" :type="verPassword ? 'text' : 'password'" autocomplete="new-password" maxlength="72" placeholder="Repítela">
              </span>
              <span v-if="errores.confirmacion" class="error-campo">{{ errores.confirmacion }}</span>
            </label>
          </div>
          <label class="campo-check"><input v-model="verPassword" type="checkbox"> Mostrar contraseñas</label>

          <p v-if="errorGeneral" class="mensaje-error" role="alert"><i class="fa-solid fa-circle-exclamation"></i> {{ errorGeneral }}</p>

          <button type="submit" class="entrar" :disabled="enviando">
            <template v-if="enviando"><i class="fa-solid fa-spinner fa-spin"></i> Creando cuenta...</template>
            <template v-else>Crear cuenta de cliente <i class="fa-solid fa-arrow-right"></i></template>
          </button>
          <p class="cambio-modo">¿Ya tienes cuenta? <button type="button" @click="cambiarModo('login')">Inicia sesión</button></p>
        </form>
      </section>
    </div>
  </main>
</template>

<style scoped>
.pagina-login {
  min-height: 100vh;
  padding: calc(var(--alto-header) + 36px) 16px 56px;
  background: radial-gradient(110% 80% at 15% 10%, #3a1a05 0%, #161210 55%, #0d0b0a 100%);
}

.contenedor {
  display: grid;
  grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
  max-width: 940px;
  margin: 0 auto;
  overflow: hidden;
  border-radius: 28px;
  box-shadow: 0 40px 80px -24px rgba(0, 0, 0, 0.8);
}

.punto {
  color: var(--naranja);
}

/* ---------- Panel lateral ---------- */
.lateral {
  position: relative;
  display: flex;
  align-items: flex-end;
  padding: 40px 36px;
  overflow: hidden;
  color: #fff;
  background: linear-gradient(160deg, #1d1612 0%, #0f0c0a 100%);
  border-right: 1px solid rgba(255, 255, 255, 0.06);
}

/* Halo naranja que cambia de lugar según el rol */
.lateral::before {
  content: "";
  position: absolute;
  width: 380px;
  height: 380px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(255, 102, 0, 0.45) 0%, rgba(255, 102, 0, 0) 70%);
  transition: transform 0.6s cubic-bezier(0.2, 0.8, 0.2, 1);
  transform: translate(-30%, -60%);
  top: 0;
  left: 0;
}

.lateral[data-rol="vendedor"]::before { transform: translate(40%, -30%); }
.lateral[data-rol="admin"]::before { transform: translate(10%, 20%); }

.eco {
  position: absolute;
  right: -10px;
  top: 20px;
  font-size: 9rem;
  font-weight: 900;
  line-height: 1;
  letter-spacing: -0.05em;
  color: transparent;
  -webkit-text-stroke: 1.5px rgba(255, 255, 255, 0.08);
  pointer-events: none;
}

.lateral-contenido {
  position: relative;
}

.lateral-icono {
  display: grid;
  place-items: center;
  width: 58px;
  height: 58px;
  margin-bottom: 22px;
  font-size: 1.5rem;
  color: #000;
  background: var(--naranja);
  border-radius: 18px;
  box-shadow: 0 12px 30px -8px rgba(255, 102, 0, 0.8);
}

.antetitulo {
  margin-bottom: 6px;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.25em;
  text-transform: uppercase;
  color: var(--naranja);
}

.lateral h2 {
  margin-bottom: 22px;
  font-size: clamp(1.8rem, 3.2vw, 2.4rem);
  line-height: 1.05;
  color: #fff;
}

.lateral ul {
  display: grid;
  gap: 12px;
}

.lateral li {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 0.95rem;
  color: rgba(255, 255, 255, 0.75);
}

.lateral li i {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 24px;
  height: 24px;
  font-size: 0.7rem;
  color: var(--naranja);
  background: rgba(255, 102, 0, 0.12);
  border-radius: 50%;
}

.desliza-enter-active,
.desliza-leave-active {
  transition: opacity 0.25s, transform 0.25s;
}

.desliza-enter-from { opacity: 0; transform: translateY(14px); }
.desliza-leave-to { opacity: 0; transform: translateY(-14px); }

/* ---------- Tarjeta del formulario ---------- */
.tarjeta {
  display: grid;
  align-content: start;
  gap: 18px;
  padding: 40px 38px;
  background: #fff;
}

.tarjeta h1 {
  font-size: 2rem;
  line-height: 1;
}

.bajada {
  margin-top: -8px;
  font-size: 0.95rem;
  color: var(--texto-secundario);
}

/* ---------- Desplegable "Ingresar como" ---------- */
.selector {
  position: relative;
}

.selector-etiqueta {
  display: block;
  margin-bottom: 6px;
  font-size: 0.9rem;
  font-weight: 500;
}

.selector-boton,
.opcion {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 10px 14px 10px 10px;
  text-align: left;
}

.selector-boton {
  background: #f7f5f5;
  border: 1.5px solid #e2dede;
  border-radius: 16px;
  cursor: pointer;
  transition: border-color 0.2s, box-shadow 0.2s, background-color 0.2s;
}

.selector-boton:hover {
  border-color: #cfc8c8;
}

.abierto .selector-boton,
.selector-boton:focus-visible {
  background: #fff;
  border-color: var(--naranja);
  box-shadow: 0 0 0 4px rgba(255, 102, 0, 0.15);
  outline: none;
}

.rol-icono {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 42px;
  height: 42px;
  font-size: 1.05rem;
  color: #fff;
  background: #111;
  border-radius: 12px;
}

.selector-boton .rol-icono {
  color: #000;
  background: var(--naranja);
}

.rol-texto {
  display: grid;
  flex: 1;
  min-width: 0;
  font-size: 0.8rem;
  color: #777;
}

.rol-texto strong {
  font-size: 0.98rem;
  color: #111;
}

.chevron {
  color: #888;
  transition: transform 0.25s;
}

.abierto .chevron {
  transform: rotate(180deg);
}

.selector-lista {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  right: 0;
  z-index: 20;
  display: grid;
  gap: 4px;
  padding: 6px;
  background: #fff;
  border-radius: 18px;
  box-shadow: 0 24px 50px -14px rgba(0, 0, 0, 0.35), 0 0 0 1px rgba(0, 0, 0, 0.05);
}

.opcion {
  border-radius: 12px;
  cursor: pointer;
}

.opcion.resaltada {
  background: #fff3eb;
}

.opcion[aria-selected="true"] .rol-icono {
  color: #000;
  background: var(--naranja);
}

.marca {
  color: var(--naranja);
}

.despliega-enter-active,
.despliega-leave-active {
  transition: opacity 0.18s, transform 0.18s;
}

.despliega-enter-from,
.despliega-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

/* ---------- Campos ---------- */
.con-icono {
  position: relative;
  display: block;
}

.con-icono > i {
  position: absolute;
  left: 14px;
  top: 50%;
  color: #aaa;
  transform: translateY(-50%);
  pointer-events: none;
  transition: color 0.2s;
}

.campo .con-icono input {
  padding: 13px 44px 13px 42px;
  background: #f7f5f5;
  border: 1.5px solid #e2dede;
  border-radius: 14px;
  transition: border-color 0.2s, box-shadow 0.2s, background-color 0.2s;
}

.campo .con-icono input:focus {
  background: #fff;
  border-color: var(--naranja);
  box-shadow: 0 0 0 4px rgba(255, 102, 0, 0.15);
  outline: none;
}

.con-icono:focus-within > i {
  color: var(--naranja);
}

.ver {
  position: absolute;
  right: 6px;
  top: 50%;
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  color: #888;
  background: none;
  border: 0;
  border-radius: 10px;
  cursor: pointer;
  transform: translateY(-50%);
}

.ver:hover {
  color: #111;
  background: #eee;
}

.fila {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.olvido {
  justify-self: end;
  margin-top: -6px;
  font-size: 0.86rem;
  font-weight: 500;
  color: #c24e00;
}

.olvido:hover {
  text-decoration: underline;
}

.mensaje-error {
  padding: 10px 14px;
  font-size: 0.9rem;
  color: var(--error);
  background: #fdecea;
  border-radius: 12px;
}

.entrar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 15px;
  font-size: 1rem;
  font-weight: 700;
  color: #000;
  background: var(--naranja);
  border: 0;
  border-radius: 999px;
  box-shadow: 0 12px 26px -10px rgba(255, 102, 0, 0.8);
  cursor: pointer;
  transition: transform 0.2s, background-color 0.2s;
}

.entrar:hover:not(:disabled) {
  background: #ff8533;
  transform: translateY(-2px);
}

.entrar i {
  transition: transform 0.2s;
}

.entrar:hover:not(:disabled) .fa-arrow-right {
  transform: translateX(4px);
}

.entrar:disabled {
  opacity: 0.7;
  cursor: wait;
}

.cambio-modo {
  font-size: 0.9rem;
  text-align: center;
  color: var(--texto-secundario);
}

.cambio-modo button {
  font-weight: 700;
  color: #c24e00;
  background: none;
  border: 0;
  cursor: pointer;
}

.cambio-modo button:hover {
  text-decoration: underline;
}

.cambio-modo.nota {
  font-size: 0.82rem;
}

/* ---------- Celulares ---------- */
@media (max-width: 760px) {
  .contenedor {
    grid-template-columns: minmax(0, 1fr); /* minmax: el contenido ancho (tablas) no estira la página */
  }

  .lateral {
    padding: 26px 24px;
  }

  .lateral ul,
  .eco {
    display: none;
  }

  .lateral h2 {
    margin-bottom: 0;
    font-size: 1.6rem;
  }

  .lateral-icono {
    width: 46px;
    height: 46px;
    margin-bottom: 14px;
    font-size: 1.2rem;
  }

  .tarjeta {
    padding: 28px 22px;
  }
}

@media (max-width: 420px) {
  .fila {
    grid-template-columns: minmax(0, 1fr); /* minmax: el contenido ancho (tablas) no estira la página */
  }
}

@media (prefers-reduced-motion: reduce) {
  .pagina-login * {
    transition: none !important;
  }
}
</style>
