<script setup>
// Botón flotante "Ver Carrito" y ventana del carrito (diseño original de carrito.css),
// ahora con cantidades, talla, color y opción de quitar productos.
import { onBeforeUnmount, onMounted } from 'vue'
import { useCarritoStore } from '@/stores/carrito'
import { useAuthStore } from '@/stores/auth'
import { urlImagen } from '@/services/api'
import { formatearPrecio } from '@/utils/formato'

const carrito = useCarritoStore()
const auth = useAuthStore()

function cerrarConEscape(evento) {
  if (evento.key === 'Escape') carrito.abierto = false
}

onMounted(() => document.addEventListener('keydown', cerrarConEscape))
onBeforeUnmount(() => document.removeEventListener('keydown', cerrarConEscape))
</script>

<template>
  <div class="carro">
    <button type="button" id="cart-icon" :aria-label="`Ver carrito: ${carrito.cantidadTotal} producto(s)`" @click="carrito.abierto = true">
      <i class="fa-solid fa-cart-shopping"></i><span class="texto-carrito">Ver Carrito</span><span id="cart-count" class="contador-carrito">{{ carrito.cantidadTotal }}</span>
    </button>
  </div>

  <div v-if="carrito.abierto" class="cart-modal" @click.self="carrito.abierto = false">
    <div class="cart-content" role="dialog" aria-modal="true" aria-labelledby="titulo-carrito">
      <button type="button" class="close-cart" aria-label="Cerrar carrito" @click="carrito.abierto = false">&times;</button>
      <h2 id="titulo-carrito">Carrito de compras</h2>

      <p v-if="carrito.items.length === 0" class="vacio">Tu carrito está vacío.</p>

      <ul v-else id="cart-items">
        <li v-for="item in carrito.items" :key="item.varianteId" class="linea">
          <img :src="urlImagen(item.imagen)" :alt="item.nombre">
          <div class="detalle">
            <RouterLink :to="{ name: 'producto', params: { slug: item.slug } }" class="nombre" @click="carrito.abierto = false">
              {{ item.nombre }}
            </RouterLink>
            <span class="variante">{{ item.color }} · Talla {{ item.talla }}</span>
            <div class="cantidad">
              <button type="button" aria-label="Quitar una unidad" :disabled="item.cantidad <= 1" @click="carrito.cambiarCantidad(item.varianteId, item.cantidad - 1)">−</button>
              <span>{{ item.cantidad }}</span>
              <button type="button" aria-label="Agregar una unidad" :disabled="item.cantidad >= item.stock" @click="carrito.cambiarCantidad(item.varianteId, item.cantidad + 1)">+</button>
              <button type="button" class="quitar" @click="carrito.quitar(item.varianteId)">Quitar</button>
            </div>
          </div>
          <span class="subtotal">{{ formatearPrecio(item.precio * item.cantidad) }}</span>
        </li>
      </ul>

      <p id="total">Total: {{ formatearPrecio(carrito.total) }}</p>

      <RouterLink
        v-if="!auth.autenticado && carrito.items.length"
        :to="{ name: 'login', query: { redirect: $route.fullPath } }"
        id="checkout"
        class="boton-checkout"
        @click="carrito.abierto = false"
      >
        Inicia sesión para comprar
      </RouterLink>
      <RouterLink
        v-else-if="carrito.items.length && auth.rol === 'cliente'"
        :to="{ name: 'checkout' }"
        id="checkout"
        class="boton-checkout"
        @click="carrito.abierto = false"
      >
        Finalizar Compra
      </RouterLink>
      <p v-else-if="carrito.items.length" class="nota">
        Las compras se hacen con una cuenta de cliente. Tu cuenta es de personal de la tienda.
      </p>
    </div>
  </div>
</template>

<style scoped>
/* Estilos originales (carrito.css) */
.carro {
  display: flex;
  align-items: center;
  background-color: #ff6600;
  z-index: 900;
  position: fixed;
  /* En el original estaba a media pantalla y tapaba tallas y botones; va en la esquina. */
  right: 16px;
  bottom: 20px;
  border-radius: 50px;
  box-shadow: 0 0 20px rgb(0, 0, 0, 0.4);
}

#cart-icon {
  padding: 15px;
  color: black;
  background: none;
  border: 0;
  border-radius: 50px;
  cursor: pointer;
}

#cart-icon i {
  margin-right: 5px;
}

/* En computador se lee "Ver Carrito(2)", como en el diseño original */
.contador-carrito::before { content: "("; }
.contador-carrito::after { content: ")"; }

/* En celular: botón redondo con el número en una burbuja, para no tapar el contenido */
@media (max-width: 700px) {
  .carro {
    right: 14px;
    bottom: 16px;
  }

  #cart-icon {
    position: relative;
    display: grid;
    place-items: center;
    width: 54px;
    height: 54px;
    padding: 0;
    font-size: 1.2rem;
  }

  #cart-icon i {
    margin: 0;
  }

  .texto-carrito {
    display: none;
  }

  .contador-carrito {
    position: absolute;
    top: -4px;
    right: -4px;
    display: grid;
    place-items: center;
    min-width: 22px;
    height: 22px;
    padding: 0 5px;
    font-size: .72rem;
    font-weight: 700;
    color: #fff;
    background: #111;
    border: 2px solid #fff;
    border-radius: 999px;
  }

  .contador-carrito::before,
  .contador-carrito::after {
    content: none;
  }
}

.cart-modal {
  display: flex;
  position: fixed;
  inset: 0;
  z-index: 1100;
  padding: 16px;
  background-color: rgb(0, 0, 0, 0.5);
  justify-content: center;
  align-items: center;
}

.cart-content {
  position: relative;
  background-color: #ffffff;
  padding: 24px 20px 20px;
  border-radius: 10px;
  width: 420px;
  max-width: 100%;
  max-height: 85vh;
  overflow-y: auto;
}

.close-cart {
  position: absolute;
  top: 6px;
  right: 12px;
  font-size: 34px;
  line-height: 1;
  color: black;
  background: none;
  border: 0;
  cursor: pointer;
}

.vacio {
  padding: 16px 0;
  color: var(--texto-secundario);
}

#cart-items {
  padding: 0;
  margin-top: 10px;
}

.linea {
  display: grid;
  grid-template-columns: 64px 1fr auto;
  gap: 12px;
  align-items: start;
  padding: 12px 0;
  border-bottom: 1px solid #dddddd;
}

.linea img {
  width: 64px;
  height: 64px;
  object-fit: cover;
  border-radius: 8px;
}

.detalle {
  display: grid;
  gap: 2px;
  min-width: 0;
}

.nombre {
  font-weight: 600;
  font-size: .92rem;
}

.variante {
  font-size: .82rem;
  color: var(--texto-secundario);
}

.cantidad {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 4px;
}

.cantidad button {
  min-width: 28px;
  height: 28px;
  background: var(--gris-suave);
  border: 0;
  border-radius: 6px;
  cursor: pointer;
}

.cantidad button:disabled {
  opacity: .4;
  cursor: not-allowed;
}

.cantidad .quitar {
  padding: 0 8px;
  font-size: .8rem;
  color: var(--error);
  background: none;
}

.subtotal {
  font-weight: 600;
  font-size: .9rem;
  white-space: nowrap;
}

#total {
  margin-top: 15px;
  font-weight: 900;
  color: black;
}

.boton-checkout {
  display: block;
  width: 100%;
  margin-top: 10px;
  padding: 10px;
  text-align: center;
  color: #ffffff;
  background-color: #ff6600;
  border: none;
  cursor: pointer;
}

.boton-checkout:disabled {
  opacity: .6;
  cursor: not-allowed;
}

.nota {
  margin-top: 8px;
  font-size: .8rem;
  color: var(--texto-secundario);
}
</style>
