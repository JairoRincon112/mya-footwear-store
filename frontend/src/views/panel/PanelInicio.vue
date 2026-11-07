<script setup>
// Resumen del panel: indicadores y alertas que requieren atención.
import { onMounted, ref } from 'vue'
import adminService from '@/services/adminService'
import { useAuthStore } from '@/stores/auth'
import { formatearPrecio } from '@/utils/formato'

const auth = useAuthStore()
const resumen = ref(null)
const error = ref('')

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
      <h2>Hola, {{ auth.usuario?.nombre }}</h2>
    </div>

    <p v-if="error" class="alerta alerta-error">{{ error }}</p>
    <p v-else-if="!resumen" class="cargando"><i class="fa-solid fa-spinner fa-spin"></i>Cargando resumen...</p>

    <template v-else>
      <div class="tarjetas">
        <div class="panel-tarjeta dato">
          <span class="texto-secundario">Ventas de este mes</span>
          <strong>{{ formatearPrecio(resumen.ventas_mes.total) }}</strong>
        </div>
        <div class="panel-tarjeta dato">
          <span class="texto-secundario">Productos en la tienda</span>
          <strong>{{ resumen.productos_activos }}</strong>
        </div>
        <div class="panel-tarjeta dato">
          <span class="texto-secundario">Clientes registrados</span>
          <strong>{{ resumen.clientes }}</strong>
        </div>
      </div>

      <div class="panel-tarjeta">
        <h3>Requiere atención</h3>
        <ul class="pendientes">
          <li>
            <i class="fa-solid fa-clock"></i>
            <span><strong>{{ resumen.pedidos_por_estado.pendiente }}</strong> pedidos pendientes de pago</span>
            <RouterLink :to="{ name: 'panel-pedidos' }" class="boton boton-secundario boton-pequeno">Ver pedidos</RouterLink>
          </li>
          <li>
            <i class="fa-solid fa-credit-card"></i>
            <span><strong>{{ resumen.pedidos_por_estado.pagado }}</strong> pedidos pagados por enviar</span>
            <RouterLink :to="{ name: 'panel-pedidos' }" class="boton boton-secundario boton-pequeno">Ver pedidos</RouterLink>
          </li>
          <li>
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span><strong>{{ resumen.inventario.bajo_stock }}</strong> variantes con bajo stock y <strong>{{ resumen.inventario.agotadas }}</strong> agotadas</span>
            <RouterLink :to="{ name: 'panel-inventario', query: { bajo_stock: '1' } }" class="boton boton-secundario boton-pequeno">Ver inventario</RouterLink>
          </li>
          <li>
            <i class="fa-solid fa-envelope"></i>
            <span><strong>{{ resumen.mensajes_no_leidos }}</strong> mensajes de contacto sin leer</span>
            <RouterLink :to="{ name: 'panel-mensajes' }" class="boton boton-secundario boton-pequeno">Ver mensajes</RouterLink>
          </li>
        </ul>
      </div>

      <div class="panel-tarjeta">
        <h3>Accesos rápidos</h3>
        <div class="accesos">
          <RouterLink :to="{ name: 'panel-reportes' }" class="boton boton-secundario"><i class="fa-solid fa-chart-column"></i> Reportes</RouterLink>
          <RouterLink :to="{ name: 'panel-productos' }" class="boton boton-secundario"><i class="fa-solid fa-shoe-prints"></i> Productos</RouterLink>
          <RouterLink v-if="auth.esAdmin" :to="{ name: 'panel-producto-nuevo' }" class="boton"><i class="fa-solid fa-plus"></i> Nuevo producto</RouterLink>
        </div>
      </div>
    </template>
  </section>
</template>

<style scoped>
.tarjetas {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
}

.dato {
  display: grid;
  gap: 6px;
}

.dato strong {
  font-size: 1.8rem;
  font-variant-numeric: tabular-nums;
}

.pendientes {
  display: grid;
  gap: 10px;
}

.pendientes li {
  display: grid;
  grid-template-columns: 24px 1fr auto;
  gap: 12px;
  align-items: center;
  padding: 10px 0;
  border-bottom: 1px solid #efefef;
}

.pendientes li:last-child {
  border-bottom: 0;
}

.pendientes i {
  color: #c24e00;
  text-align: center;
}

.accesos {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}
</style>
