<script setup>
// Listado de pedidos para el personal, con filtros por estado, búsqueda y fechas.
import MenuAcciones from '@/components/MenuAcciones.vue'
import { onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import EstadoPedido from '@/components/EstadoPedido.vue'
import Paginacion from '@/components/Paginacion.vue'
import pedidoService from '@/services/pedidoService'
import { ESTADOS_PEDIDO, formatearFecha, formatearPrecio } from '@/utils/formato'

const router = useRouter()
const filtros = reactive({ estado: '', q: '', desde: '', hasta: '', pagina: 1 })
const resultado = ref({ items: [], total: 0, paginas: 0 })
const cargando = ref(true)
const error = ref('')
let temporizador = null

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    resultado.value = await pedidoService.listar(filtros)
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

watch(() => [filtros.estado, filtros.desde, filtros.hasta], reiniciarYCargar)
watch(() => filtros.q, () => {
  clearTimeout(temporizador)
  temporizador = setTimeout(reiniciarYCargar, 350)
})

function abrirPedido(pedido) {
  router.push({ name: 'panel-pedido', params: { id: pedido.id } })
}

function irAPagina(pagina) {
  filtros.pagina = pagina
  cargar()
}

onMounted(cargar)
</script>

<template>
  <section>
    <div class="panel-encabezado">
      <h2>Pedidos</h2>
    </div>

    <div class="panel-tarjeta">
      <div class="semaforo" role="group" aria-label="Filtrar por estado">
        <button type="button" class="sem-neutro" :class="{ activo: filtros.estado === '' }" :aria-pressed="filtros.estado === ''" @click="filtros.estado = ''">
          <i class="fa-solid fa-list" aria-hidden="true"></i>Todos
        </button>
        <button
          v-for="(info, clave) in ESTADOS_PEDIDO"
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
          <input v-model="filtros.q" type="search" placeholder="#número, nombre o correo del cliente" maxlength="100">
        </label>
        <label class="campo">
          Desde
          <input v-model="filtros.desde" type="date">
        </label>
        <label class="campo">
          Hasta
          <input v-model="filtros.hasta" type="date">
        </label>
      </form>

      <p v-if="error" class="alerta alerta-error">{{ error }}</p>
      <p v-if="cargando && !resultado.items.length" class="cargando"><i class="fa-solid fa-spinner fa-spin"></i>Cargando...</p>
      <p v-else-if="!resultado.items.length" class="cargando">No hay pedidos con esos filtros.</p>

      <div v-else class="tabla-contenedor">
        <table class="tabla">
          <thead>
            <tr>
              <th>Pedido</th>
              <th>Fecha</th>
              <th>Cliente</th>
              <th>Ciudad</th>
              <th class="numero">Pares</th>
              <th class="numero">Total</th>
              <th>Estado</th>
              <th>Vendedor</th>
              <th><span class="solo-lectores">Acciones</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="pedido in resultado.items" :key="pedido.id" class="fila-clic" @click="abrirPedido(pedido)">
              <td><strong>#{{ pedido.id }}</strong></td>
              <td class="texto-secundario sin-salto">{{ formatearFecha(pedido.creado_en) }}</td>
              <td>
                {{ pedido.cliente.nombre }}
                <div class="texto-secundario">{{ pedido.cliente.email }}</div>
              </td>
              <td>{{ pedido.ciudad }}</td>
              <td class="numero">{{ pedido.unidades }}</td>
              <td class="numero">{{ formatearPrecio(pedido.total) }}</td>
              <td><EstadoPedido :estado="pedido.estado" /></td>
              <td class="texto-secundario">{{ pedido.vendedor ?? '—' }}</td>
              <td class="acciones" @click.stop>
                <MenuAcciones :etiqueta="`Acciones del pedido #${pedido.id}`" :items="[{ texto: 'Ver detalle', icono: 'fa-eye', to: { name: 'panel-pedido', params: { id: pedido.id } } }]" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <Paginacion :pagina="filtros.pagina" :paginas="resultado.paginas" :total="resultado.total" etiqueta="pedidos" @cambiar="irAPagina" />
    </div>
  </section>
</template>

<style scoped>
.sin-salto {
  white-space: nowrap;
}


.filtros {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr;
  gap: 12px;
  margin-bottom: 16px;
}

@media (max-width: 640px) {
  .filtros {
    grid-template-columns: minmax(0, 1fr); /* minmax: el contenido ancho (tablas) no estira la página */
  }
}
</style>
