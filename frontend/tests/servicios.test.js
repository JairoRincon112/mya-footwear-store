// Servicios del panel: los filtros vacíos no se envían a la API.
import { beforeEach, describe, expect, it, vi } from 'vitest'
import api from '@/services/api'
import pedidoService from '@/services/pedidoService'
import devolucionService from '@/services/devolucionService'
import adminService from '@/services/adminService'

vi.mock('@/services/api', () => ({ default: { get: vi.fn(async () => ({ data: { items: [] } })) }, urlImagen: (ruta) => ruta }))

describe('filtros enviados a la API', () => {
  beforeEach(() => api.get.mockClear())

  it('pedidos: quita los filtros vacíos y conserva los que tienen valor', async () => {
    await pedidoService.listar({ estado: '', q: 'ana', desde: null, hasta: undefined, pagina: 2 })

    expect(api.get).toHaveBeenCalledWith('/pedidos', { params: { q: 'ana', pagina: 2 } })
  })

  it('devoluciones e inventario usan la misma limpieza', async () => {
    await devolucionService.listar({ estado: 'aprobada', tipo: '', pagina: 1 })
    await pedidoService.inventario({ q: '', marca: 'nike', bajo_stock: '', pagina: 1 })

    expect(api.get).toHaveBeenNthCalledWith(1, '/devoluciones', { params: { estado: 'aprobada', pagina: 1 } })
    expect(api.get).toHaveBeenNthCalledWith(2, '/inventario', { params: { marca: 'nike', pagina: 1 } })
  })

  it('usuarios: el 0 de "inactivos" sí se envía', async () => {
    await adminService.usuarios({ q: '', rol: '', activo: '0', pagina: 1 })

    expect(api.get.mock.calls[0][1].params).toEqual({ activo: '0', pagina: 1 })
  })
})
