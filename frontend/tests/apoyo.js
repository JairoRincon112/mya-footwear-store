// Utilidades compartidas por las pruebas de las vistas del panel.
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

/** Sustituto del menú "⋮": muestra cada acción como un botón para poder hacer clic en ella. */
export const MenuAccionesSimple = {
  props: { items: { type: Array, required: true }, etiqueta: { type: String, default: '' } },
  template: '<div class="menu-prueba"><button v-for="item in items" :key="item.texto" type="button" class="accion" @click="item.accion()">{{ item.texto }}</button></div>',
}

export async function montar(componente) {
  const pinia = createPinia()
  setActivePinia(pinia)
  const envoltura = mount(componente, {
    global: {
      plugins: [pinia],
      stubs: { RouterLink: { template: '<a><slot /></a>' }, MenuAcciones: MenuAccionesSimple, Teleport: true },
    },
  })
  await flushPromises()
  return envoltura
}

/** Botón del semáforo de filtros por su texto visible. */
export function botonSemaforo(envoltura, texto) {
  return envoltura.findAll('.semaforo button').find((boton) => boton.text().includes(texto))
}

/** Botón por su texto dentro de un selector. */
export function boton(envoltura, selector, texto) {
  return envoltura.findAll(`${selector} button`).find((b) => b.text().startsWith(texto))
}
