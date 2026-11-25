// Quita los filtros vacíos antes de enviarlos a la API (el 0 y el false sí se envían).
export const limpiarFiltros = (filtros) =>
  Object.fromEntries(Object.entries(filtros).filter(([, valor]) => valor !== '' && valor != null))
