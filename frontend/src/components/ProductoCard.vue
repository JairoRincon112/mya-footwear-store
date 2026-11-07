<script setup>
// Tarjeta de producto con la animación original del catálogo
// (la ficha aparece al pasar el mouse sobre la imagen).
import PrecioProducto from '@/components/PrecioProducto.vue'
import { urlImagen } from '@/services/api'

defineProps({
  producto: { type: Object, required: true },
})
</script>

<template>
  <article class="card_article card-product">
    <img :src="urlImagen(producto.imagen)" :alt="producto.nombre" class="card_img" loading="lazy">
    <span v-if="producto.stock_total === 0" class="agotado">AGOTADO</span>
    <span v-else-if="producto.en_oferta" class="agotado oferta">OFERTA</span>

    <div class="card_data">
      <h3 class="card_description">{{ producto.nombre }}</h3>
      <p class="card_title price">
        <PrecioProducto :precio="producto.precio" :precio-regular="producto.precio_regular" :en-oferta="producto.en_oferta" />
      </p>
      <RouterLink :to="{ name: 'producto', params: { slug: producto.slug } }" class="card_button">
        {{ producto.stock_total === 0 ? 'VER DETALLE' : 'COMPRAR' }}
      </RouterLink>
    </div>
  </article>
</template>

<style scoped>
/* Estilos originales de la tarjeta (segunda.css) */
.card_article {
  position: relative;
  overflow: hidden;
}

.card_img {
  width: 348px;
  max-width: 100%;
  height: 400px;
  object-fit: cover;
  border-radius: 1.5rem;
  background: #fff;
}

.agotado {
  position: absolute;
  top: 16px;
  left: 16px;
  padding: 4px 12px;
  font-size: .75rem;
  font-weight: 700;
  color: #fff;
  background: rgba(0, 0, 0, .78);
  border-radius: 20px;
}

.agotado.oferta {
  background: #c24e00;
}

.card_data {
  width: 316px;
  max-width: calc(100% - 32px);
  background-color: var(--container-color);
  padding: 1.5rem 2.5rem;
  box-shadow: 0 8px 24px hsla(0, 100%, 50%, 0.15);
  border-radius: 1rem;
  position: absolute;
  bottom: -9rem;
  left: 0;
  right: 0;
  margin-inline: auto;
  opacity: 0;
  transition: opacity 1s 1s;
}

.card_description {
  display: block;
  font-size: var(--small-font-size);
  margin-bottom: .25rem;
  text-transform: uppercase;
}

.card_title {
  font-size: var(--h2-font-size);
  font-weight: 500;
  color: var(--title-color);
  margin-bottom: .75rem;
}

.card_button {
  font-size: var(--small-font-size);
  font-weight: 500;
  color: var(--first-color);
}

.card_button:hover {
  text-decoration: underline;
}

/* Animación al pasar el mouse */
.card_article:hover .card_data,
.card_article:focus-within .card_data {
  animation: show-data 1s forwards;
  opacity: 1;
  transition: opacity .3s;
}

.card_article:hover,
.card_article:focus-within {
  animation: remove-overflow 2s forwards;
}

.card_article:not(:hover) {
  animation: show-overflow 2s forwards;
}

.card_article:not(:hover):not(:focus-within) .card_data {
  animation: remove-data 1s forwards;
}

@keyframes show-data {
  50% {
    transform: translateY(-10rem);
  }
  100% {
    transform: translateY(-7rem);
  }
}

@keyframes remove-overflow {
  to {
    overflow: initial;
  }
}

@keyframes remove-data {
  0% {
    transform: translateY(-7rem);
  }
  50% {
    transform: translateY(-10rem);
  }
  100% {
    transform: translateY(.5rem);
  }
}

@keyframes show-overflow {
  0% {
    overflow: initial;
    pointer-events: none;
  }
  50% {
    overflow: hidden;
  }
}

/* En pantallas táctiles (sin hover) la ficha se muestra siempre */
@media (hover: none) {
  .card_data {
    position: relative;
    bottom: auto;
    margin-top: -3rem;
    opacity: 1;
    animation: none !important;
  }

  .card_article {
    overflow: visible;
    animation: none !important;
  }
}

@media screen and (max-width: 340px) {
  .card_data {
    padding: 1rem;
  }
}
</style>
