// Store del carrito (Pinia). Se guarda en localStorage para no perderlo al recargar.
// Cada línea es una VARIANTE (producto + talla + color). El backend vuelve a
// validar precios y stock al crear el pedido.
import { defineStore } from 'pinia'
import { computed, ref, watch } from 'vue'
import { almacenamiento } from '@/utils/formato'

const CLAVE_CARRITO = 'mya_carrito'

export const useCarritoStore = defineStore('carrito', () => {
  const items = ref(almacenamiento.leer(CLAVE_CARRITO, []))
  const abierto = ref(false)

  const cantidadTotal = computed(() => items.value.reduce((suma, item) => suma + item.cantidad, 0))
  const total = computed(() => items.value.reduce((suma, item) => suma + item.precio * item.cantidad, 0))

  watch(items, (valor) => almacenamiento.guardar(CLAVE_CARRITO, valor), { deep: true })

  /** Agrega una variante; nunca supera el stock disponible. Devuelve la cantidad final. */
  function agregar(producto, variante, cantidad = 1) {
    const existente = items.value.find((item) => item.varianteId === variante.id)
    if (existente) {
      existente.cantidad = Math.min(variante.stock, existente.cantidad + cantidad)
      existente.stock = variante.stock
      return existente.cantidad
    }

    items.value.push({
      varianteId: variante.id,
      slug: producto.slug,
      nombre: producto.nombre,
      marca: producto.marca.nombre,
      color: variante.color.nombre,
      talla: variante.talla.valor,
      precio: producto.precio,
      imagen: producto.imagen,
      stock: variante.stock,
      cantidad: Math.min(variante.stock, cantidad),
    })
    return Math.min(variante.stock, cantidad)
  }

  function cambiarCantidad(varianteId, cantidad) {
    const item = items.value.find((linea) => linea.varianteId === varianteId)
    if (item) {
      item.cantidad = Math.max(1, Math.min(item.stock, cantidad))
    }
  }

  function quitar(varianteId) {
    items.value = items.value.filter((item) => item.varianteId !== varianteId)
  }

  function vaciar() {
    items.value = []
  }

  /** Actualiza los precios del carrito con los del servidor (por si una oferta empezó o terminó). */
  function actualizarPrecios(lineas) {
    for (const linea of lineas) {
      const item = items.value.find((i) => i.varianteId === linea.variante_id)
      if (item) item.precio = linea.precio
    }
  }

  return { items, abierto, cantidadTotal, total, agregar, cambiarCantidad, quitar, vaciar, actualizarPrecios }
})
