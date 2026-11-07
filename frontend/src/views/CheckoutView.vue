<script setup>
// Finalizar compra: datos de envío + resumen calculado por el servidor (ofertas y cupón)
// -> POST /api/pedidos.
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import pedidoService from '@/services/pedidoService'
import { urlImagen } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useCarritoStore } from '@/stores/carrito'
import { REGEX_TELEFONO, formatearPrecio } from '@/utils/formato'

const auth = useAuthStore()
const carrito = useCarritoStore()
const router = useRouter()

const envio = reactive({
  nombre_contacto: auth.usuario?.nombre ?? '',
  telefono: auth.usuario?.telefono ?? '',
  direccion: '',
  ciudad: '',
  notas: '',
})
const errores = ref({})
const errorGeneral = ref('')
const enviando = ref(false)

// Cotización del servidor: precios vigentes, stock y cupón.
const cotizacion = ref(null)
const cotizando = ref(false)
const codigoCupon = ref('')
const cuponAplicado = ref(null) // código validado
const mensajeCupon = ref({ tipo: '', texto: '' })

const vacio = computed(() => carrito.items.length === 0)
const itemsParaApi = () => carrito.items.map((item) => ({ variante_id: item.varianteId, cantidad: item.cantidad }))
const lineaDe = (varianteId) => cotizacion.value?.lineas.find((l) => l.variante_id === varianteId)

async function cotizar(cupon = cuponAplicado.value) {
  if (vacio.value) return
  cotizando.value = true
  try {
    cotizacion.value = await pedidoService.cotizar(itemsParaApi(), cupon)
    // Si una oferta empezó o terminó, el carrito queda con el precio real.
    carrito.actualizarPrecios(cotizacion.value.lineas)
  } catch (e) {
    errorGeneral.value = e.message
  } finally {
    cotizando.value = false
  }
}

async function aplicarCupon() {
  const codigo = codigoCupon.value.trim()
  mensajeCupon.value = { tipo: '', texto: '' }
  if (!/^[A-Za-z0-9_-]{3,30}$/.test(codigo)) {
    mensajeCupon.value = { tipo: 'error', texto: 'Escribe un código válido.' }
    return
  }
  await cotizar(codigo)
  if (cotizacion.value?.error_cupon) {
    mensajeCupon.value = { tipo: 'error', texto: cotizacion.value.error_cupon }
    cuponAplicado.value = null
  } else if (cotizacion.value?.cupon) {
    cuponAplicado.value = cotizacion.value.cupon.codigo
    codigoCupon.value = cuponAplicado.value
    mensajeCupon.value = { tipo: 'exito', texto: `Cupón ${cuponAplicado.value} aplicado: ${cotizacion.value.cupon.descripcion ?? ''}` }
  }
}

function quitarCupon() {
  cuponAplicado.value = null
  codigoCupon.value = ''
  mensajeCupon.value = { tipo: '', texto: '' }
  cotizar(null)
}

// Si cambia el carrito (cantidades), se vuelve a cotizar.
watch(() => carrito.items.map((i) => `${i.varianteId}:${i.cantidad}`).join(','), () => cotizar())
onMounted(() => cotizar())

function validar() {
  const nuevos = {}
  if (envio.nombre_contacto.trim().length < 2) nuevos.nombre_contacto = 'Escribe el nombre de quien recibe.'
  if (!REGEX_TELEFONO.test(envio.telefono.trim())) nuevos.telefono = 'Escribe un teléfono válido.'
  if (envio.direccion.trim().length < 5) nuevos.direccion = 'Escribe la dirección completa.'
  if (envio.ciudad.trim().length < 2) nuevos.ciudad = 'Escribe la ciudad.'
  if (envio.notas.length > 500) nuevos.notas = 'Máximo 500 caracteres.'
  errores.value = nuevos
  return Object.keys(nuevos).length === 0
}

async function confirmar() {
  errorGeneral.value = ''
  if (!validar() || vacio.value) return

  enviando.value = true
  try {
    const respuesta = await pedidoService.crear(
      {
        nombre_contacto: envio.nombre_contacto.trim(),
        telefono: envio.telefono.trim(),
        direccion: envio.direccion.trim(),
        ciudad: envio.ciudad.trim(),
        notas: envio.notas.trim() || null,
      },
      itemsParaApi(),
      cuponAplicado.value,
    )
    carrito.vaciar()
    router.replace({ name: 'mi-pedido', params: { id: String(respuesta.data.id) }, query: { nuevo: '1' } })
  } catch (e) {
    errores.value = e.errores ?? {}
    if (e.errores?.cupon) {
      // El cupón dejó de servir entre la vista previa y la compra (por ejemplo, se agotó).
      mensajeCupon.value = { tipo: 'error', texto: e.errores.cupon }
      cuponAplicado.value = null
      cotizar(null)
    }
    errorGeneral.value = e.errores?.items ? `${e.message} ${e.errores.items}` : e.message
  } finally {
    enviando.value = false
  }
}
</script>

<template>
  <main class="checkout">
    <h2 class="titulo_descuento">FINALIZAR COMPRA</h2>

    <div v-if="vacio" class="vacio">
      <p>Tu carrito está vacío.</p>
      <RouterLink to="/" class="boton">Ir a la tienda</RouterLink>
    </div>

    <div v-else class="contenido">
      <form class="tarjeta formulario" novalidate @submit.prevent="confirmar">
        <h3>Datos de envío</h3>
        <label class="campo" :class="{ 'con-error': errores.nombre_contacto }">
          ¿Quién recibe?
          <input v-model="envio.nombre_contacto" type="text" autocomplete="name" maxlength="120">
          <span v-if="errores.nombre_contacto" class="error-campo">{{ errores.nombre_contacto }}</span>
        </label>
        <label class="campo" :class="{ 'con-error': errores.telefono }">
          Teléfono
          <input v-model="envio.telefono" type="tel" autocomplete="tel" maxlength="20">
          <span v-if="errores.telefono" class="error-campo">{{ errores.telefono }}</span>
        </label>
        <label class="campo" :class="{ 'con-error': errores.direccion }">
          Dirección
          <input v-model="envio.direccion" type="text" autocomplete="street-address" maxlength="255" placeholder="Calle, número, barrio, apartamento">
          <span v-if="errores.direccion" class="error-campo">{{ errores.direccion }}</span>
        </label>
        <label class="campo" :class="{ 'con-error': errores.ciudad }">
          Ciudad
          <input v-model="envio.ciudad" type="text" autocomplete="address-level2" maxlength="80">
          <span v-if="errores.ciudad" class="error-campo">{{ errores.ciudad }}</span>
        </label>
        <label class="campo" :class="{ 'con-error': errores.notas }">
          Notas para la entrega (opcional)
          <textarea v-model="envio.notas" maxlength="500"></textarea>
          <span v-if="errores.notas" class="error-campo">{{ errores.notas }}</span>
        </label>
        <p class="nota-pago">
          <i class="fa-solid fa-circle-info"></i>
          Al confirmar, el pedido queda <strong>pendiente</strong> y podrás pagarlo en línea con Wompi (tarjeta, PSE o Nequi) o esperar a que un vendedor te contacte.
        </p>
        <p v-if="errorGeneral" class="alerta alerta-error" role="alert">{{ errorGeneral }}</p>
        <button type="submit" class="boton" :disabled="enviando || cotizando || !cotizacion || cotizacion.errores.length > 0">
          {{ enviando ? 'Registrando pedido...' : `Confirmar pedido · ${formatearPrecio(cotizacion?.total ?? carrito.total)}` }}
        </button>
      </form>

      <aside class="tarjeta resumen" aria-label="Resumen del pedido">
        <h3>Tu pedido</h3>

        <p v-if="cotizacion?.errores.length" class="alerta alerta-error" role="alert">
          {{ cotizacion.errores.join(' ') }} Ajusta tu carrito para continuar.
        </p>

        <ul>
          <li v-for="item in carrito.items" :key="item.varianteId">
            <img :src="urlImagen(item.imagen)" :alt="item.nombre">
            <div>
              <strong>{{ item.nombre }}</strong>
              <span class="texto-secundario">{{ item.color }} · Talla {{ item.talla }} · {{ item.cantidad }} und.</span>
              <span v-if="lineaDe(item.varianteId) && lineaDe(item.varianteId).precio < lineaDe(item.varianteId).precio_regular" class="en-oferta">
                <i class="fa-solid fa-tag"></i> En oferta (antes {{ formatearPrecio(lineaDe(item.varianteId).precio_regular) }})
              </span>
            </div>
            <span class="precio">{{ formatearPrecio(lineaDe(item.varianteId)?.subtotal ?? item.precio * item.cantidad) }}</span>
          </li>
        </ul>

        <form class="cupon" @submit.prevent="aplicarCupon">
          <label class="solo-lectores" for="cupon">Cupón de descuento</label>
          <input id="cupon" v-model="codigoCupon" type="text" placeholder="¿Tienes un cupón?" maxlength="30" :disabled="!!cuponAplicado" autocomplete="off">
          <button v-if="!cuponAplicado" type="submit" class="boton boton-secundario boton-pequeno" :disabled="cotizando || !codigoCupon.trim()">Aplicar</button>
          <button v-else type="button" class="boton boton-secundario boton-pequeno" @click="quitarCupon">Quitar</button>
        </form>
        <p v-if="mensajeCupon.texto" class="mensaje-cupon" :class="mensajeCupon.tipo" role="status">{{ mensajeCupon.texto }}</p>

        <dl class="totales">
          <div><dt>Subtotal</dt><dd>{{ formatearPrecio(cotizacion?.subtotal ?? carrito.total) }}</dd></div>
          <div v-if="cotizacion?.descuento" class="descuento"><dt>Descuento ({{ cotizacion.cupon?.codigo }})</dt><dd>−{{ formatearPrecio(cotizacion.descuento) }}</dd></div>
          <div class="total"><dt>Total</dt><dd>{{ formatearPrecio(cotizacion?.total ?? carrito.total) }}</dd></div>
        </dl>
        <button type="button" class="boton boton-secundario boton-pequeno" @click="carrito.abierto = true">Editar carrito</button>
      </aside>
    </div>
  </main>
</template>

<style scoped>
.checkout {
  max-width: 1100px;
  min-height: 70vh;
  margin: 0 auto;
  padding: calc(var(--alto-header) + 30px) 16px 40px;
}

.vacio {
  display: grid;
  justify-items: center;
  gap: 14px;
  padding: 40px 0;
}

.contenido {
  display: grid;
  grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr);
  gap: 24px;
  align-items: start;
}

.tarjeta {
  padding: 24px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 4px 18px rgba(0, 0, 0, .08);
}

.tarjeta h3 {
  font-size: 1.1rem;
}

.nota-pago {
  font-size: .88rem;
  color: var(--texto-secundario);
}

.nota-pago i {
  color: var(--naranja);
}

.resumen {
  position: sticky;
  top: calc(var(--alto-header) + 20px);
  display: grid;
  grid-template-columns: minmax(0, 1fr); /* nada dentro del resumen se sale de la tarjeta */
  gap: 14px;
}

.resumen ul {
  display: grid;
  gap: 12px;
}

.resumen li {
  display: grid;
  grid-template-columns: 56px minmax(0, 1fr) auto;
  gap: 10px;
  align-items: center;
}

.resumen img {
  width: 56px;
  height: 56px;
  object-fit: cover;
  border-radius: 8px;
}

.resumen li div {
  display: grid;
  min-width: 0;
  font-size: .9rem;
}

.en-oferta {
  font-size: .78rem;
  color: #c24e00;
}

.precio {
  font-weight: 600;
  font-size: .9rem;
  white-space: nowrap;
}

.cupon {
  display: flex;
  gap: 8px;
}

.cupon input {
  flex: 1;
  min-width: 0;
  padding: 8px 12px;
  text-transform: uppercase;
  border: 1px solid var(--gris-borde);
  border-radius: 8px;
}

.cupon input::placeholder {
  text-transform: none;
}

.mensaje-cupon {
  margin-top: -6px;
  font-size: .85rem;
}

.mensaje-cupon.error {
  color: var(--error);
}

.mensaje-cupon.exito {
  color: var(--exito);
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

.totales dd {
  font-variant-numeric: tabular-nums;
}

.totales .descuento {
  color: var(--exito);
}

.totales .total {
  font-size: 1.1rem;
  font-weight: 700;
}

.texto-secundario {
  font-size: .82rem;
  color: var(--texto-secundario);
}

@media (max-width: 800px) {
  .contenido {
    grid-template-columns: minmax(0, 1fr); /* minmax: el contenido ancho (tablas) no estira la página */
  }

  .resumen {
    position: static;
    order: -1;
  }
}
</style>
