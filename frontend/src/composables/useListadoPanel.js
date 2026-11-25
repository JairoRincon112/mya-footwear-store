// Listado paginado del panel con filtros (pedidos, productos, inventario, usuarios, devoluciones).
//
// Reúne lo que antes repetía cada vista: el estado de carga y error, volver a la página 1
// cuando cambia un filtro, esperar a que se deje de escribir en la búsqueda y cambiar de página.
//
// Uso:
//   const { filtros, resultado, cargando, error, cargar, irAPagina } =
//     useListadoPanel((f) => pedidoService.listar(f), { estado: '', desde: '', hasta: '' })
import { onBeforeUnmount, reactive, ref, watch } from 'vue'

/** Milisegundos sin escribir antes de buscar (evita una consulta por cada tecla). */
export const ESPERA_BUSQUEDA_MS = 350

/**
 * @param {(filtros: object) => Promise<object>} consultar Pide una página a la API con los filtros
 * @param {object} filtrosIniciales Filtros propios de la vista (además de q y pagina)
 * @param {object} resultadoInicial Campos extra del resultado vacío (por ejemplo el umbral de stock)
 */
export function useListadoPanel(consultar, filtrosIniciales = {}, resultadoInicial = {}) {
  const filtros = reactive({ q: '', ...filtrosIniciales, pagina: 1 })
  const resultado = ref({ items: [], total: 0, paginas: 0, ...resultadoInicial })
  const cargando = ref(true)
  const error = ref('')
  let temporizador = null

  async function cargar() {
    cargando.value = true
    error.value = ''
    try {
      resultado.value = await consultar(filtros)
    } catch (e) {
      error.value = e.message
    } finally {
      cargando.value = false
    }
  }

  function reiniciarYCargar() {
    filtros.pagina = 1
    cargar()
  }

  function irAPagina(pagina) {
    filtros.pagina = pagina
    cargar()
  }

  // Los filtros de selección (semáforo, listas, fechas) consultan de inmediato...
  const inmediatos = Object.keys(filtros).filter((clave) => !['q', 'pagina'].includes(clave))
  watch(() => inmediatos.map((clave) => filtros[clave]), reiniciarYCargar)

  // ...y la búsqueda espera a que se deje de escribir.
  watch(() => filtros.q, () => {
    clearTimeout(temporizador)
    temporizador = setTimeout(reiniciarYCargar, ESPERA_BUSQUEDA_MS)
  })
  onBeforeUnmount(() => clearTimeout(temporizador))

  return { filtros, resultado, cargando, error, cargar, irAPagina }
}
