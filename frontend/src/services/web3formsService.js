// Cliente de la API de Web3Forms (https://web3forms.com).
// Envía los datos del formulario de contacto directo al correo de la tienda,
// sin servidor de correo propio.
//
// La clave se lee de frontend/.env (VITE_WEB3FORMS_KEY), que no se sube al repositorio.
// Se consume desde el navegador porque así lo exige el plan gratuito de Web3Forms
// (las peticiones desde un servidor requieren plan de pago y lista blanca de IP).
// La clave de Web3Forms está pensada para usarse en el navegador: solo permite
// enviar mensajes al correo registrado, no leerlos.

const ENDPOINT = 'https://api.web3forms.com/submit'
const CLAVE = import.meta.env.VITE_WEB3FORMS_KEY?.trim() ?? ''

export default {
  /** true si hay clave configurada en el .env */
  configurado: CLAVE !== '',

  /**
   * datos: { nombre, email, telefono, motivo, mensaje, botcheck }
   * Devuelve la respuesta de Web3Forms ({ success, message }) o lanza un Error.
   */
  async enviar({ nombre, email, telefono, motivo, mensaje, botcheck = false }) {
    if (!CLAVE) throw new Error('Falta VITE_WEB3FORMS_KEY en frontend/.env')

    const respuesta = await fetch(ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        access_key: CLAVE,
        subject: `Contacto web (${motivo}) - ${nombre}`,
        from_name: 'MyA FOOTWEAR STORE',
        // Campos que llegan en el correo (Web3Forms usa "email" como dirección de respuesta)
        name: nombre,
        email,
        telefono: telefono || 'No indicado',
        motivo,
        message: mensaje,
        botcheck, // campo trampa contra bots: si viene marcado, Web3Forms lo descarta
      }),
    })

    const datos = await respuesta.json().catch(() => ({}))
    if (!respuesta.ok || !datos.success) {
      throw new Error(datos.message || datos.body?.message || `Web3Forms respondió ${respuesta.status}`)
    }
    return datos
  },
}
