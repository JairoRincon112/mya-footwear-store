<script setup>
// Devoluciones de un pedido entregado: el cliente ve las que ya pidió y puede solicitar una nueva.
import { computed, reactive, ref } from 'vue'
import EstadoDevolucion from '@/components/EstadoDevolucion.vue'
import devolucionService from '@/services/devolucionService'
import { TIPOS_DEVOLUCION, formatearFecha } from '@/utils/formato'

const props = defineProps({
  pedido: { type: Object, required: true },
})
const emit = defineEmits(['actualizado'])

const abierto = ref(false)
const enviando = ref(false)
const errores = ref({})
const aviso = ref({ tipo: '', texto: '' })
const formulario = reactive({ detalle_id: '', tipo: '', cantidad: 1, descripcion: '' })

/** Unidades de cada línea que todavía se pueden devolver (sin contar las rechazadas). */
const disponibles = computed(() => Object.fromEntries(props.pedido.detalle.map((linea) => {
  const enCurso = (props.pedido.devoluciones ?? [])
    .filter((d) => d.detalle_id === linea.id && d.estado !== 'rechazada')
    .reduce((suma, d) => suma + d.cantidad, 0)
  return [linea.id, linea.cantidad - enCurso]
})))

const lineas = computed(() => props.pedido.detalle.filter((linea) => disponibles.value[linea.id] > 0))
const maximo = computed(() => disponibles.value[formulario.detalle_id] ?? 1)
const puedePedir = computed(() => props.pedido.estado === 'entregado' && lineas.value.length > 0)

function abrir() {
  Object.assign(formulario, { detalle_id: lineas.value[0]?.id ?? '', tipo: '', cantidad: 1, descripcion: '' })
  errores.value = {}
  aviso.value = { tipo: '', texto: '' }
  abierto.value = true
}

function validar() {
  const e = {}
  if (!formulario.detalle_id) e.detalle_id = 'Elige el producto.'
  if (!formulario.tipo) e.tipo = 'Elige el motivo de la devolución.'
  if (!(formulario.cantidad >= 1 && formulario.cantidad <= maximo.value)) e.cantidad = `Puedes devolver entre 1 y ${maximo.value}.`
  if (formulario.descripcion.trim().length < 10) e.descripcion = 'Cuéntanos qué pasó (mínimo 10 caracteres).'
  errores.value = e
  return Object.keys(e).length === 0
}

async function enviar() {
  if (!validar()) return
  enviando.value = true
  try {
    const respuesta = await devolucionService.solicitar(props.pedido.id, {
      detalle_id: Number(formulario.detalle_id),
      tipo: formulario.tipo,
      cantidad: Number(formulario.cantidad),
      descripcion: formulario.descripcion.trim(),
    })
    abierto.value = false
    aviso.value = { tipo: 'exito', texto: respuesta.mensaje }
    emit('actualizado')
  } catch (e) {
    errores.value = e.errores ?? {}
    if (!Object.keys(errores.value).length) aviso.value = { tipo: 'error', texto: e.message }
  } finally {
    enviando.value = false
  }
}
</script>

<template>
  <section v-if="pedido.estado === 'entregado' || pedido.devoluciones?.length" class="devoluciones" aria-labelledby="titulo-devoluciones">
    <div class="encabezado">
      <h2 id="titulo-devoluciones"><i class="fa-solid fa-rotate-left"></i> Devoluciones</h2>
      <button v-if="puedePedir && !abierto" type="button" class="boton boton-secundario" @click="abrir">
        Solicitar devolución
      </button>
    </div>
    <p v-if="pedido.estado === 'entregado'" class="texto-secundario">
      Tienes {{ pedido.devolucion_dias_plazo }} días después de la entrega para pedir una devolución.
    </p>

    <p v-if="aviso.texto" class="alerta" :class="`alerta-${aviso.tipo}`" role="status">{{ aviso.texto }}</p>

    <form v-if="abierto" class="formulario" novalidate @submit.prevent="enviar">
      <label class="campo" :class="{ 'con-error': errores.detalle_id }">
        Producto
        <select v-model="formulario.detalle_id">
          <option v-for="linea in lineas" :key="linea.id" :value="linea.id">
            {{ linea.descripcion }} ({{ disponibles[linea.id] }} {{ disponibles[linea.id] === 1 ? 'unidad' : 'unidades' }})
          </option>
        </select>
        <span v-if="errores.detalle_id" class="error-campo">{{ errores.detalle_id }}</span>
      </label>

      <fieldset class="tipos" :class="{ 'con-error': errores.tipo }">
        <legend>Motivo de la devolución</legend>
        <label v-for="(info, clave) in TIPOS_DEVOLUCION" :key="clave" class="tipo" :class="{ elegido: formulario.tipo === clave }">
          <input v-model="formulario.tipo" type="radio" name="tipo" :value="clave">
          <i class="fa-solid" :class="info.icono" aria-hidden="true"></i>
          <span><strong>{{ info.texto }}</strong><small>{{ info.ayuda }}</small></span>
        </label>
        <span v-if="errores.tipo" class="error-campo">{{ errores.tipo }}</span>
      </fieldset>

      <div class="fila">
        <label class="campo cantidad" :class="{ 'con-error': errores.cantidad }">
          Unidades
          <input v-model.number="formulario.cantidad" type="number" min="1" :max="maximo">
          <span v-if="errores.cantidad" class="error-campo">{{ errores.cantidad }}</span>
        </label>
        <label class="campo descripcion" :class="{ 'con-error': errores.descripcion }">
          ¿Qué pasó?
          <textarea v-model="formulario.descripcion" rows="3" maxlength="500" placeholder="Ej: me quedó pequeña, necesito una talla 40."></textarea>
          <span v-if="errores.descripcion" class="error-campo">{{ errores.descripcion }}</span>
        </label>
      </div>

      <div class="botones">
        <button type="submit" class="boton" :disabled="enviando">
          <i v-if="enviando" class="fa-solid fa-spinner fa-spin"></i> Enviar solicitud
        </button>
        <button type="button" class="boton boton-secundario" @click="abierto = false">Cancelar</button>
      </div>
    </form>

    <ul v-if="pedido.devoluciones?.length" class="lista">
      <li v-for="devolucion in pedido.devoluciones" :key="devolucion.id">
        <div class="linea-1">
          <strong>#{{ devolucion.id }} · {{ TIPOS_DEVOLUCION[devolucion.tipo]?.texto }}</strong>
          <EstadoDevolucion :estado="devolucion.estado" />
        </div>
        <p>{{ devolucion.producto }} · {{ devolucion.cantidad }} {{ devolucion.cantidad === 1 ? 'unidad' : 'unidades' }}</p>
        <p class="texto-secundario">{{ devolucion.descripcion }} · {{ formatearFecha(devolucion.creado_en) }}</p>
        <p v-if="devolucion.respuesta" class="respuesta"><i class="fa-solid fa-store"></i> {{ devolucion.respuesta }}</p>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.devoluciones {
  display: grid;
  gap: 12px;
  padding-top: 16px;
  border-top: 1px solid #e6e6e6;
}

.encabezado {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.encabezado h2 {
  font-size: 1.15rem;
}

.formulario {
  display: grid;
  gap: 14px;
  padding: 16px;
  background: #fafafa;
  border: 1px solid #e6e6e6;
  border-radius: 10px;
}

.tipos {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 8px;
  padding: 0;
  border: 0;
}

.tipos legend {
  margin-bottom: 6px;
  font-weight: 600;
}

.tipo {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 12px;
  background: #fff;
  border: 2px solid #e2e2e2;
  border-radius: 10px;
  cursor: pointer;
}

.tipo input {
  position: absolute;
  opacity: 0;
}

.tipo i {
  margin-top: 3px;
  color: var(--naranja);
}

.tipo span {
  display: grid;
}

.tipo small {
  font-size: .8rem;
  color: var(--texto-secundario);
}

.tipo.elegido {
  background: #fff3eb;
  border-color: var(--naranja);
}

.tipo:focus-within {
  outline: 3px solid #1d2430;
  outline-offset: 2px;
}

.tipos.con-error .tipo {
  border-color: var(--error);
}

.fila {
  display: grid;
  grid-template-columns: 140px minmax(0, 1fr);
  align-items: start;
  gap: 12px;
}

.cantidad input {
  height: auto;
}

.botones {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.lista {
  display: grid;
  gap: 10px;
  padding: 0;
  list-style: none;
}

.lista li {
  display: grid;
  gap: 4px;
  padding: 12px 14px;
  border: 1px solid #e6e6e6;
  border-radius: 10px;
}

.linea-1 {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.respuesta {
  padding: 8px 10px;
  font-size: .88rem;
  background: #f4f6f8;
  border-radius: 8px;
}

.texto-secundario {
  font-size: .85rem;
  color: var(--texto-secundario);
}

@media (max-width: 560px) {
  .fila {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
