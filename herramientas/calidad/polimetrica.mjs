#!/usr/bin/env node
// Vista polimétrica de los componentes intervenidos, antes y después de la refactorización.
//
// Cada componente es un rectángulo:
//   alto  = líneas de código (cloc)
//   ancho = cantidad de métodos o funciones
//   color = complejidad ciclomática máxima de sus métodos (verde baja, amarillo moderada,
//           naranja alta, rojo muy alta)
//
// Lee las evidencias de docs/evidencias/antes y docs/evidencias/despues (las genera
// analizar.mjs) y escribe docs/img/polimetrica.svg y docs/evidencias/polimetrica.json.
//
// Uso: node herramientas/calidad/polimetrica.mjs

import { mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const RAIZ = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..')
const leer = (etapa, archivo) => JSON.parse(readFileSync(join(RAIZ, 'docs', 'evidencias', etapa, archivo), 'utf8'))

// Rutas tal como las escribe cloc desde la carpeta frontend
const COMPONENTES = [
  { nombre: 'Validator', archivo: '../backend/src/Core/Validator.php' },
  { nombre: 'ReglasValidacion', archivo: '../backend/src/Core/ReglasValidacion.php' },
  { nombre: 'ProductoController', archivo: '../backend/src/Controllers/ProductoController.php' },
  { nombre: 'ImagenSubida', archivo: '../backend/src/Core/ImagenSubida.php' },
  { nombre: 'UsuarioController', archivo: '../backend/src/Controllers/UsuarioController.php' },
  { nombre: 'AuthController', archivo: '../backend/src/Controllers/AuthController.php' },
  { nombre: 'PedidosAdmin.vue', archivo: 'src/views/panel/PedidosAdmin.vue' },
  { nombre: 'InventarioAdmin.vue', archivo: 'src/views/panel/InventarioAdmin.vue' },
  { nombre: 'useListadoPanel.js', archivo: 'src/composables/useListadoPanel.js' },
]

const normalizar = (ruta) => ruta.replace(/\\/g, '/')
const terminaEn = (ruta, archivo) => normalizar(ruta).endsWith(archivo.replace(/^(\.\.\/)?/, '').replace(/^src\//, 'frontend/src/'))

function medir(etapa) {
  const cloc = leer(etapa, 'cloc-por-archivo.json')
  const php = leer(etapa, 'complejidad-php.json').files ?? []
  const js = leer(etapa, 'complejidad-js.json')

  return COMPONENTES.map(({ nombre, archivo }) => {
    const lineas = cloc[archivo]?.code ?? 0
    const valores = archivo.endsWith('.php')
      ? (php.find((f) => terminaEn(f.file, archivo))?.violations ?? []).map((v) => Number(v.description.match(/Complexity of (\d+)/)[1]))
      : (js.find((f) => terminaEn(f.filePath, archivo))?.messages ?? []).filter((m) => m.ruleId === 'complexity').map((m) => Number(m.message.match(/complexity of (\d+)/)[1]))
    return {
      nombre,
      existe: lineas > 0,
      lineas,
      metodos: valores.length,
      complejidad_maxima: Math.max(0, ...valores),
      complejidad_total: valores.reduce((a, b) => a + b, 0),
    }
  })
}

const color = (cc) => (cc > 20 ? '#c62828' : cc > 10 ? '#ef6c00' : cc > 5 ? '#f2b705' : '#2e7d32')

function dibujar(datos) {
  const ESCALA_ALTO = 0.75
  const ANCHO_METODO = 7
  const SEPARACION = 26
  const maxAlto = Math.max(...Object.values(datos).flat().map((c) => c.lineas)) * ESCALA_ALTO
  const filaAlto = maxAlto + 70
  const anchoDe = (c) => Math.max(14, c.metodos * ANCHO_METODO)
  // Cada componente ocupa la misma columna en las dos filas para compararlo de arriba abajo.
  const columnas = datos.antes.map((c, i) => Math.max(anchoDe(c), anchoDe(datos.despues[i])) + SEPARACION + 40)
  let svg = ''
  let anchoTotal = 0

  Object.entries(datos).forEach(([etapa, componentes], fila) => {
    const base = 40 + fila * filaAlto + maxAlto
    const inicio = 150
    svg += `<text x="16" y="${base - maxAlto / 2}" font-size="15" font-weight="700">${etapa === 'antes' ? 'Antes' : 'Después'}</text>`
    componentes.forEach((c, i) => {
      const ancho = anchoDe(c)
      const x = inicio + columnas.slice(0, i).reduce((a, b) => a + b, 0) + (columnas[i] - ancho) / 2 - (SEPARACION + 40) / 2
      if (c.existe) {
        const alto = Math.max(4, c.lineas * ESCALA_ALTO)
        svg += `<rect x="${x}" y="${base - alto}" width="${ancho}" height="${alto}" fill="${color(c.complejidad_maxima)}" stroke="#1c1917" stroke-width="1"><title>${c.nombre}: ${c.lineas} líneas, ${c.metodos} métodos, complejidad máxima ${c.complejidad_maxima}</title></rect>`
        svg += `<text x="${x + ancho / 2}" y="${base - alto - 6}" font-size="11" text-anchor="middle">${c.lineas} · CC ${c.complejidad_maxima}</text>`
      } else {
        svg += `<rect x="${x}" y="${base - 10}" width="${ancho}" height="10" fill="none" stroke="#9aa3b2" stroke-dasharray="3 3"/>`
        svg += `<text x="${x + ancho / 2}" y="${base - 16}" font-size="11" text-anchor="middle" fill="#6b7280">no existía</text>`
      }
      svg += `<text x="${x + ancho / 2}" y="${base + 16}" font-size="11" text-anchor="middle" transform="rotate(25 ${x + ancho / 2} ${base + 16})">${c.nombre}</text>`
    })
    anchoTotal = inicio + columnas.reduce((a, b) => a + b, 0)
  })

  const leyendaY = 40 + 2 * filaAlto + 10
  const leyenda = [['≤ 5 baja', '#2e7d32'], ['6–10 moderada', '#f2b705'], ['11–20 alta', '#ef6c00'], ['> 20 muy alta', '#c62828']]
    .map(([texto, relleno], i) => `<rect x="${150 + i * 150}" y="${leyendaY}" width="14" height="14" fill="${relleno}"/><text x="${170 + i * 150}" y="${leyendaY + 12}" font-size="12">${texto}</text>`).join('')
  const nota = `<text x="16" y="${leyendaY + 36}" font-size="12" fill="#444">Alto = líneas de código · Ancho = número de métodos/funciones · Color = complejidad ciclomática máxima (CC)</text>`

  return `<svg xmlns="http://www.w3.org/2000/svg" width="${anchoTotal + 20}" height="${leyendaY + 52}" font-family="Arial, sans-serif">`
    + `<rect width="100%" height="100%" fill="#ffffff"/>${svg}${leyenda}${nota}</svg>\n`
}

const datos = { antes: medir('antes'), despues: medir('despues') }
mkdirSync(join(RAIZ, 'docs', 'img'), { recursive: true })
writeFileSync(join(RAIZ, 'docs', 'img', 'polimetrica.svg'), dibujar(datos))
writeFileSync(join(RAIZ, 'docs', 'evidencias', 'polimetrica.json'), JSON.stringify(datos, null, 2) + '\n')
console.table(COMPONENTES.map(({ nombre }, i) => ({
  componente: nombre,
  'LOC antes': datos.antes[i].lineas, 'LOC después': datos.despues[i].lineas,
  'métodos antes': datos.antes[i].metodos, 'métodos después': datos.despues[i].metodos,
  'CC máx antes': datos.antes[i].complejidad_maxima, 'CC máx después': datos.despues[i].complejidad_maxima,
})))
