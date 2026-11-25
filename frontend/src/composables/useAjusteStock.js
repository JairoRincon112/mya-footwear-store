// Formulario de ajuste manual de stock que comparten Inventario y el Editor de producto.
// Abre el formulario para una variante, valida con validarAjusteStock, envía el ajuste y
// deja los errores por campo; cada vista decide qué aviso mostrar y cómo recargar.
import { reactive, ref } from 'vue'
import productoService from '@/services/productoService'
import { validarAjusteStock } from '@/utils/inventario'

/**
 * @param {{ alGuardar: (respuesta: object) => (void|Promise<void>), alFallar: (mensaje: string) => void }} acciones
 */
export function useAjusteStock({ alGuardar, alFallar }) {
  const ajuste = reactive({ varianteId: null, tipo: 'entrada', cantidad: 1, motivo: '' })
  const erroresAjuste = ref({})

  function abrirAjuste(variante) {
    Object.assign(ajuste, { varianteId: variante.id, tipo: 'entrada', cantidad: 1, motivo: '' })
    erroresAjuste.value = {}
  }

  async function guardarAjuste() {
    const { errores, datos } = validarAjusteStock(ajuste)
    erroresAjuste.value = errores
    if (Object.keys(errores).length) return

    try {
      const respuesta = await productoService.ajustarStock(ajuste.varianteId, datos)
      ajuste.varianteId = null
      await alGuardar(respuesta)
    } catch (e) {
      // Errores por campo junto al formulario; los demás, como aviso de la vista.
      erroresAjuste.value = e.errores ?? {}
      if (!Object.keys(erroresAjuste.value).length) alFallar(e.message)
    }
  }

  return { ajuste, erroresAjuste, abrirAjuste, guardarAjuste }
}
