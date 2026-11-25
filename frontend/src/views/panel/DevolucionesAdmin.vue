<script setup>
// Devoluciones para el personal: filtros en semáforo por estado, tipo de devolución
// y acciones según el estado (aprobar, rechazar con motivo, marcar como recibida).
import MenuAcciones from '@/components/MenuAcciones.vue'
import { onMounted, reactive, ref, watch } from 'vue'
import EstadoDevolucion from '@/components/EstadoDevolucion.vue'
import Paginacion from '@/components/Paginacion.vue'
import devolucionService from '@/services/devolucionService'
import { ESTADOS_DEVOLUCION, TIPOS_DEVOLUCION, formatearFecha } from '@/utils/formato'

const filtros = reactive({ estado: '', tipo: '', q: '', pagina: 1 })
const resultado = ref({ items: [], total: 0, paginas: 0, por_revisar: 0 })
const cargando = ref(true)
const error = ref('')
const aviso = ref({ tipo: '', texto: '' })
const gestion = reactive({ id: null, estado: '', respuesta: '', error: '', enviando: false })
let temporizador = null

const ACCIONES = {
  aprobada: { texto: 'Aprobar', icono: 'fa-thumbs-up', clase: '' },
  rechazada: { texto: 'Rechazar', icono: 'fa-ban', clase: 'boton-secundario' },
  recibida: { texto: 'Marcar como recibida', icono: 'fa-box-open', clase: '' },
}

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    resultado.value = await devolucionService.listar(filtros)
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

watch(() => [filtros.estado, filtros.tipo], reiniciarYCargar)
watch(() => filtros.q, () => {
  clearTimeout(temporizador)
  temporizador = setTimeout(reiniciarYCargar, 350)
})

function irAPagina(pagina) {
  filtros.pagina = pagina
  cargar()
}

function abrirGestion(devolucion, estado) {
  Object.assign(gestion, { id: devolucion.id, estado, respuesta: '', error: '', enviando: false })
}

async function confirmarGestion() {
  if (gestion.estado === 'rechazada' && gestion.respuesta.trim() === '') {
    gestion.error = 'Explica al cliente por qué se rechaza la devolución.'
    return
  }
  gestion.enviando = true
  try {
    const respuesta = await devolucionService.cambiarEstado(gestion.id, gestion.estado, gestion.respuesta.trim())
    aviso.value = { tipo: 'exito', texto: `Devolución #${gestion.id}: ${respuesta.mensaje}` }
    gestion.id = null
    cargar()
  } catch (e) {
    gestion.error = e.errores?.respuesta ?? e.errores?.estado ?? e.message
  } finally {
    gestion.enviando = false
  }
}

onMounted(cargar)
</script>

<template>
  <section>
    <div class="panel-encabezado">
      <h2>Devoluciones <span v-if="resultado.por_revisar" class="contador">{{ resultado.por_revisar }} por revisar</span></h2>
    </div>

    <div class="panel-tarjeta">
      <div class="semaforo" role="group" aria-label="Filtrar por estado">
        <button type="button" class="sem-neutro" :class="{ activo: filtros.estado === '' }" :aria-pressed="filtros.estado === ''" @click="filtros.estado = ''">
          <i class="fa-solid fa-list" aria-hidden="true"></i>Todas
        </button>
        <button
          v-for="(info, clave) in ESTADOS_DEVOLUCION"
          :key="clave"
          type="button"
          :class="[info.semaforo, { activo: filtros.estado === clave }]"
          :aria-pressed="filtros.estado === clave"
          @click="filtros.estado = clave"
        >
          <span class="luz" aria-hidden="true"></span>{{ info.texto }}
        </button>
      </div>

      <form class="filtros" role="search" @submit.prevent>
        <label class="campo">
          Buscar
          <input v-model="filtros.q" type="search" placeholder="#devolución o #pedido, cliente o producto" maxlength="100">
        </label>
        <label class="campo">
          Tipo de devolución
          <select v-model="filtros.tipo">
            <option value="">Todos los tipos</option>
            <option v-for="(info, clave) in TIPOS_DEVOLUCION" :key="clave" :value="clave">{{ info.texto }}</option>
          </select>
        </label>
      </form>

      <p v-if="aviso.texto" class="alerta" :class="`alerta-${aviso.tipo}`" role="status">{{ aviso.texto }}</p>
      <p v-if="error" class="alerta alerta-error">{{ error }}</p>
      <p v-if="cargando && !resultado.items.length" class="cargando"><i class="fa-solid fa-spinner fa-spin"></i>Cargando...</p>
      <p v-else-if="!resultado.items.length" class="cargando">No hay devoluciones con esos filtros.</p>

      <ul v-else class="devoluciones">
        <li v-for="devolucion in resultado.items" :key="devolucion.id" :class="`borde-${devolucion.estado}`">
          <div class="cabecera">
            <div>
              <strong>Devolución #{{ devolucion.id }}</strong>
              <RouterLink :to="{ name: 'panel-pedido', params: { id: devolucion.pedido_id } }" class="pedido">Pedido #{{ devolucion.pedido_id }}</RouterLink>
              <span class="texto-secundario">{{ formatearFecha(devolucion.creado_en) }}</span>
            </div>
            <div class="estado-acciones">
              <EstadoDevolucion :estado="devolucion.estado" />
              <MenuAcciones
                v-if="devolucion.estados_siguientes.length && gestion.id !== devolucion.id"
                :etiqueta="`Acciones de la devolución #${devolucion.id}`"
                :items="devolucion.estados_siguientes.map((estado) => ({ texto: ACCIONES[estado].texto, icono: ACCIONES[estado].icono, peligro: estado === 'rechazada', accion: () => abrirGestion(devolucion, estado) }))"
              />
            </div>
          </div>

          <div class="cuerpo">
            <span class="tipo">
              <i class="fa-solid" :class="TIPOS_DEVOLUCION[devolucion.tipo]?.icono" aria-hidden="true"></i>
              {{ TIPOS_DEVOLUCION[devolucion.tipo]?.texto }}
            </span>
            <p><strong>{{ devolucion.producto }}</strong> · {{ devolucion.cantidad }} de {{ devolucion.comprada }} {{ devolucion.comprada === 1 ? 'unidad' : 'unidades' }}</p>
            <p class="texto-secundario">{{ devolucion.cliente.nombre }} · {{ devolucion.cliente.email }}</p>
            <blockquote>{{ devolucion.descripcion }}</blockquote>
            <p v-if="devolucion.respuesta" class="respuesta">
              <i class="fa-solid fa-store"></i> {{ devolucion.respuesta }}
              <span v-if="devolucion.gestionado_por" class="texto-secundario"> — {{ devolucion.gestionado_por }}</span>
            </p>
          </div>

          <div v-if="gestion.id === devolucion.id" class="gestion">
            <label class="campo" :class="{ 'con-error': gestion.error }">
              {{ gestion.estado === 'rechazada' ? 'Motivo del rechazo (lo verá el cliente)' : 'Mensaje para el cliente (opcional)' }}
              <textarea v-model="gestion.respuesta" rows="2" maxlength="500"></textarea>
              <span v-if="gestion.error" class="error-campo">{{ gestion.error }}</span>
            </label>
            <div class="botones">
              <button type="button" class="boton boton-pequeno" :disabled="gestion.enviando" @click="confirmarGestion">
                Confirmar: {{ ACCIONES[gestion.estado].texto.toLowerCase() }}
              </button>
              <button type="button" class="boton boton-secundario boton-pequeno" @click="gestion.id = null">Cancelar</button>
            </div>
          </div>
          <p v-else-if="devolucion.estado === 'aprobada'" class="texto-secundario">Cuando llegue el producto, márcala como recibida en «⋮»: las unidades vuelven al inventario.</p>
        </li>
      </ul>

      <Paginacion :pagina="filtros.pagina" :paginas="resultado.paginas" :total="resultado.total" etiqueta="devoluciones" @cambiar="irAPagina" />
    </div>
  </section>
</template>

<style scoped>
.contador {
  margin-left: 8px;
  padding: 3px 10px;
  font-size: .78rem;
  vertical-align: middle;
  color: #7a5600;
  background: #fff6d6;
  border: 1.5px solid currentColor;
  border-radius: 20px;
}

.filtros {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 12px;
  margin-bottom: 16px;
}

.devoluciones {
  display: grid;
  gap: 12px;
  padding: 0;
  list-style: none;
}

.devoluciones li {
  display: grid;
  gap: 10px;
  padding: 14px 16px;
  border: 1px solid #e4e6ea;
  border-left: 5px solid #9aa3b2;
  border-radius: 10px;
}

/* Borde izquierdo con el color del semáforo del estado */
.borde-solicitada { border-left-color: #b58100 !important; }
.borde-aprobada { border-left-color: #1565c0 !important; }
.borde-recibida { border-left-color: #1e8e3e !important; }
.borde-rechazada { border-left-color: #c62828 !important; }

.cabecera {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.cabecera > div {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 10px;
}

.pedido {
  font-size: .88rem;
  font-weight: 600;
  color: #a8521f;
}

.cuerpo {
  display: grid;
  gap: 4px;
}

.tipo {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  width: fit-content;
  padding: 3px 10px;
  font-size: .82rem;
  font-weight: 600;
  background: #f1f3f5;
  border-radius: 6px;
}

blockquote {
  margin: 4px 0 0;
  padding: 8px 12px;
  font-size: .92rem;
  background: #fafafa;
  border-left: 3px solid #d9dde3;
  border-radius: 4px;
}

.respuesta {
  padding: 8px 10px;
  font-size: .88rem;
  background: #eef4fb;
  border-radius: 8px;
}

.gestion {
  display: grid;
  gap: 8px;
  padding: 12px;
  background: #fafafa;
  border-radius: 8px;
}

.estado-acciones {
  display: flex;
  align-items: center;
  gap: 8px;
}

.botones {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

@media (max-width: 640px) {
  .filtros {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
