<script setup>
// Listado de productos del panel con filtros y paginación.
import { onMounted, reactive, ref, watch } from 'vue'
import productoService from '@/services/productoService'
import { urlImagen } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useCatalogoStore } from '@/stores/catalogo'
import { formatearPrecio } from '@/utils/formato'

const auth = useAuthStore()
const catalogo = useCatalogoStore()

const filtros = reactive({ q: '', marca: '', estado: 'todos', pagina: 1 })
const resultado = ref({ items: [], total: 0, paginas: 0 })
const cargando = ref(true)
const error = ref('')
const aviso = ref('')

let temporizador = null

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    resultado.value = await productoService.listar({ ...filtros, por_pagina: 15, orden: 'recientes' })
  } catch (e) {
    error.value = e.message
  } finally {
    cargando.value = false
  }
}

watch(() => [filtros.marca, filtros.estado], () => {
  filtros.pagina = 1
  cargar()
})
watch(() => filtros.q, () => {
  clearTimeout(temporizador)
  temporizador = setTimeout(() => {
    filtros.pagina = 1
    cargar()
  }, 350)
})

function irAPagina(pagina) {
  filtros.pagina = pagina
  cargar()
}

async function desactivar(producto) {
  if (!confirm(`¿Desactivar "${producto.nombre}"? Dejará de verse en la tienda.`)) return
  try {
    const respuesta = await productoService.desactivar(producto.id)
    aviso.value = respuesta.mensaje
    cargar()
  } catch (e) {
    error.value = e.message
  }
}

onMounted(() => {
  catalogo.cargar().catch(() => {})
  cargar()
})
</script>

<template>
  <section>
    <div class="panel-encabezado">
      <h2>Productos</h2>
      <RouterLink v-if="auth.esAdmin" :to="{ name: 'panel-producto-nuevo' }" class="boton">
        <i class="fa-solid fa-plus"></i> Nuevo producto
      </RouterLink>
    </div>

    <div class="panel-tarjeta">
      <form class="filtros" role="search" @submit.prevent>
        <label class="campo">
          Buscar
          <input v-model="filtros.q" type="search" placeholder="Nombre del producto" maxlength="100">
        </label>
        <label class="campo">
          Marca
          <select v-model="filtros.marca">
            <option value="">Todas</option>
            <option v-for="marca in catalogo.marcas" :key="marca.id" :value="marca.slug">{{ marca.nombre }}</option>
          </select>
        </label>
        <label class="campo">
          Estado
          <select v-model="filtros.estado">
            <option value="todos">Todos</option>
            <option value="activos">Activos</option>
            <option value="inactivos">Desactivados</option>
          </select>
        </label>
      </form>

      <p v-if="aviso" class="alerta alerta-exito" role="status">{{ aviso }}</p>
      <p v-if="error" class="alerta alerta-error" role="alert">{{ error }}</p>

      <p v-if="cargando && !resultado.items.length" class="cargando"><i class="fa-solid fa-spinner fa-spin"></i>Cargando...</p>
      <p v-else-if="!resultado.items.length" class="cargando">No hay productos con esos filtros.</p>

      <div v-else class="tabla-contenedor">
        <table class="tabla">
          <thead>
            <tr>
              <th><span class="solo-lectores">Imagen</span></th>
              <th>Producto</th>
              <th>Marca</th>
              <th class="numero">Precio</th>
              <th class="numero">Stock</th>
              <th class="numero">Variantes</th>
              <th>Estado</th>
              <th><span class="solo-lectores">Acciones</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="producto in resultado.items" :key="producto.id">
              <td><img :src="urlImagen(producto.imagen)" alt="" class="miniatura"></td>
              <td>
                <strong>{{ producto.nombre }}</strong>
                <div class="texto-secundario">{{ producto.slug }}</div>
              </td>
              <td>{{ producto.marca.nombre }}</td>
              <td class="numero">
                {{ formatearPrecio(producto.precio) }}
                <div v-if="producto.en_oferta" class="texto-secundario"><i class="fa-solid fa-tag"></i> antes {{ formatearPrecio(producto.precio_regular) }}</div>
              </td>
              <td class="numero">
                <span v-if="producto.stock_total === 0" class="etiqueta etiqueta-agotada">Agotado</span>
                <template v-else>{{ producto.stock_total }}</template>
              </td>
              <td class="numero">{{ producto.total_variantes }}</td>
              <td>
                <span class="etiqueta" :class="producto.activo ? 'etiqueta-activa' : 'etiqueta-inactiva'">
                  {{ producto.activo ? 'Activo' : 'Desactivado' }}
                </span>
              </td>
              <td class="acciones">
                <RouterLink :to="{ name: 'panel-producto', params: { id: producto.id } }" class="boton boton-secundario boton-pequeno">
                  {{ auth.esAdmin ? 'Editar' : 'Ver / stock' }}
                </RouterLink>
                <button v-if="auth.esAdmin && producto.activo" type="button" class="boton boton-secundario boton-pequeno" @click="desactivar(producto)">
                  Desactivar
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="resultado.paginas > 1" class="paginacion">
        <button type="button" class="boton boton-secundario boton-pequeno" :disabled="filtros.pagina <= 1" @click="irAPagina(filtros.pagina - 1)">Anterior</button>
        <span>Página {{ filtros.pagina }} de {{ resultado.paginas }} · {{ resultado.total }} productos</span>
        <button type="button" class="boton boton-secundario boton-pequeno" :disabled="filtros.pagina >= resultado.paginas" @click="irAPagina(filtros.pagina + 1)">Siguiente</button>
      </div>
    </div>
  </section>
</template>

<style scoped>
.filtros {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr;
  gap: 12px;
  margin-bottom: 16px;
}

.acciones {
  display: flex;
  gap: 6px;
  justify-content: flex-end;
  white-space: nowrap;
}

.paginacion {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 14px;
  margin-top: 16px;
  font-size: .9rem;
}

.alerta {
  margin-bottom: 12px;
}

@media (max-width: 640px) {
  .filtros {
    grid-template-columns: minmax(0, 1fr); /* minmax: el contenido ancho (tablas) no estira la página */
  }
}
</style>
