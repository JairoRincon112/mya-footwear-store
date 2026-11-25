<script setup>
// Listado de productos del panel con filtros y paginación.
import MenuAcciones from '@/components/MenuAcciones.vue'
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useListadoPanel } from '@/composables/useListadoPanel'
import productoService from '@/services/productoService'
import { urlImagen } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useCatalogoStore } from '@/stores/catalogo'
import { formatearPrecio } from '@/utils/formato'

const PRODUCTOS_POR_PAGINA = 15

const router = useRouter()
const auth = useAuthStore()
const catalogo = useCatalogoStore()

const { filtros, resultado, cargando, error, cargar, irAPagina } = useListadoPanel(
  (f) => productoService.listar({ ...f, por_pagina: PRODUCTOS_POR_PAGINA, orden: 'recientes' }),
  { marca: '', estado: 'todos' },
)
const aviso = ref('')

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
      <div class="semaforo" role="group" aria-label="Filtrar por estado">
        <button type="button" class="sem-neutro" :class="{ activo: filtros.estado === 'todos' }" :aria-pressed="filtros.estado === 'todos'" @click="filtros.estado = 'todos'"><i class="fa-solid fa-list" aria-hidden="true"></i>Todos</button>
        <button type="button" class="sem-verde" :class="{ activo: filtros.estado === 'activos' }" :aria-pressed="filtros.estado === 'activos'" @click="filtros.estado = 'activos'"><span class="luz" aria-hidden="true"></span>Activos</button>
        <button type="button" class="sem-rojo" :class="{ activo: filtros.estado === 'inactivos' }" :aria-pressed="filtros.estado === 'inactivos'" @click="filtros.estado = 'inactivos'"><span class="luz" aria-hidden="true"></span>Desactivados</button>
      </div>

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
            <tr v-for="producto in resultado.items" :key="producto.id" class="fila-clic" @click="router.push({ name: 'panel-producto', params: { id: producto.id } })">
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
              <td class="acciones" @click.stop>
                <MenuAcciones
                  :etiqueta="`Acciones de ${producto.nombre}`"
                  :items="[
                    { texto: auth.esAdmin ? 'Editar' : 'Ver y ajustar stock', icono: auth.esAdmin ? 'fa-pen' : 'fa-eye', to: { name: 'panel-producto', params: { id: producto.id } } },
                    auth.esAdmin && producto.activo && { texto: 'Desactivar', icono: 'fa-ban', peligro: true, accion: () => desactivar(producto) },
                  ]"
                />
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
  grid-template-columns: 2fr 1fr;
  gap: 12px;
  margin-bottom: 16px;
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
