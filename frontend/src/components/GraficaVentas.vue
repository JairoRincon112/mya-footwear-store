<script setup>
// Gráfica de barras de ventas por día (una sola serie), con tooltip al pasar el mouse
// y vista de tabla como alternativa accesible.
import { computed, ref } from 'vue'
import { formatearPrecio } from '@/utils/formato'

const props = defineProps({
  datos: { type: Array, required: true }, // [{ dia: 'AAAA-MM-DD', pedidos, total }]
})

const vistaTabla = ref(false)
const activo = ref(null)

const formatoDia = new Intl.DateTimeFormat('es-CO', { day: 'numeric', month: 'short' })
const etiquetaDia = (dia) => formatoDia.format(new Date(`${dia}T12:00:00`))

/** Escala "redonda" para el eje: 1, 2 o 5 × 10^n por encima del máximo. */
const maximo = computed(() => {
  const mayor = Math.max(...props.datos.map((d) => d.total), 0)
  if (mayor === 0) return 1
  const potencia = 10 ** Math.floor(Math.log10(mayor))
  const paso = [1, 2, 5, 10].find((m) => m * potencia >= mayor)
  return paso * potencia
})

const lineasGuia = computed(() => [1, 0.5, 0].map((f) => ({ fraccion: f, valor: maximo.value * f })))
const totalPeriodo = computed(() => props.datos.reduce((suma, d) => suma + d.total, 0))

const compacto = new Intl.NumberFormat('es-CO', { notation: 'compact', maximumFractionDigits: 1 })
</script>

<template>
  <div class="grafica-ventas">
    <div class="cabecera">
      <p class="texto-secundario">Total del periodo: <strong>{{ formatearPrecio(totalPeriodo) }}</strong></p>
      <button type="button" class="boton boton-secundario boton-pequeno" :aria-pressed="vistaTabla" @click="vistaTabla = !vistaTabla">
        <i class="fa-solid" :class="vistaTabla ? 'fa-chart-column' : 'fa-table'"></i>
        {{ vistaTabla ? 'Ver gráfica' : 'Ver tabla' }}
      </button>
    </div>

    <div v-if="!vistaTabla" class="area" role="img" :aria-label="`Ventas por día de los últimos ${datos.length} días. Total ${formatearPrecio(totalPeriodo)}.`">
      <div class="eje-y" aria-hidden="true">
        <span v-for="linea in lineasGuia" :key="linea.fraccion" :style="{ bottom: `${linea.fraccion * 100}%` }">${{ compacto.format(linea.valor) }}</span>
      </div>
      <div class="plot">
        <div v-for="linea in lineasGuia" :key="linea.fraccion" class="guia" :style="{ bottom: `${linea.fraccion * 100}%` }"></div>
        <div class="barras" @mouseleave="activo = null">
          <div
            v-for="(dato, indice) in datos"
            :key="dato.dia"
            class="columna"
            @mouseenter="activo = indice"
            @focus="activo = indice"
            @blur="activo = null"
            tabindex="0"
            :aria-label="`${etiquetaDia(dato.dia)}: ${formatearPrecio(dato.total)}, ${dato.pedidos} pedidos`"
          >
            <div class="barra" :class="{ resaltada: activo === indice }" :style="{ height: `${(dato.total / maximo) * 100}%` }"></div>
            <div v-if="activo === indice" class="tooltip" :class="{ izquierda: indice > datos.length * 0.7 }" role="tooltip">
              <strong>{{ etiquetaDia(dato.dia) }}</strong>
              <span>{{ formatearPrecio(dato.total) }}</span>
              <span class="texto-secundario">{{ dato.pedidos }} pedido(s)</span>
            </div>
          </div>
        </div>
      </div>
      <div class="eje-x" aria-hidden="true">
        <span v-for="(dato, indice) in datos" :key="dato.dia" :class="{ secundaria: indice % 15 !== 0 && indice !== datos.length - 1 }">{{ indice % 5 === 0 || indice === datos.length - 1 ? etiquetaDia(dato.dia) : '' }}</span>
      </div>
    </div>

    <div v-else class="tabla-contenedor tabla-ventas">
      <table class="tabla">
        <thead>
          <tr>
            <th>Día</th>
            <th class="numero">Pedidos</th>
            <th class="numero">Ventas</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="dato in [...datos].reverse()" :key="dato.dia">
            <td>{{ etiquetaDia(dato.dia) }}</td>
            <td class="numero">{{ dato.pedidos }}</td>
            <td class="numero">{{ formatearPrecio(dato.total) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.grafica-ventas {
  --color-serie: #e05a00; /* naranja de la marca validado: contraste ≥ 3:1 sobre blanco */
}

.cabecera {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 16px;
}

.area {
  display: grid;
  /* minmax(0, 1fr): las 30 etiquetas del eje no ensanchan la gráfica en celulares */
  grid-template-columns: 56px minmax(0, 1fr);
  grid-template-rows: 220px auto;
}

.eje-y {
  position: relative;
}

.eje-y span {
  position: absolute;
  right: 8px;
  transform: translateY(50%);
  font-size: .75rem;
  color: #6b6b6b;
  font-variant-numeric: tabular-nums;
}

.plot {
  position: relative;
}

.guia {
  position: absolute;
  left: 0;
  right: 0;
  border-top: 1px solid #ececec;
}

.barras {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: flex-end;
  gap: 2px;
}

.columna {
  position: relative;
  flex: 1;
  height: 100%;
  display: flex;
  align-items: flex-end;
  cursor: default;
  outline: none;
}

.columna:focus-visible {
  background: #fff3eb;
}

.barra {
  width: 100%;
  min-height: 0;
  background: var(--color-serie);
  border-radius: 4px 4px 0 0;
  opacity: .85;
  transition: opacity .15s;
}

.barra.resaltada {
  opacity: 1;
}

.tooltip {
  position: absolute;
  bottom: calc(100% - 60px);
  left: 50%;
  z-index: 2;
  display: grid;
  gap: 2px;
  min-width: 130px;
  padding: 8px 10px;
  font-size: .8rem;
  background: #fff;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  box-shadow: 0 4px 14px rgba(0, 0, 0, .12);
  pointer-events: none;
}

.tooltip.izquierda {
  left: auto;
  right: 50%;
}

.eje-x {
  grid-column: 2;
  display: flex;
  overflow: hidden; /* la última fecha no se sale de la tarjeta */
  gap: 2px;
  padding-top: 6px;
  border-top: 1px solid #cfcfcf;
}

.eje-x span {
  flex: 1 1 0;
  min-width: 0;
  overflow: visible;
  font-size: .72rem;
  white-space: nowrap;
  color: #6b6b6b;
}

/* En pantallas angostas solo se muestran la primera, la del medio y la última fecha */
@media (max-width: 600px) {
  .eje-x span.secundaria {
    visibility: hidden;
  }

  /* la última fecha se alinea a la derecha para que no se corte */
  .eje-x span:last-child {
    display: flex;
    justify-content: flex-end;
  }
}

.tabla-ventas {
  max-height: 320px;
  overflow-y: auto;
}

.texto-secundario {
  color: var(--texto-secundario);
}
</style>
