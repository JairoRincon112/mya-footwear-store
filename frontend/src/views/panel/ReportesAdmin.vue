<script setup>
// Reportes: ventas por día, pedidos por estado, productos más vendidos e inventario.
import { computed, onMounted, ref } from 'vue'
import EstadoPedido from '@/components/EstadoPedido.vue'
import GraficaVentas from '@/components/GraficaVentas.vue'
import adminService from '@/services/adminService'
import { formatearPrecio } from '@/utils/formato'

const resumen = ref(null)
const error = ref('')

const maxEstado = computed(() => Math.max(1, ...Object.values(resumen.value?.pedidos_por_estado ?? {})))
const maxVendido = computed(() => Math.max(1, ...(resumen.value?.mas_vendidos ?? []).map((p) => p.unidades)))

onMounted(async () => {
  try {
    resumen.value = await adminService.resumen()
  } catch (e) {
    error.value = e.message
  }
})
</script>

<template>
  <section>
    <div class="panel-encabezado">
      <h2>Reportes</h2>
    </div>

    <p v-if="error" class="alerta alerta-error">{{ error }}</p>
    <p v-else-if="!resumen" class="cargando"><i class="fa-solid fa-spinner fa-spin"></i>Cargando reportes...</p>

    <template v-else>
      <div class="kpis">
        <div class="panel-tarjeta kpi">
          <span class="texto-secundario">Ventas totales</span>
          <strong>{{ formatearPrecio(resumen.ventas.total) }}</strong>
          <span class="texto-secundario">{{ resumen.ventas.pedidos }} pedidos (sin cancelados)</span>
        </div>
        <div class="panel-tarjeta kpi">
          <span class="texto-secundario">Ventas de este mes</span>
          <strong>{{ formatearPrecio(resumen.ventas_mes.total) }}</strong>
          <span class="texto-secundario">{{ resumen.ventas_mes.pedidos }} pedidos</span>
        </div>
        <div class="panel-tarjeta kpi">
          <span class="texto-secundario">Ticket promedio</span>
          <strong>{{ formatearPrecio(resumen.ventas.pedidos ? resumen.ventas.total / resumen.ventas.pedidos : 0) }}</strong>
          <span class="texto-secundario">por pedido</span>
        </div>
        <div class="panel-tarjeta kpi">
          <span class="texto-secundario">Unidades en inventario</span>
          <strong>{{ resumen.inventario.unidades.toLocaleString('es-CO') }}</strong>
          <span class="texto-secundario">{{ resumen.inventario.agotadas }} variantes agotadas</span>
        </div>
      </div>

      <div class="panel-tarjeta">
        <h3>Ventas por día · últimos {{ resumen.ventas_por_dia.length }} días</h3>
        <GraficaVentas :datos="resumen.ventas_por_dia" />
      </div>

      <div class="dos-columnas">
        <div class="panel-tarjeta">
          <h3>Pedidos por estado</h3>
          <ul class="barras-h">
            <li v-for="(cantidad, estado) in resumen.pedidos_por_estado" :key="estado">
              <EstadoPedido :estado="estado" />
              <div class="pista"><div class="relleno" :style="{ width: `${(cantidad / maxEstado) * 100}%` }"></div></div>
              <strong class="valor">{{ cantidad }}</strong>
            </li>
          </ul>
        </div>

        <div class="panel-tarjeta">
          <h3>Productos más vendidos</h3>
          <p v-if="!resumen.mas_vendidos.length" class="texto-secundario">Aún no hay ventas.</p>
          <ol v-else class="barras-h ranking">
            <li v-for="producto in resumen.mas_vendidos" :key="producto.slug">
              <div class="nombre">
                <RouterLink :to="{ name: 'producto', params: { slug: producto.slug } }">{{ producto.nombre }}</RouterLink>
                <span class="texto-secundario">{{ producto.marca }} · {{ formatearPrecio(producto.ingresos) }}</span>
              </div>
              <div class="pista"><div class="relleno" :style="{ width: `${(producto.unidades / maxVendido) * 100}%` }"></div></div>
              <strong class="valor">{{ producto.unidades }} und.</strong>
            </li>
          </ol>
        </div>
      </div>
    </template>
  </section>
</template>

<style scoped>
.kpis {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
}

.kpi {
  display: grid;
  gap: 4px;
}

.kpi strong {
  font-size: 1.6rem;
  font-variant-numeric: tabular-nums;
}

.dos-columnas {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 20px;
}

.barras-h {
  display: grid;
  gap: 12px;
}

.barras-h li {
  display: grid;
  grid-template-columns: 120px 1fr 60px;
  gap: 12px;
  align-items: center;
}

.ranking li {
  grid-template-columns: minmax(0, 1.4fr) 1fr 60px;
}

.nombre {
  display: grid;
  min-width: 0;
  font-size: .9rem;
}

.nombre a {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-weight: 500;
}

.nombre a:hover {
  color: var(--naranja);
}

.pista {
  height: 14px;
  background: #f3f3f3;
  border-radius: 4px;
}

.relleno {
  height: 100%;
  min-width: 2px;
  background: #e05a00;
  border-radius: 4px;
}

.valor {
  text-align: right;
  font-size: .9rem;
  font-variant-numeric: tabular-nums;
}

.texto-secundario {
  font-size: .82rem;
  color: var(--texto-secundario);
}
</style>
