// Reglas del ajuste manual de stock en el panel (Inventario y Editor de producto).
// Son las mismas que aplica el backend en Variante; aquí se revisan antes de enviar
// para mostrar el error al lado del campo sin esperar la respuesta del servidor.

/**
 * @param {{ tipo: string, cantidad: number|string, motivo: string }} ajuste Datos del formulario
 * @returns {{ errores: object, datos: { tipo: string, cantidad: number, motivo: string|null } }}
 */
export function validarAjusteStock({ tipo, cantidad, motivo }) {
  const errores = {}
  const unidades = Number(cantidad)
  const explicacion = String(motivo ?? '').trim()

  if (!Number.isInteger(unidades) || unidades === 0) {
    errores.cantidad = 'Escribe una cantidad entera distinta de 0.'
  } else if (tipo !== 'ajuste' && unidades < 0) {
    errores.cantidad = 'Para descontar unidades usa el tipo "Ajuste".'
  }
  if (tipo === 'ajuste' && !explicacion) {
    errores.motivo = 'Explica el motivo del ajuste.'
  }

  return { errores, datos: { tipo, cantidad: unidades, motivo: explicacion || null } }
}
