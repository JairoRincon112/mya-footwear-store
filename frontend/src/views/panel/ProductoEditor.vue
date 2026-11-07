<script setup>
// Crear y editar un producto: datos, imágenes, variantes (talla + color) y stock.
// El administrador puede editar todo; el vendedor solo consulta y ajusta stock.
import { computed, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import productoService from '@/services/productoService'
import { urlImagen } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useCatalogoStore } from '@/stores/catalogo'
import { formatearPrecio } from '@/utils/formato'

const props = defineProps({
  id: { type: String, default: null },
})

const auth = useAuthStore()
const catalogo = useCatalogoStore()
const router = useRouter()

const TAMANO_MAXIMO_MB = 5
const TIPOS_IMAGEN = ['image/jpeg', 'image/png', 'image/webp']

const esNuevo = computed(() => !props.id)
const puedeEditar = computed(() => auth.esAdmin)

const producto = ref(null)
const cargando = ref(false)
const errorCarga = ref('')

// ---- Datos del producto ----
const VACIO = { marca_id: '', nombre: '', precio: '', descripcion: '', activo: true, precio_oferta: '', oferta_inicio: '', oferta_fin: '' }
const formulario = reactive({ ...VACIO })

/** "2026-12-31 23:59:00" (BD) -> "2026-12-31T23:59" (input datetime-local) */
const aInputFecha = (valor) => (valor ? valor.slice(0, 16).replace(' ', 'T') : '')
const erroresFormulario = ref({})
const guardando = ref(false)
const avisoDatos = ref({ tipo: '', texto: '' })

// ---- Imágenes ----
const archivoImagen = ref(null)
const colorImagen = ref('')
const subiendo = ref(false)
const avisoImagenes = ref({ tipo: '', texto: '' })
const inputArchivo = ref(null)

// ---- Variantes ----
const nuevaVariante = reactive({ color_id: '', talla_ids: [], stock: 10 })
const erroresVariante = ref({})
const creandoVariantes = ref(false)
const avisoVariantes = ref({ tipo: '', texto: '' })
const ajuste = reactive({ varianteId: null, tipo: 'entrada', cantidad: 1, motivo: '' })
const erroresAjuste = ref({})

/** Variantes ordenadas por color y talla. */
const variantes = computed(() => producto.value?.variantes ?? [])

/** Colores que ya tienen variantes (para asociar imágenes). */
const coloresDelProducto = computed(() => {
  const mapa = new Map(variantes.value.map((variante) => [variante.color.id, variante.color]))
  return [...mapa.values()]
})

function llenarFormulario(datos) {
  Object.assign(formulario, {
    marca_id: datos.marca.id,
    nombre: datos.nombre,
    precio: datos.precio_regular, // "precio" de la API ya trae la oferta aplicada
    descripcion: datos.descripcion ?? '',
    activo: datos.activo,
    precio_oferta: datos.precio_oferta ?? '',
    oferta_inicio: aInputFecha(datos.oferta_inicio),
    oferta_fin: aInputFecha(datos.oferta_fin),
  })
}

async function cargar() {
  catalogo.cargar().catch(() => {})
  if (esNuevo.value) {
    producto.value = null
    Object.assign(formulario, VACIO)
    return
  }

  cargando.value = true
  errorCarga.value = ''
  try {
    producto.value = await productoService.obtener(props.id)
    llenarFormulario(producto.value)
  } catch (e) {
    errorCarga.value = e.estado === 404 ? 'El producto no existe.' : e.message
  } finally {
    cargando.value = false
  }
}

watch(() => props.id, cargar, { immediate: true })

/** Recarga solo los datos (sin pisar el formulario si el usuario lo está editando). */
async function refrescar() {
  producto.value = await productoService.obtener(props.id)
}

// ------------------------------------------------------------------ Datos
function validarFormulario() {
  const errores = {}
  if (!formulario.marca_id) errores.marca_id = 'Selecciona la marca.'
  if (formulario.nombre.trim().length < 2) errores.nombre = 'El nombre debe tener al menos 2 caracteres.'
  const precio = Number(formulario.precio)
  if (!formulario.precio || Number.isNaN(precio) || precio < 1) errores.precio = 'Escribe un precio mayor a 0.'
  if (formulario.descripcion.length > 2000) errores.descripcion = 'Máximo 2000 caracteres.'
  if (formulario.precio_oferta !== '' && formulario.precio_oferta !== null) {
    const oferta = Number(formulario.precio_oferta)
    if (Number.isNaN(oferta) || oferta < 1) errores.precio_oferta = 'Escribe un precio de oferta válido.'
    else if (oferta >= precio) errores.precio_oferta = 'Debe ser menor que el precio regular.'
    if (formulario.oferta_inicio && formulario.oferta_fin && formulario.oferta_inicio >= formulario.oferta_fin) {
      errores.oferta_fin = 'La oferta debe terminar después de empezar.'
    }
  }
  erroresFormulario.value = errores
  return Object.keys(errores).length === 0
}

async function guardar() {
  avisoDatos.value = { tipo: '', texto: '' }
  if (!validarFormulario()) return

  const datos = {
    marca_id: Number(formulario.marca_id),
    nombre: formulario.nombre.trim(),
    precio: Number(formulario.precio),
    descripcion: formulario.descripcion.trim() || null,
    activo: formulario.activo,
    // Sin precio de oferta no se envían fechas (la oferta queda desactivada).
    precio_oferta: formulario.precio_oferta === '' || formulario.precio_oferta === null ? null : Number(formulario.precio_oferta),
    oferta_inicio: formulario.precio_oferta === '' ? null : formulario.oferta_inicio || null,
    oferta_fin: formulario.precio_oferta === '' ? null : formulario.oferta_fin || null,
  }

  guardando.value = true
  try {
    if (esNuevo.value) {
      const respuesta = await productoService.crear(datos)
      await router.replace({ name: 'panel-producto', params: { id: String(respuesta.data.id) } })
      avisoDatos.value = { tipo: 'exito', texto: 'Producto creado. Ahora agrega sus variantes (tallas y colores) e imágenes.' }
    } else {
      const respuesta = await productoService.actualizar(props.id, datos)
      producto.value = respuesta.data
      llenarFormulario(respuesta.data)
      avisoDatos.value = { tipo: 'exito', texto: respuesta.mensaje }
    }
  } catch (e) {
    erroresFormulario.value = e.errores
    avisoDatos.value = { tipo: 'error', texto: e.message }
  } finally {
    guardando.value = false
  }
}

// ------------------------------------------------------------------ Imágenes
function elegirArchivo(evento) {
  avisoImagenes.value = { tipo: '', texto: '' }
  const archivo = evento.target.files?.[0] ?? null
  if (archivo && !TIPOS_IMAGEN.includes(archivo.type)) {
    avisoImagenes.value = { tipo: 'error', texto: 'Solo se permiten imágenes JPG, PNG o WEBP.' }
    evento.target.value = ''
    archivoImagen.value = null
    return
  }
  if (archivo && archivo.size > TAMANO_MAXIMO_MB * 1024 * 1024) {
    avisoImagenes.value = { tipo: 'error', texto: `La imagen no puede pesar más de ${TAMANO_MAXIMO_MB} MB.` }
    evento.target.value = ''
    archivoImagen.value = null
    return
  }
  archivoImagen.value = archivo
}

async function subirImagen() {
  if (!archivoImagen.value) return
  subiendo.value = true
  try {
    const respuesta = await productoService.subirImagen(props.id, archivoImagen.value, colorImagen.value || null)
    avisoImagenes.value = { tipo: 'exito', texto: respuesta.mensaje }
    archivoImagen.value = null
    colorImagen.value = ''
    if (inputArchivo.value) inputArchivo.value.value = ''
    await refrescar()
  } catch (e) {
    avisoImagenes.value = { tipo: 'error', texto: e.errores?.imagen ?? e.message }
  } finally {
    subiendo.value = false
  }
}

async function eliminarImagen(imagen) {
  if (!confirm('¿Eliminar esta imagen?')) return
  try {
    const respuesta = await productoService.eliminarImagen(imagen.id)
    avisoImagenes.value = { tipo: 'exito', texto: respuesta.mensaje }
    await refrescar()
  } catch (e) {
    avisoImagenes.value = { tipo: 'error', texto: e.message }
  }
}

const nombreColor = (id) => catalogo.colores.find((color) => color.id === id)?.nombre ?? ''

// ------------------------------------------------------------------ Variantes
function alternarTodasLasTallas() {
  nuevaVariante.talla_ids =
    nuevaVariante.talla_ids.length === catalogo.tallas.length ? [] : catalogo.tallas.map((talla) => talla.id)
}

async function crearVariantes() {
  avisoVariantes.value = { tipo: '', texto: '' }
  const errores = {}
  if (!nuevaVariante.color_id) errores.color_id = 'Selecciona un color.'
  if (!nuevaVariante.talla_ids.length) errores.talla_ids = 'Selecciona al menos una talla.'
  const stock = Number(nuevaVariante.stock)
  if (!Number.isInteger(stock) || stock < 0) errores.stock = 'El stock debe ser un número entero mayor o igual a 0.'
  erroresVariante.value = errores
  if (Object.keys(errores).length) return

  creandoVariantes.value = true
  try {
    const respuesta = await productoService.crearVariantes(props.id, {
      color_id: Number(nuevaVariante.color_id),
      talla_ids: [...nuevaVariante.talla_ids],
      stock,
    })
    avisoVariantes.value = { tipo: 'exito', texto: respuesta.mensaje }
    nuevaVariante.talla_ids = []
    await refrescar()
  } catch (e) {
    erroresVariante.value = e.errores
    avisoVariantes.value = { tipo: 'error', texto: e.message }
  } finally {
    creandoVariantes.value = false
  }
}

async function alternarVariante(variante) {
  try {
    const respuesta = await productoService.actualizarVariante(variante.id, { sku: variante.sku, activo: !variante.activo })
    avisoVariantes.value = { tipo: 'exito', texto: respuesta.mensaje }
    await refrescar()
  } catch (e) {
    avisoVariantes.value = { tipo: 'error', texto: e.message }
  }
}

function abrirAjuste(variante) {
  Object.assign(ajuste, { varianteId: variante.id, tipo: 'entrada', cantidad: 1, motivo: '' })
  erroresAjuste.value = {}
}

async function guardarAjuste() {
  const errores = {}
  const cantidad = Number(ajuste.cantidad)
  if (!Number.isInteger(cantidad) || cantidad === 0) errores.cantidad = 'Escribe una cantidad entera distinta de 0.'
  else if (ajuste.tipo !== 'ajuste' && cantidad < 0) errores.cantidad = 'Para descontar unidades usa el tipo "Ajuste".'
  if (ajuste.tipo === 'ajuste' && !ajuste.motivo.trim()) errores.motivo = 'Explica el motivo del ajuste.'
  erroresAjuste.value = errores
  if (Object.keys(errores).length) return

  try {
    const respuesta = await productoService.ajustarStock(ajuste.varianteId, {
      tipo: ajuste.tipo,
      cantidad,
      motivo: ajuste.motivo.trim() || null,
    })
    avisoVariantes.value = { tipo: 'exito', texto: `${respuesta.mensaje} Nuevo stock: ${respuesta.data.stock}.` }
    ajuste.varianteId = null
    await refrescar()
  } catch (e) {
    erroresAjuste.value = e.errores
    if (!Object.keys(e.errores).length) avisoVariantes.value = { tipo: 'error', texto: e.message }
  }
}
</script>

<template>
  <section>
    <div class="panel-encabezado">
      <div>
        <RouterLink :to="{ name: 'panel-productos' }" class="texto-secundario">← Volver a productos</RouterLink>
        <h2>{{ esNuevo ? 'Nuevo producto' : (producto?.nombre ?? 'Producto') }}</h2>
      </div>
      <RouterLink
        v-if="producto?.activo"
        :to="{ name: 'producto', params: { slug: producto.slug } }"
        class="boton boton-secundario boton-pequeno"
        target="_blank"
      >
        Ver en la tienda <i class="fa-solid fa-arrow-up-right-from-square"></i>
      </RouterLink>
    </div>

    <p v-if="cargando" class="cargando"><i class="fa-solid fa-spinner fa-spin"></i>Cargando producto...</p>
    <p v-else-if="errorCarga" class="alerta alerta-error">{{ errorCarga }}</p>

    <template v-else>
      <!-- ============================ Datos ============================ -->
      <div class="panel-tarjeta">
        <h3>Datos del producto</h3>
        <form class="formulario datos" novalidate @submit.prevent="guardar">
          <fieldset :disabled="!puedeEditar" class="sin-borde">
            <div class="fila">
              <label class="campo" :class="{ 'con-error': erroresFormulario.marca_id }">
                Marca
                <select v-model="formulario.marca_id">
                  <option value="" disabled>Selecciona...</option>
                  <option v-for="marca in catalogo.marcas" :key="marca.id" :value="marca.id">{{ marca.nombre }}</option>
                </select>
                <span v-if="erroresFormulario.marca_id" class="error-campo">{{ erroresFormulario.marca_id }}</span>
              </label>
              <label class="campo" :class="{ 'con-error': erroresFormulario.nombre }">
                Nombre
                <input v-model="formulario.nombre" type="text" maxlength="150">
                <span v-if="erroresFormulario.nombre" class="error-campo">{{ erroresFormulario.nombre }}</span>
              </label>
              <label class="campo" :class="{ 'con-error': erroresFormulario.precio }">
                Precio (COP)
                <input v-model="formulario.precio" type="number" min="1" step="1" inputmode="numeric">
                <span v-if="erroresFormulario.precio" class="error-campo">{{ erroresFormulario.precio }}</span>
              </label>
            </div>
            <fieldset class="oferta">
              <legend>
                <i class="fa-solid fa-tag"></i> Oferta (opcional)
                <span v-if="producto?.en_oferta" class="etiqueta etiqueta-activa">Vigente ahora</span>
                <span v-else-if="producto?.precio_oferta" class="etiqueta etiqueta-inactiva">No vigente (fuera de fechas)</span>
              </legend>
              <div class="fila">
                <label class="campo" :class="{ 'con-error': erroresFormulario.precio_oferta }">
                  Precio en oferta (COP)
                  <input v-model="formulario.precio_oferta" type="number" min="1" step="1" inputmode="numeric" placeholder="Vacío = sin oferta">
                  <span v-if="erroresFormulario.precio_oferta" class="error-campo">{{ erroresFormulario.precio_oferta }}</span>
                </label>
                <label class="campo" :class="{ 'con-error': erroresFormulario.oferta_inicio }">
                  Empieza (vacío = ya)
                  <input v-model="formulario.oferta_inicio" type="datetime-local" :disabled="formulario.precio_oferta === ''">
                  <span v-if="erroresFormulario.oferta_inicio" class="error-campo">{{ erroresFormulario.oferta_inicio }}</span>
                </label>
                <label class="campo" :class="{ 'con-error': erroresFormulario.oferta_fin }">
                  Termina (vacío = sin fin)
                  <input v-model="formulario.oferta_fin" type="datetime-local" :disabled="formulario.precio_oferta === ''">
                  <span v-if="erroresFormulario.oferta_fin" class="error-campo">{{ erroresFormulario.oferta_fin }}</span>
                </label>
              </div>
            </fieldset>
            <label class="campo" :class="{ 'con-error': erroresFormulario.descripcion }">
              Descripción
              <textarea v-model="formulario.descripcion" maxlength="2000"></textarea>
              <span v-if="erroresFormulario.descripcion" class="error-campo">{{ erroresFormulario.descripcion }}</span>
            </label>
            <label class="campo-check">
              <input v-model="formulario.activo" type="checkbox">
              Visible en la tienda
            </label>
          </fieldset>

          <div v-if="puedeEditar">
            <button type="submit" class="boton" :disabled="guardando">
              {{ guardando ? 'Guardando...' : (esNuevo ? 'Crear producto' : 'Guardar cambios') }}
            </button>
          </div>
          <p v-if="avisoDatos.texto" class="alerta" :class="`alerta-${avisoDatos.tipo}`" role="status">{{ avisoDatos.texto }}</p>
        </form>
      </div>

      <template v-if="!esNuevo && producto">
        <!-- ============================ Imágenes ============================ -->
        <div class="panel-tarjeta">
          <h3>Imágenes</h3>
          <p v-if="!producto.imagenes.length" class="texto-secundario">Este producto aún no tiene imágenes.</p>
          <div class="galeria">
            <figure v-for="imagen in producto.imagenes" :key="imagen.id">
              <img :src="urlImagen(imagen.ruta)" :alt="producto.nombre">
              <figcaption>
                <span class="texto-secundario">{{ imagen.color_id ? nombreColor(imagen.color_id) : 'Todos los colores' }}</span>
                <button v-if="puedeEditar" type="button" class="boton boton-secundario boton-pequeno" @click="eliminarImagen(imagen)">Eliminar</button>
              </figcaption>
            </figure>
          </div>

          <form v-if="puedeEditar" class="subir" @submit.prevent="subirImagen">
            <label class="campo">
              Nueva imagen (JPG, PNG o WEBP, máx. {{ TAMANO_MAXIMO_MB }} MB)
              <input ref="inputArchivo" type="file" accept="image/jpeg,image/png,image/webp" @change="elegirArchivo">
            </label>
            <label class="campo">
              Color de la foto
              <select v-model="colorImagen">
                <option value="">Todos los colores</option>
                <option v-for="color in coloresDelProducto" :key="color.id" :value="color.id">{{ color.nombre }}</option>
              </select>
            </label>
            <button type="submit" class="boton" :disabled="!archivoImagen || subiendo">{{ subiendo ? 'Subiendo...' : 'Subir imagen' }}</button>
          </form>
          <p v-if="avisoImagenes.texto" class="alerta" :class="`alerta-${avisoImagenes.tipo}`" role="status">{{ avisoImagenes.texto }}</p>
        </div>

        <!-- ============================ Variantes ============================ -->
        <div class="panel-tarjeta">
          <h3>Variantes y stock <span class="texto-secundario">· {{ producto.stock_total }} unidades activas · precio {{ formatearPrecio(producto.precio) }}</span></h3>

          <p v-if="avisoVariantes.texto" class="alerta" :class="`alerta-${avisoVariantes.tipo}`" role="status">{{ avisoVariantes.texto }}</p>

          <p v-if="!variantes.length" class="texto-secundario">Agrega al menos una variante para poder vender este producto.</p>
          <div v-else class="tabla-contenedor">
            <table class="tabla">
              <thead>
                <tr>
                  <th>Color</th>
                  <th>Talla</th>
                  <th>SKU</th>
                  <th class="numero">Stock</th>
                  <th>Estado</th>
                  <th><span class="solo-lectores">Acciones</span></th>
                </tr>
              </thead>
              <tbody>
                <template v-for="variante in variantes" :key="variante.id">
                  <tr>
                    <td>
                      <span class="muestra" :style="variante.color.hex ? { background: variante.color.hex } : null" :class="{ multicolor: !variante.color.hex }"></span>
                      {{ variante.color.nombre }}
                    </td>
                    <td>{{ variante.talla.valor }}</td>
                    <td class="texto-secundario">{{ variante.sku }}</td>
                    <td class="numero">
                      <span v-if="variante.stock === 0" class="etiqueta etiqueta-agotada">0</span>
                      <template v-else>{{ variante.stock }}</template>
                    </td>
                    <td>
                      <span class="etiqueta" :class="variante.activo ? 'etiqueta-activa' : 'etiqueta-inactiva'">
                        {{ variante.activo ? 'Activa' : 'Inactiva' }}
                      </span>
                    </td>
                    <td class="acciones">
                      <button type="button" class="boton boton-secundario boton-pequeno" @click="abrirAjuste(variante)">Ajustar stock</button>
                      <button v-if="puedeEditar" type="button" class="boton boton-secundario boton-pequeno" @click="alternarVariante(variante)">
                        {{ variante.activo ? 'Desactivar' : 'Activar' }}
                      </button>
                    </td>
                  </tr>
                  <tr v-if="ajuste.varianteId === variante.id" class="fila-ajuste">
                    <td colspan="6">
                      <form class="ajuste" novalidate @submit.prevent="guardarAjuste">
                        <label class="campo">
                          Tipo
                          <select v-model="ajuste.tipo">
                            <option value="entrada">Entrada (llegó mercancía)</option>
                            <option value="devolucion">Devolución de cliente</option>
                            <option value="ajuste">Ajuste (corrección, + o −)</option>
                          </select>
                        </label>
                        <label class="campo" :class="{ 'con-error': erroresAjuste.cantidad }">
                          Cantidad
                          <input v-model="ajuste.cantidad" type="number" step="1">
                          <span v-if="erroresAjuste.cantidad" class="error-campo">{{ erroresAjuste.cantidad }}</span>
                        </label>
                        <label class="campo" :class="{ 'con-error': erroresAjuste.motivo }">
                          Motivo {{ ajuste.tipo === 'ajuste' ? '' : '(opcional)' }}
                          <input v-model="ajuste.motivo" type="text" maxlength="255">
                          <span v-if="erroresAjuste.motivo" class="error-campo">{{ erroresAjuste.motivo }}</span>
                        </label>
                        <div class="botones">
                          <button type="submit" class="boton boton-pequeno">Guardar</button>
                          <button type="button" class="boton boton-secundario boton-pequeno" @click="ajuste.varianteId = null">Cancelar</button>
                        </div>
                      </form>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>

          <form v-if="puedeEditar" class="nueva-variante" novalidate @submit.prevent="crearVariantes">
            <h4>Agregar variantes</h4>
            <div class="fila">
              <label class="campo" :class="{ 'con-error': erroresVariante.color_id }">
                Color
                <select v-model="nuevaVariante.color_id">
                  <option value="" disabled>Selecciona...</option>
                  <option v-for="color in catalogo.colores" :key="color.id" :value="color.id">{{ color.nombre }}</option>
                </select>
                <span v-if="erroresVariante.color_id" class="error-campo">{{ erroresVariante.color_id }}</span>
              </label>
              <label class="campo" :class="{ 'con-error': erroresVariante.stock }">
                Stock inicial por talla
                <input v-model="nuevaVariante.stock" type="number" min="0" step="1">
                <span v-if="erroresVariante.stock" class="error-campo">{{ erroresVariante.stock }}</span>
              </label>
            </div>
            <fieldset class="campo sin-borde" :class="{ 'con-error': erroresVariante.talla_ids }">
              <legend>
                Tallas
                <button type="button" class="enlace" @click="alternarTodasLasTallas">
                  {{ nuevaVariante.talla_ids.length === catalogo.tallas.length ? 'Quitar todas' : 'Seleccionar todas' }}
                </button>
              </legend>
              <div class="tallas">
                <label v-for="talla in catalogo.tallas" :key="talla.id" class="talla">
                  <input v-model="nuevaVariante.talla_ids" type="checkbox" :value="talla.id">
                  <span>{{ talla.valor }}</span>
                </label>
              </div>
              <span v-if="erroresVariante.talla_ids" class="error-campo">{{ erroresVariante.talla_ids }}</span>
            </fieldset>
            <div>
              <button type="submit" class="boton" :disabled="creandoVariantes">{{ creandoVariantes ? 'Creando...' : 'Crear variantes' }}</button>
            </div>
          </form>
        </div>
      </template>
    </template>
  </section>
</template>

<style scoped>
.sin-borde {
  border: 0;
  display: grid;
  gap: 14px;
}

.fila {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 14px;
}

.oferta {
  display: grid;
  gap: 10px;
  padding: 14px;
  border: 1px dashed #e0b48f;
  border-radius: 10px;
  background: #fffaf5;
}

.oferta legend {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 6px;
  font-size: .92rem;
  font-weight: 600;
  color: #c24e00;
}

.galeria {
  display: flex;
  flex-wrap: wrap;
  gap: 14px;
  margin-bottom: 16px;
}

.galeria figure {
  display: grid;
  gap: 6px;
  width: 150px;
}

.galeria img {
  width: 150px;
  height: 150px;
  object-fit: cover;
  border-radius: 10px;
  background: var(--gris-suave);
}

.galeria figcaption {
  display: grid;
  gap: 4px;
}

.subir {
  display: grid;
  grid-template-columns: 2fr 1fr auto;
  gap: 12px;
  align-items: end;
  margin-bottom: 12px;
}

.muestra {
  display: inline-block;
  width: 14px;
  height: 14px;
  margin-right: 6px;
  vertical-align: middle;
  border-radius: 50%;
  border: 1px solid rgba(0, 0, 0, .25);
}

.muestra.multicolor {
  background: conic-gradient(#e53935, #fdd835, #43a047, #1e88e5, #8e24aa, #e53935);
}

.acciones {
  display: flex;
  gap: 6px;
  justify-content: flex-end;
  white-space: nowrap;
}

.fila-ajuste td {
  background: #fff8f2;
}

.ajuste {
  display: grid;
  grid-template-columns: 1.3fr .7fr 1.5fr auto;
  gap: 12px;
  align-items: end;
}

.ajuste .botones {
  display: flex;
  gap: 6px;
}

.nueva-variante {
  display: grid;
  gap: 14px;
  margin-top: 22px;
  padding-top: 18px;
  border-top: 1px solid #e6e6e6;
}

.nueva-variante h4 {
  font-size: 1rem;
}

.tallas {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.talla {
  position: relative;
}

.talla input {
  position: absolute;
  opacity: 0;
}

.talla span {
  display: inline-block;
  min-width: 52px;
  padding: 8px 10px;
  text-align: center;
  background: #fff;
  border: 2px solid #ddd;
  border-radius: 10px;
  cursor: pointer;
}

.talla input:checked + span {
  border-color: var(--naranja);
  background: #fff3eb;
}

.talla input:focus-visible + span {
  outline: 2px solid var(--naranja);
}

.enlace {
  margin-left: 10px;
  font-size: .82rem;
  color: #c24e00;
  text-decoration: underline;
  background: none;
  border: 0;
  cursor: pointer;
}

.alerta {
  margin-bottom: 12px;
}

@media (max-width: 760px) {
  .subir,
  .ajuste {
    grid-template-columns: minmax(0, 1fr); /* minmax: el contenido ancho (tablas) no estira la página */
  }
}
</style>
