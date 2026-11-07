<script setup>
// Historial de pedidos del cliente.
import { onMounted, ref } from 'vue'
import EstadoPedido from '@/components/EstadoPedido.vue'
import Paginacion from '@/components/Paginacion.vue'
import pedidoService from '@/services/pedidoService'
import { formatearFecha, formatearPrecio } from '@/utils/formato'

const resultado = ref({ items: [], total: 0, paginas: 0, pagina: 1 })
const cargando = ref(true)
const error = ref('')

async function cargar(pagina = 1) {
  cargando.value = true
  error.value = ''
  try {
    resultado.value = await pedidoService.mios(pagina)
  } catch (e) {
    error.value = e.message
  } finally {
    cargando.value = false
  }
}

onMounted(() => cargar())
</script>

<template>
  <main class="mis-pedidos">
    <h2 class="titulo_descuento">MIS PEDIDOS</h2>

    <p v-if="cargando" class="cargando"><i class="fa-solid fa-spinner fa-spin"></i>Cargando tus pedidos...</p>
    <p v-else-if="error" class="alerta alerta-error">{{ error }}</p>

    <div v-else-if="!resultado.items.length" class="vacio">
      <p>Aún no has hecho pedidos.</p>
      <RouterLink to="/" class="boton">Ir a la tienda</RouterLink>
    </div>

    <template v-else>
      <ul class="lista">
        <li v-for="pedido in resultado.items" :key="pedido.id">
          <RouterLink :to="{ name: 'mi-pedido', params: { id: pedido.id } }" class="pedido">
            <div>
              <strong>Pedido #{{ pedido.id }}</strong>
              <span class="texto-secundario">{{ formatearFecha(pedido.creado_en) }} · {{ pedido.unidades }} par(es) · {{ pedido.ciudad }}</span>
            </div>
            <EstadoPedido :estado="pedido.estado" />
            <strong class="total">{{ formatearPrecio(pedido.total) }}</strong>
            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
          </RouterLink>
        </li>
      </ul>
      <Paginacion :pagina="resultado.pagina" :paginas="resultado.paginas" :total="resultado.total" etiqueta="pedidos" @cambiar="cargar" />
    </template>
  </main>
</template>

<style scoped>
.mis-pedidos {
  max-width: 860px;
  min-height: 70vh;
  margin: 0 auto;
  padding: calc(var(--alto-header) + 30px) 16px 40px;
}

.vacio {
  display: grid;
  justify-items: center;
  gap: 14px;
  padding: 30px 0;
}

.lista {
  display: grid;
  gap: 12px;
}

.pedido {
  display: grid;
  grid-template-columns: 1fr auto auto auto;
  gap: 16px;
  align-items: center;
  padding: 18px 20px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 4px 18px rgba(0, 0, 0, .06);
  transition: transform .2s;
}

.pedido:hover {
  transform: translateY(-2px);
}

.pedido div {
  display: grid;
  gap: 2px;
}

.texto-secundario {
  font-size: .85rem;
  color: var(--texto-secundario);
}

.total {
  white-space: nowrap;
}

@media (max-width: 600px) {
  .pedido {
    grid-template-columns: 1fr auto;
  }

  .pedido > i {
    display: none;
  }
}
</style>
