<script setup>
// Catálogo por marca: reemplaza a SEGUNDA.HTML, tercera.html y cuarta.html.
// Un solo componente que recibe la marca por la URL (/catalogo/adidas, /catalogo/nike...).
// Los filtros viven en un solo buscador (BuscadorCatalogo) y "solo ofertas" es un
// botón flotante para activarlo desde cualquier parte de la página.
import { computed, reactive, ref, watch } from 'vue'
import BuscadorCatalogo from '@/components/BuscadorCatalogo.vue'
import ProductoCard from '@/components/ProductoCard.vue'
import productoService from '@/services/productoService'
import { useCatalogoStore } from '@/stores/catalogo'

const props = defineProps({
  marca: { type: String, required: true },
})

const catalogo = useCatalogoStore()
const productos = ref([])
const cargando = ref(true)
const error = ref('')
const FILTROS_INICIALES = { q: '', talla: '', color: '', orden: 'catalogo', oferta: false }
const filtros = reactive({ ...FILTROS_INICIALES })

const titulo = computed(() => (catalogo.marcaPorSlug(props.marca)?.nombre ?? props.marca).toUpperCase())
const conteo = computed(() => {
  const n = productos.value.length
  const base = `${n} ${n === 1 ? 'modelo' : 'modelos'}`
  return filtros.oferta ? `${base} en oferta` : base
})

let consultaActual = 0

async function cargar() {
  const consulta = ++consultaActual
  cargando.value = true
  error.value = ''
  try {
    const resultado = await productoService.listar({ marca: props.marca, por_pagina: 60, ...filtros, oferta: filtros.oferta ? 1 : '' })
    if (consulta === consultaActual) productos.value = resultado.items
  } catch (e) {
    if (consulta === consultaActual) error.value = e.message
  } finally {
    if (consulta === consultaActual) cargando.value = false
  }
}

// Al cambiar de marca se limpian los filtros y se recarga.
watch(
  () => props.marca,
  () => {
    Object.assign(filtros, FILTROS_INICIALES)
    cargar()
    catalogo.cargar().catch(() => {})
  },
  { immediate: true },
)

// El buscador ya espera a que el usuario deje de escribir antes de cambiar "q".
watch(
  () => [filtros.q, filtros.talla, filtros.color, filtros.orden, filtros.oferta],
  () => cargar(),
)

function limpiarFiltros() {
  Object.assign(filtros, FILTROS_INICIALES)
}

function alternarOfertas() {
  filtros.oferta = !filtros.oferta
  window.scrollTo({ top: 0, behavior: 'smooth' })
}
</script>

<template>
  <main id="containerCarrito">
    <header class="cabecera">
      <span class="eco" aria-hidden="true">{{ titulo }}</span>
      <div class="titulos">
        <p class="antetitulo">Catálogo</p>
        <h2 class="titulo-marca">{{ titulo }}<span class="punto">.</span></h2>
        <p class="conteo" aria-live="polite">{{ cargando && !productos.length ? 'Cargando...' : conteo }}</p>
      </div>
      <BuscadorCatalogo
        v-model:q="filtros.q"
        v-model:talla="filtros.talla"
        v-model:color="filtros.color"
        v-model:orden="filtros.orden"
      />
    </header>

    <div v-if="error" class="estado">
      <p class="alerta alerta-error">{{ error }}</p>
      <button type="button" class="boton" @click="cargar">Reintentar</button>
    </div>

    <p v-else-if="cargando && !productos.length" class="cargando">
      <i class="fa-solid fa-spinner fa-spin"></i>Cargando productos...
    </p>

    <div v-else-if="!productos.length" class="estado">
      <p>{{ filtros.oferta ? 'No hay ofertas con esos filtros.' : 'No encontramos productos con esos filtros.' }}</p>
      <button type="button" class="boton" @click="limpiarFiltros">Ver todos</button>
    </div>

    <section v-else class="container" :class="{ actualizando: cargando }">
      <div class="card_container">
        <ProductoCard v-for="producto in productos" :key="producto.id" :producto="producto" />
      </div>
    </section>

    <!-- Botón flotante de ofertas (abajo a la izquierda; el carrito está a la derecha) -->
    <button
      type="button"
      class="flotante-ofertas"
      :class="{ activo: filtros.oferta }"
      :aria-pressed="filtros.oferta"
      @click="alternarOfertas"
    >
      <i :class="filtros.oferta ? 'fa-solid fa-xmark' : 'fa-solid fa-tag'"></i>
      {{ filtros.oferta ? 'Ver todo' : 'Ver ofertas' }}
    </button>
  </main>
</template>

<style scoped>
/* Estilos originales del contenedor del catálogo (segunda.css) */
#containerCarrito {
  padding-top: var(--alto-header);
  min-height: 70vh;
}

.container {
  display: grid;
  place-items: center;
  margin-inline: 1.5rem;
  padding-block: 2rem 10rem;
  transition: opacity .2s;
}

.container.actualizando {
  opacity: .6;
}

.card_container {
  display: grid;
  row-gap: 3.5rem;
}

@media screen and (min-width: 768px) {
  .card_container {
    grid-template-columns: repeat(2, 1fr);
    column-gap: 1.5rem;
  }
}

@media screen and (min-width: 1150px) {
  .card_container {
    grid-template-columns: repeat(3, 1fr);
  }
}

/* ---------- Encabezado: título grande + buscador (mismo estilo del carrusel) ---------- */
.cabecera {
  position: relative;
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 24px;
  max-width: 1092px; /* mismo ancho que la cuadrícula de 3 tarjetas */
  margin: 0 auto;
  padding: 48px 16px 8px;
}

/* Nombre de la marca gigante y solo con borde, de fondo */
.eco {
  position: absolute;
  left: 0;
  top: 0;
  font-size: clamp(5rem, 14vw, 11rem);
  font-weight: 900;
  line-height: 1;
  letter-spacing: -0.04em;
  color: transparent;
  -webkit-text-stroke: 1.5px rgba(0, 0, 0, 0.06);
  white-space: nowrap;
  pointer-events: none;
  user-select: none;
}

.titulos {
  position: relative;
}

.antetitulo {
  margin-bottom: 4px;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.25em;
  text-transform: uppercase;
  color: #c24e00;
}

.titulo-marca {
  font-size: clamp(2.6rem, 6vw, 4rem);
  font-weight: 800;
  line-height: 1;
  letter-spacing: -0.03em;
  color: #111;
}

.punto {
  color: var(--naranja);
}

.conteo {
  margin-top: 8px;
  font-size: 0.95rem;
  color: var(--texto-secundario);
}

/* ---------- Botón flotante de ofertas ---------- */
.flotante-ofertas {
  position: fixed;
  left: 16px;
  bottom: 20px;
  z-index: 900;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 14px 22px;
  font-weight: 700;
  color: #fff;
  background: #111;
  border: 2px solid var(--naranja);
  border-radius: 999px;
  box-shadow: 0 12px 30px -8px rgba(0, 0, 0, 0.5);
  cursor: pointer;
  transition: transform 0.2s ease, background-color 0.2s ease;
}

.flotante-ofertas i {
  color: var(--naranja);
}

.flotante-ofertas:hover {
  transform: translateY(-3px);
}

.flotante-ofertas.activo {
  color: #000;
  background: var(--naranja);
  border-color: #000;
}

.flotante-ofertas.activo i {
  color: #000;
}

.flotante-ofertas:focus-visible {
  outline: 3px solid #000;
  outline-offset: 3px;
}

.estado {
  display: grid;
  justify-items: center;
  gap: 14px;
  padding: 50px 16px;
  text-align: center;
}

@media (max-width: 700px) {
  .cabecera {
    padding-top: 32px;
  }

  .flotante-ofertas {
    padding: 12px 18px;
  }
}
</style>
