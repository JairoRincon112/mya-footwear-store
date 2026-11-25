// Piezas extraídas en la refactorización: reglas del ajuste de stock, limpieza de filtros
// y el composable de listados del panel.
import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { defineComponent } from 'vue'
import { useAjusteStock } from '@/composables/useAjusteStock'
import { ESPERA_BUSQUEDA_MS, useListadoPanel } from '@/composables/useListadoPanel'
import productoService from '@/services/productoService'
import { limpiarFiltros } from '@/utils/filtros'
import { validarAjusteStock } from '@/utils/inventario'

describe('validarAjusteStock', () => {
  it.each([
    [{ tipo: 'entrada', cantidad: '0', motivo: '' }, 'cantidad', /entera distinta de 0/],
    [{ tipo: 'entrada', cantidad: '2.5', motivo: '' }, 'cantidad', /entera distinta de 0/],
    [{ tipo: 'devolucion', cantidad: '-1', motivo: '' }, 'cantidad', /Para descontar.*Ajuste/],
    [{ tipo: 'ajuste', cantidad: '-3', motivo: '   ' }, 'motivo', /Explica el motivo/],
  ])('rechaza %o', (ajuste, campo, mensaje) => {
    expect(validarAjusteStock(ajuste).errores[campo]).toMatch(mensaje)
  })

  it('convierte la cantidad y limpia el motivo del ajuste válido', () => {
    expect(validarAjusteStock({ tipo: 'ajuste', cantidad: '-3', motivo: '  Par dañado ' })).toEqual({
      errores: {},
      datos: { tipo: 'ajuste', cantidad: -3, motivo: 'Par dañado' },
    })
    expect(validarAjusteStock({ tipo: 'entrada', cantidad: 4, motivo: '' }).datos.motivo).toBeNull()
  })
})

vi.mock('@/services/productoService', () => ({ default: { ajustarStock: vi.fn() } }))

describe('useAjusteStock', () => {
  function preparar() {
    const acciones = { alGuardar: vi.fn(), alFallar: vi.fn() }
    const formulario = useAjusteStock(acciones)
    formulario.abrirAjuste({ id: 9 })
    return { ...formulario, ...acciones }
  }

  it('envía el ajuste válido, cierra el formulario y avisa a la vista', async () => {
    productoService.ajustarStock.mockResolvedValue({ data: { stock: 12 } })
    const { ajuste, guardarAjuste, alGuardar } = preparar()
    Object.assign(ajuste, { cantidad: '2', motivo: ' Llegó pedido ' })

    await guardarAjuste()

    expect(productoService.ajustarStock).toHaveBeenCalledWith(9, { tipo: 'entrada', cantidad: 2, motivo: 'Llegó pedido' })
    expect(ajuste.varianteId).toBeNull()
    expect(alGuardar).toHaveBeenCalledWith({ data: { stock: 12 } })
  })

  it('los errores por campo de la API quedan en el formulario; los demás van al aviso', async () => {
    const { ajuste, erroresAjuste, guardarAjuste, alFallar } = preparar()

    productoService.ajustarStock.mockRejectedValueOnce({ errores: { cantidad: 'No hay tantas unidades.' }, message: 'Revisa los datos' })
    await guardarAjuste()
    expect(erroresAjuste.value).toEqual({ cantidad: 'No hay tantas unidades.' })
    expect(alFallar).not.toHaveBeenCalled()

    productoService.ajustarStock.mockRejectedValueOnce(new Error('Sin conexión'))
    await guardarAjuste()
    expect(alFallar).toHaveBeenCalledWith('Sin conexión')
    expect(ajuste.varianteId).toBe(9)
  })
})

describe('limpiarFiltros', () => {
  it('quita vacíos, null y undefined; conserva 0 y false', () => {
    expect(limpiarFiltros({ a: '', b: null, c: undefined, d: 0, e: false, f: 'x' })).toEqual({ d: 0, e: false, f: 'x' })
  })
})

describe('useListadoPanel', () => {
  afterEach(() => vi.useRealTimers())

  function montarListado(consultar) {
    let listado
    const Vista = defineComponent({
      setup() {
        listado = useListadoPanel(consultar, { estado: '' }, { umbral: 3 })
        return () => null
      },
    })
    return { envoltura: mount(Vista), listado: () => listado }
  }

  it('empieza vacío con los campos extra y guarda el resultado de la consulta', async () => {
    const consultar = vi.fn(async () => ({ items: [1, 2], total: 2, paginas: 1, umbral: 5 }))
    const { listado } = montarListado(consultar)

    expect(listado().resultado.value).toEqual({ items: [], total: 0, paginas: 0, umbral: 3 })
    await listado().cargar()

    expect(listado().resultado.value.items).toEqual([1, 2])
    expect(listado().cargando.value).toBe(false)
  })

  it('un filtro de selección vuelve a la página 1 y consulta de inmediato', async () => {
    const consultar = vi.fn(async () => ({ items: [], total: 0, paginas: 3 }))
    const { listado } = montarListado(consultar)

    listado().irAPagina(3)
    listado().filtros.estado = 'pendiente'
    await flushPromises()

    expect(consultar).toHaveBeenLastCalledWith(expect.objectContaining({ estado: 'pendiente', pagina: 1 }))
  })

  it('cancela la búsqueda pendiente si la vista se cierra', async () => {
    vi.useFakeTimers()
    const consultar = vi.fn(async () => ({ items: [], total: 0, paginas: 0 }))
    const { envoltura, listado } = montarListado(consultar)

    listado().filtros.q = 'nike'
    await flushPromises()
    envoltura.unmount()
    await vi.advanceTimersByTimeAsync(ESPERA_BUSQUEDA_MS + 50)

    expect(consultar).not.toHaveBeenCalled()
  })

  it('muestra el mensaje del error de la API', async () => {
    const { listado } = montarListado(vi.fn(async () => { throw new Error('Sin conexión') }))

    await listado().cargar()

    expect(listado().error.value).toBe('Sin conexión')
  })
})
