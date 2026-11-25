#!/usr/bin/env node
// Evaluación de calidad del código de MyA FOOTWEAR STORE.
//
// Ejecuta las herramientas de análisis estático y las pruebas automatizadas, guarda la
// salida de cada una en docs/evidencias/<etapa>/ y escribe un resumen con los indicadores.
//
// Uso (desde la raíz del repositorio):
//   node herramientas/calidad/analizar.mjs antes
//   node herramientas/calidad/analizar.mjs despues
//
// Requisitos: dependencias instaladas (composer install en backend, npm install en frontend)
// y XAMPP encendido para las pruebas de integración de la API.
// Si Composer no está en el PATH, indicar el composer.phar con la variable MYA_COMPOSER.

import { spawnSync } from 'node:child_process'
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const RAIZ = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..')
const BACKEND = join(RAIZ, 'backend')
const FRONTEND = join(RAIZ, 'frontend')
const etapa = process.argv[2]
if (!['antes', 'despues'].includes(etapa)) {
  console.error('Indica la etapa: antes | despues')
  process.exit(1)
}
const SALIDA = join(RAIZ, 'docs', 'evidencias', etapa)
mkdirSync(SALIDA, { recursive: true })

const php = process.env.MYA_PHP || 'php'
const composer = process.env.MYA_COMPOSER ? [php, process.env.MYA_COMPOSER] : ['composer']
const binNode = (nombre) => join(FRONTEND, 'node_modules', '.bin', nombre + (process.platform === 'win32' ? '.cmd' : ''))
const binPhp = (nombre) => [php, join(BACKEND, 'vendor', 'bin', nombre)]

/** Ejecuta un comando, guarda su salida en un archivo y la devuelve como texto. */
function ejecutar(archivo, [programa, ...argumentos], cwd) {
  // En Windows los .cmd necesitan la consola; las rutas con espacios van entre comillas.
  const conConsola = process.platform === 'win32' && programa.endsWith('.cmd')
  const citar = (texto) => (conConsola && /\s/.test(texto) ? `"${texto}"` : texto)
  const resultado = spawnSync(citar(programa), argumentos.map(citar), {
    cwd, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024, shell: conConsola,
  })
  const texto = (resultado.stdout || '') + (resultado.stderr ? `\n${resultado.stderr}` : '')
  writeFileSync(join(SALIDA, archivo), texto.replace(/\x1b\[[0-9;]*m/g, ''))
  console.log(`· ${archivo} (código ${resultado.status})`)
  return resultado.stdout || ''
}

const json = (texto) => {
  const inicio = texto.search(/[[{]/)
  return inicio === -1 ? {} : JSON.parse(texto.slice(inicio))
}

// ---------------------------------------------------------------------------------------
// 1. Tamaño: líneas de código y archivos (cloc)
const cloc = json(ejecutar('cloc.json', [binNode('cloc'), '--json', '--quiet', '../backend/src', '../backend/bin', 'src'], FRONTEND))
ejecutar('cloc.txt', [binNode('cloc'), '--quiet', '../backend/src', '../backend/bin', 'src'], FRONTEND)

// 2. Complejidad ciclomática por método/función
const complejidadPhp = json(ejecutar('complejidad-php.json', [...binPhp('phpmd'), 'src,bin', 'json', 'phpmd-complejidad.xml'], BACKEND))
const valoresPhp = (complejidadPhp.files ?? []).flatMap((f) => f.violations.map((v) => ({
  archivo: f.file.split(/[\\/]src[\\/]|[\\/]bin[\\/]/).pop(), metodo: v.method ?? v.function, valor: Number(v.description.match(/Complexity of (\d+)/)[1]),
})))
const complejidadJs = json(ejecutar('complejidad-js.json', [binNode('eslint'), 'src', '-f', 'json', '-c', 'eslint.complejidad.config.js'], FRONTEND))
const valoresJs = complejidadJs.flatMap((f) => f.messages.filter((m) => m.ruleId === 'complexity').map((m) => ({
  archivo: f.filePath.split(/[\\/]src[\\/]/).pop(), metodo: (m.message.match(/^(?:Async )?(?:Method|Function|Arrow function)\s*'?([^']*)'?/i) || [])[1] || 'anónima', valor: Number(m.message.match(/complexity of (\d+)/)[1]),
})))

const resumirComplejidad = (valores) => {
  const total = valores.reduce((suma, v) => suma + v.valor, 0)
  return {
    funciones: valores.length,
    total,
    promedio: valores.length ? +(total / valores.length).toFixed(2) : 0,
    maxima: Math.max(0, ...valores.map((v) => v.valor)),
    mayores_a_10: valores.filter((v) => v.valor > 10).length,
    top: [...valores].sort((a, b) => b.valor - a.valor).slice(0, 8),
  }
}

// 3. Duplicidad (jscpd)
ejecutar('duplicidad.txt', [binNode('jscpd'), '../backend/src', '../backend/bin', 'src', '--min-lines', '5', '--min-tokens', '50', '--reporters', 'console,json', '--output', join(SALIDA, 'jscpd'), '--silent'], FRONTEND)
ejecutar('duplicidad-detalle.txt', [binNode('jscpd'), '../backend/src', '../backend/bin', 'src', '--min-lines', '5', '--min-tokens', '50', '--reporters', 'console'], FRONTEND)
const jscpd = JSON.parse(readFileSync(join(SALIDA, 'jscpd', 'jscpd-report.json'), 'utf8'))

// 4. Problemas de calidad, diseño y estándares
const phpstan = json(ejecutar('phpstan.json', [...binPhp('phpstan'), 'analyse', '--no-progress', '--memory-limit=512M', '--error-format=json'], BACKEND))
ejecutar('phpstan.txt', [...binPhp('phpstan'), 'analyse', '--no-progress', '--memory-limit=512M'], BACKEND)
const phpmd = json(ejecutar('phpmd.json', [...binPhp('phpmd'), 'src,bin', 'json', 'phpmd.xml'], BACKEND))
ejecutar('phpmd.txt', [...binPhp('phpmd'), 'src,bin', 'text', 'phpmd.xml'], BACKEND)
const phpcs = json(ejecutar('phpcs.json', [...binPhp('phpcs'), '--report=json', '-q'], BACKEND))
ejecutar('phpcs.txt', [...binPhp('phpcs'), '--report=source', '-q'], BACKEND)
const eslint = json(ejecutar('eslint.json', [binNode('eslint'), 'src', '-f', 'json'], FRONTEND))
ejecutar('eslint.txt', [binNode('eslint'), 'src'], FRONTEND)

const violacionesPhpmd = (phpmd.files ?? []).flatMap((f) => f.violations)
const mensajesEslint = eslint.flatMap((f) => f.messages)

// Clasificación de los hallazgos según el indicador que pide la evaluación
const REGLAS_DISENO_PHP = ['CyclomaticComplexity', 'NPathComplexity', 'ExcessiveClassComplexity', 'CouplingBetweenObjects', 'TooManyPublicMethods', 'ExcessiveMethodLength', 'ExcessiveClassLength', 'BooleanArgumentFlag', 'ExitExpression']
const REGLAS_DISENO_JS = ['complexity', 'max-lines', 'max-depth', 'max-params']
const diseno = violacionesPhpmd.filter((v) => REGLAS_DISENO_PHP.includes(v.rule)).length + mensajesEslint.filter((m) => REGLAS_DISENO_JS.includes(m.ruleId)).length
const calidad = (phpstan.totals?.file_errors ?? 0) + violacionesPhpmd.filter((v) => !REGLAS_DISENO_PHP.includes(v.rule)).length
  + mensajesEslint.filter((m) => !REGLAS_DISENO_JS.includes(m.ruleId) && !m.ruleId?.startsWith('vue/')).length
const estandares = (phpcs.totals?.errors ?? 0) + (phpcs.totals?.warnings ?? 0) + mensajesEslint.filter((m) => m.ruleId?.startsWith('vue/')).length

// 5. Posibles vulnerabilidades en dependencias
const auditComposer = json(ejecutar('audit-composer.json', [...composer, 'audit', '--format=json', '--no-interaction'], BACKEND))
const auditNpm = json(ejecutar('audit-npm.json', ['npm' + (process.platform === 'win32' ? '.cmd' : ''), 'audit', '--json'], FRONTEND))
const auditNpmProduccion = json(ejecutar('audit-npm-produccion.json', ['npm' + (process.platform === 'win32' ? '.cmd' : ''), 'audit', '--json', '--omit=dev'], FRONTEND))
const vulnerabilidades = {
  composer: Object.values(auditComposer.advisories ?? {}).flat().length,
  npm_produccion: auditNpmProduccion.metadata?.vulnerabilities?.total ?? 0,
  npm_desarrollo: (auditNpm.metadata?.vulnerabilities?.total ?? 0) - (auditNpmProduccion.metadata?.vulnerabilities?.total ?? 0),
  ocultar_errores_php: violacionesPhpmd.filter((v) => v.rule === 'ErrorControlOperator').length,
}

// 6. Deuda técnica estimada (minutos de corrección por hallazgo, modelo SQALE simplificado)
const MINUTOS = {
  phpstan: 15, complejidad: 30, acoplamiento: 60, otraPhpmd: 5, lineaLarga: 2, otraPhpcs: 5,
  eslintDiseno: 30, eslintArchivoLargo: 60, eslintOtra: 5, clon: 10,
}
const minutosPhpmd = violacionesPhpmd.reduce((suma, v) => suma + (
  ['CyclomaticComplexity', 'NPathComplexity', 'ExcessiveClassComplexity'].includes(v.rule) ? MINUTOS.complejidad
    : ['CouplingBetweenObjects', 'TooManyPublicMethods'].includes(v.rule) ? MINUTOS.acoplamiento : MINUTOS.otraPhpmd), 0)
const mensajesPhpcs = Object.values(phpcs.files ?? {}).flatMap((f) => f.messages)
const minutosPhpcs = mensajesPhpcs.reduce((suma, m) => suma + (m.source.includes('LineLength') ? MINUTOS.lineaLarga : MINUTOS.otraPhpcs), 0)
const minutosEslint = mensajesEslint.reduce((suma, m) => suma + (
  m.ruleId === 'max-lines' ? MINUTOS.eslintArchivoLargo : REGLAS_DISENO_JS.includes(m.ruleId) ? MINUTOS.eslintDiseno : MINUTOS.eslintOtra), 0)
const minutosDeuda = (phpstan.totals?.file_errors ?? 0) * MINUTOS.phpstan + minutosPhpmd + minutosPhpcs + minutosEslint + jscpd.duplicates.length * MINUTOS.clon

// 7. Pruebas automatizadas
const salidaPhpunit = ejecutar('pruebas-backend.txt', [...binPhp('phpunit'), '--colors=never'], BACKEND)
const salidaVitest = ejecutar('pruebas-frontend.txt', [binNode('vitest'), 'run', '--reporter=verbose'], FRONTEND)
const phpunit = salidaPhpunit.match(/OK \((\d+) tests?, (\d+) assertions?\)/) || salidaPhpunit.match(/Tests: (\d+), Assertions: (\d+)/)
const fallosPhpunit = Number((salidaPhpunit.match(/Failures: (\d+)/) || [0, 0])[1]) + Number((salidaPhpunit.match(/Errors: (\d+)/) || [0, 0])[1])
const vitestPasan = Number((salidaVitest.match(/Tests\s+(?:\d+ failed \| )?(\d+) passed/) || [0, 0])[1])
const vitestFallan = Number((salidaVitest.match(/Tests\s+(\d+) failed/) || [0, 0])[1])

// ---------------------------------------------------------------------------------------
const lenguajes = Object.fromEntries(Object.entries(cloc).filter(([clave]) => !['header', 'SUM'].includes(clave)).map(([clave, v]) => [clave, { archivos: v.nFiles, codigo: v.code, comentarios: v.comment, blancos: v.blank }]))
const resumen = {
  etapa,
  fecha: new Date().toISOString(),
  commit: spawnSync('git', ['rev-parse', '--short', 'HEAD'], { cwd: RAIZ, encoding: 'utf8' }).stdout.trim(),
  lineas_codigo: cloc.SUM.code,
  archivos: cloc.SUM.nFiles,
  lenguajes,
  complejidad: { php: resumirComplejidad(valoresPhp), javascript: resumirComplejidad(valoresJs) },
  duplicidad: {
    clones: jscpd.duplicates.length,
    lineas_duplicadas: jscpd.statistics.total.duplicatedLines,
    lineas_analizadas: jscpd.statistics.total.lines,
    porcentaje: +jscpd.statistics.total.percentage.toFixed(2),
  },
  problemas: {
    calidad,
    diseno,
    estandares,
    detalle: {
      phpstan: phpstan.totals?.file_errors ?? 0,
      phpmd: violacionesPhpmd.length,
      phpcs_errores: phpcs.totals?.errors ?? 0,
      phpcs_advertencias: phpcs.totals?.warnings ?? 0,
      eslint_errores: mensajesEslint.filter((m) => m.severity === 2).length,
      eslint_advertencias: mensajesEslint.filter((m) => m.severity === 1).length,
    },
  },
  vulnerabilidades,
  deuda_tecnica: { minutos: minutosDeuda, horas: +(minutosDeuda / 60).toFixed(1), modelo_minutos: MINUTOS },
  pruebas: {
    backend: { ejecutadas: Number(phpunit?.[1] ?? 0), aserciones: Number(phpunit?.[2] ?? 0), fallidas: fallosPhpunit },
    frontend: { aprobadas: vitestPasan, fallidas: vitestFallan },
  },
}
writeFileSync(join(SALIDA, 'resumen.json'), JSON.stringify(resumen, null, 2) + '\n')
console.log(`\nResumen guardado en docs/evidencias/${etapa}/resumen.json`)
console.log(JSON.stringify({ ...resumen, lenguajes: undefined, complejidad: { php: { ...resumen.complejidad.php, top: undefined }, javascript: { ...resumen.complejidad.javascript, top: undefined } } }, null, 2))
