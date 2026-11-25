# Evaluación del software — MyA FOOTWEAR STORE

Evaluación estática del código fuente de la tienda MyA FOOTWEAR STORE: API REST en PHP 8.3 con MVC propio, frontend en Vue 3 y base de datos MariaDB. Se hizo sobre el mismo repositorio del proyecto, sin crear un proyecto aparte.

## 1. Proceso de evaluación

1. **Alcance.** Solo se mide el código escrito por el equipo:
   - `backend/src` (controladores, modelos, núcleo, middlewares y estrategias de descuento);
   - `backend/bin` (script de mantenimiento de imágenes);
   - `frontend/src` (vistas, componentes, servicios, stores, utilidades y CSS).

   Se **excluyen** `backend/vendor`, `frontend/node_modules`, el compilado `public/mya` (lo genera Vite), las pruebas, los scripts SQL y las imágenes.
2. **Herramientas.** Se eligieron herramientas compatibles con las tecnologías del proyecto (PHP, JavaScript, Vue) y se instalaron como dependencias de desarrollo, así la medición se repite con las mismas versiones.
3. **Medición inicial («antes»).** Se ejecutaron todas las herramientas sobre el commit `ef4486a`, el estado del proyecto antes de refactorizar. Las salidas quedaron en [`docs/evidencias/antes/`](evidencias/antes/).
4. **Pruebas antes de cambiar el código.** Antes de cualquier refactorización se escribieron las pruebas automatizadas (ver [PRUEBAS_SOFTWARE.md](PRUEBAS_SOFTWARE.md)) y se comprobó que todas pasaban con el código original.
5. **Identificación de problemas.** Con los reportes se seleccionaron los problemas de mayor impacto: complejidad, duplicidad, código muerto y acoplamiento.
6. **Refactorización.** Cada mejora va en su propio commit (ver [REFACTORIZACION.md](REFACTORIZACION.md)) y las pruebas se ejecutaron después de cada una.
7. **Medición posterior («después»).** Se repitió el mismo script, con la misma configuración, sobre el commit `4382a5a`. Las salidas quedaron en [`docs/evidencias/despues/`](evidencias/despues/) y la comparación está en [METRICAS_SOFTWARE.md](METRICAS_SOFTWARE.md).

**Mismas condiciones en las dos mediciones.** Las herramientas, sus versiones (fijadas en `composer.lock` y `package-lock.json`), los archivos de configuración, el alcance y el equipo fueron los mismos. Entre las dos mediciones solo se agregaron dos cosas:

- **`backend/pdepend.xml`:** desactiva el caché en disco de PDepend, el motor de PHPMD. Con el caché activo, PHPMD mezclaba la complejidad de versiones anteriores del código. Para confirmar que la medición inicial no estaba afectada, se repitió sobre el código original con el caché desactivado y dio exactamente los mismos valores: 230 métodos, complejidad total 643, máxima 41 y 40 hallazgos de PHPMD.
- **`cloc-por-archivo.json`:** el conteo de líneas por archivo para la vista polimétrica. El de «antes» se generó con el mismo cloc sobre una copia del commit `ef4486a`.

Ninguno de los dos cambios altera las reglas ni los umbrales de medición.

## 2. Herramientas utilizadas

| Herramienta | Versión | Tecnología | Qué mide en el proyecto |
|---|---|---|---|
| **cloc** | 1.96 (paquete npm `cloc` 2.11) | Todas | Líneas de código, comentarios, líneas en blanco y archivos por lenguaje |
| **PHPStan** | 2.1 (nivel 5) | PHP | Errores de tipos, comparaciones imposibles, código sin efecto, llamadas inválidas |
| **PHPMD** | 2.15 | PHP | Complejidad ciclomática y NPath, acoplamiento, código no usado, nombres, `else` innecesarios, operador `@` |
| **PHP_CodeSniffer** | 3.13 (estándar PSR-12) | PHP | Cumplimiento del estándar de codificación PSR-12 |
| **ESLint** + `eslint-plugin-vue` | 9.39 / 10.12 | JavaScript y Vue | Errores de JavaScript, reglas recomendadas de Vue 3, complejidad, tamaño de archivos |
| **jscpd** | 4.3 | PHP, JS, Vue, CSS | Bloques de código duplicado y porcentaje de duplicidad |
| **composer audit** / **npm audit** | Composer 2.11 / npm | Dependencias | Vulnerabilidades conocidas en las librerías |
| **PHPUnit** / **Vitest** | 11.5 / 5.1 | PHP / Vue | Pruebas automatizadas (ver [PRUEBAS_SOFTWARE.md](PRUEBAS_SOFTWARE.md)) |

SonarQube no se usó porque necesita un servidor propio (Java + base de datos) que no está disponible en el entorno del proyecto. Por eso la deuda técnica se estima con el modelo descrito en la sección 4.4 de [METRICAS_SOFTWARE.md](METRICAS_SOFTWARE.md#44-deuda-técnica-estimada).

## 3. Configuración del análisis

Toda la configuración está versionada en el repositorio:

| Archivo | Configuración |
|---|---|
| [`backend/phpstan.neon`](../backend/phpstan.neon) | Nivel 5, analiza `src` y `bin` |
| [`backend/phpmd.xml`](../backend/phpmd.xml) | Reglas `codesize`, `design`, `unusedcode`, `naming` y `cleancode`. Excepciones: no se reporta `StaticAccess`, porque `Response`, `Validator` y `Config` son utilidades estáticas a propósito, y se permiten las variables cortas `id`, `db`, `ok` y `e` |
| [`backend/phpmd-complejidad.xml`](../backend/phpmd-complejidad.xml) | Solo para medir: reporta la complejidad ciclomática de **todos** los métodos (umbral 1) |
| [`backend/pdepend.xml`](../backend/pdepend.xml) | PDepend, el motor que usa PHPMD, trabaja sin caché en disco: cada análisis lee el código actual |
| [`backend/phpcs.xml`](../backend/phpcs.xml) | Estándar PSR-12 sobre `src` y `bin` |
| [`frontend/eslint.config.js`](../frontend/eslint.config.js) | `@eslint/js` recomendado + `plugin:vue/recommended` y los límites `complexity` 10, `max-depth` 4, `max-params` 4 y `max-lines` 500. Se desactivan solo las reglas de formato de plantillas (saltos de línea e indentación), porque el proyecto tiene su propio estilo |
| [`frontend/eslint.complejidad.config.js`](../frontend/eslint.complejidad.config.js) | Solo para medir: la complejidad de **todas** las funciones (umbral 0) |
| jscpd (en el script) | Mínimo 5 líneas y 50 tokens para contar un bloque como duplicado |

Los umbrales de complejidad siguen la escala de McCabe: 1–5 baja, 6–10 moderada, 11–20 alta y más de 20 muy alta. PHPMD y ESLint reportan los métodos que pasan de 10.

## 4. Comandos ejecutados

Toda la evaluación se ejecuta con un solo script, que guarda la salida de cada herramienta y un resumen:

```powershell
# Desde la raíz del repositorio (XAMPP encendido para las pruebas de integración)
node herramientas/calidad/analizar.mjs antes     # o: despues
node herramientas/calidad/polimetrica.mjs        # vista polimétrica antes/después
```

El script [`herramientas/calidad/analizar.mjs`](../herramientas/calidad/analizar.mjs) ejecuta estos comandos:

```powershell
# Tamaño
cloc --json ../backend/src ../backend/bin src             # (desde frontend/)
cloc --by-file --json ../backend/src ../backend/bin src

# Complejidad ciclomática por método/función
vendor/bin/phpmd src,bin json phpmd-complejidad.xml       # (desde backend/)
npx eslint src -f json -c eslint.complejidad.config.js    # (desde frontend/)

# Duplicidad
npx jscpd ../backend/src ../backend/bin src --min-lines 5 --min-tokens 50

# Calidad, diseño y estándares
vendor/bin/phpstan analyse --no-progress --memory-limit=512M
vendor/bin/phpmd src,bin text phpmd.xml
vendor/bin/phpcs --report=source
npx eslint src

# Vulnerabilidades en dependencias
composer audit
npm audit            # todas las dependencias
npm audit --omit=dev # solo las que llegan a producción

# Pruebas
vendor/bin/phpunit
npx vitest run
```

Cada herramienta también se puede ejecutar por separado con los scripts del proyecto:

```powershell
cd backend;  composer analisis      # PHPStan + PHPMD + PHP_CodeSniffer
cd backend;  composer pruebas       # PHPUnit
cd frontend; npm run lint           # ESLint
cd frontend; npm run duplicados     # jscpd
cd frontend; npm run loc            # cloc
cd frontend; npm test               # Vitest
```

## 5. Resultados de la evaluación inicial

Medición del commit `ef4486a` ([`resumen.json`](evidencias/antes/resumen.json)):

| Indicador | Resultado inicial |
|---|---|
| Líneas de código | **13 361** (PHP 3 721 · Vue 8 732 · JavaScript 598 · CSS 310) |
| Archivos de código fuente | **106** (48 PHP · 42 Vue · 15 JS · 1 CSS) |
| Complejidad ciclomática PHP | 230 métodos · promedio **2,80** · máxima **41** · 5 métodos con más de 10 |
| Complejidad ciclomática JavaScript/Vue | 413 funciones · promedio **2,21** · máxima **15** · 5 funciones con más de 10 |
| Porcentaje de duplicidad | **1,27 %** (206 de 16 280 líneas, 23 bloques repetidos) |
| Problemas de calidad | **25** (PHPStan 2 · PHPMD 23 · ESLint 0) |
| Problemas de diseño | **26** (PHPMD 17 · ESLint 9) |
| Incumplimiento de estándares | **86** (PSR-12: 2 errores + 70 advertencias · reglas de Vue: 14) |
| Posibles vulnerabilidades | **0** en dependencias de producción · 5 moderadas en dependencias de desarrollo · 6 usos del operador `@` que ocultan errores en `Imagen.php` |
| Deuda técnica estimada | **25,2 horas** (1 510 minutos) |
| Pruebas automatizadas | 65 pruebas, todas aprobadas (51 PHPUnit + 14 Vitest) |

### Clasificación de los hallazgos

El script clasifica cada hallazgo según el indicador que afecta:

- **Diseño:** complejidad ciclomática, NPath y de clase, acoplamiento entre objetos, clases con demasiados métodos públicos, parámetros booleanos de control, `exit` y, en ESLint, `complexity`, `max-lines`, `max-depth` y `max-params`.
- **Estándares:** PSR-12 (PHP_CodeSniffer) y las reglas `vue/*` de la guía de estilo de Vue.
- **Calidad:** el resto. Errores de PHPStan, parámetros sin usar, imports faltantes, `else` innecesarios, operador `@` y nombres demasiado cortos.

## 6. Problemas identificados

Se seleccionaron estos problemas reales para corregirlos. El detalle de la solución está en [REFACTORIZACION.md](REFACTORIZACION.md).

| # | Archivo / componente | Ubicación | Tipo de problema | Causa | Impacto en la calidad | Solución propuesta |
|---|---|---|---|---|---|---|
| 1 | `backend/src/Core/Validator.php` | `aplicarRegla()`, líneas 78–165 | **Long Method** · complejidad muy alta (CC **41**) · clase con complejidad total 53 (umbral 50) | Un solo `switch` de 16 casos con condiciones anidadas para todas las reglas de validación | El método más complejo del sistema. Agregar una regla obliga a modificarlo y aumenta el riesgo de romper otras; es difícil de probar por partes | Extraer cada regla a un método de una clase nueva `ReglasValidacion` y despacharla con un mapa nombre → función (principio abierto/cerrado) |
| 2 | `backend/src/Controllers/ProductoController.php` | `subirImagen()`, líneas 131–181 | **Long Method** · CC **15** · NPath **2 592** · acoplamiento **14** (umbral 13) · **Duplicate Code** (la lista de formatos ya existía en `Imagen::tipoReal`) · **Magic Number** (`1024 * 1024`) | El controlador validaba el archivo, revisaba su tipo real con `finfo`, creaba la carpeta, generaba el nombre y optimizaba la imagen | El controlador asumía responsabilidades de infraestructura (baja cohesión). La lista de formatos permitidos estaba en dos clases distintas | Extraer la clase `ImagenSubida` (validar y guardar) y dejar los formatos en una sola constante `Imagen::EXTENSIONES` |
| 3 | `AuthController.php`, `UsuarioController.php`, `Models/Usuario.php` | `registro()`, `actualizarPerfil()`, `crear()`, `actualizar()` | **Duplicate Code** (jscpd: 9 líneas iguales entre los dos controladores) · **Long Parameter List** (`Usuario::crear` con 5 parámetros) | Las reglas de nombre, correo, contraseña y teléfono, y la verificación «Ya existe una cuenta con este correo», estaban copiadas en 4 métodos | Cambiar una regla de la cuenta (por ejemplo la longitud mínima de la contraseña) exigía editar 4 lugares; si se olvidaba uno, el registro y el panel validaban distinto | Llevar las reglas al modelo (`Usuario::REGLAS_CUENTA`, `REGLA_PASSWORD`) junto con `asegurarEmailDisponible()`, y hacer que `crear()` reciba los datos ya validados |
| 4 | `backend/src/Controllers/*` · `PedidoController.php:114` · `Models/Talla.php:34` | 11 bloques «buscar o responder 404» en 6 controladores · `codigoCupon()` · `buscarVarias()` | **Duplicate Code** (jscpd: `VarianteController` repetía 9 líneas consigo mismo) · **Dead Code** (PHPStan: comparación que siempre es falsa y `array_values` sin efecto) | El mismo `if (... === null) throw noEncontrado()` copiado en cada acción, y condiciones defensivas que el `Validator` ya cubría | Más ramas por método y más código que mantener. PHPStan reportaba 2 errores | Método `encontrado()` en la clase base `Controller` y eliminar el código muerto |
| 5 | `frontend/src/views/panel/` (5 vistas) y `frontend/src/services/` (3 servicios) | `PedidosAdmin`, `ProductosAdmin`, `InventarioAdmin`, `UsuariosAdmin`, `DevolucionesAdmin` · función `limpiar` | **Duplicate Code** (jscpd: 13 líneas idénticas entre `PedidosAdmin` y `DevolucionesAdmin`; la misma función `limpiar` en 3 servicios) · **Magic Number** (`350` ms en 5 archivos) | Cada pantalla del panel copiaba la carga, el manejo de errores, los `watch` de filtros, la espera de la búsqueda y la paginación | Un cambio en el comportamiento de los listados exigía tocar 5 archivos. Además, el temporizador de búsqueda no se cancelaba al cerrar la pantalla | Composable `useListadoPanel` y utilidad `limpiarFiltros`, compartidos |
| 6 | `InventarioAdmin.vue` y `ProductoEditor.vue` | `guardarAjuste()` en ambos | **Duplicate Code** con **mensajes inconsistentes** · CC **11** en las dos funciones | La validación del ajuste de stock estaba escrita dos veces y con textos distintos («Cantidad entera distinta de 0.» frente a «Escribe una cantidad entera distinta de 0.») | El mismo error se mostraba con palabras diferentes según la pantalla; dos funciones por encima del umbral de complejidad | Función `validarAjusteStock` en `utils/inventario.js` y composable `useAjusteStock` con el formulario completo |

Además, se corrigieron estos incumplimientos de estándares:

- **PSR-12:** en `routes.php` y `optimizar_imagenes.php` el docblock del archivo iba después de `declare`, y PSR-12 lo exige justo después de `<?php`.
- **Guía de estilo de Vue:** el componente `Paginacion` tenía un nombre de una sola palabra, que choca con elementos HTML futuros. Se renombró a `PaginacionListado`. También se corrigieron 11 atributos en desorden dentro de las plantillas.

## 7. Evidencias obtenidas

Todas las salidas son verificables. Se pueden abrir en el repositorio o volver a generar con el script.

| Evidencia | Antes | Después |
|---|---|---|
| Resumen de indicadores | [resumen.json](evidencias/antes/resumen.json) | [resumen.json](evidencias/despues/resumen.json) |
| Líneas de código | [cloc.txt](evidencias/antes/cloc.txt) · [por archivo](evidencias/antes/cloc-por-archivo.json) | [cloc.txt](evidencias/despues/cloc.txt) · [por archivo](evidencias/despues/cloc-por-archivo.json) |
| Complejidad PHP / JS | [PHP](evidencias/antes/complejidad-php.json) · [JS](evidencias/antes/complejidad-js.json) | [PHP](evidencias/despues/complejidad-php.json) · [JS](evidencias/despues/complejidad-js.json) |
| Duplicidad | [duplicidad-detalle.txt](evidencias/antes/duplicidad-detalle.txt) | [duplicidad-detalle.txt](evidencias/despues/duplicidad-detalle.txt) |
| PHPStan | [phpstan.txt](evidencias/antes/phpstan.txt) | [phpstan.txt](evidencias/despues/phpstan.txt) |
| PHPMD | [phpmd.txt](evidencias/antes/phpmd.txt) | [phpmd.txt](evidencias/despues/phpmd.txt) |
| PHP_CodeSniffer (PSR-12) | [phpcs.txt](evidencias/antes/phpcs.txt) | [phpcs.txt](evidencias/despues/phpcs.txt) |
| ESLint | [eslint.txt](evidencias/antes/eslint.txt) | [eslint.txt](evidencias/despues/eslint.txt) |
| Vulnerabilidades | [composer](evidencias/antes/audit-composer.json) · [npm](evidencias/antes/audit-npm.json) | [composer](evidencias/despues/audit-composer.json) · [npm](evidencias/despues/audit-npm.json) |
| Pruebas | [backend](evidencias/antes/pruebas-backend.txt) · [frontend](evidencias/antes/pruebas-frontend.txt) | [backend](evidencias/despues/pruebas-backend.txt) · [frontend](evidencias/despues/pruebas-frontend.txt) |
| Vista polimétrica | — | [polimetrica.svg](img/polimetrica.svg) · [datos](evidencias/polimetrica.json) |

Extracto de la evaluación inicial de PHPMD ([phpmd.txt](evidencias/antes/phpmd.txt)):

```text
src\Controllers\ProductoController.php:24   CouplingBetweenObjects    The class ProductoController has a coupling between objects value of 14.
src\Controllers\ProductoController.php:131  CyclomaticComplexity      The method subirImagen() has a Cyclomatic Complexity of 15.
src\Controllers\ProductoController.php:131  NPathComplexity           The method subirImagen() has an NPath complexity of 2592.
src\Core\Validator.php:25                   ExcessiveClassComplexity  The class Validator has an overall complexity of 53 which is very high.
src\Core\Validator.php:78                   CyclomaticComplexity      The method aplicarRegla() has a Cyclomatic Complexity of 41.
```

Extracto de la evaluación inicial de PHPStan ([phpstan.txt](evidencias/antes/phpstan.txt)):

```text
src\Controllers\PedidoController.php:114  Strict comparison using === between mixed and null will always evaluate to false.
src\Models\Talla.php:34                   Parameter #1 $array (non-empty-list<int>) of array_values is already a list, call has no effect.
[ERROR] Found 2 errors
```
