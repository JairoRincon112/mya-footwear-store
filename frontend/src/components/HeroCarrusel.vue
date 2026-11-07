<script setup>
// Carrusel principal del inicio.
// - Diapositiva 1: presentación de la tienda con un abanico de productos reales.
// - Una diapositiva por cada producto en oferta (datos en vivo de la API) con
//   cuenta regresiva hasta que termina la oferta.
// - Diapositiva final: invitación a Instagram.
// Avanza solo (la barra de cada indicador muestra el tiempo), se pausa con el
// mouse o el foco, responde a las flechas del teclado y al deslizar en celular.
// Si el usuario pidió "reducir movimiento" en su sistema, no avanza solo.
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import productoService from '@/services/productoService'
import { urlImagen } from '@/services/api'
import { formatearPrecio } from '@/utils/formato'
import celular from '@/assets/img/celular.jpg'
import imagenAdidas from '@/assets/img/home-adidas.jpg'
import imagenPuma from '@/assets/img/home-puma.png'

const DURACION_MS = 7000

const ofertas = ref([])
const destacados = ref([])
const actual = ref(0)
const pausado = ref(false)
const ahora = ref(Date.now())
const reducirMovimiento = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false

/** Lista de diapositivas: se arma con lo que haya llegado de la API. */
const diapositivas = computed(() => [
  { tipo: 'intro', etiqueta: 'Bienvenida' },
  ...ofertas.value.map((p) => ({ tipo: 'oferta', etiqueta: p.nombre, producto: p })),
  { tipo: 'social', etiqueta: 'Instagram' },
])

/** Tres fotos para el abanico de la portada (respaldo: imágenes locales). */
const abanico = computed(() => {
  const fotos = destacados.value.map((p) => ({ src: urlImagen(p.imagen), alt: p.nombre }))
  return fotos.length === 3
    ? fotos
    : [
        { src: imagenAdidas, alt: 'Adidas Adi2000' },
        { src: celular, alt: 'Perfil de Instagram de la tienda' },
        { src: imagenPuma, alt: 'Puma x Rocket League' },
      ]
})

const porcentaje = (p) => Math.round((1 - p.precio / p.precio_regular) * 100)

/** Tiempo restante de la oferta en días, horas, minutos y segundos. */
function restante(fin) {
  if (!fin) return null
  const ms = new Date(fin.replace(' ', 'T')).getTime() - ahora.value
  if (Number.isNaN(ms) || ms <= 0) return null
  const s = Math.floor(ms / 1000)
  return [
    { valor: Math.floor(s / 86400), unidad: 'días' },
    { valor: Math.floor((s % 86400) / 3600), unidad: 'horas' },
    { valor: Math.floor((s % 3600) / 60), unidad: 'min' },
    { valor: s % 60, unidad: 'seg' },
  ]
}

const dosDigitos = (n) => String(n).padStart(2, '0')

function ir(indice) {
  const total = diapositivas.value.length
  actual.value = (indice + total) % total
}
const siguiente = () => ir(actual.value + 1)
const anterior = () => ir(actual.value - 1)

function teclado(evento) {
  if (evento.key === 'ArrowRight') siguiente()
  else if (evento.key === 'ArrowLeft') anterior()
}

// Deslizar con el dedo en celulares
let inicioX = null
const tocarInicio = (e) => { inicioX = e.touches[0].clientX }
function tocarFin(e) {
  if (inicioX === null) return
  const dx = e.changedTouches[0].clientX - inicioX
  if (Math.abs(dx) > 50) (dx < 0 ? siguiente : anterior)()
  inicioX = null
}

// Efecto 3D: la foto se inclina siguiendo el mouse
const inclinacion = ref({ x: 0, y: 0 })
function moverMouse(e) {
  if (reducirMovimiento) return
  const caja = e.currentTarget.getBoundingClientRect()
  inclinacion.value = {
    x: ((e.clientX - caja.left) / caja.width - 0.5) * 14,
    y: ((e.clientY - caja.top) / caja.height - 0.5) * -14,
  }
}
const salirMouse = () => { inclinacion.value = { x: 0, y: 0 } }

let reloj = null
onMounted(async () => {
  reloj = setInterval(() => { ahora.value = Date.now() }, 1000)
  try {
    // Ofertas vigentes (mayor descuento primero) y el producto más nuevo de cada marca
    const [enOferta, ...porMarca] = await Promise.all([
      productoService.listar({ oferta: 1, orden: 'descuento', por_pagina: 6 }),
      ...['adidas', 'nike', 'puma'].map((marca) => productoService.listar({ marca, por_pagina: 1, orden: 'recientes' })),
    ])
    ofertas.value = enOferta.items
    destacados.value = porMarca.map((r) => r.items[0]).filter(Boolean)
  } catch {
    // Sin API el carrusel sigue funcionando con la portada y la diapositiva de Instagram
  }
})
onBeforeUnmount(() => clearInterval(reloj))
</script>

<template>
  <section
    class="hero"
    aria-roledescription="carrusel"
    aria-label="Destacados de la tienda"
    tabindex="0"
    @keydown="teclado"
    @mouseenter="pausado = true"
    @mouseleave="pausado = false; salirMouse()"
    @focusin="pausado = true"
    @focusout="pausado = false"
    @touchstart.passive="tocarInicio"
    @touchend="tocarFin"
    @mousemove="moverMouse"
  >
    <div class="brillo" aria-hidden="true"></div>

    <div
      v-for="(d, i) in diapositivas"
      :key="d.tipo + i"
      class="slide"
      :class="{ activa: i === actual }"
      role="group"
      aria-roledescription="diapositiva"
      :aria-label="`${i + 1} de ${diapositivas.length}: ${d.etiqueta}`"
      :aria-hidden="i !== actual"
      :inert="i !== actual"
    >
      <!-- Texto gigante de fondo -->
      <span class="texto-fondo" aria-hidden="true">
        {{ d.tipo === 'oferta' ? d.producto.marca.nombre : d.tipo === 'social' ? '@MYA' : 'MyA' }}
      </span>

      <!-- ===== Portada ===== -->
      <template v-if="d.tipo === 'intro'">
        <div class="texto">
          <h1>Pisa con <span class="resalte">estilo</span>.<br>Camina con actitud.</h1>
          <p class="descripcion">
            MyA FOOTWEAR STORE lleva las últimas tendencias a todos: calzado moderno y de alta calidad para
            que expreses tu estilo único.
          </p>
          <div class="acciones">
            <RouterLink to="/catalogo/adidas" class="cta">Ver catálogo <i class="fa-solid fa-arrow-right"></i></RouterLink>
            <RouterLink :to="{ hash: '#informacion' }" class="cta cta-fantasma">Conócenos</RouterLink>
          </div>
          <ul class="marcas">
            <li><RouterLink to="/catalogo/adidas">ADIDAS</RouterLink></li>
            <li><RouterLink to="/catalogo/nike">NIKE</RouterLink></li>
            <li><RouterLink to="/catalogo/puma">PUMA</RouterLink></li>
          </ul>
        </div>
        <div class="visual abanico" :style="{ '--rx': `${inclinacion.y}deg`, '--ry': `${inclinacion.x}deg` }">
          <img
            v-for="(foto, n) in abanico"
            :key="n"
            :src="foto.src"
            :alt="foto.alt"
            :class="`carta carta-${n}`"
            :loading="i === 0 ? 'eager' : 'lazy'"
          >
        </div>
      </template>

      <!-- ===== Oferta en vivo ===== -->
      <template v-else-if="d.tipo === 'oferta'">
        <div class="texto">
          <p class="antetitulo"><i class="fa-solid fa-fire"></i> Oferta {{ d.producto.marca.nombre }}</p>
          <h2 class="titulo-producto">{{ d.producto.nombre }}</h2>
          <div class="precios">
            <span class="precio">{{ formatearPrecio(d.producto.precio) }}</span>
            <del class="precio-antes">{{ formatearPrecio(d.producto.precio_regular) }}</del>
          </div>
          <div v-if="restante(d.producto.oferta_fin)" class="cuenta" aria-label="Tiempo restante de la oferta">
            <span class="cuenta-titulo">Termina en</span>
            <div class="cuenta-cajas">
              <span v-for="parte in restante(d.producto.oferta_fin)" :key="parte.unidad" class="caja">
                <strong>{{ dosDigitos(parte.valor) }}</strong>{{ parte.unidad }}
              </span>
            </div>
          </div>
          <p v-else class="descripcion">Precio especial por tiempo limitado, hasta agotar existencias.</p>
          <div class="acciones">
            <RouterLink :to="{ name: 'producto', params: { slug: d.producto.slug } }" class="cta">
              Comprar ahora <i class="fa-solid fa-cart-shopping"></i>
            </RouterLink>
            <RouterLink :to="`/catalogo/${d.producto.marca.slug}`" class="cta cta-fantasma">Más {{ d.producto.marca.nombre }}</RouterLink>
          </div>
        </div>
        <div class="visual" :style="{ '--rx': `${inclinacion.y}deg`, '--ry': `${inclinacion.x}deg` }">
          <div class="marco">
            <img :src="urlImagen(d.producto.imagen)" :alt="d.producto.nombre" loading="lazy">
            <span class="sello"><strong>-{{ porcentaje(d.producto) }}%</strong>OFF</span>
          </div>
        </div>
      </template>

      <!-- ===== Instagram ===== -->
      <template v-else>
        <div class="texto">
          <p class="antetitulo"><i class="fa-brands fa-instagram"></i> Comunidad</p>
          <h2 class="titulo-producto">Síguenos en <span class="resalte">Instagram</span></h2>
          <p class="descripcion">
            Lanzamientos, fotos reales de cada par y promociones antes que nadie. Únete a la comunidad
            <strong>@mya112102</strong>.
          </p>
          <div class="acciones">
            <a href="https://www.instagram.com/mya112102/" target="_blank" rel="noopener" class="cta">
              Ir a Instagram <i class="fa-solid fa-arrow-up-right-from-square"></i>
            </a>
            <RouterLink to="/contacto" class="cta cta-fantasma">Escríbenos</RouterLink>
          </div>
        </div>
        <div class="visual" :style="{ '--rx': `${inclinacion.y}deg`, '--ry': `${inclinacion.x}deg` }">
          <div class="marco marco-celular">
            <img :src="celular" alt="Perfil de Instagram de la tienda en un celular" loading="lazy">
          </div>
        </div>
      </template>
    </div>

    <!-- Controles -->
    <button type="button" class="flecha flecha-izq" aria-label="Diapositiva anterior" @click="anterior">
      <i class="fa-solid fa-chevron-left"></i>
    </button>
    <button type="button" class="flecha flecha-der" aria-label="Diapositiva siguiente" @click="siguiente">
      <i class="fa-solid fa-chevron-right"></i>
    </button>

    <div class="indicadores">
      <button
        v-for="(d, i) in diapositivas"
        :key="'i' + i"
        type="button"
        class="indicador"
        :class="{ activo: i === actual, visto: i < actual }"
        :aria-label="`Ir a: ${d.etiqueta}`"
        :aria-current="i === actual"
        @click="ir(i)"
      >
        <span class="etiqueta-indicador">{{ d.etiqueta }}</span>
        <span class="pista">
          <span
            v-if="i === actual"
            :key="actual"
            class="progreso"
            :class="{ quieto: reducirMovimiento }"
            :style="{ animationDuration: `${DURACION_MS}ms`, animationPlayState: pausado ? 'paused' : 'running' }"
            @animationend="siguiente"
          ></span>
        </span>
      </button>
    </div>
  </section>
</template>

<style scoped>
.hero {
  --naranja-hero: #ff6600;
  /* alto de la imagen según la pantalla, para que el carrusel completo quepa sin desplazarse */
  --alto-visual: clamp(300px, calc(100vh - var(--alto-header) - 170px), 460px);
  position: relative;
  min-height: min(680px, calc(100vh - var(--alto-header)));
  overflow: hidden;
  color: #fff;
  background: radial-gradient(120% 90% at 85% 40%, #3a1a05 0%, #161210 55%, #0d0b0a 100%);
  outline: none;
}

.hero:focus-visible {
  box-shadow: inset 0 0 0 3px var(--naranja-hero);
}

/* Halo naranja que respira detrás de la imagen */
.brillo {
  position: absolute;
  right: 12%;
  top: 50%;
  width: 520px;
  height: 520px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(255, 102, 0, 0.55) 0%, rgba(255, 102, 0, 0) 70%);
  transform: translateY(-50%);
  filter: blur(10px);
  animation: respirar 6s ease-in-out infinite;
  pointer-events: none;
}

@keyframes respirar {
  50% { transform: translateY(-50%) scale(1.12); opacity: 0.75; }
}

/* ---------- Diapositivas ---------- */
.slide {
  position: absolute;
  inset: 0;
  display: grid;
  grid-template-columns: 1.05fr 1fr;
  align-items: center;
  gap: 40px;
  max-width: 1200px;
  margin: 0 auto;
  padding: 28px 80px 96px;
  opacity: 0;
  visibility: hidden;
  transition: opacity 0.6s ease, visibility 0.6s;
}

.slide.activa {
  position: relative;
  opacity: 1;
  visibility: visible;
}

.texto-fondo {
  position: absolute;
  left: 50%;
  top: 46%;
  transform: translate(-50%, -50%);
  font-size: clamp(6rem, 20vw, 17rem);
  font-weight: 900;
  line-height: 1;
  letter-spacing: -0.04em;
  white-space: nowrap;
  text-transform: uppercase;
  color: transparent;
  -webkit-text-stroke: 1.5px rgba(255, 255, 255, 0.07);
  pointer-events: none;
  user-select: none;
}

.texto {
  position: relative;
  z-index: 1;
}

/* Entrada escalonada del texto al activarse la diapositiva */
.slide.activa .texto > * {
  animation: subir 0.7s cubic-bezier(0.2, 0.8, 0.2, 1) both;
}
.slide.activa .texto > *:nth-child(2) { animation-delay: 0.08s; }
.slide.activa .texto > *:nth-child(3) { animation-delay: 0.16s; }
.slide.activa .texto > *:nth-child(4) { animation-delay: 0.24s; }
.slide.activa .texto > *:nth-child(5) { animation-delay: 0.32s; }

@keyframes subir {
  from { opacity: 0; transform: translateY(24px); }
}

.antetitulo {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 18px;
  padding: 6px 14px;
  font-size: 0.8rem;
  font-weight: 600;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--naranja-hero);
  border: 1px solid rgba(255, 102, 0, 0.45);
  border-radius: 999px;
  background: rgba(255, 102, 0, 0.08);
}

.texto h1,
.titulo-producto {
  color: #fff;
  font-size: clamp(2.1rem, 4.2vw, 3.5rem);
  font-weight: 800;
  line-height: 1.05;
  letter-spacing: -0.02em;
  margin-bottom: 18px;
}

.resalte {
  color: var(--naranja-hero);
}

.descripcion {
  max-width: 480px;
  margin-bottom: 28px;
  font-size: 1.02rem;
  line-height: 1.6;
  color: rgba(255, 255, 255, 0.72);
}

.acciones {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.cta {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 14px 26px;
  font-weight: 600;
  color: #000;
  background: var(--naranja-hero);
  border-radius: 999px;
  box-shadow: 0 10px 30px -8px rgba(255, 102, 0, 0.7);
  transition: transform 0.25s ease, box-shadow 0.25s ease, background-color 0.25s ease;
}

.cta:hover {
  transform: translateY(-2px);
  background: #ff8533;
  box-shadow: 0 14px 34px -8px rgba(255, 102, 0, 0.85);
}

.cta-fantasma {
  color: #fff;
  background: transparent;
  border: 1px solid rgba(255, 255, 255, 0.35);
  box-shadow: none;
}

.cta-fantasma:hover {
  color: #000;
  background: #fff;
  box-shadow: none;
}

.marcas {
  display: flex;
  gap: 28px;
  margin-top: 36px;
}

.marcas a {
  font-size: 0.85rem;
  font-weight: 700;
  letter-spacing: 0.25em;
  color: rgba(255, 255, 255, 0.45);
  transition: color 0.2s;
}

.marcas a:hover {
  color: var(--naranja-hero);
}

/* ---------- Ofertas ---------- */
.precios {
  display: flex;
  align-items: baseline;
  gap: 14px;
  margin-bottom: 22px;
}

.precio {
  font-size: 2.2rem;
  font-weight: 700;
  color: var(--naranja-hero);
}

.precio-antes {
  font-size: 1.1rem;
  color: rgba(255, 255, 255, 0.5);
}

.cuenta {
  margin-bottom: 28px;
}

.cuenta-titulo {
  display: block;
  margin-bottom: 8px;
  font-size: 0.78rem;
  letter-spacing: 0.15em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.55);
}

.cuenta-cajas {
  display: flex;
  gap: 10px;
}

.caja {
  display: grid;
  min-width: 64px;
  padding: 10px 8px;
  font-size: 0.7rem;
  text-align: center;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: rgba(255, 255, 255, 0.6);
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  backdrop-filter: blur(6px);
}

.caja strong {
  font-size: 1.6rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  color: #fff;
}

/* ---------- Imagen con inclinación 3D ---------- */
.visual {
  position: relative;
  z-index: 1;
  display: flex;
  justify-content: center;
  perspective: 1000px;
}

.marco {
  position: relative;
  height: var(--alto-visual);
  max-width: 100%;
  aspect-ratio: 4 / 5;
  border-radius: 28px;
  transform: rotateX(var(--rx, 0deg)) rotateY(var(--ry, 0deg));
  transition: transform 0.25s ease-out;
  box-shadow: 0 40px 80px -20px rgba(0, 0, 0, 0.8), 0 0 0 1px rgba(255, 255, 255, 0.08);
}

.marco img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: inherit;
}

.slide.activa .marco,
.slide.activa .abanico {
  animation: aparecer 0.9s cubic-bezier(0.2, 0.8, 0.2, 1) both;
}

@keyframes aparecer {
  from { opacity: 0; transform: translateX(60px) rotate(4deg) scale(0.92); }
}

.marco-celular {
  aspect-ratio: 9 / 16;
  border-radius: 36px;
  border: 8px solid #1d1d1d;
}

.sello {
  position: absolute;
  top: -18px;
  right: -18px;
  display: grid;
  place-content: center;
  width: 104px;
  height: 104px;
  font-size: 0.75rem;
  font-weight: 700;
  line-height: 1.1;
  text-align: center;
  letter-spacing: 0.1em;
  color: #000;
  background: var(--naranja-hero);
  border-radius: 50%;
  box-shadow: 0 10px 30px -6px rgba(255, 102, 0, 0.8);
  transform: rotate(12deg) translateZ(40px);
  animation: latir 2.4s ease-in-out infinite;
}

.sello strong {
  font-size: 1.7rem;
  letter-spacing: -0.02em;
}

@keyframes latir {
  50% { transform: rotate(12deg) scale(1.07); }
}

/* Abanico de tres fotos en la portada */
.abanico {
  height: var(--alto-visual);
  transform-style: preserve-3d;
}

.carta {
  position: absolute;
  top: 50%;
  left: 50%;
  height: calc(var(--alto-visual) * 0.72);
  aspect-ratio: 3 / 4;
  object-fit: cover;
  border-radius: 22px;
  border: 4px solid #fff;
  box-shadow: 0 30px 60px -15px rgba(0, 0, 0, 0.8);
  transition: transform 0.5s cubic-bezier(0.2, 0.8, 0.2, 1);
}

.carta-0 { transform: translate(-50%, -50%) translateX(-130px) rotate(-14deg) rotateX(var(--rx)) rotateY(var(--ry)); }
.carta-1 { z-index: 2; transform: translate(-50%, -50%) translateY(-16px) rotateX(var(--rx)) rotateY(var(--ry)); }
.carta-2 { transform: translate(-50%, -50%) translateX(130px) rotate(14deg) rotateX(var(--rx)) rotateY(var(--ry)); }

.abanico:hover .carta-0 { transform: translate(-50%, -50%) translateX(-175px) rotate(-20deg); }
.abanico:hover .carta-2 { transform: translate(-50%, -50%) translateX(175px) rotate(20deg); }

/* ---------- Controles ---------- */
.flecha {
  position: absolute;
  top: 50%;
  z-index: 3;
  display: grid;
  place-items: center;
  width: 48px;
  height: 48px;
  color: #fff;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.18);
  border-radius: 50%;
  cursor: pointer;
  transform: translateY(-50%);
  backdrop-filter: blur(6px);
  transition: background-color 0.2s, color 0.2s;
}

.flecha:hover,
.flecha:focus-visible {
  color: #000;
  background: var(--naranja-hero);
}

.flecha-izq { left: 20px; }
.flecha-der { right: 20px; }

.indicadores {
  position: absolute;
  left: 50%;
  bottom: 28px;
  z-index: 3;
  display: flex;
  gap: 10px;
  width: min(1040px, calc(100% - 32px));
  transform: translateX(-50%);
}

.indicador {
  flex: 1;
  display: grid;
  gap: 8px;
  min-width: 0;
  padding: 6px 0;
  text-align: left;
  color: rgba(255, 255, 255, 0.5);
  background: none;
  border: 0;
  cursor: pointer;
}

.indicador.activo,
.indicador:hover {
  color: #fff;
}

.etiqueta-indicador {
  overflow: hidden;
  font-size: 0.75rem;
  font-weight: 500;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.pista {
  position: relative;
  height: 3px;
  overflow: hidden;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 3px;
}

.indicador.visto .pista {
  background: rgba(255, 255, 255, 0.55);
}

.progreso {
  position: absolute;
  inset: 0;
  background: var(--naranja-hero);
  transform-origin: left;
  animation-name: llenar;
  animation-timing-function: linear;
  animation-fill-mode: forwards;
}

.progreso.quieto {
  animation: none;
}

@keyframes llenar {
  from { transform: scaleX(0); }
  to { transform: scaleX(1); }
}

/* ---------- Celulares y tabletas ---------- */
@media (max-width: 900px) {
  .slide {
    grid-template-columns: minmax(0, 1fr); /* minmax: el contenido ancho (tablas) no estira la página */
    gap: 24px;
    padding: 36px 20px 110px;
    text-align: center;
  }

  .descripcion { margin-left: auto; margin-right: auto; }
  .acciones, .precios, .cuenta-cajas, .marcas { justify-content: center; }
  .visual { order: -1; }
  .hero { --alto-visual: 300px; }
  .carta-0 { transform: translate(-50%, -50%) translateX(-90px) rotate(-14deg); }
  .carta-2 { transform: translate(-50%, -50%) translateX(90px) rotate(14deg); }
  .sello { width: 82px; height: 82px; top: -12px; right: -12px; }
  .sello strong { font-size: 1.3rem; }
  .brillo { right: 50%; top: 28%; width: 340px; height: 340px; transform: translate(50%, -50%); animation: none; }
  .flecha { display: none; }
  .etiqueta-indicador { display: none; }
  .caja { min-width: 56px; }
}

@media (prefers-reduced-motion: reduce) {
  .hero *,
  .brillo {
    animation: none !important;
    transition: none !important;
  }
}
</style>
