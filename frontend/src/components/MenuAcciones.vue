<script setup>
// Un solo botón "⋮" por fila que abre la lista de acciones (en vez de varios botones sueltos).
// items: [{ texto, icono, accion?: () => void, to?: ruta de Vue Router, peligro?, deshabilitado? }]
import { nextTick, onBeforeUnmount, ref } from 'vue'
import { useRouter } from 'vue-router'

const props = defineProps({
  items: { type: Array, required: true },
  etiqueta: { type: String, default: 'Acciones' },
})

const router = useRouter()
const abierto = ref(false)
const boton = ref(null)
const menu = ref(null)
const posicion = ref({ top: 0, left: 0 })
const visibles = () => props.items.filter(Boolean)

function cerrar(devolverFoco = false) {
  if (!abierto.value) return
  abierto.value = false
  document.removeEventListener('pointerdown', alTocarFuera, true)
  window.removeEventListener('scroll', alDesplazar, true)
  window.removeEventListener('resize', alDesplazar)
  if (devolverFoco) boton.value?.focus()
}

function alTocarFuera(evento) {
  if (!menu.value?.contains(evento.target) && !boton.value?.contains(evento.target)) cerrar()
}

function alDesplazar() {
  cerrar()
}

async function abrir() {
  if (abierto.value) return cerrar(true)
  // El menú usa position: fixed para que no lo recorte el contenedor con scroll de la tabla.
  const caja = boton.value.getBoundingClientRect()
  posicion.value = { top: caja.bottom + 4, left: caja.right }
  abierto.value = true
  document.addEventListener('pointerdown', alTocarFuera, true)
  window.addEventListener('scroll', alDesplazar, true)
  window.addEventListener('resize', alDesplazar)
  await nextTick()
  const caja2 = menu.value.getBoundingClientRect()
  // Si no cabe abajo, se abre hacia arriba.
  if (caja2.bottom > window.innerHeight - 8) posicion.value.top = caja.top - caja2.height - 4
  menu.value.querySelector('[role="menuitem"]:not([disabled])')?.focus()
}

function elegir(item) {
  if (item.deshabilitado) return
  cerrar(true)
  if (item.to) router.push(item.to)
  else item.accion?.()
}

function teclado(evento) {
  const opciones = [...menu.value.querySelectorAll('[role="menuitem"]:not([disabled])')]
  const actual = opciones.indexOf(document.activeElement)
  if (evento.key === 'Escape') { evento.preventDefault(); cerrar(true) }
  else if (evento.key === 'ArrowDown') { evento.preventDefault(); opciones[(actual + 1) % opciones.length]?.focus() }
  else if (evento.key === 'ArrowUp') { evento.preventDefault(); opciones[(actual - 1 + opciones.length) % opciones.length]?.focus() }
  else if (evento.key === 'Tab') cerrar()
}

onBeforeUnmount(() => cerrar())
</script>

<template>
  <div class="menu-acciones">
    <button
      ref="boton"
      type="button"
      class="disparador"
      :aria-label="etiqueta"
      :title="etiqueta"
      aria-haspopup="menu"
      :aria-expanded="abierto"
      @click.stop="abrir"
    >
      <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
    </button>

    <Teleport to="body">
      <div
        v-if="abierto"
        ref="menu"
        class="menu-acciones-lista"
        role="menu"
        :aria-label="etiqueta"
        :style="{ top: `${posicion.top}px`, left: `${posicion.left}px` }"
        @keydown="teclado"
      >
        <button
          v-for="item in visibles()"
          :key="item.texto"
          type="button"
          role="menuitem"
          :class="{ peligro: item.peligro }"
          :disabled="item.deshabilitado"
          @click="elegir(item)"
        >
          <i class="fa-solid" :class="item.icono" aria-hidden="true"></i>{{ item.texto }}
        </button>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.menu-acciones {
  display: inline-block;
}

.disparador {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  font-size: 1rem;
  color: #3a3f47;
  background: transparent;
  border: 1px solid #d9dde3;
  border-radius: 8px;
  cursor: pointer;
}

.disparador:hover,
.disparador[aria-expanded='true'] {
  background: #f1f3f5;
}

.disparador:focus-visible {
  outline: 3px solid #1d2430;
  outline-offset: 2px;
}
</style>

<style>
/* Sin scoped: la lista se monta en <body> con Teleport. */
.menu-acciones-lista {
  position: fixed;
  z-index: 1000;
  display: grid;
  min-width: 190px;
  padding: 6px;
  background: #fff;
  border: 1px solid #e4e6ea;
  border-radius: 10px;
  box-shadow: 0 10px 28px rgba(0, 0, 0, .14);
  transform: translateX(-100%);
}

.menu-acciones-lista button {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 12px;
  font: inherit;
  font-size: .9rem;
  text-align: left;
  color: #1d2430;
  background: none;
  border: 0;
  border-radius: 6px;
  cursor: pointer;
}

.menu-acciones-lista button i {
  width: 16px;
  text-align: center;
  color: #6b7280;
}

.menu-acciones-lista button:hover:not(:disabled),
.menu-acciones-lista button:focus-visible {
  background: #f1f3f5;
  outline: none;
}

.menu-acciones-lista button.peligro,
.menu-acciones-lista button.peligro i {
  color: #b71c1c;
}

.menu-acciones-lista button:disabled {
  color: #a0a4ab;
  cursor: not-allowed;
}
</style>
