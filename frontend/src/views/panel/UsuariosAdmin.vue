<script setup>
// Administración de usuarios (solo admin): crear, editar, activar y desactivar.
import { computed, onMounted, reactive, ref, watch } from 'vue'
import Paginacion from '@/components/Paginacion.vue'
import adminService from '@/services/adminService'
import { useAuthStore } from '@/stores/auth'
import { REGEX_EMAIL, REGEX_TELEFONO, formatearFecha } from '@/utils/formato'

const auth = useAuthStore()
const ROLES = { admin: 'Administrador', vendedor: 'Vendedor', cliente: 'Cliente' }

const filtros = reactive({ q: '', rol: '', activo: '', pagina: 1 })
const resultado = ref({ items: [], total: 0, paginas: 0 })
const cargando = ref(true)
const error = ref('')
const aviso = ref({ tipo: '', texto: '' })

const VACIO = { nombre: '', email: '', telefono: '', rol: 'vendedor', password: '' }
const formulario = reactive({ ...VACIO })
const editandoId = ref(null)
const errores = ref({})
const guardando = ref(false)
let temporizador = null

const editandoMiCuenta = computed(() => editandoId.value === auth.usuario?.id)

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    resultado.value = await adminService.usuarios(filtros)
  } catch (e) {
    error.value = e.message
  } finally {
    cargando.value = false
  }
}

function reiniciarYCargar() {
  filtros.pagina = 1
  cargar()
}

watch(() => [filtros.rol, filtros.activo], reiniciarYCargar)
watch(() => filtros.q, () => {
  clearTimeout(temporizador)
  temporizador = setTimeout(reiniciarYCargar, 350)
})

function irAPagina(pagina) {
  filtros.pagina = pagina
  cargar()
}

function editar(usuario) {
  editandoId.value = usuario.id
  Object.assign(formulario, { nombre: usuario.nombre, email: usuario.email, telefono: usuario.telefono ?? '', rol: usuario.rol, password: '' })
  errores.value = {}
  aviso.value = { tipo: '', texto: '' }
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function cancelarEdicion() {
  editandoId.value = null
  Object.assign(formulario, VACIO)
  errores.value = {}
}

function validar() {
  const nuevos = {}
  if (formulario.nombre.trim().length < 2) nuevos.nombre = 'Escribe el nombre.'
  if (!REGEX_EMAIL.test(formulario.email.trim())) nuevos.email = 'Escribe un correo válido.'
  if (formulario.telefono.trim() && !REGEX_TELEFONO.test(formulario.telefono.trim())) nuevos.telefono = 'Teléfono no válido.'
  if (!editandoId.value && formulario.password.length < 8) nuevos.password = 'Mínimo 8 caracteres.'
  if (editandoId.value && formulario.password && formulario.password.length < 8) nuevos.password = 'Mínimo 8 caracteres.'
  errores.value = nuevos
  return Object.keys(nuevos).length === 0
}

async function guardar() {
  aviso.value = { tipo: '', texto: '' }
  if (!validar()) return

  const datos = {
    nombre: formulario.nombre.trim(),
    email: formulario.email.trim(),
    telefono: formulario.telefono.trim() || null,
    rol: formulario.rol,
  }
  if (formulario.password) datos.password = formulario.password

  guardando.value = true
  try {
    const respuesta = editandoId.value
      ? await adminService.actualizarUsuario(editandoId.value, datos)
      : await adminService.crearUsuario(datos)
    aviso.value = { tipo: 'exito', texto: respuesta.mensaje }
    cancelarEdicion()
    cargar()
  } catch (e) {
    errores.value = e.errores
    aviso.value = { tipo: 'error', texto: e.message }
  } finally {
    guardando.value = false
  }
}

async function alternarActivo(usuario) {
  if (usuario.activo && !confirm(`¿Desactivar a ${usuario.nombre}? No podrá iniciar sesión.`)) return
  try {
    const respuesta = await adminService.cambiarActivo(usuario.id, !usuario.activo)
    aviso.value = { tipo: 'exito', texto: respuesta.mensaje }
    cargar()
  } catch (e) {
    aviso.value = { tipo: 'error', texto: e.errores?.activo ?? e.message }
  }
}

onMounted(cargar)
</script>

<template>
  <section>
    <div class="panel-encabezado">
      <h2>Usuarios</h2>
    </div>

    <div class="panel-tarjeta">
      <h3>{{ editandoId ? 'Editar usuario' : 'Crear usuario' }}</h3>
      <form class="formulario" novalidate @submit.prevent="guardar">
        <div class="fila">
          <label class="campo" :class="{ 'con-error': errores.nombre }">
            Nombre
            <input v-model="formulario.nombre" type="text" maxlength="120" autocomplete="off">
            <span v-if="errores.nombre" class="error-campo">{{ errores.nombre }}</span>
          </label>
          <label class="campo" :class="{ 'con-error': errores.email }">
            Correo
            <input v-model="formulario.email" type="email" maxlength="150" autocomplete="off">
            <span v-if="errores.email" class="error-campo">{{ errores.email }}</span>
          </label>
          <label class="campo" :class="{ 'con-error': errores.telefono }">
            Teléfono (opcional)
            <input v-model="formulario.telefono" type="tel" maxlength="20" autocomplete="off">
            <span v-if="errores.telefono" class="error-campo">{{ errores.telefono }}</span>
          </label>
          <label class="campo" :class="{ 'con-error': errores.rol }">
            Rol
            <select v-model="formulario.rol" :disabled="editandoMiCuenta">
              <option v-for="(texto, clave) in ROLES" :key="clave" :value="clave">{{ texto }}</option>
            </select>
            <span v-if="errores.rol" class="error-campo">{{ errores.rol }}</span>
          </label>
          <label class="campo" :class="{ 'con-error': errores.password }">
            {{ editandoId ? 'Nueva contraseña (opcional)' : 'Contraseña' }}
            <input v-model="formulario.password" type="password" maxlength="72" autocomplete="new-password">
            <span v-if="errores.password" class="error-campo">{{ errores.password }}</span>
          </label>
        </div>
        <div class="botones">
          <button type="submit" class="boton" :disabled="guardando">{{ editandoId ? 'Guardar cambios' : 'Crear usuario' }}</button>
          <button v-if="editandoId" type="button" class="boton boton-secundario" @click="cancelarEdicion">Cancelar</button>
        </div>
        <p v-if="aviso.texto" class="alerta" :class="`alerta-${aviso.tipo}`" role="status">{{ aviso.texto }}</p>
      </form>
    </div>

    <div class="panel-tarjeta">
      <form class="filtros" role="search" @submit.prevent>
        <label class="campo">
          Buscar
          <input v-model="filtros.q" type="search" placeholder="Nombre o correo" maxlength="100">
        </label>
        <label class="campo">
          Rol
          <select v-model="filtros.rol">
            <option value="">Todos</option>
            <option v-for="(texto, clave) in ROLES" :key="clave" :value="clave">{{ texto }}</option>
          </select>
        </label>
        <label class="campo">
          Estado
          <select v-model="filtros.activo">
            <option value="">Todos</option>
            <option value="1">Activos</option>
            <option value="0">Desactivados</option>
          </select>
        </label>
      </form>

      <p v-if="error" class="alerta alerta-error">{{ error }}</p>
      <p v-if="cargando && !resultado.items.length" class="cargando"><i class="fa-solid fa-spinner fa-spin"></i>Cargando...</p>

      <div v-else class="tabla-contenedor">
        <table class="tabla">
          <thead>
            <tr>
              <th>Nombre</th>
              <th>Rol</th>
              <th class="numero">Pedidos</th>
              <th>Registro</th>
              <th>Estado</th>
              <th><span class="solo-lectores">Acciones</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="usuario in resultado.items" :key="usuario.id">
              <td>
                <strong>{{ usuario.nombre }}</strong>
                <span v-if="usuario.id === auth.usuario?.id" class="texto-secundario"> (tú)</span>
                <div class="texto-secundario">{{ usuario.email }}</div>
              </td>
              <td>{{ ROLES[usuario.rol] }}</td>
              <td class="numero">{{ usuario.rol === 'cliente' ? usuario.total_pedidos : '—' }}</td>
              <td class="texto-secundario">{{ formatearFecha(usuario.creado_en) }}</td>
              <td>
                <span class="etiqueta" :class="usuario.activo ? 'etiqueta-activa' : 'etiqueta-inactiva'">{{ usuario.activo ? 'Activo' : 'Desactivado' }}</span>
              </td>
              <td class="acciones">
                <button type="button" class="boton boton-secundario boton-pequeno" @click="editar(usuario)">Editar</button>
                <button
                  type="button"
                  class="boton boton-secundario boton-pequeno"
                  :disabled="usuario.id === auth.usuario?.id"
                  @click="alternarActivo(usuario)"
                >
                  {{ usuario.activo ? 'Desactivar' : 'Activar' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <Paginacion :pagina="filtros.pagina" :paginas="resultado.paginas" :total="resultado.total" etiqueta="usuarios" @cambiar="irAPagina" />
    </div>
  </section>
</template>

<style scoped>
.fila {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
}

.botones {
  display: flex;
  gap: 10px;
}

.filtros {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr;
  gap: 12px;
  margin-bottom: 16px;
}

.acciones {
  display: flex;
  gap: 6px;
  justify-content: flex-end;
  white-space: nowrap;
}

@media (max-width: 640px) {
  .filtros {
    grid-template-columns: minmax(0, 1fr); /* minmax: el contenido ancho (tablas) no estira la página */
  }
}
</style>
