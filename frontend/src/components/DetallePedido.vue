<script setup>
// Detalle de un pedido (productos, envío y total). Lo usan el cliente y el panel.
import EstadoPedido from '@/components/EstadoPedido.vue'
import { urlImagen } from '@/services/api'
import { formatearFecha, formatearPrecio } from '@/utils/formato'

defineProps({
  pedido: { type: Object, required: true },
  mostrarCliente: { type: Boolean, default: false },
})
</script>

<template>
  <div class="detalle-pedido">
    <div class="cabecera">
      <div>
        <h3>Pedido #{{ pedido.id }}</h3>
        <span class="texto-secundario">{{ formatearFecha(pedido.creado_en) }}</span>
      </div>
      <EstadoPedido :estado="pedido.estado" />
    </div>

    <div class="bloques">
      <section>
        <h4>Envío</h4>
        <p>{{ pedido.nombre_contacto }} · {{ pedido.telefono }}</p>
        <p>{{ pedido.direccion }}, {{ pedido.ciudad }}</p>
        <p v-if="pedido.notas" class="texto-secundario">Notas: {{ pedido.notas }}</p>
      </section>
      <section v-if="mostrarCliente">
        <h4>Cliente</h4>
        <p>{{ pedido.cliente.nombre }}</p>
        <p class="texto-secundario">{{ pedido.cliente.email }}</p>
        <p class="texto-secundario">Vendedor: {{ pedido.vendedor ?? 'Sin asignar' }}</p>
      </section>
    </div>

    <ul class="lineas">
      <li v-for="linea in pedido.detalle" :key="linea.id">
        <img :src="urlImagen(linea.imagen)" alt="">
        <div>
          <RouterLink :to="{ name: 'producto', params: { slug: linea.slug } }">{{ linea.descripcion }}</RouterLink>
          <span class="texto-secundario">{{ linea.cantidad }} × {{ formatearPrecio(linea.precio_unitario) }}<template v-if="mostrarCliente"> · {{ linea.sku }}</template></span>
        </div>
        <strong>{{ formatearPrecio(linea.subtotal) }}</strong>
      </li>
    </ul>

    <dl class="totales">
      <template v-if="pedido.descuento > 0">
        <div><dt>Subtotal</dt><dd>{{ formatearPrecio(pedido.subtotal) }}</dd></div>
        <div class="descuento"><dt>Descuento<template v-if="pedido.cupon"> (cupón {{ pedido.cupon }})</template></dt><dd>−{{ formatearPrecio(pedido.descuento) }}</dd></div>
      </template>
      <div class="total"><dt>Total</dt><dd>{{ formatearPrecio(pedido.total) }}</dd></div>
    </dl>
  </div>
</template>

<style scoped>
.detalle-pedido {
  display: grid;
  gap: 18px;
}

.cabecera {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.cabecera h3 {
  font-size: 1.3rem;
}

.bloques {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
}

.bloques h4 {
  margin-bottom: 4px;
  font-size: .85rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: var(--texto-secundario);
}

.lineas {
  display: grid;
  gap: 12px;
}

.lineas li {
  display: grid;
  grid-template-columns: 60px 1fr auto;
  gap: 12px;
  align-items: center;
}

.lineas img {
  width: 60px;
  height: 60px;
  object-fit: cover;
  border-radius: 8px;
  background: var(--gris-suave);
}

.lineas div {
  display: grid;
  gap: 2px;
  min-width: 0;
}

.lineas a:hover {
  color: var(--naranja);
}

.totales {
  display: grid;
  gap: 6px;
  padding-top: 12px;
  border-top: 1px solid #e6e6e6;
}

.totales div {
  display: flex;
  justify-content: space-between;
}

.totales .descuento {
  color: var(--exito);
}

.totales .total {
  font-size: 1.15rem;
  font-weight: 700;
}

.texto-secundario {
  font-size: .85rem;
  color: var(--texto-secundario);
}
</style>
