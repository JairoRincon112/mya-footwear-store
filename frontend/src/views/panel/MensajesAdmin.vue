<script setup>
// Mensajes del formulario de contacto.
import { onMounted, ref, watch } from 'vue'
import Paginacion from '@/components/Paginacion.vue'
import adminService from '@/services/adminService'
import { formatearFecha } from '@/utils/formato'

const filtro = ref('0') // '0' = no leídos, '1' = leídos, '' = todos
const pagina = ref(1)
const resultado = ref({ items: [], total: 0, paginas: 0, no_leidos: 0 })
const cargando = ref(true)
const error = ref('')

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    resultado.value = await adminService.mensajes({ leido: filtro.value, pagina: pagina.value })
  } catch (e) {
    error.value = e.message
  } finally {
    cargando.value = false
  }
}

watch(filtro, () => {
  pagina.value = 1
  cargar()
})

function irAPagina(nueva) {
  pagina.value = nueva
  cargar()
}

async function alternarLeido(mensaje) {
  try {
    const respuesta = await adminService.marcarLeido(mensaje.id, !mensaje.leido)
    mensaje.leido = respuesta.data.leido
    resultado.value.no_leidos = respuesta.data.no_leidos
  } catch (e) {
    error.value = e.message
  }
}

onMounted(cargar)
</script>

<template>
  <section>
    <div class="panel-encabezado">
      <h2>Mensajes <span v-if="resultado.no_leidos" class="contador">{{ resultado.no_leidos }} sin leer</span></h2>
    </div>

    <div class="panel-tarjeta">
      <div class="pestanas" role="group" aria-label="Filtrar mensajes">
        <button type="button" :class="{ activo: filtro === '0' }" @click="filtro = '0'">Sin leer</button>
        <button type="button" :class="{ activo: filtro === '1' }" @click="filtro = '1'">Leídos</button>
        <button type="button" :class="{ activo: filtro === '' }" @click="filtro = ''">Todos</button>
      </div>

      <p v-if="error" class="alerta alerta-error">{{ error }}</p>
      <p v-if="cargando && !resultado.items.length" class="cargando"><i class="fa-solid fa-spinner fa-spin"></i>Cargando...</p>
      <p v-else-if="!resultado.items.length" class="cargando">No hay mensajes aquí.</p>

      <ul v-else class="mensajes">
        <li v-for="mensaje in resultado.items" :key="mensaje.id" :class="{ nuevo: !mensaje.leido }">
          <div class="cabecera">
            <div>
              <strong>{{ mensaje.nombre }}</strong>
              <span class="texto-secundario">
                · <a :href="`mailto:${mensaje.email}`">{{ mensaje.email }}</a>
                <template v-if="mensaje.telefono"> · <a :href="`tel:${mensaje.telefono}`">{{ mensaje.telefono }}</a></template>
              </span>
            </div>
            <span class="texto-secundario">{{ formatearFecha(mensaje.creado_en) }}</span>
          </div>
          <p class="texto">{{ mensaje.mensaje }}</p>
          <div class="acciones">
            <a :href="`mailto:${mensaje.email}?subject=${encodeURIComponent('Respuesta de MyA FOOTWEAR STORE')}`" class="boton boton-pequeno">
              <i class="fa-solid fa-reply"></i> Responder por correo
            </a>
            <button type="button" class="boton boton-secundario boton-pequeno" @click="alternarLeido(mensaje)">
              {{ mensaje.leido ? 'Marcar como no leído' : 'Marcar como leído' }}
            </button>
          </div>
        </li>
      </ul>

      <Paginacion :pagina="pagina" :paginas="resultado.paginas" :total="resultado.total" etiqueta="mensajes" @cambiar="irAPagina" />
    </div>
  </section>
</template>

<style scoped>
.contador {
  margin-left: 8px;
  padding: 2px 10px;
  font-size: .8rem;
  vertical-align: middle;
  color: #7a4b00;
  background: #fff1d6;
  border-radius: 20px;
}

.pestanas {
  display: flex;
  gap: 8px;
  margin-bottom: 16px;
}

.pestanas button {
  padding: 6px 14px;
  font-size: .88rem;
  font-weight: 500;
  background: var(--gris-suave);
  border: 0;
  border-radius: 20px;
  cursor: pointer;
}

.pestanas button.activo {
  background: var(--naranja);
}

.mensajes {
  display: grid;
  gap: 12px;
}

.mensajes li {
  display: grid;
  gap: 10px;
  padding: 16px;
  border: 1px solid #e6e6e6;
  border-radius: 10px;
}

.mensajes li.nuevo {
  border-left: 4px solid var(--naranja);
}

.cabecera {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 8px;
}

.cabecera a:hover {
  color: var(--naranja);
}

.texto {
  white-space: pre-line;
  line-height: 1.5;
}

.acciones {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
</style>
