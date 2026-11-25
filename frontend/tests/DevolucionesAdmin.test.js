// Devoluciones en el panel: filtros por estado y tipo, y el rechazo exige un motivo.
import { flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import DevolucionesAdmin from '@/views/panel/DevolucionesAdmin.vue'
import devolucionService from '@/services/devolucionService'
import { boton, botonSemaforo, montar } from './apoyo'

vi.mock('@/services/devolucionService', () => ({ default: { listar: vi.fn(), cambiarEstado: vi.fn() } }))

const DEVOLUCION = {
  id: 4, pedido_id: 15, estado: 'solicitada', tipo: 'talla_incorrecta', cantidad: 1, comprada: 2,
  producto: 'Nike Air · Talla 40', descripcion: 'Me quedó pequeño', respuesta: null, gestionado_por: null,
  creado_en: '2026-11-21 09:00:00', cliente: { nombre: 'Ana Pérez', email: 'ana@example.com' },
  estados_siguientes: ['aprobada', 'rechazada'],
}

describe('DevolucionesAdmin', () => {
  beforeEach(() => {
    devolucionService.listar.mockReset().mockResolvedValue({ items: [DEVOLUCION], total: 1, paginas: 1, por_revisar: 1 })
    devolucionService.cambiarEstado.mockReset().mockResolvedValue({ mensaje: 'Devolución rechazada.' })
  })

  it('muestra las devoluciones con su tipo y el contador por revisar', async () => {
    const vista = await montar(DevolucionesAdmin)

    expect(vista.text()).toContain('1 por revisar')
    expect(vista.text()).toContain('Talla incorrecta')
    expect(vista.text()).toContain('Me quedó pequeño')
  })

  it('filtra por estado con el semáforo y por tipo con la lista', async () => {
    const vista = await montar(DevolucionesAdmin)
    devolucionService.listar.mockClear()

    await botonSemaforo(vista, 'Aprobada').trigger('click')
    await flushPromises()
    await vista.find('select').setValue('garantia')
    await flushPromises()

    expect(devolucionService.listar).toHaveBeenCalledTimes(2)
    expect(devolucionService.listar.mock.calls[1][0]).toMatchObject({ estado: 'aprobada', tipo: 'garantia', pagina: 1 })
  })

  it('no deja rechazar sin explicar el motivo al cliente', async () => {
    const vista = await montar(DevolucionesAdmin)

    await boton(vista, '.menu-prueba', 'Rechazar').trigger('click')
    await boton(vista, '.gestion', 'Confirmar').trigger('click')

    expect(vista.find('.gestion .error-campo').text()).toContain('por qué se rechaza')
    expect(devolucionService.cambiarEstado).not.toHaveBeenCalled()

    await vista.find('.gestion textarea').setValue('El producto fue usado.')
    await boton(vista, '.gestion', 'Confirmar').trigger('click')
    await flushPromises()

    expect(devolucionService.cambiarEstado).toHaveBeenCalledWith(4, 'rechazada', 'El producto fue usado.')
  })
})
