// Ajuste manual de stock desde el inventario: reglas de la cantidad y del motivo.
import { flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import InventarioAdmin from '@/views/panel/InventarioAdmin.vue'
import pedidoService from '@/services/pedidoService'
import productoService from '@/services/productoService'
import { boton, montar } from './apoyo'

vi.mock('vue-router', () => ({ useRouter: () => ({ push: vi.fn() }), useRoute: () => ({ query: {} }) }))
vi.mock('@/services/pedidoService', () => ({ default: { inventario: vi.fn(), movimientos: vi.fn() } }))
vi.mock('@/services/productoService', () => ({
  default: { ajustarStock: vi.fn(), marcas: vi.fn(async () => []), tallas: vi.fn(async () => []), colores: vi.fn(async () => []) },
}))

const VARIANTE = {
  id: 9, sku: 'NK-AIR-40', talla: '40', stock: 2, activo: true,
  color: { nombre: 'Negro' }, producto: { id: 1, nombre: 'Nike Air', marca: 'Nike', activo: true },
}

async function abrirAjuste() {
  const vista = await montar(InventarioAdmin)
  await boton(vista, '.menu-prueba', 'Ajustar stock').trigger('click')
  return vista
}

async function guardar(vista, { tipo, cantidad, motivo = '' }) {
  await vista.find('form.ajuste select').setValue(tipo)
  await vista.find('form.ajuste input[type=number]').setValue(cantidad)
  await vista.find('form.ajuste input[type=text]').setValue(motivo)
  await vista.find('form.ajuste').trigger('submit')
  await flushPromises()
}

describe('InventarioAdmin · ajuste de stock', () => {
  beforeEach(() => {
    pedidoService.inventario.mockReset().mockResolvedValue({ items: [VARIANTE], total: 1, paginas: 1, umbral: 3 })
    productoService.ajustarStock.mockReset().mockResolvedValue({ data: { sku: 'NK-AIR-40', stock: 7 } })
  })

  it('marca el bajo stock de la variante', async () => {
    const vista = await montar(InventarioAdmin)

    expect(vista.find('.etiqueta-bajo').text()).toContain('2')
  })

  it('rechaza cantidad cero o con decimales', async () => {
    const vista = await abrirAjuste()

    for (const cantidad of ['0', '1.5']) {
      await guardar(vista, { tipo: 'entrada', cantidad })
      expect(vista.find('form.ajuste .error-campo').text()).toMatch(/entera distinta de 0/)
    }
    expect(productoService.ajustarStock).not.toHaveBeenCalled()
  })

  it('una entrada no puede ser negativa y un ajuste necesita motivo', async () => {
    const vista = await abrirAjuste()

    await guardar(vista, { tipo: 'entrada', cantidad: '-2' })
    expect(vista.text()).toMatch(/Para descontar.*Ajuste/)

    await guardar(vista, { tipo: 'ajuste', cantidad: '-2' })
    expect(vista.text()).toMatch(/Explica el motivo/)
    expect(productoService.ajustarStock).not.toHaveBeenCalled()
  })

  it('envía el ajuste válido y muestra el stock nuevo', async () => {
    const vista = await abrirAjuste()

    await guardar(vista, { tipo: 'entrada', cantidad: '5', motivo: '  Llegó mercancía  ' })

    expect(productoService.ajustarStock).toHaveBeenCalledWith(9, { tipo: 'entrada', cantidad: 5, motivo: 'Llegó mercancía' })
    expect(vista.find('[role=status]').text()).toContain('stock actualizado a 7')
  })
})
