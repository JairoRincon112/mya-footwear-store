<script setup>
// Buscador "inteligente" del catálogo: un solo campo reemplaza la barra de filtros.
// - Al hacer clic se despliega un panel con tallas, colores y orden.
// - Si el cliente escribe una talla o un color ("negro 42"), se convierten solos
//   en filtros (fichas dentro del campo) y el resto del texto busca por modelo.
// - Cada filtro activo se ve como una ficha con su botón para quitarlo.
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useCatalogoStore } from '@/stores/catalogo'

const q = defineModel('q', { type: String, default: '' })
const talla = defineModel('talla', { default: '' })
const color = defineModel('color', { default: '' })
const orden = defineModel('orden', { type: String, default: 'catalogo' })

const catalogo = useCatalogoStore()
const abierto = ref(false)
const contenedor = ref(null)
const campo = ref(null)
const texto = ref(q.value)

const ORDENES = [
  { valor: 'catalogo', texto: 'Recomendados' },
  { valor: 'precio_asc', texto: 'Menor precio' },
  { valor: 'precio_desc', texto: 'Mayor precio' },
  { valor: 'descuento', texto: 'Mayor descuento' },
  { valor: 'nombre', texto: 'Nombre A-Z' },
]

const tallaActiva = computed(() => catalogo.tallas.find((t) => t.id === talla.value) ?? null)
const colorActivo = computed(() => catalogo.colores.find((c) => c.id === color.value) ?? null)
const ordenActivo = computed(() => ORDENES.find((o) => o.valor === orden.value && o.valor !== 'catalogo') ?? null)
const cantidadFiltros = computed(() => [tallaActiva.value, colorActivo.value, ordenActivo.value].filter(Boolean).length)

/** Quita tildes y pasa a minúsculas para comparar ("Café" = "cafe"). */
const normalizar = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()

/**
 * Revisa cada palabra escrita: si es una talla (42, t42, talla 42) o un color
 * conocido, la convierte en filtro y la quita del texto de búsqueda.
 */
function interpretar(valor) {
  const restantes = []
  const palabras = valor.split(/\s+/).filter(Boolean)
  for (let i = 0; i < palabras.length; i++) {
    const palabra = normalizar(palabras[i])
    if (palabra === 'talla' && palabras[i + 1]) continue // "talla 42": la palabra "talla" se descarta
    const numero = palabra.replace(/^t(alla)?/, '')
    const tallaEncontrada = catalogo.tallas.find((t) => t.valor === numero)
    const colorEncontrado = catalogo.colores.find((c) => normalizar(c.nombre) === palabra)
    if (tallaEncontrada) talla.value = tallaEncontrada.id
    else if (colorEncontrado) color.value = colorEncontrado.id
    else restantes.push(palabras[i])
  }
  return restantes.join(' ')
}

// Se interpreta cuando el cliente termina una palabra (espacio) o deja de escribir.
let temporizador = null
watch(texto, (valor) => {
  clearTimeout(temporizador)
  const aplicar = () => {
    const limpio = interpretar(valor)
    if (limpio !== valor.trim()) texto.value = limpio + (valor.endsWith(' ') && limpio ? ' ' : '')
    q.value = limpio.trim()
  }
  if (valor.endsWith(' ')) aplicar()
  else temporizador = setTimeout(aplicar, 450)
})

// Si el padre limpia los filtros (cambio de marca), el campo también se limpia.
watch(q, (valor) => {
  if (valor !== texto.value.trim()) texto.value = valor
})

/** Sugerencias según lo que se está escribiendo (última palabra). */
const sugerencias = computed(() => {
  const ultima = normalizar(texto.value.split(/\s+/).pop() ?? '')
  if (!ultima) return []
  const tallas = catalogo.tallas
    .filter((t) => t.valor.startsWith(ultima.replace(/^t(alla)?/, '')) && /\d/.test(ultima))
    .map((t) => ({ tipo: 'talla', id: t.id, texto: `Talla ${t.valor}` }))
  const colores = catalogo.colores
    .filter((c) => normalizar(c.nombre).startsWith(ultima))
    .map((c) => ({ tipo: 'color', id: c.id, texto: c.nombre, hex: c.hex }))
  return [...tallas, ...colores].slice(0, 6)
})

function elegirSugerencia(s) {
  if (s.tipo === 'talla') talla.value = s.id
  else color.value = s.id
  const palabras = texto.value.trim().split(/\s+/)
  palabras.pop()
  texto.value = palabras.join(' ')
  q.value = texto.value
  campo.value?.focus()
}

const alternarTalla = (id) => { talla.value = talla.value === id ? '' : id }
const alternarColor = (id) => { color.value = color.value === id ? '' : id }

function limpiarTodo() {
  texto.value = ''
  q.value = ''
  talla.value = ''
  color.value = ''
  orden.value = 'catalogo'
}

// Borrar con la tecla de retroceso en el campo vacío quita la última ficha.
function teclaCampo(evento) {
  if (evento.key === 'Escape') abierto.value = false
  if (evento.key === 'Enter' && sugerencias.value.length) {
    evento.preventDefault()
    elegirSugerencia(sugerencias.value[0])
  }
  if (evento.key === 'Backspace' && !texto.value) {
    if (ordenActivo.value) orden.value = 'catalogo'
    else if (colorActivo.value) color.value = ''
    else if (tallaActiva.value) talla.value = ''
  }
}

// Cierra el panel al hacer clic fuera
function clicFuera(evento) {
  if (contenedor.value && !contenedor.value.contains(evento.target)) abierto.value = false
}
onMounted(() => document.addEventListener('pointerdown', clicFuera))
onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', clicFuera)
  clearTimeout(temporizador)
})
</script>

<template>
  <div ref="contenedor" class="buscador" :class="{ abierto }" role="search">
    <div class="caja" @click="campo?.focus()">
      <i class="fa-solid fa-magnifying-glass icono" aria-hidden="true"></i>

      <!-- Fichas de los filtros activos -->
      <button v-if="tallaActiva" type="button" class="ficha" :aria-label="`Quitar talla ${tallaActiva.valor}`" @click.stop="talla = ''">
        Talla {{ tallaActiva.valor }} <i class="fa-solid fa-xmark"></i>
      </button>
      <button v-if="colorActivo" type="button" class="ficha" :aria-label="`Quitar color ${colorActivo.nombre}`" @click.stop="color = ''">
        <span class="muestra" :style="{ background: colorActivo.hex ?? 'conic-gradient(red, yellow, lime, cyan, blue, magenta, red)' }"></span>
        {{ colorActivo.nombre }} <i class="fa-solid fa-xmark"></i>
      </button>
      <button v-if="ordenActivo" type="button" class="ficha" :aria-label="`Quitar orden ${ordenActivo.texto}`" @click.stop="orden = 'catalogo'">
        {{ ordenActivo.texto }} <i class="fa-solid fa-xmark"></i>
      </button>

      <label class="solo-lectores" for="buscador-catalogo">Buscar modelo, talla o color</label>
      <input
        id="buscador-catalogo"
        ref="campo"
        v-model="texto"
        type="search"
        maxlength="100"
        autocomplete="off"
        :placeholder="cantidadFiltros ? 'Seguir buscando...' : 'Busca modelo, talla o color (ej: negro 42)'"
        @focus="abierto = true"
        @keydown="teclaCampo"
      >

      <button
        type="button"
        class="boton-filtros"
        :aria-expanded="abierto"
        aria-controls="panel-filtros"
        @click.stop="abierto = !abierto"
      >
        <i class="fa-solid fa-sliders"></i>
        <span class="texto-filtros">Filtros</span>
        <span v-if="cantidadFiltros" class="contador">{{ cantidadFiltros }}</span>
      </button>
    </div>

    <!-- Panel desplegable -->
    <div v-show="abierto" id="panel-filtros" class="panel">
      <div v-if="sugerencias.length" class="grupo">
        <p class="grupo-titulo">Sugerencias</p>
        <div class="opciones">
          <button v-for="s in sugerencias" :key="s.tipo + s.id" type="button" class="opcion sugerida" @click="elegirSugerencia(s)">
            <span v-if="s.tipo === 'color'" class="muestra" :style="{ background: s.hex ?? 'conic-gradient(red, yellow, lime, cyan, blue, magenta, red)' }"></span>
            <i v-else class="fa-solid fa-shoe-prints"></i>
            {{ s.texto }}
          </button>
        </div>
      </div>

      <div class="grupo">
        <p class="grupo-titulo">Talla</p>
        <div class="opciones tallas">
          <button
            v-for="t in catalogo.tallas"
            :key="t.id"
            type="button"
            class="opcion"
            :class="{ activa: talla === t.id }"
            :aria-pressed="talla === t.id"
            @click="alternarTalla(t.id)"
          >
            {{ t.valor }}
          </button>
        </div>
      </div>

      <div class="grupo">
        <p class="grupo-titulo">Color</p>
        <div class="opciones">
          <button
            v-for="c in catalogo.colores"
            :key="c.id"
            type="button"
            class="opcion"
            :class="{ activa: color === c.id }"
            :aria-pressed="color === c.id"
            @click="alternarColor(c.id)"
          >
            <span class="muestra" :style="{ background: c.hex ?? 'conic-gradient(red, yellow, lime, cyan, blue, magenta, red)' }"></span>
            {{ c.nombre }}
          </button>
        </div>
      </div>

      <div class="grupo">
        <p class="grupo-titulo">Ordenar por</p>
        <div class="opciones">
          <button
            v-for="o in ORDENES"
            :key="o.valor"
            type="button"
            class="opcion"
            :class="{ activa: orden === o.valor }"
            :aria-pressed="orden === o.valor"
            @click="orden = o.valor"
          >
            {{ o.texto }}
          </button>
        </div>
      </div>

      <div class="pie-panel">
        <button type="button" class="enlace" @click="limpiarTodo">Limpiar todo</button>
        <button type="button" class="listo" @click="abierto = false">Ver resultados</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.buscador {
  position: relative;
  width: min(520px, 100%);
}

.caja {
  display: flex;
  align-items: center;
  gap: 6px;
  height: 56px;
  padding: 6px 6px 6px 18px;
  background: #fff;
  border: 1px solid #ddd;
  border-radius: 28px;
  box-shadow: 0 8px 24px -12px rgba(0, 0, 0, 0.25);
  cursor: text;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.abierto .caja,
.caja:focus-within {
  border-color: var(--naranja);
  box-shadow: 0 10px 30px -12px rgba(255, 102, 0, 0.45);
}

.icono {
  color: #888;
}

.caja input {
  flex: 1 1 60px;
  min-width: 60px;
  padding: 8px 4px;
  background: transparent;
  border: 0;
  outline: none;
}

.caja input:focus-visible {
  outline: none;
}

.ficha {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  white-space: nowrap;
  padding: 5px 10px;
  font-size: 0.82rem;
  font-weight: 600;
  color: #fff;
  background: #1a1a1a;
  border: 0;
  border-radius: 999px;
  cursor: pointer;
}

/* aro claro para que colores oscuros (negro) se vean sobre la ficha negra */
.ficha .muestra {
  box-shadow: 0 0 0 1.5px rgba(255, 255, 255, 0.7);
}

.ficha i {
  font-size: 0.75rem;
  opacity: 0.7;
}

.ficha:hover i {
  opacity: 1;
}

.boton-filtros {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
  margin-left: auto;
  padding: 10px 16px;
  font-weight: 600;
  color: #000;
  background: var(--naranja);
  border: 0;
  border-radius: 999px;
  cursor: pointer;
  transition: background-color 0.2s;
}

.boton-filtros:hover {
  background: var(--naranja-claro);
}

.contador {
  display: grid;
  place-items: center;
  min-width: 20px;
  height: 20px;
  font-size: 0.72rem;
  color: #fff;
  background: #000;
  border-radius: 999px;
}

.panel {
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  z-index: 950; /* encima de los botones flotantes (carrito y ofertas), debajo del menú */
  display: grid;
  gap: 16px;
  width: min(560px, calc(100vw - 32px));
  padding: 20px;
  background: #fff;
  border-radius: 20px;
  box-shadow: 0 24px 60px -16px rgba(0, 0, 0, 0.35);
  animation: abrir 0.18s ease-out;
}

@keyframes abrir {
  from { opacity: 0; transform: translateY(-6px); }
}

.grupo-titulo {
  margin-bottom: 8px;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: #777;
}

.opciones {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.opciones.tallas .opcion {
  justify-content: center;
  min-width: 46px;
}

.opcion {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 12px;
  font-size: 0.85rem;
  background: #f4f1f1;
  border: 1px solid transparent;
  border-radius: 999px;
  cursor: pointer;
  transition: background-color 0.15s, border-color 0.15s;
}

.opcion:hover {
  border-color: #ccc;
}

.opcion.activa {
  font-weight: 600;
  color: #fff;
  background: #1a1a1a;
}

.opcion.sugerida {
  background: #fff3eb;
  border-color: #ffd2b3;
}

.muestra {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.2);
}

.pie-panel {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 12px;
  border-top: 1px solid #eee;
}

.enlace {
  font-weight: 500;
  color: #555;
  text-decoration: underline;
  background: none;
  border: 0;
  cursor: pointer;
}

.listo {
  padding: 10px 20px;
  font-weight: 600;
  color: #fff;
  background: #1a1a1a;
  border: 0;
  border-radius: 999px;
  cursor: pointer;
}

@media (max-width: 480px) {
  .texto-filtros {
    display: none;
  }

  .panel {
    right: auto;
    left: 0;
  }
}
</style>
