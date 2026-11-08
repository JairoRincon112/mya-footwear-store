<script setup>
// Detalle del producto: elegir color y talla (variante), ver stock y agregar al carrito.
import { computed, ref, watch } from 'vue'
import productoService from '@/services/productoService'
import { urlImagen } from '@/services/api'
import PrecioProducto from '@/components/PrecioProducto.vue'
import { limiteDeLinea, useCarritoStore } from '@/stores/carrito'
import { formatearFecha } from '@/utils/formato'

const props = defineProps({
  slug: { type: String, required: true },
})

const carrito = useCarritoStore()
const producto = ref(null)
const cargando = ref(true)
const error = ref('')
const colorId = ref(null)
const tallaId = ref(null)
const cantidad = ref(1)
const imagenActiva = ref(0)
const mensaje = ref('')

async function cargar() {
  cargando.value = true
  error.value = ''
  producto.value = null
  try {
    producto.value = await productoService.obtener(props.slug)
    // Se preselecciona el primer color que tenga unidades disponibles.
    const conStock = producto.value.variantes.find((variante) => variante.stock > 0)
    colorId.value = (conStock ?? producto.value.variantes[0])?.color.id ?? null
    document.title = `${producto.value.nombre} | MyA FOOTWEAR STORE`
  } catch (e) {
    error.value = e.estado === 404 ? 'Este producto no existe o ya no está disponible.' : e.message
  } finally {
    cargando.value = false
  }
}

watch(() => props.slug, cargar, { immediate: true })

/** Colores del producto (sin repetir), con su stock total. */
const colores = computed(() => {
  const mapa = new Map()
  for (const variante of producto.value?.variantes ?? []) {
    const actual = mapa.get(variante.color.id) ?? { ...variante.color, stock: 0 }
    actual.stock += variante.stock
    mapa.set(variante.color.id, actual)
  }
  return [...mapa.values()]
})

/** Tallas disponibles en el color elegido. */
const tallasDelColor = computed(() =>
  (producto.value?.variantes ?? []).filter((variante) => variante.color.id === colorId.value),
)

const varianteElegida = computed(() => tallasDelColor.value.find((variante) => variante.talla.id === tallaId.value) ?? null)

/** Fotos del color elegido; si no hay fotos de ese color se muestran todas. */
const imagenes = computed(() => {
  const todas = producto.value?.imagenes ?? []
  const delColor = todas.filter((imagen) => imagen.color_id === null || imagen.color_id === colorId.value)
  return delColor.length ? delColor : todas
})

const enCarrito = computed(() => carrito.items.find((item) => item.varianteId === varianteElegida.value?.id)?.cantidad ?? 0)
// Respeta el stock y el máximo de unidades por producto que define el negocio.
const limiteVariante = computed(() => limiteDeLinea(varianteElegida.value?.stock ?? 0, producto.value?.maximo_por_linea))
const disponibleParaAgregar = computed(() => Math.max(0, limiteVariante.value - enCarrito.value))

watch(colorId, () => {
  tallaId.value = null
  imagenActiva.value = 0
})

watch(tallaId, () => {
  cantidad.value = 1
  mensaje.value = ''
})

function cambiarCantidad(nueva) {
  cantidad.value = Math.max(1, Math.min(disponibleParaAgregar.value, nueva))
}

function agregarAlCarrito() {
  if (!varianteElegida.value || disponibleParaAgregar.value < 1) return
  carrito.agregar({ ...producto.value, imagen: imagenes.value[0]?.ruta ?? null }, varianteElegida.value, cantidad.value)
  mensaje.value = `Agregaste ${cantidad.value} par(es) talla ${varianteElegida.value.talla.valor} al carrito.`
  cantidad.value = 1
}
</script>

<template>
  <main class="pagina-producto">
    <p v-if="cargando" class="cargando"><i class="fa-solid fa-spinner fa-spin"></i>Cargando producto...</p>

    <div v-else-if="error" class="estado">
      <p class="alerta alerta-error">{{ error }}</p>
      <RouterLink to="/" class="boton">Volver al inicio</RouterLink>
    </div>

    <template v-else-if="producto">
      <nav class="migas" aria-label="Ruta de navegación">
        <RouterLink to="/">Inicio</RouterLink>
        <span>/</span>
        <RouterLink :to="{ name: 'catalogo', params: { marca: producto.marca.slug } }">{{ producto.marca.nombre }}</RouterLink>
        <span>/</span>
        <span aria-current="page">{{ producto.nombre }}</span>
      </nav>

      <div class="producto">
        <div class="galeria">
          <img
            v-if="imagenes.length"
            :src="urlImagen(imagenes[imagenActiva]?.ruta ?? imagenes[0].ruta)"
            :alt="producto.nombre"
            class="imagen-principal"
          >
          <div v-else class="imagen-principal sin-imagen"><i class="fa-regular fa-image"></i></div>

          <div v-if="imagenes.length > 1" class="miniaturas">
            <button
              v-for="(imagen, indice) in imagenes"
              :key="imagen.id"
              type="button"
              :class="{ activa: indice === imagenActiva }"
              :aria-label="`Ver imagen ${indice + 1}`"
              @click="imagenActiva = indice"
            >
              <img :src="urlImagen(imagen.ruta)" alt="">
            </button>
          </div>
        </div>

        <div class="info">
          <p class="marca">{{ producto.marca.nombre.toUpperCase() }}</p>
          <h1>{{ producto.nombre }}</h1>
          <p class="precio">
            <PrecioProducto :precio="producto.precio" :precio-regular="producto.precio_regular" :en-oferta="producto.en_oferta" tamano="grande" />
          </p>
          <p v-if="producto.en_oferta && producto.oferta_fin" class="vence-oferta">
            <i class="fa-solid fa-clock"></i> Oferta válida hasta el {{ formatearFecha(producto.oferta_fin) }}
          </p>
          <p v-if="producto.descripcion" class="descripcion">{{ producto.descripcion }}</p>

          <p v-if="!producto.variantes.length || producto.stock_total === 0" class="alerta alerta-error">
            Este producto está agotado por ahora.
          </p>

          <template v-else>
            <fieldset class="grupo">
              <legend>Color: <strong>{{ colores.find((c) => c.id === colorId)?.nombre }}</strong></legend>
              <div class="opciones">
                <button
                  v-for="color in colores"
                  :key="color.id"
                  type="button"
                  class="opcion-color"
                  :class="{ elegida: color.id === colorId, agotada: color.stock === 0 }"
                  :aria-pressed="color.id === colorId"
                  @click="colorId = color.id"
                >
                  <span class="muestra" :style="color.hex ? { background: color.hex } : null" :class="{ multicolor: !color.hex }"></span>
                  {{ color.nombre }}
                </button>
              </div>
            </fieldset>

            <fieldset class="grupo">
              <legend>Talla (EU)</legend>
              <div class="opciones tallas">
                <button
                  v-for="variante in tallasDelColor"
                  :key="variante.id"
                  type="button"
                  class="opcion-talla"
                  :class="{ elegida: variante.talla.id === tallaId }"
                  :disabled="variante.stock === 0"
                  :aria-pressed="variante.talla.id === tallaId"
                  :title="variante.stock === 0 ? 'Agotada' : `${variante.stock} disponibles`"
                  @click="tallaId = variante.talla.id"
                >
                  {{ variante.talla.valor }}
                </button>
              </div>
            </fieldset>

            <p class="disponibilidad">
              <template v-if="!varianteElegida">Elige una talla para ver la disponibilidad.</template>
              <template v-else-if="disponibleParaAgregar === 0 && limiteVariante < varianteElegida.stock">
                Ya tienes en el carrito el máximo de {{ limiteVariante }} unidades por producto.
              </template>
              <template v-else-if="disponibleParaAgregar === 0">Ya tienes en el carrito todas las unidades disponibles.</template>
              <template v-else-if="varianteElegida.stock <= 3">¡Últimas {{ varianteElegida.stock }} unidades!</template>
              <template v-else>{{ varianteElegida.stock }} unidades disponibles.</template>
            </p>

            <div class="comprar">
              <div class="selector-cantidad" aria-label="Cantidad">
                <button type="button" aria-label="Menos" :disabled="cantidad <= 1" @click="cambiarCantidad(cantidad - 1)">−</button>
                <span aria-live="polite">{{ cantidad }}</span>
                <button type="button" aria-label="Más" :disabled="!varianteElegida || cantidad >= disponibleParaAgregar" @click="cambiarCantidad(cantidad + 1)">+</button>
              </div>
              <button type="button" class="boton boton-comprar" :disabled="!varianteElegida || disponibleParaAgregar < 1" @click="agregarAlCarrito">
                <i class="fa-solid fa-cart-plus"></i> AGREGAR AL CARRITO
              </button>
            </div>

            <p v-if="mensaje" class="alerta alerta-exito" role="status">
              {{ mensaje }}
              <button type="button" class="ver-carrito" @click="carrito.abierto = true">Ver carrito</button>
            </p>
          </template>
        </div>
      </div>
    </template>
  </main>
</template>

<style scoped>
.pagina-producto {
  max-width: 1150px;
  min-height: 70vh;
  margin: 0 auto;
  padding: calc(var(--alto-header) + 30px) 16px 40px;
}

.estado {
  display: grid;
  justify-items: center;
  gap: 14px;
  padding: 50px 0;
}

.migas {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 20px;
  font-size: .9rem;
  color: var(--texto-secundario);
}

.migas a:hover {
  color: var(--naranja);
}

.producto {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 40px;
  align-items: start;
}

.imagen-principal {
  width: 100%;
  aspect-ratio: 1 / 1;
  object-fit: cover;
  border-radius: 1.5rem;
  background: #fff;
  box-shadow: 0 8px 24px hsla(0, 100%, 50%, 0.15);
}

.sin-imagen {
  display: grid;
  place-items: center;
  font-size: 4rem;
  color: #ccc;
}

.miniaturas {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 12px;
}

.miniaturas button {
  padding: 0;
  border: 2px solid transparent;
  border-radius: 10px;
  overflow: hidden;
  cursor: pointer;
  background: none;
}

.miniaturas button.activa {
  border-color: var(--naranja);
}

.miniaturas img {
  width: 70px;
  height: 70px;
  object-fit: cover;
}

.info {
  display: grid;
  gap: 14px;
}

.marca {
  font-weight: 600;
  letter-spacing: 2px;
  color: var(--naranja);
}

.info h1 {
  font-size: 2.2rem;
  line-height: 1.15;
}

.vence-oferta {
  margin-top: -8px;
  font-size: .88rem;
  color: #c24e00;
}

.descripcion {
  color: #444;
  line-height: 1.6;
}

.grupo {
  border: 0;
}

.grupo legend {
  margin-bottom: 8px;
  font-weight: 500;
}

.opciones {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.opcion-color,
.opcion-talla {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  background: #fff;
  border: 2px solid #ddd;
  border-radius: 25px;
  cursor: pointer;
}

.opcion-color.elegida,
.opcion-talla.elegida {
  border-color: var(--naranja);
  background: #fff3eb;
}

.opcion-color.agotada {
  opacity: .5;
}

.opcion-talla {
  justify-content: center;
  min-width: 56px;
  border-radius: 10px;
  font-weight: 500;
}

.opcion-talla:disabled {
  color: #aaa;
  text-decoration: line-through;
  cursor: not-allowed;
  background: #f4f4f4;
}

.muestra {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  border: 1px solid rgba(0, 0, 0, .25);
}

.muestra.multicolor {
  background: conic-gradient(#e53935, #fdd835, #43a047, #1e88e5, #8e24aa, #e53935);
}

.disponibilidad {
  font-size: .92rem;
  color: var(--texto-secundario);
}

.comprar {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.selector-cantidad {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  padding: 4px;
  background: #fff;
  border: 1px solid #ddd;
  border-radius: 25px;
}

.selector-cantidad button {
  width: 36px;
  height: 36px;
  font-size: 1.1rem;
  background: var(--gris-suave);
  border: 0;
  border-radius: 50%;
  cursor: pointer;
}

.selector-cantidad button:disabled {
  opacity: .4;
  cursor: not-allowed;
}

.selector-cantidad span {
  min-width: 22px;
  text-align: center;
  font-weight: 600;
}

.boton-comprar {
  flex: 1;
  min-width: 220px;
  border-radius: 25px;
}

.ver-carrito {
  margin-left: 8px;
  font-weight: 600;
  color: var(--exito);
  text-decoration: underline;
  background: none;
  border: 0;
  cursor: pointer;
}

@media (max-width: 800px) {
  .producto {
    grid-template-columns: minmax(0, 1fr); /* minmax: el contenido ancho (tablas) no estira la página */
    gap: 24px;
  }

  .info h1 {
    font-size: 1.7rem;
  }
}
</style>
