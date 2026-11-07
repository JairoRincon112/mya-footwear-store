<script setup>
// Inventario: stock de todas las variantes, alerta de bajo stock, ajustes e historial.
import { onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import Paginacion from '@/components/Paginacion.vue'
import pedidoService from '@/services/pedidoService'
import productoService from '@/services/productoService'
import { useCatalogoStore } from '@/stores/catalogo'
import { formatearFecha } from '@/utils/formato'

const route = useRoute()
const catalogo = useCatalogoStore()

const filtros = reactive({ q: '', marca: '', bajo_stock: route.query.bajo_stock === '1', pagina: 1 })
const resultado = ref({ items: [], total: 0, paginas: 0, umbral: 3 })
const cargando = ref(true)
const error = ref('')
const aviso = ref({ tipo: '', texto: '' })

const ajuste = reactive({ varianteId: null, tipo: 'entrada', cantidad: 1, motivo: '' })
const erroresAjuste = ref({})
const historial = ref(null) // { variante, movimientos }
let temporizador = null

const TIPOS = { entrada: 'Entrada', venta: 'Venta', ajuste: 'Ajuste', devolucion: 'Devolución' }

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    resultado.value = await pedidoService.inventario({ ...filtros, bajo_stock: filtros.bajo_stock ? 1 : '' })
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

watch(() => [filtros.marca, filtros.bajo_stock], reiniciarYCargar)
watch(() => filtros.q, () => {
  clearTimeout(temporizador)
  temporizador = setTimeout(reiniciarYCargar, 350)
})

function irAPagina(pagina) {
  filtros.pagina = pagina
  cargar()
}

function abrirAjuste(variante) {
  historial.value = null
  Object.assign(ajuste, { varianteId: variante.id, tipo: 'entrada', cantidad: 1, motivo: '' })
  erroresAjuste.value = {}
}

async function guardarAjuste() {
  const errores = {}
  const cantidad = Number(ajuste.cantidad)
  if (!Number.isInteger(cantidad) || cantidad === 0) errores.cantidad = 'Cantidad entera distinta de 0.'
  else if (ajuste.tipo !== 'ajuste' && cantidad < 0) errores.cantidad = 'Para descontar usa "Ajuste".'
  if (ajuste.tipo === 'ajuste' && !ajuste.motivo.trim()) errores.motivo = 'Explica el motivo.'
  erroresAjuste.value = errores
  if (Object.keys(errores).length) return

  try {
    const respuesta = await productoService.ajustarStock(ajuste.varianteId, { tipo: ajuste.tipo, cantidad, motivo: ajuste.motivo.trim() || null })
    aviso.value = { tipo: 'exito', texto: `${respuesta.data.sku}: stock actualizado a ${respuesta.data.stock}.` }
    ajuste.varianteId = null
    cargar()
  } catch (e) {
    erroresAjuste.value = e.errores
    if (!Object.keys(e.errores).length) aviso.value = { tipo: 'error', texto: e.message }
  }
}

async function verHistorial(variante) {
  ajuste.varianteId = null
  if (historial.value?.variante.id === variante.id) {
    historial.value = null
    return
  }
  try {
    historial.value = await pedidoService.movimientos(variante.id)
  } catch (e) {
    aviso.value = { tipo: 'error', texto: e.message }
  }
}

onMounted(() => {
  catalogo.cargar().catch(() => {})
  cargar()
})
</script>

<template>
  <section>
    <div class="panel-encabezado">
      <h2>Inventario</h2>
    </div>

    <div class="panel-tarjeta">
      <form class="filtros" role="search" @submit.prevent>
        <label class="campo">
          Buscar
          <input v-model="filtros.q" type="search" placeholder="Producto o SKU" maxlength="100">
        </label>
        <label class="campo">
          Marca
          <select v-model="filtros.marca">
            <option value="">Todas</option>
            <option v-for="marca in catalogo.marcas" :key="marca.id" :value="marca.slug">{{ marca.nombre }}</option>
          </select>
        </label>
        <label class="campo-check bajo">
          <input v-model="filtros.bajo_stock" type="checkbox">
          Solo bajo stock (≤ {{ resultado.umbral }})
        </label>
      </form>

      <p v-if="aviso.texto" class="alerta aviso" :class="`alerta-${aviso.tipo}`" role="status">{{ aviso.texto }}</p>
      <p v-if="error" class="alerta alerta-error">{{ error }}</p>
      <p v-if="cargando && !resultado.items.length" class="cargando"><i class="fa-solid fa-spinner fa-spin"></i>Cargando...</p>
      <p v-else-if="!resultado.items.length" class="cargando">No hay variantes con esos filtros.</p>

      <div v-else class="tabla-contenedor">
        <table class="tabla">
          <thead>
            <tr>
              <th>Producto</th>
              <th>Color</th>
              <th>Talla</th>
              <th>SKU</th>
              <th class="numero">Stock</th>
              <th><span class="solo-lectores">Acciones</span></th>
            </tr>
          </thead>
          <tbody>
            <template v-for="variante in resultado.items" :key="variante.id">
              <tr>
                <td>
                  <RouterLink :to="{ name: 'panel-producto', params: { id: variante.producto.id } }"><strong>{{ variante.producto.nombre }}</strong></RouterLink>
                  <div class="texto-secundario">
                    {{ variante.producto.marca }}
                    <template v-if="!variante.producto.activo || !variante.activo"> · inactivo</template>
                  </div>
                </td>
                <td>{{ variante.color.nombre }}</td>
                <td>{{ variante.talla }}</td>
                <td class="texto-secundario">{{ variante.sku }}</td>
                <td class="numero">
                  <span v-if="variante.stock === 0" class="etiqueta etiqueta-agotada"><i class="fa-solid fa-triangle-exclamation"></i> Agotado</span>
                  <span v-else-if="variante.stock <= resultado.umbral" class="etiqueta etiqueta-bajo"><i class="fa-solid fa-arrow-down"></i> {{ variante.stock }}</span>
                  <template v-else>{{ variante.stock }}</template>
                </td>
                <td class="acciones">
                  <button type="button" class="boton boton-secundario boton-pequeno" @click="abrirAjuste(variante)">Ajustar</button>
                  <button type="button" class="boton boton-secundario boton-pequeno" @click="verHistorial(variante)">Historial</button>
                </td>
              </tr>

              <tr v-if="ajuste.varianteId === variante.id" class="fila-extra">
                <td colspan="6">
                  <form class="ajuste" novalidate @submit.prevent="guardarAjuste">
                    <label class="campo">
                      Tipo
                      <select v-model="ajuste.tipo">
                        <option value="entrada">Entrada (llegó mercancía)</option>
                        <option value="devolucion">Devolución de cliente</option>
                        <option value="ajuste">Ajuste (corrección, + o −)</option>
                      </select>
                    </label>
                    <label class="campo" :class="{ 'con-error': erroresAjuste.cantidad }">
                      Cantidad
                      <input v-model="ajuste.cantidad" type="number" step="1">
                      <span v-if="erroresAjuste.cantidad" class="error-campo">{{ erroresAjuste.cantidad }}</span>
                    </label>
                    <label class="campo" :class="{ 'con-error': erroresAjuste.motivo }">
                      Motivo
                      <input v-model="ajuste.motivo" type="text" maxlength="255">
                      <span v-if="erroresAjuste.motivo" class="error-campo">{{ erroresAjuste.motivo }}</span>
                    </label>
                    <div class="botones">
                      <button type="submit" class="boton boton-pequeno">Guardar</button>
                      <button type="button" class="boton boton-secundario boton-pequeno" @click="ajuste.varianteId = null">Cancelar</button>
                    </div>
                  </form>
                </td>
              </tr>

              <tr v-if="historial?.variante.id === variante.id" class="fila-extra">
                <td colspan="6">
                  <table class="tabla historial">
                    <thead>
                      <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th class="numero">Cantidad</th>
                        <th class="numero">Stock final</th>
                        <th>Detalle</th>
                        <th>Usuario</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="movimiento in historial.movimientos" :key="movimiento.id">
                        <td class="texto-secundario">{{ formatearFecha(movimiento.creado_en) }}</td>
                        <td>{{ TIPOS[movimiento.tipo] }}</td>
                        <td class="numero">{{ movimiento.cantidad > 0 ? '+' : '−' }}{{ Math.abs(movimiento.cantidad) }}</td>
                        <td class="numero">{{ movimiento.stock_resultante }}</td>
                        <td>
                          <RouterLink v-if="movimiento.pedido_id" :to="{ name: 'panel-pedido', params: { id: movimiento.pedido_id } }">{{ movimiento.motivo }}</RouterLink>
                          <template v-else>{{ movimiento.motivo ?? '—' }}</template>
                        </td>
                        <td class="texto-secundario">{{ movimiento.usuario ?? '—' }}</td>
                      </tr>
                    </tbody>
                  </table>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <Paginacion :pagina="filtros.pagina" :paginas="resultado.paginas" :total="resultado.total" etiqueta="variantes" @cambiar="irAPagina" />
    </div>
  </section>
</template>

<style scoped>
.filtros {
  display: grid;
  grid-template-columns: 2fr 1fr auto;
  gap: 12px;
  align-items: end;
  margin-bottom: 16px;
}

.bajo {
  padding-bottom: 10px;
}

.aviso {
  margin-bottom: 12px;
}

.etiqueta-bajo {
  color: #7a4b00;
  background: #fff1d6;
}

.acciones {
  display: flex;
  gap: 6px;
  justify-content: flex-end;
  white-space: nowrap;
}

.fila-extra > td {
  background: #fff8f2;
}

.ajuste {
  display: grid;
  grid-template-columns: 1.3fr .7fr 1.5fr auto;
  gap: 12px;
  align-items: end;
}

.ajuste .botones {
  display: flex;
  gap: 6px;
}

.historial {
  background: #fff;
}

@media (max-width: 760px) {
  .filtros,
  .ajuste {
    grid-template-columns: minmax(0, 1fr); /* minmax: el contenido ancho (tablas) no estira la página */
  }
}
</style>
