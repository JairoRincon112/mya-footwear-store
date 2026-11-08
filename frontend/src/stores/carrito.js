// Store del carrito (Pinia). Se guarda en localStorage para no perderlo al recargar.
// Cada línea es una VARIANTE (producto + talla + color). El backend vuelve a
// validar precios y stock al crear el pedido.
import { defineStore } from 'pinia'
import { computed, ref, watch } from 'vue'
import { almacenamiento } from '@/utils/formato'

const CLAVE_CARRITO = 'mya_carrito'

/** Unidades que admite una línea: el stock, o el máximo por producto si es menor. */
export function limiteDeLinea(stock, maximoPorLinea) {
  return maximoPorLinea ? Math.min(stock, maximoPorLinea) : stock
}

// Las líneas guardadas antes de existir "limite" usan el stock.
const limiteDe = (item) => item.limite ?? item.stock

export const useCarritoStore = defineStore('carrito', () => {
  const items = ref(almacenamiento.leer(CLAVE_CARRITO, []))
  const abierto = ref(false)

  const cantidadTotal = computed(() => items.value.reduce((suma, item) => suma + item.cantidad, 0))
  const total = computed(() => items.value.reduce((suma, item) => suma + item.precio * item.cantidad, 0))

  watch(items, (valor) => almacenamiento.guardar(CLAVE_CARRITO, valor), { deep: true })

  /**
   * Agrega una variante. Nunca supera el límite de la línea: el stock disponible o el
   * máximo por producto que publica el servidor (maximo_por_linea), el que sea menor.
   * Devuelve la cantidad final.
   */
  function agregar(producto, variante, cantidad = 1) {
    const limite = limiteDeLinea(variante.stock, producto.maximo_por_linea)
    const existente = items.value.find((item) => item.varianteId === variante.id)
    if (existente) {
      existente.stock = variante.stock
      existente.limite = limite
      existente.cantidad = Math.min(limite, existente.cantidad + cantidad)
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
      limite,
      cantidad: Math.min(limite, cantidad),
    })
    return Math.min(limite, cantidad)
  }

  function cambiarCantidad(varianteId, cantidad) {
    const item = items.value.find((linea) => linea.varianteId === varianteId)
    if (item) {
      item.cantidad = Math.max(1, Math.min(limiteDe(item), cantidad))
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

  return { items, abierto, cantidadTotal, total, agregar, cambiarCantidad, quitar, vaciar, actualizarPrecios, limiteDe }
})
