# Mantenimiento del software — MyA FOOTWEAR STORE

Estado del sistema después de la evaluación y la refactorización, deuda técnica que queda pendiente y plan de mantenimiento propuesto. Todos los datos salen de la medición posterior ([`evidencias/despues/`](evidencias/despues/)).

## 1. Estado actual del software

**Funcionalidad.** La tienda está completa y publicada en un hosting:

- catálogo con filtros, carrito, pedidos y pago con Wompi;
- cupones de descuento (patrón Strategy);
- inventario con movimientos, ajustes manuales y alerta de bajo stock;
- devoluciones por tipo, con plazo de 30 días;
- reportes de ventas;
- panel con tres roles (administrador, vendedor y cliente) y autenticación JWT.

**Calidad del código** (commit `4382a5a`):

| Aspecto | Estado |
|---|---|
| Tamaño | 13 328 líneas de código en 112 archivos (PHP 3 764 · Vue 8 579 · JS 675 · CSS 310) |
| Complejidad | Promedio PHP 2,48 y JS/Vue 2,22 (baja). Ningún método PHP pasa de 12. Quedan 5 funciones por encima de 10 (2 en PHP y 3 en Vue) |
| Duplicidad | 1,06 % (20 bloques), casi toda en estilos CSS y plantillas Vue |
| Análisis estático | PHPStan nivel 5: 0 errores · PSR-12: 0 errores y 70 advertencias de líneas largas · ESLint: 0 errores y 9 advertencias · PHPMD: 31 avisos |
| Seguridad | Sin vulnerabilidades conocidas en las dependencias de producción |
| Pruebas | 77 pruebas automatizadas, todas aprobadas (51 PHPUnit + 26 Vitest) |
| Deuda técnica estimada | 18,3 horas (antes 25,2) |

**Cómo repetir la evaluación.** Basta un comando:

```powershell
node herramientas/calidad/analizar.mjs despues
```

Las herramientas están instaladas como dependencias de desarrollo y versionadas en `composer.lock` y `package-lock.json`.

## 2. Deuda técnica pendiente

| # | Deuda | Ubicación | Origen (herramienta) | Esfuerzo estimado |
|---|---|---|---|---|
| D1 | Validaciones de formulario muy complejas (CC 15) | `frontend/src/views/panel/CuponesAdmin.vue` → `validar()` · `ProductoEditor.vue` → `validarFormulario()` | ESLint `complexity` | 2 h |
| D2 | `ProductoEditor.guardar()` con CC 11 | `ProductoEditor.vue` | ESLint `complexity` | 1 h |
| D3 | Métodos de negocio con complejidad alta | `Pedido::cotizar()` (CC 12, parámetro booleano `$bloquear`) · `Cupon::motivoInvalido()` (CC 11, NPath 324) | PHPMD | 3 h |
| D4 | Archivos Vue de más de 500 líneas | `ContactoView.vue` (743) · `LoginView.vue` (666) · `HeroCarrusel.vue` (649) · `ProductoEditor.vue` (592). La mayor parte es CSS | ESLint `max-lines` | 4 h |
| D5 | Operador `@` que oculta advertencias de GD y EXIF | `backend/src/Core/Imagen.php` (6 usos) | PHPMD `ErrorControlOperator` | 1 h |
| D6 | 70 líneas de más de 120 caracteres | 20 archivos PHP (consultas SQL y mensajes largos) | PHP_CodeSniffer `LineLength` | 2,5 h |
| D7 | Estilos y plantillas repetidos entre vistas | Por ejemplo los semáforos de filtros de `PedidosAdmin` y `DevolucionesAdmin`, las tablas de `ProductosAdmin` y `UsuariosAdmin`, el CSS de `RecuperarView` y `RestablecerView` | jscpd (17 bloques CSS/markup) | 3 h |
| D8 | Parámetro `$request` sin usar en acciones públicas | `CatalogoController` (4) · `CuponController` · `ReporteController` | PHPMD `UnusedFormalParameter` | 0,5 h |
| D9 | `Cupon` y `Variante` con 11 métodos públicos | `Models/Cupon.php`, `Models/Variante.php` | PHPMD `TooManyPublicMethods` | 2 h |
| D10 | Props sin tipo | `BuscadorCatalogo.vue` (`talla`, `color`) | ESLint `vue/require-prop-types` | 0,2 h |
| D11 | Vulnerabilidades moderadas en dependencias de desarrollo | `minimatch` dentro de `@vue/test-utils` (solo para pruebas, no llega al sitio) | npm audit | 0,3 h |
| D12 | Cobertura de pruebas limitada a los módulos intervenidos | Pagos con Wompi, devoluciones del cliente, checkout y reportes no tienen pruebas automatizadas | Revisión del equipo | 8 h |

## 3. Problemas que requieren atención futura

1. **Imágenes de muy alta resolución.** `Imagen::optimizar` usa `@` para no detenerse con imágenes dañadas. Funciona, pero si la extensión GD cambia de comportamiento, los errores no quedan registrados (D5).
2. **Validaciones duplicadas entre el frontend y el backend.** Las reglas de cupones y productos están escritas en PHP y otra vez en Vue para mostrar los errores sin esperar al servidor. Si cambia una regla en el backend, hay que actualizar también el formulario (D1).
3. **Credenciales del entorno.** Las pruebas de integración y el sitio leen el `backend/.env`. Hay que mantener ese archivo fuera del repositorio y con permisos restringidos en el servidor, como está hoy.
4. **Versiones de PHP y de las dependencias.** El proyecto exige PHP ≥ 8.1 y usa Vue 3.5, Vite 8 y Vue Router 5. Las actualizaciones mayores deben probarse con la suite completa antes de publicarse.
5. **Crecimiento del catálogo.** Los listados ya están paginados, pero los reportes calculan las ventas con consultas directas sobre `pedidos`. Con muchos más pedidos convendría revisar índices o resúmenes por mes.

## 4. Actividades de mantenimiento propuestas

| # | Actividad | Tipo de mantenimiento | Prioridad | Deuda relacionada |
|---|---|---|---|---|
| M1 | Ejecutar la evaluación (`analizar.mjs`) y las pruebas antes de cada publicación, y no publicar si aparece un error nuevo de PHPStan o ESLint, o si falla una prueba | **Preventivo** | **Alta** | — |
| M2 | Agregar pruebas automatizadas del flujo de pago con Wompi (con el modo de pruebas de Wompi), del checkout y de las devoluciones del cliente | **Preventivo** | **Alta** | D12 |
| M3 | Dividir `CuponesAdmin.validar()` y `ProductoEditor.validarFormulario()` en una función por campo, como se hizo con `ReglasValidacion` | **Perfectivo** | **Media** | D1, D2 |
| M4 | Extraer de `Pedido::cotizar()` el cálculo de líneas y el del cupón, y reemplazar el parámetro `$bloquear` por dos métodos con nombre propio (cotizar y reservar) | **Perfectivo** | **Media** | D3 |
| M5 | Reemplazar el operador `@` de `Imagen.php` por comprobaciones explícitas y registrar el error con `error_log` | **Correctivo / preventivo** | **Media** | D5 |
| M6 | Llevar los estilos repetidos del panel (semáforo, tablas, formularios de filtros) a `PanelLayout.vue` o a componentes (`FiltroSemaforo.vue`) | **Perfectivo** | **Media** | D7, D4 |
| M7 | Separar las vistas largas en componentes (por ejemplo `ContactoView` en formulario y mapa; `HeroCarrusel` en diapositiva y controles) | **Perfectivo** | Baja | D4 |
| M8 | Partir las líneas largas de SQL y mensajes para cumplir PSR-12 al 100 % | **Perfectivo** | Baja | D6 |
| M9 | Quitar el parámetro sin uso o documentarlo como parte de la firma común de las acciones del router | **Perfectivo** | Baja | D8 |
| M10 | Actualizar `@vue/test-utils` cuando publique una versión sin la dependencia vulnerable, y revisar `composer audit` y `npm audit` cada mes | **Adaptativo** | Baja | D11 |
| M11 | Revisar los avisos de deprecación de PHP 8.4 y de Vue antes de cambiar la versión del hosting | **Adaptativo** | Baja | — |
| M12 | Agregar índices para los reportes cuando el volumen de pedidos lo justifique | **Perfectivo** (rendimiento) | Baja | — |

**Resumen por tipo:**

- **Preventivo:** M1, M2 y M5. Son la base para no acumular deuda nueva.
- **Perfectivo:** M3, M4, M6, M7, M8, M9 y M12.
- **Correctivo:** M5, por los errores silenciados.
- **Adaptativo:** M10 y M11.

## 5. Recomendaciones para la evolución del sistema

1. **Mantener el ciclo medir → probar → refactorizar.** Lo hecho en esta evaluación demostró que, con pruebas escritas antes del cambio, se pueden reducir la complejidad máxima de 41 a 12 y la deuda en un 27 % sin alterar el comportamiento. Conviene repetir la medición en cada entrega y comparar contra `docs/evidencias/despues/`.
2. **Automatizar la revisión en GitHub.** Un flujo de GitHub Actions podría ejecutar `composer analisis`, `npm run lint` y las pruebas unitarias en cada *push*. Las de integración necesitan la base de datos y se seguirían ejecutando en local.
3. **Seguir los patrones que ya funcionan en el proyecto:**
   - reglas registradas en un mapa (`ReglasValidacion`, `EstrategiasDescuento`) para lo que crece por tipos;
   - composables (`useListadoPanel`, `useAjusteStock`) para la lógica repetida entre vistas;
   - modelos que concentran las reglas de su entidad (`Usuario::REGLAS_CUENTA`, `Variante`).
4. **Fijar umbrales de calidad para el código nuevo:**
   - complejidad máxima de 10 por método;
   - archivos Vue de menos de 500 líneas;
   - 0 errores en PHPStan, PSR-12 y ESLint;
   - duplicidad por debajo del 1 %.
5. **Documentar cada cambio importante en `docs/`.** Así el historial técnico queda junto al código y versionado con Git.
