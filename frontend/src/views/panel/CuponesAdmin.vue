<script setup>
// Cupones de descuento (solo admin): crear, editar, activar y desactivar.
import { onMounted, reactive, ref } from 'vue'
import adminService from '@/services/adminService'
import { formatearFecha, formatearPrecio } from '@/utils/formato'

const cupones = ref([])
const cargando = ref(true)
const error = ref('')
const aviso = ref({ tipo: '', texto: '' })

const VACIO = {
  codigo: '', descripcion: '', tipo: 'porcentaje', valor: '', minimo_compra: '',
  usos_maximos: '', uno_por_cliente: false, inicio: '', fin: '', activo: true,
}
const formulario = reactive({ ...VACIO })
const editandoId = ref(null)
const errores = ref({})
const guardando = ref(false)

const aInputFecha = (valor) => (valor ? valor.slice(0, 16).replace(' ', 'T') : '')

/** Estado del cupón para mostrarlo: siempre con texto (no solo color). */
function estadoDe(cupon) {
  const ahora = new Date()
  const fecha = (v) => (v ? new Date(v.replace(' ', 'T')) : null)
  if (!cupon.activo) return { texto: 'Desactivado', clase: 'etiqueta-inactiva' }
  if (fecha(cupon.fin) && fecha(cupon.fin) < ahora) return { texto: 'Vencido', clase: 'etiqueta-inactiva' }
  if (fecha(cupon.inicio) && fecha(cupon.inicio) > ahora) return { texto: 'Programado', clase: 'etiqueta-programado' }
  if (cupon.usos_maximos !== null && cupon.usos >= cupon.usos_maximos) return { texto: 'Agotado', clase: 'etiqueta-agotada' }
  return { texto: 'Vigente', clase: 'etiqueta-activa' }
}

// El servidor envía el valor ya descrito por la estrategia de su tipo ("10 %", "$20.000").
const descripcionValor = (cupon) => cupon.valor_texto ?? formatearPrecio(cupon.valor)

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    cupones.value = await adminService.cupones()
  } catch (e) {
    error.value = e.message
  } finally {
    cargando.value = false
  }
}

function editar(cupon) {
  editandoId.value = cupon.id
  Object.assign(formulario, {
    codigo: cupon.codigo,
    descripcion: cupon.descripcion ?? '',
    tipo: cupon.tipo,
    valor: cupon.valor,
    minimo_compra: cupon.minimo_compra || '',
    usos_maximos: cupon.usos_maximos ?? '',
    uno_por_cliente: cupon.uno_por_cliente,
    inicio: aInputFecha(cupon.inicio),
    fin: aInputFecha(cupon.fin),
    activo: cupon.activo,
  })
  errores.value = {}
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function cancelar() {
  editandoId.value = null
  Object.assign(formulario, VACIO)
  errores.value = {}
}

function validar() {
  const nuevos = {}
  if (!/^[A-Za-z0-9_-]{3,30}$/.test(formulario.codigo.trim())) nuevos.codigo = 'Letras, números y guiones (3 a 30).'
  const valor = Number(formulario.valor)
  if (!formulario.valor || Number.isNaN(valor) || valor < 1) nuevos.valor = 'Escribe un valor mayor a 0.'
  else if (formulario.tipo === 'porcentaje' && valor > 90) nuevos.valor = 'Máximo 90 %.'
  if (formulario.minimo_compra !== '' && Number(formulario.minimo_compra) < 0) nuevos.minimo_compra = 'No puede ser negativo.'
  if (formulario.usos_maximos !== '' && (!Number.isInteger(Number(formulario.usos_maximos)) || Number(formulario.usos_maximos) < 1)) {
    nuevos.usos_maximos = 'Número entero mayor a 0.'
  }
  if (formulario.inicio && formulario.fin && formulario.inicio >= formulario.fin) nuevos.fin = 'Debe ser posterior al inicio.'
  errores.value = nuevos
  return Object.keys(nuevos).length === 0
}

async function guardar() {
  aviso.value = { tipo: '', texto: '' }
  if (!validar()) return

  const datos = {
    codigo: formulario.codigo.trim(),
    descripcion: formulario.descripcion.trim() || null,
    tipo: formulario.tipo,
    valor: Number(formulario.valor),
    minimo_compra: formulario.minimo_compra === '' ? 0 : Number(formulario.minimo_compra),
    usos_maximos: formulario.usos_maximos === '' ? null : Number(formulario.usos_maximos),
    uno_por_cliente: formulario.uno_por_cliente,
    inicio: formulario.inicio || null,
    fin: formulario.fin || null,
    activo: formulario.activo,
  }

  guardando.value = true
  try {
    const respuesta = editandoId.value
      ? await adminService.actualizarCupon(editandoId.value, datos)
      : await adminService.crearCupon(datos)
    aviso.value = { tipo: 'exito', texto: respuesta.mensaje }
    cancelar()
    cargar()
  } catch (e) {
    errores.value = e.errores
    aviso.value = { tipo: 'error', texto: e.message }
  } finally {
    guardando.value = false
  }
}

async function alternar(cupon) {
  try {
    const respuesta = await adminService.cambiarActivoCupon(cupon.id, !cupon.activo)
    aviso.value = { tipo: 'exito', texto: respuesta.mensaje }
    cargar()
  } catch (e) {
    aviso.value = { tipo: 'error', texto: e.message }
  }
}

onMounted(cargar)
</script>

<template>
  <section>
    <div class="panel-encabezado">
      <h2>Cupones de descuento</h2>
    </div>

    <div class="panel-tarjeta">
      <h3>{{ editandoId ? `Editar cupón ${formulario.codigo}` : 'Crear cupón' }}</h3>
      <form class="formulario" novalidate @submit.prevent="guardar">
        <div class="fila">
          <label class="campo" :class="{ 'con-error': errores.codigo }">
            Código
            <input v-model="formulario.codigo" type="text" maxlength="30" class="mayusculas" placeholder="EJ: VERANO20" autocomplete="off">
            <span v-if="errores.codigo" class="error-campo">{{ errores.codigo }}</span>
          </label>
          <label class="campo">
            Tipo
            <select v-model="formulario.tipo">
              <option value="porcentaje">Porcentaje (%)</option>
              <option value="valor">Valor fijo ($)</option>
            </select>
          </label>
          <label class="campo" :class="{ 'con-error': errores.valor }">
            {{ formulario.tipo === 'porcentaje' ? 'Porcentaje (1 a 90)' : 'Valor en pesos' }}
            <input v-model="formulario.valor" type="number" min="1" :max="formulario.tipo === 'porcentaje' ? 90 : undefined" step="1">
            <span v-if="errores.valor" class="error-campo">{{ errores.valor }}</span>
          </label>
          <label class="campo" :class="{ 'con-error': errores.minimo_compra }">
            Compra mínima (opcional)
            <input v-model="formulario.minimo_compra" type="number" min="0" step="1000" placeholder="0">
            <span v-if="errores.minimo_compra" class="error-campo">{{ errores.minimo_compra }}</span>
          </label>
          <label class="campo" :class="{ 'con-error': errores.usos_maximos }">
            Límite de usos (opcional)
            <input v-model="formulario.usos_maximos" type="number" min="1" step="1" placeholder="Sin límite">
            <span v-if="errores.usos_maximos" class="error-campo">{{ errores.usos_maximos }}</span>
          </label>
          <label class="campo" :class="{ 'con-error': errores.inicio }">
            Empieza (opcional)
            <input v-model="formulario.inicio" type="datetime-local">
            <span v-if="errores.inicio" class="error-campo">{{ errores.inicio }}</span>
          </label>
          <label class="campo" :class="{ 'con-error': errores.fin }">
            Termina (opcional)
            <input v-model="formulario.fin" type="datetime-local">
            <span v-if="errores.fin" class="error-campo">{{ errores.fin }}</span>
          </label>
        </div>
        <label class="campo">
          Descripción (la ve el cliente al aplicarlo)
          <input v-model="formulario.descripcion" type="text" maxlength="255" placeholder="Ej: 20 % en toda la tienda">
        </label>
        <div class="checks">
          <label class="campo-check"><input v-model="formulario.uno_por_cliente" type="checkbox"> Un solo uso por cliente</label>
          <label class="campo-check"><input v-model="formulario.activo" type="checkbox"> Activo</label>
        </div>
        <div class="botones">
          <button type="submit" class="boton" :disabled="guardando">{{ editandoId ? 'Guardar cambios' : 'Crear cupón' }}</button>
          <button v-if="editandoId" type="button" class="boton boton-secundario" @click="cancelar">Cancelar</button>
        </div>
        <p v-if="aviso.texto" class="alerta" :class="`alerta-${aviso.tipo}`" role="status">{{ aviso.texto }}</p>
      </form>
    </div>

    <div class="panel-tarjeta">
      <p v-if="error" class="alerta alerta-error">{{ error }}</p>
      <p v-if="cargando && !cupones.length" class="cargando"><i class="fa-solid fa-spinner fa-spin"></i>Cargando...</p>
      <p v-else-if="!cupones.length" class="cargando">Aún no hay cupones.</p>

      <div v-else class="tabla-contenedor">
        <table class="tabla">
          <thead>
            <tr>
              <th>Código</th>
              <th>Descuento</th>
              <th>Condiciones</th>
              <th>Vigencia</th>
              <th class="numero">Usos</th>
              <th class="numero">Descontado</th>
              <th>Estado</th>
              <th><span class="solo-lectores">Acciones</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="cupon in cupones" :key="cupon.id">
              <td>
                <strong class="codigo">{{ cupon.codigo }}</strong>
                <div class="texto-secundario">{{ cupon.descripcion }}</div>
              </td>
              <td>{{ descripcionValor(cupon) }}</td>
              <td class="texto-secundario">
                <template v-if="cupon.minimo_compra > 0">Desde {{ formatearPrecio(cupon.minimo_compra) }}<br></template>
                <template v-if="cupon.uno_por_cliente">1 por cliente</template>
                <template v-if="!cupon.minimo_compra && !cupon.uno_por_cliente">—</template>
              </td>
              <td class="texto-secundario">
                <template v-if="cupon.inicio">Desde {{ formatearFecha(cupon.inicio) }}<br></template>
                <template v-if="cupon.fin">Hasta {{ formatearFecha(cupon.fin) }}</template>
                <template v-if="!cupon.inicio && !cupon.fin">Sin fecha</template>
              </td>
              <td class="numero">{{ cupon.usos }}<template v-if="cupon.usos_maximos !== null"> / {{ cupon.usos_maximos }}</template></td>
              <td class="numero">{{ formatearPrecio(cupon.descuento_total) }}</td>
              <td><span class="etiqueta" :class="estadoDe(cupon).clase">{{ estadoDe(cupon).texto }}</span></td>
              <td class="acciones">
                <button type="button" class="boton boton-secundario boton-pequeno" @click="editar(cupon)">Editar</button>
                <button type="button" class="boton boton-secundario boton-pequeno" @click="alternar(cupon)">{{ cupon.activo ? 'Desactivar' : 'Activar' }}</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</template>

<style scoped>
.fila {
  display: grid;
  /* 250 px: el selector de fecha y hora de Chrome no cabe en columnas más angostas */
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 12px;
}

.mayusculas {
  text-transform: uppercase;
}

.mayusculas::placeholder {
  text-transform: none;
}

.checks,
.botones {
  display: flex;
  flex-wrap: wrap;
  gap: 18px;
}

.botones {
  gap: 10px;
}

.codigo {
  font-family: ui-monospace, Consolas, monospace;
  letter-spacing: 1px;
}

.etiqueta-programado {
  color: #0b4f8a;
  background: #e2effb;
}

.acciones {
  display: flex;
  gap: 6px;
  justify-content: flex-end;
  white-space: nowrap;
}
</style>
