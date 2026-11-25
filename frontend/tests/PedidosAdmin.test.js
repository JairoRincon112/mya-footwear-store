// Listado de pedidos del panel: carga inicial, semáforo de estados, búsqueda con espera y errores.
import { flushPromises } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import PedidosAdmin from '@/views/panel/PedidosAdmin.vue'
import pedidoService from '@/services/pedidoService'
import { botonSemaforo, montar } from './apoyo'

vi.mock('vue-router', () => ({ useRouter: () => ({ push: vi.fn() }), useRoute: () => ({ query: {} }) }))
vi.mock('@/services/pedidoService', () => ({ default: { listar: vi.fn() } }))

const PEDIDO = {
  id: 15, estado: 'enviado', creado_en: '2026-11-20 10:00:00', total: 189900, unidades: 1,
  cliente: { nombre: 'Ana Pérez', email: 'ana@example.com' },
}

describe('PedidosAdmin', () => {
  beforeEach(() => {
    pedidoService.listar.mockReset().mockResolvedValue({ items: [PEDIDO], total: 1, paginas: 1 })
  })
  afterEach(() => vi.useRealTimers())

  it('carga la primera página al abrir y muestra los pedidos', async () => {
    const vista = await montar(PedidosAdmin)

    expect(pedidoService.listar).toHaveBeenCalledTimes(1)
    expect(pedidoService.listar.mock.calls[0][0]).toMatchObject({ estado: '', q: '', pagina: 1 })
    expect(vista.text()).toContain('Ana Pérez')
    expect(vista.text()).toContain('En tránsito')
  })

  it('el semáforo filtra por estado y vuelve a la página 1', async () => {
    const vista = await montar(PedidosAdmin)
    pedidoService.listar.mockClear()

    await botonSemaforo(vista, 'Pendiente').trigger('click')
    await flushPromises()

    expect(pedidoService.listar).toHaveBeenCalledTimes(1)
    expect(pedidoService.listar.mock.calls[0][0]).toMatchObject({ estado: 'pendiente', pagina: 1 })
    expect(botonSemaforo(vista, 'Pendiente').attributes('aria-pressed')).toBe('true')
  })

  it('la búsqueda espera a que se deje de escribir antes de consultar', async () => {
    vi.useFakeTimers()
    const vista = await montar(PedidosAdmin)
    pedidoService.listar.mockClear()

    await vista.find('input[type=search]').setValue('ana')
    await vi.advanceTimersByTimeAsync(200)
    expect(pedidoService.listar).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(200)
    expect(pedidoService.listar).toHaveBeenCalledTimes(1)
    expect(pedidoService.listar.mock.calls[0][0]).toMatchObject({ q: 'ana', pagina: 1 })
  })

  it('muestra el error si la API falla', async () => {
    pedidoService.listar.mockRejectedValue(new Error('No hay conexión con el servidor.'))

    const vista = await montar(PedidosAdmin)

    expect(vista.find('.alerta-error').text()).toBe('No hay conexión con el servidor.')
  })
})
