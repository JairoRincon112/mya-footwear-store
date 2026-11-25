# Pruebas del software — MyA FOOTWEAR STORE

Pruebas automatizadas de las funcionalidades y componentes intervenidos en la refactorización. Se escribieron **antes** de modificar el código: primero se comprobó que pasaban con el código original y luego se volvieron a ejecutar después de cada refactorización. Así sirven como pruebas de regresión: demuestran que el comportamiento observable no cambió.

## 1. Herramientas de pruebas utilizadas

| Herramienta | Versión | Uso |
|---|---|---|
| **PHPUnit** | 11.5 | Pruebas unitarias y de integración del backend PHP |
| **cURL** (extensión de PHP) | PHP 8.3 | Peticiones HTTP reales a la API desde las pruebas de integración |
| **Vitest** | 5.1 | Pruebas del frontend (unitarias y de componentes) |
| **Vue Test Utils** | 2.6 | Montar componentes Vue, hacer clic, escribir y leer el resultado |
| **jsdom** | 26 | Navegador simulado donde corren las pruebas de Vitest |

Las pruebas de integración del backend llaman a la API real que sirve XAMPP (Apache + PHP + MariaDB) en `http://localhost/mya-api`. Para no guardar contraseñas en el repositorio, el token de sesión se firma en la prueba con el `JWT_SECRET` del `backend/.env` local, igual que lo hace la API al iniciar sesión. Si la API no responde, esas pruebas se marcan como omitidas en lugar de fallar.

En las pruebas del frontend, los servicios que llaman a la API se reemplazan por dobles de prueba (`vi.mock`). Así se prueba solo el componente: qué consulta, qué muestra y qué valida.

## 2. Ubicación de las pruebas en el repositorio

```text
backend/
├── phpunit.xml                         ← configuración (suites "unitarias" e "integracion")
└── tests/
    ├── bootstrap.php                   ← carga el autoload y el .env local
    ├── datos/                          ← archivos de prueba: zapato.png, texto-disfrazado.jpg
    ├── Unit/
    │   └── ValidatorTest.php
    └── Integracion/
        ├── ApiTestCase.php             ← peticiones HTTP y token de prueba
        ├── ImagenesProductoTest.php
        ├── RegistroUsuariosTest.php
        └── CotizacionCuponTest.php
frontend/
├── vitest.config.js
└── tests/
    ├── apoyo.js                        ← montaje con Pinia y sustitutos de RouterLink y del menú ⋮
    ├── PedidosAdmin.test.js
    ├── DevolucionesAdmin.test.js
    ├── InventarioAdmin.test.js
    ├── servicios.test.js
    └── utilidades.test.js
```

## 3. Comandos de ejecución

```powershell
# Backend (XAMPP encendido para las pruebas de integración)
cd backend
vendor/bin/phpunit                          # todas
vendor/bin/phpunit --testsuite unitarias    # solo unitarias
vendor/bin/phpunit --testsuite integracion  # solo integración
composer pruebas                            # atajo

# Frontend
cd frontend
npm test                                    # vitest run
npx vitest run --reporter=verbose           # con el nombre de cada prueba

# Todo, guardando la evidencia en docs/evidencias/<etapa>/
node herramientas/calidad/analizar.mjs despues
```

## 4. Casos de prueba implementados

### 4.1 Backend — PHPUnit (51 pruebas, 116 aserciones)

| # | Prueba | Tipo | Qué verifica | Resultado esperado |
|---|---|---|---|---|
| B1 | `ValidatorTest::testConvierteYLimpiaLosValoresValidos` (15 casos) | Unitaria · validación | Cada regla acepta valores correctos y los convierte | `" Tenis "` → `"Tenis"`, correo en minúsculas, `"42"` → `42`, `"1"` → `true`, lista sin repetidos, código y SKU en mayúsculas, fecha del formulario → `Y-m-d H:i:s` |
| B2 | `ValidatorTest::testRechazaLosValoresInvalidosConSuMensaje` (17 casos) | Unitaria · validación y manejo de errores | Cada regla rechaza lo inválido con su mensaje exacto | `HttpException` 422 con, por ejemplo, «Escribe un correo electrónico válido.», «Debe ser mayor o igual a 1.» o «Escribe una fecha válida.» (el 30 de febrero no existe) |
| B3 | `ValidatorTest`: requerido vacío, opcional vacío, solo campos validados, errores de todos los campos, regla desconocida (5 pruebas) | Unitaria · reglas de negocio | Comportamiento general del validador | Requerido vacío → error. Opcional enviado vacío → `null`; ausente → no aparece. Se descartan campos no declarados (por ejemplo `rol`). Se reúnen todos los errores. Una regla inexistente lanza `LogicException` |
| B4 | `ImagenesProductoTest` (6 pruebas) | Integración · validación, seguridad y almacenamiento | `POST /api/productos/{id}/imagenes` | Sin archivo → 422 «Selecciona una imagen.». Texto con extensión `.jpg` → 422 «Solo se permiten imágenes JPG, PNG o WEBP.». Color inexistente → 422. Producto inexistente → 404. Vendedor → 403. PNG real → 201, archivo guardado como `uploads/productos/<32 hex>.png`; al borrarlo responde 200 y el archivo desaparece del disco |
| B5 | `RegistroUsuariosTest` (5 pruebas) | Integración · reglas de negocio y persistencia | `POST /api/auth/registro` y `POST /api/usuarios` | El registro público crea un **cliente** aunque se envíe `rol: admin`, guarda el correo en minúsculas y entrega token. El correo repetido se rechaza igual en el registro y en el panel. Los datos inválidos dan los mismos 4 errores en el mismo orden en las dos rutas. El admin crea un vendedor con teléfono. Un rol inexistente → 422. Las cuentas de prueba se borran al terminar |
| B6 | `CotizacionCuponTest` (3 pruebas) | Integración · reglas de negocio | `POST /api/pedidos/cotizar` | Sin cupón, con `""` o con `null` → 200 y descuento 0. Cupón mal escrito → 422 en `cupon`. Carrito vacío → 422 «Tu carrito está vacío.» |

### 4.2 Frontend — Vitest

| # | Prueba | Tipo | Qué verifica | Resultado esperado |
|---|---|---|---|---|
| F1 | `PedidosAdmin.test.js` (4 pruebas) | Componente · funcionalidad y errores | Listado de pedidos del panel | Al abrir consulta la página 1 y muestra el cliente y el estado «En tránsito». El semáforo «Pendiente» consulta con `estado: pendiente` y página 1, y queda marcado (`aria-pressed`). La búsqueda no consulta a los 200 ms y sí a los 400 ms. Si la API falla, se muestra su mensaje |
| F2 | `DevolucionesAdmin.test.js` (3 pruebas) | Componente · reglas de negocio | Devoluciones en el panel | Muestra el tipo «Talla incorrecta» y «1 por revisar». El semáforo y la lista de tipos filtran juntos y vuelven a la página 1. Rechazar sin motivo muestra el error y no llama a la API; con motivo llama a `cambiarEstado(4, 'rechazada', motivo)` |
| F3 | `InventarioAdmin.test.js` (4 pruebas) | Componente · validación | Ajuste manual de stock | Marca el bajo stock. Rechaza cantidad 0 o 1,5. Una entrada no puede ser negativa y un ajuste exige motivo; en esos casos no llama a la API. Un ajuste válido envía `{ tipo, cantidad: 5, motivo }` sin espacios y muestra el stock nuevo |
| F4 | `servicios.test.js` (3 pruebas) | Unitaria · integración con la API | Filtros que se envían | Se quitan los vacíos, `null` y `undefined`. Pedidos, devoluciones e inventario limpian igual. El `0` de «inactivos» sí se envía |
| F5 | `utilidades.test.js` (12 pruebas, agregadas después de refactorizar) | Unitaria | Piezas extraídas | `validarAjusteStock`: 4 casos inválidos y la conversión del válido. `useAjusteStock`: envía y cierra el formulario; los errores por campo quedan en el formulario y los demás van al aviso. `limpiarFiltros`: conserva `0` y `false`. `useListadoPanel`: resultado inicial con campos extra, filtro inmediato a la página 1, búsqueda cancelada al cerrar la vista, mensaje de error |

### 4.3 Relación con las refactorizaciones

| Refactorización | Pruebas que la protegen |
|---|---|
| 1. `Validator` → `ReglasValidacion` | B1, B2, B3, y de forma indirecta B4, B5 y B6 (todas las rutas validan) |
| 2. `ImagenSubida` | B4 |
| 3. Reglas de la cuenta en `Usuario` | B5 |
| 4. `encontrado()` y código muerto | B4 (producto inexistente → 404), B6 (cupón ausente, vacío o `null`) |
| 5. `useListadoPanel` y `limpiarFiltros` | F1, F2, F4, F5 |
| 6. `validarAjusteStock` y `useAjusteStock` | F3, F5 |

### 4.4 Aspectos verificados

| Aspecto | Pruebas |
|---|---|
| Funcionamiento de las funcionalidades | B4, B5, B6, F1, F2 |
| Cumplimiento de las reglas de negocio | B3, B5 (el registro público solo crea clientes; un correo por cuenta), B6, F2 (el rechazo exige motivo), F3 |
| Validación de información | B1, B2, B4, F3, F5 |
| Almacenamiento y recuperación de datos | B4 (la imagen se guarda en disco y en la base, y se elimina), B5 (la cuenta creada se consulta con sus datos y su rol) |
| Manejo de errores | B2, B4 (404 y 403), B6, F1 (la API falla), F5 |
| Integraciones externas | **No corresponde a los componentes intervenidos.** La única integración externa del proyecto es la pasarela de pagos Wompi, y ninguna refactorización tocó ese módulo (`Wompi.php`, `PagoController`). Las pruebas de integración sí ejercitan la integración entre el frontend, la API, el servidor web y la base de datos. Las pruebas de Wompi quedan propuestas en [MANTENIMIENTO.md](MANTENIMIENTO.md) (actividad M2) |

## 5. Resultados obtenidos

### 5.1 Antes de refactorizar (código original del commit `ef4486a`, con las pruebas agregadas en `a2b6648`)

Las pruebas se escribieron sin tocar el código de la aplicación: el commit `a2b6648` solo agrega las herramientas y las pruebas. La primera ejecución quedó guardada en la evidencia inicial (commit `e5faddc`).

| Suite | Ejecutadas | Aprobadas | Fallidas |
|---|---|---|---|
| Backend: PHPUnit | 51 | 51 | 0 |
| Frontend: Vitest | 14 | 14 | 0 |
| **Total** | **65** | **65** | **0** |

Evidencia: [pruebas-backend.txt](evidencias/antes/pruebas-backend.txt) · [pruebas-frontend.txt](evidencias/antes/pruebas-frontend.txt)

```text
PHPUnit 11.5.58 by Sebastian Bergmann and contributors.
Runtime:       PHP 8.3.36
...................................................               51 / 51 (100%)
OK (51 tests, 116 assertions)

 Test Files  4 passed (4)
      Tests  14 passed (14)
```

### 5.2 Durante la refactorización

Después de cada refactorización, y antes de su commit, se ejecutó la suite completa del lado intervenido (`vendor/bin/phpunit` o `npx vitest run`). Ninguna prueba falló en ningún commit, y no fue necesario modificar ninguna prueba escrita antes de los cambios.

### 5.3 Después de refactorizar (commit `4382a5a`)

| Suite | Ejecutadas | Aprobadas | Fallidas |
|---|---|---|---|
| Backend: PHPUnit | 51 | 51 | 0 |
| Frontend: Vitest | 26 | 26 | 0 |
| **Total** | **77** | **77** | **0** |

Las 12 pruebas nuevas (`utilidades.test.js`) cubren directamente las funciones y composables creados en la refactorización.

Evidencia: [pruebas-backend.txt](evidencias/despues/pruebas-backend.txt) · [pruebas-frontend.txt](evidencias/despues/pruebas-frontend.txt)

```text
OK (51 tests, 116 assertions)

 ✓ tests/utilidades.test.js > useAjusteStock > los errores por campo de la API quedan en el formulario; los demás van al aviso
 ✓ tests/utilidades.test.js > useListadoPanel > cancela la búsqueda pendiente si la vista se cierra
 ✓ tests/DevolucionesAdmin.test.js > DevolucionesAdmin > no deja rechazar sin explicar el motivo al cliente
 ✓ tests/InventarioAdmin.test.js > InventarioAdmin · ajuste de stock > envía el ajuste válido y muestra el stock nuevo
 ...
 Test Files  5 passed (5)
      Tests  26 passed (26)
```

### 5.4 Verificación en el navegador

Además de las pruebas automatizadas, el compilado final (`npm run build` → `public/mya`) se revisó con un navegador real (Edge sin interfaz) sobre la tienda local. Se comprobó lo siguiente:

- El menú «⋮» de las 6 pantallas del panel abre y se cierra con Escape.
- El clic en la fila abre el detalle.
- «Ajustar stock» abre el formulario, tanto en Inventario como en el Editor de producto.
- Las dos pantallas muestran ahora los mismos mensajes de validación del ajuste.

## 6. Resumen

| | Antes | Después |
|---|---|---|
| Pruebas ejecutadas | 65 | 77 |
| Aprobadas | 65 | 77 |
| Fallidas | 0 | 0 |
| Pruebas que se tuvieron que cambiar por la refactorización | — | 0 |
