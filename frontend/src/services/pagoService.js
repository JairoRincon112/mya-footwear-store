// Pagos en línea con Wompi: /api/pedidos/{id}/pago y /api/pagos/verificar
import api from './api'

const WIDGET_URL = 'https://checkout.wompi.co/widget.js'
let cargaWidget = null

/** Carga una sola vez el script oficial del widget de Wompi. */
function cargarWidget() {
  if (window.WidgetCheckout) return Promise.resolve()
  cargaWidget ??= new Promise((resolve, reject) => {
    const script = document.createElement('script')
    script.src = WIDGET_URL
    script.async = true
    script.onload = () => resolve()
    script.onerror = () => {
      cargaWidget = null
      reject(new Error('No se pudo cargar el widget de Wompi. Revisa tu conexión a internet.'))
    }
    document.head.appendChild(script)
  })
  return cargaWidget
}

export default {
  /** Pide al servidor los datos firmados para pagar el pedido. */
  async iniciar(pedidoId) {
    return (await api.post(`/pedidos/${pedidoId}/pago`)).data
  },

  /** El servidor consulta la transacción en Wompi y actualiza el pedido. */
  async verificar(transaccionId) {
    return api.get('/pagos/verificar', { params: { id: transaccionId } })
  },

  /**
   * Abre el widget de Wompi con los datos que firmó el servidor.
   * Se resuelve con la transacción cuando el cliente termina (aprobada, rechazada o pendiente).
   */
  async abrirWidget(datos) {
    await cargarWidget()
    const checkout = new window.WidgetCheckout({
      currency: datos.moneda,
      amountInCents: datos.monto_centavos,
      reference: datos.referencia,
      publicKey: datos.llave_publica,
      signature: { integrity: datos.firma_integridad },
      customerData: {
        email: datos.cliente.email,
        fullName: datos.cliente.nombre,
        phoneNumber: datos.cliente.telefono,
        phoneNumberPrefix: '+57',
      },
    })
    return new Promise((resolve) => {
      checkout.open((resultado) => resolve(resultado?.transaction ?? null))
    })
  },
}
