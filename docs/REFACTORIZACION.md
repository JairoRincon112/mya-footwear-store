# Refactorización — MyA FOOTWEAR STORE

Mejoras hechas directamente en el código del proyecto para corregir los problemas de [EVALUACION_SOFTWARE.md](EVALUACION_SOFTWARE.md#6-problemas-identificados). Se cumplió el mínimo de cinco refactorizaciones; se hizo una sexta y algunos ajustes de estándares.

Cada mejora está en su propio commit y no cambia el comportamiento observable. Las pruebas automatizadas, escritas antes de empezar, pasaron después de cada commit (ver [PRUEBAS_SOFTWARE.md](PRUEBAS_SOFTWARE.md)).

| # | Mejora | Commit | Técnica principal |
|---|---|---|---|
| 1 | Reglas de validación separadas de `Validator` | `bd9e6f1` | Extracción de clase y de métodos · reemplazo de condicional por mapa de funciones |
| 2 | Subida de imágenes fuera del controlador | `ed01916` | Extracción de clase · eliminación de duplicidad |
| 3 | Reglas de la cuenta en el modelo `Usuario` | `a4ce3ef` | Mover reglas al modelo · objeto de parámetros |
| 4 | Respuesta 404 única y código muerto | `76afb55` | Extracción de método a la clase base · eliminación de código muerto |
| 5 | Composable de listados del panel | `4f66e05` | Extracción de componente reutilizable (composable) · constante con nombre |
| 6 | Ajuste de stock compartido (validación y formulario) | `1278353`, `6ae3f8f` | Extracción de función y de composable · unificación de mensajes |
| — | Ajustes de estándares PSR-12 y Vue | `e14bf7b` | Renombramiento · reorganización |

---

## Mejora 1. Las reglas de validación salen de `Validator`

**Problema.** `Validator::aplicarRegla()` tenía un `switch` de 16 casos con condiciones anidadas: complejidad ciclomática **41** (muy alta) y complejidad total de la clase 53. Todas las peticiones de la API pasan por este validador. Agregar o cambiar una regla obligaba a modificar ese método gigante (Long Method), y las reglas no se podían leer ni probar por separado.

**Archivos intervenidos:**

- `backend/src/Core/Validator.php`
- `backend/src/Core/ReglasValidacion.php` (nuevo)

**Antes** (fragmento de `Validator.php`):

```php
/** @return array{0: ?string, 1: mixed} [mensaje de error o null, valor convertido] */
private static function aplicarRegla(string $regla, string $parametro, mixed $valor): array
{
    switch ($regla) {
        case 'requerido':
            return [null, $valor];

        case 'texto':
            return is_string($valor) ? [null, $valor] : ['Debe ser un texto.', $valor];

        case 'email':
            if (!is_string($valor) || filter_var($valor, FILTER_VALIDATE_EMAIL) === false) {
                return ['Escribe un correo electrónico válido.', $valor];
            }
            return [null, mb_strtolower($valor)];

        case 'min':
            return mb_strlen((string) $valor) < (int) $parametro
                ? ["Debe tener al menos $parametro caracteres.", $valor]
                : [null, $valor];
        // ... 11 casos más: max, entero, numerico, minimo, maximo, en, booleano,
        //     lista_enteros, telefono, fecha_hora, codigo, sku ...
        default:
            throw new LogicException("Regla de validación desconocida: $regla");
    }
}
```

**Después.** `Validator` solo recorre los campos y aplica las reglas en orden:

```php
private static function validarCampo(array $datos, string $campo, array $listaReglas): array
{
    $valor = $datos[$campo] ?? null;
    if (is_string($valor)) {
        $valor = trim($valor);
    }

    if ($valor === null || $valor === '' || $valor === []) {
        $obligatorio = in_array('requerido', $listaReglas, true);
        return [$obligatorio ? 'Este campo es obligatorio.' : null, null];
    }

    foreach ($listaReglas as $regla) {
        [$nombre, $parametro] = array_pad(explode(':', $regla, 2), 2, '');
        [$error, $valor] = ReglasValidacion::aplicar($nombre, $valor, $parametro);
        if ($error !== null) {
            return [$error, $valor];
        }
    }

    return [null, $valor];
}
```

Cada regla es una función pequeña de `ReglasValidacion`, registrada en un mapa:

```php
public static function aplicar(string $regla, mixed $valor, string $parametro): array
{
    $funcion = self::mapa()[$regla] ?? throw new LogicException("Regla de validación desconocida: $regla");
    return $funcion($valor, $parametro);
}

private static function mapa(): array
{
    return self::$mapa ??= [
        'requerido' => static fn (mixed $valor): array => [null, $valor],
        'texto' => self::texto(...),
        'email' => self::email(...),
        'min' => self::longitudMinima(...),
        // ...
        'codigo' => static fn (mixed $valor): array => self::formato('codigo', $valor),
        'sku' => static fn (mixed $valor): array => self::formato('sku', $valor),
    ];
}

private static function email(mixed $valor): array
{
    if (!is_string($valor) || filter_var($valor, FILTER_VALIDATE_EMAIL) === false) {
        return ['Escribe un correo electrónico válido.', $valor];
    }
    return [null, mb_strtolower($valor)];
}
```

Las tres reglas de formato (`telefono`, `codigo`, `sku`), que repetían la misma estructura, quedaron como datos en `FORMATOS`: expresión regular, mensaje y si el valor se pasa a mayúsculas. Todas usan la misma función `formato()`.

**Técnica de refactorización:**

- Extracción de clase (`ReglasValidacion`).
- Extracción de métodos (una función por regla, más `validarCampo`).
- Reemplazo de condicional por un mapa de funciones.
- Eliminación de duplicidad en las reglas de formato.

**Principios y buenas prácticas:**

- **SRP:** `Validator` recorre los campos y `ReglasValidacion` sabe validar cada tipo de dato.
- **Abierto/cerrado:** una regla nueva se agrega registrándola en el mapa, sin tocar el recorrido.
- **KISS** y **DRY.**
- Nombres descriptivos: `longitudMinima` en lugar de `min`, `unoDe` en lugar de `en`.

**Justificación técnica.** Era el método más complejo del sistema y de él dependen todos los controladores. Se separó sin cambiar la interfaz pública (`Validator::validar` recibe y devuelve lo mismo), así ningún controlador tuvo que modificarse. `ValidatorTest` cubre las 16 reglas con 37 casos y confirma que se conservan los mismos mensajes y conversiones.

**Beneficios obtenidos:**

- Complejidad máxima: de **41 a 8** en `Validator`, y **5** como máximo en `ReglasValidacion`.
- `Validator` pasó de 115 a 44 líneas.
- PHPMD dejó de reportar `CyclomaticComplexity` y `ExcessiveClassComplexity` en la clase.
- Cada regla se lee en pocas líneas y se puede probar por separado.

---

## Mejora 2. La subida de imágenes sale de `ProductoController`

**Problema.** `ProductoController::subirImagen()` tenía complejidad **15**, NPath **2 592** y elevaba el acoplamiento de la clase a **14** objetos (umbral 13).

El controlador hacía trabajo de infraestructura:

- leer los códigos de error de `$_FILES`;
- revisar el tamaño;
- detectar el tipo real con `finfo`;
- crear la carpeta y generar el nombre aleatorio;
- optimizar la imagen.

Además, la lista de formatos permitidos (`TIPOS_IMAGEN`) estaba duplicada: `Imagen::tipoReal()` ya tenía la misma lista. Y el tamaño máximo se calculaba con el número mágico `1024 * 1024`.

**Archivos intervenidos:**

- `backend/src/Controllers/ProductoController.php`
- `backend/src/Core/ImagenSubida.php` (nuevo)
- `backend/src/Core/Imagen.php`

**Antes** (fragmento de `ProductoController.php`):

```php
private const TIPOS_IMAGEN = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

public function subirImagen(Request $request): void
{
    // ... validación del producto y del color ...
    $archivo = $request->archivo('imagen');
    if ($archivo === null || $archivo['error'] === UPLOAD_ERR_NO_FILE) {
        throw HttpException::validacion(['imagen' => 'Selecciona una imagen.']);
    }
    if ($archivo['error'] === UPLOAD_ERR_INI_SIZE || $archivo['error'] === UPLOAD_ERR_FORM_SIZE) {
        throw HttpException::validacion(['imagen' => 'La imagen es demasiado grande.']);
    }
    if ($archivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
        throw HttpException::validacion(['imagen' => 'No se pudo recibir la imagen. Intenta de nuevo.']);
    }

    $maximoMb = Config::entero('UPLOAD_MAX_MB', 5);
    if ($archivo['size'] > $maximoMb * 1024 * 1024) {
        throw HttpException::validacion(['imagen' => "La imagen no puede pesar más de $maximoMb MB."]);
    }

    $tipo = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
    $extension = self::TIPOS_IMAGEN[$tipo] ?? null;
    if ($extension === null) {
        throw HttpException::validacion(['imagen' => 'Solo se permiten imágenes JPG, PNG o WEBP.']);
    }

    $carpeta = self::carpetaPublica() . '/' . self::CARPETA_SUBIDAS;
    if (!is_dir($carpeta) && !mkdir($carpeta, 0775, true) && !is_dir($carpeta)) {
        throw new \RuntimeException('No se pudo crear la carpeta de imágenes.');
    }

    $nombreArchivo = bin2hex(random_bytes(16)) . '.' . $extension;
    Imagen::optimizar($archivo['tmp_name'], "$carpeta/$nombreArchivo", $tipo);
    // ... guardar el registro y responder ...
}
```

**Después.** El controlador solo coordina:

```php
public function subirImagen(Request $request): void
{
    $productoId = $this->idDeRuta($request);
    if (!(new Producto())->existe($productoId)) {
        throw HttpException::noEncontrado('El producto no existe.');
    }

    $colorId = $this->colorDeLaImagen($request);

    // ImagenSubida revisa el archivo (tamaño y tipo real) y lo guarda reducido y recomprimido
    // con un nombre aleatorio: nunca se usa el nombre original.
    $nombreArchivo = ImagenSubida::guardar(
        $request->archivo('imagen'),
        self::carpetaPublica() . '/' . self::CARPETA_SUBIDAS,
        Config::entero('UPLOAD_MAX_MB', 5)
    );

    $imagenes = new ProductoImagen();
    $id = $imagenes->crear($productoId, self::CARPETA_SUBIDAS . "/$nombreArchivo", $colorId);
    Response::creado($imagenes->buscarPorId($id), 'Imagen agregada.');
}
```

Toda la lógica del archivo vive en `ImagenSubida`:

```php
private const MENSAJES_ERROR = [
    UPLOAD_ERR_NO_FILE => 'Selecciona una imagen.',
    UPLOAD_ERR_INI_SIZE => 'La imagen es demasiado grande.',
    UPLOAD_ERR_FORM_SIZE => 'La imagen es demasiado grande.',
];
private const BYTES_POR_MB = 1024 * 1024;

private static function validar(?array $archivo, int $maximoMb): string
{
    $codigo = $archivo['error'] ?? UPLOAD_ERR_NO_FILE;
    if (isset(self::MENSAJES_ERROR[$codigo])) {
        throw self::error(self::MENSAJES_ERROR[$codigo]);
    }
    if ($codigo !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
        throw self::error('No se pudo recibir la imagen. Intenta de nuevo.');
    }
    if ($archivo['size'] > $maximoMb * self::BYTES_POR_MB) {
        throw self::error("La imagen no puede pesar más de $maximoMb MB.");
    }

    // Se revisa el contenido real del archivo para evitar subir scripts disfrazados de imagen.
    return Imagen::tipoReal($archivo['tmp_name'])
        ?? throw self::error('Solo se permiten imágenes JPG, PNG o WEBP.');
}
```

Los formatos aceptados quedaron en una sola constante, `Imagen::EXTENSIONES`. La usan `Imagen::tipoReal()` y `ImagenSubida` para elegir la extensión.

**Técnica de refactorización:**

- Extracción de clase (`ImagenSubida`).
- Extracción de método (`colorDeLaImagen`).
- Tabla de mensajes en lugar de condicionales encadenados.
- Constante con nombre (`BYTES_POR_MB`) en lugar del número mágico.
- Eliminación de la lista duplicada de formatos.

**Principios y buenas prácticas:**

- **SRP** y alta cohesión: el controlador coordina y la clase de infraestructura maneja archivos.
- **Bajo acoplamiento:** el controlador ya no depende de `finfo` ni de `Imagen`.
- **DRY.**
- Seguridad: se mantiene la validación del contenido real del archivo.

**Justificación técnica.** Un controlador en esta arquitectura MVC debe recibir la petición, llamar al modelo o servicio y responder. El manejo de archivos subidos es infraestructura reutilizable: la misma clase serviría, por ejemplo, para una foto de perfil. Las pruebas de integración `ImagenesProductoTest` envían archivos reales a la API y confirman el mismo comportamiento antes y después: archivo faltante, texto disfrazado de `.jpg`, color inexistente, producto inexistente, rol sin permiso, y subida y borrado exitosos.

**Beneficios obtenidos:**

- `subirImagen`: complejidad de **15 a 2**; NPath de 2 592 a 2.
- Desapareció el aviso de acoplamiento de `ProductoController`.
- La lista de formatos de imagen está en un solo lugar.
- La validación del archivo se puede reutilizar.

---

## Mejora 3. Las reglas de la cuenta pasan al modelo `Usuario`

**Problema.** Las reglas de nombre, correo, contraseña y teléfono, y la verificación «Ya existe una cuenta con este correo», estaban copiadas en cuatro métodos:

- `AuthController::registro`
- `AuthController::actualizarPerfil`
- `UsuarioController::crear`
- `UsuarioController::actualizar`

La regla de la contraseña (`texto|min:8|max:72`) aparecía en dos lugares más. jscpd detectó 9 líneas idénticas entre los dos controladores. Además, `Usuario::crear()` recibía 5 parámetros sueltos (Long Parameter List).

**Archivos intervenidos:**

- `backend/src/Models/Usuario.php`
- `backend/src/Controllers/AuthController.php`
- `backend/src/Controllers/UsuarioController.php`

**Antes** (el mismo bloque estaba en `AuthController::registro` y en `UsuarioController::crear`):

```php
$datos = Validator::validar($request->cuerpo(), [
    'nombre' => 'requerido|texto|min:2|max:120',
    'email' => 'requerido|email|max:150',
    'password' => 'requerido|texto|min:8|max:72',
    'telefono' => 'telefono',
]);

$usuarios = new Usuario();
if ($usuarios->existeEmail($datos['email'])) {
    throw HttpException::validacion(['email' => 'Ya existe una cuenta con este correo.']);
}

$id = $usuarios->crear($datos['nombre'], $datos['email'], $datos['password'], 'cliente', $datos['telefono'] ?? null);
```

**Después.** El modelo concentra las reglas de la cuenta:

```php
// Models/Usuario.php
public const REGLA_PASSWORD = 'texto|min:8|max:72';

public const REGLAS_CUENTA = [
    'nombre' => 'requerido|texto|min:2|max:120',
    'email' => 'requerido|email|max:150',
    'password' => 'requerido|' . self::REGLA_PASSWORD,
    'telefono' => 'telefono',
];

public function crear(array $datos, string $rol): int
{
    $this->asegurarEmailDisponible($datos['email']);
    // INSERT ...
}

public function asegurarEmailDisponible(string $email, ?int $exceptoId = null): void
{
    if ($this->existeEmail($email, $exceptoId)) {
        throw HttpException::validacion(['email' => 'Ya existe una cuenta con este correo.']);
    }
}
```

Y los controladores solo lo usan:

```php
// AuthController::registro — el registro público solo crea clientes
$datos = Validator::validar($request->cuerpo(), Usuario::REGLAS_CUENTA);
$id = (new Usuario())->crear($datos, 'cliente');

// UsuarioController::crear — el administrador elige el rol
$datos = Validator::validar($request->cuerpo(), Usuario::REGLAS_CUENTA + self::reglaRol());
$id = $usuarios->crear($datos, $datos['rol']);

// UsuarioController::actualizar — al editar, la contraseña es opcional
$reglas = array_replace(Usuario::REGLAS_CUENTA, ['password' => Usuario::REGLA_PASSWORD]) + self::reglaRol();
```

`existeEmail()` pasó a ser privado, porque ya nadie fuera del modelo lo necesita. Así `Usuario` no supera el límite de 10 métodos públicos.

**Técnica de refactorización:**

- Mover las reglas al modelo dueño de los datos.
- Extracción de método (`asegurarEmailDisponible`).
- Objeto de parámetros: `crear(array $datos, string $rol)` en lugar de 5 argumentos.
- Encapsulamiento (`existeEmail` privado).

**Principios y buenas prácticas:**

- **DRY:** una sola definición de lo que es una cuenta válida.
- **Experto en información (GRASP):** el modelo `Usuario` sabe qué datos son válidos para un usuario.
- **SRP** en los controladores.
- Interfaz más pequeña.

**Justificación técnica.** Con las reglas copiadas, bastaba un cambio olvidado para que el registro público y el panel aceptaran datos distintos. Las pruebas `RegistroUsuariosTest` envían los mismos datos inválidos al registro y al panel. También verifican que los dos rechacen el correo repetido con el mismo mensaje, y que los errores lleguen en el mismo orden.

**Beneficios obtenidos:**

- Desapareció el bloque duplicado entre `AuthController` y `UsuarioController`.
- La regla de la contraseña se define una vez y la usan 5 lugares.
- `UsuarioController` pasó de 84 a 67 líneas.
- `Usuario::crear` ya no tiene una lista larga de parámetros.

---

## Mejora 4. Una sola forma de responder 404 y eliminación del código muerto

**Problema:**

1. **Código duplicado.** Seis controladores repetían el mismo bloque «buscar el registro y, si no existe, responder 404», 11 veces en total. jscpd marcaba `VarianteController` duplicándose a sí mismo.
2. **Código muerto** (PHPStan, 2 errores):
   - En `PedidoController::codigoCupon()` la condición `$cuerpo['cupon'] === null` nunca podía cumplirse después de `!isset(...)`. Además, todo el `if` repetía algo que `Validator` ya hace: un campo opcional vacío se convierte en `null`.
   - En `Talla::buscarVarias()` se llamaba `array_values()` sobre algo que ya es una lista.

**Archivos intervenidos:**

- `backend/src/Controllers/Controller.php` (clase base)
- Los controladores `CuponController`, `DevolucionController`, `InventarioController`, `ProductoController`, `UsuarioController`, `VarianteController` y `PedidoController`
- `backend/src/Models/Talla.php`

**Antes:**

```php
// VarianteController::actualizar (y el mismo bloque en ajustarStock, InventarioController, etc.)
$variantes = new Variante();
$variante = $variantes->buscarPorId($this->idDeRuta($request));
if ($variante === null) {
    throw HttpException::noEncontrado('La variante no existe.');
}

// PedidoController
private function codigoCupon(array $cuerpo): ?string
{
    if (!isset($cuerpo['cupon']) || $cuerpo['cupon'] === '' || $cuerpo['cupon'] === null) {
        return null;
    }
    return Validator::validar($cuerpo, ['cupon' => 'codigo'])['cupon'];
}

// Talla::buscarVarias
foreach (array_values($ids) as $posicion => $id) {
```

**Después:**

```php
// Controller.php (clase base de todos los controladores)
protected function encontrado(?array $registro, string $mensaje): array
{
    return $registro ?? throw HttpException::noEncontrado($mensaje);
}

// VarianteController::actualizar
$variantes = new Variante();
$variante = $this->encontrado($variantes->buscarPorId($this->idDeRuta($request)), 'La variante no existe.');

// PedidoController
/** El cupón es opcional: Validator ya devuelve null si llega vacío y lo omite si no llega. */
private function codigoCupon(array $cuerpo): ?string
{
    return Validator::validar($cuerpo, ['cupon' => 'codigo'])['cupon'] ?? null;
}

// Talla::buscarVarias
foreach ($ids as $posicion => $id) {
```

**Técnica de refactorización:**

- Extracción de método hacia la superclase (`encontrado`).
- Eliminación de código muerto.
- Simplificación de condicionales.
- Eliminación de imports sin uso (`HttpException` en dos controladores).

**Principios y buenas prácticas:**

- **DRY** y **KISS.**
- **YAGNI:** no se repite una validación que otra capa ya garantiza.
- Uso de las herramientas de análisis para encontrar código que no hace nada.

**Justificación técnica.** Cada bloque repetido sumaba una rama a su método y un mensaje que mantener. El método de la clase base deja cada acción en una línea y conserva el mensaje propio de cada recurso. `CotizacionCuponTest` confirma que, sin el código muerto, el cupón ausente, vacío o `null` sigue cotizando sin descuento, y que un cupón mal escrito sigue dando error 422.

**Beneficios obtenidos:**

- PHPStan pasó de **2 errores a 0**.
- 11 bloques de 4 líneas pasaron a 11 líneas.
- Bajó la complejidad de 11 métodos de controlador.
- La duplicidad de PHP bajó de 0,47 % a 0,23 %, contando también la mejora 3.

---

## Mejora 5. Composable `useListadoPanel` para los listados del panel

**Problema.** Las cinco pantallas de listado del panel (Pedidos, Productos, Inventario, Usuarios y Devoluciones) copiaban la misma lógica:

- los estados `cargando` y `error`;
- la función `cargar`;
- volver a la página 1 cuando cambia un filtro;
- esperar 350 ms (número mágico) a que se deje de escribir en la búsqueda;
- cambiar de página.

jscpd detectó 13 líneas idénticas entre `PedidosAdmin` y `DevolucionesAdmin`. Además, la misma función `limpiar` (quitar filtros vacíos) estaba copiada en tres servicios. Había también un defecto latente: el temporizador de la búsqueda no se cancelaba al salir de la pantalla y podía consultar la API cuando la vista ya no existía.

**Archivos intervenidos:**

- `frontend/src/composables/useListadoPanel.js` (nuevo)
- `frontend/src/utils/filtros.js` (nuevo)
- Las vistas `views/panel/PedidosAdmin.vue`, `ProductosAdmin.vue`, `InventarioAdmin.vue`, `UsuariosAdmin.vue` y `DevolucionesAdmin.vue`
- Los servicios `services/pedidoService.js`, `devolucionService.js` y `adminService.js`

**Antes** (repetido, con pequeñas variaciones, en las cinco vistas):

```js
const filtros = reactive({ estado: '', q: '', desde: '', hasta: '', pagina: 1 })
const resultado = ref({ items: [], total: 0, paginas: 0 })
const cargando = ref(true)
const error = ref('')
let temporizador = null

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    resultado.value = await pedidoService.listar(filtros)
  } catch (e) {
    error.value = e.message
  } finally {
    cargando.value = false
  }
}

function reiniciarYCargar() {
  filtros.pagina = 1
  cargar()
}

watch(() => [filtros.estado, filtros.desde, filtros.hasta], reiniciarYCargar)
watch(() => filtros.q, () => {
  clearTimeout(temporizador)
  temporizador = setTimeout(reiniciarYCargar, 350)
})

function irAPagina(pagina) {
  filtros.pagina = pagina
  cargar()
}
```

```js
// pedidoService.js, devolucionService.js y adminService.js
const limpiar = (filtros) => Object.fromEntries(Object.entries(filtros).filter(([, valor]) => valor !== '' && valor != null))
```

**Después.** Cada vista declara solo lo propio:

```js
// PedidosAdmin.vue
const { filtros, resultado, cargando, error, cargar, irAPagina } = useListadoPanel(
  (f) => pedidoService.listar(f),
  { estado: '', desde: '', hasta: '' },
)

// InventarioAdmin.vue: su consulta y un campo extra en el resultado vacío
const { filtros, resultado, cargando, error, cargar, irAPagina } = useListadoPanel(
  (f) => pedidoService.inventario({ ...f, bajo_stock: f.bajo_stock ? 1 : '' }),
  { marca: '', bajo_stock: route.query.bajo_stock === '1' },
  { umbral: 3 },
)
```

La lógica común vive una sola vez en el composable:

```js
// composables/useListadoPanel.js
export const ESPERA_BUSQUEDA_MS = 350

export function useListadoPanel(consultar, filtrosIniciales = {}, resultadoInicial = {}) {
  const filtros = reactive({ q: '', ...filtrosIniciales, pagina: 1 })
  // ... resultado, cargando, error, cargar(), reiniciarYCargar(), irAPagina() ...

  // Los filtros de selección consultan de inmediato...
  const inmediatos = Object.keys(filtros).filter((clave) => !['q', 'pagina'].includes(clave))
  watch(() => inmediatos.map((clave) => filtros[clave]), reiniciarYCargar)

  // ...y la búsqueda espera a que se deje de escribir.
  watch(() => filtros.q, () => {
    clearTimeout(temporizador)
    temporizador = setTimeout(reiniciarYCargar, ESPERA_BUSQUEDA_MS)
  })
  onBeforeUnmount(() => clearTimeout(temporizador))

  return { filtros, resultado, cargando, error, cargar, irAPagina }
}
```

```js
// utils/filtros.js: la usan los tres servicios
export const limpiarFiltros = (filtros) =>
  Object.fromEntries(Object.entries(filtros).filter(([, valor]) => valor !== '' && valor != null))
```

**Técnica de refactorización:**

- Extracción de componente reutilizable (composable de Vue 3).
- Extracción de función (`limpiarFiltros`).
- Constante con nombre (`ESPERA_BUSQUEDA_MS`, `PRODUCTOS_POR_PAGINA`).
- Parametrización: cada vista entrega su consulta y sus filtros.

**Principios y buenas prácticas:**

- **DRY.**
- **SRP:** la vista se ocupa de su pantalla y el composable del ciclo de carga.
- **Abierto/cerrado:** un listado nuevo usa el composable sin copiar código.
- Manejo correcto del ciclo de vida: se limpia el temporizador al desmontar.

**Justificación técnica.** Los composables son el mecanismo de Vue 3 para reutilizar lógica con estado entre componentes. Las pruebas `PedidosAdmin.test.js`, `DevolucionesAdmin.test.js` e `InventarioAdmin.test.js` se escribieron contra las vistas originales y pasaron sin cambios con el composable. Comprueban:

- la carga inicial;
- el filtro del semáforo que vuelve a la página 1;
- la espera de 350 ms de la búsqueda;
- el mensaje de error.

**Beneficios obtenidos:**

- Se eliminaron unas 25 líneas por vista. Por ejemplo, `PedidosAdmin` pasó de 7 a 2 funciones propias y de 136 a 112 líneas.
- Desaparecieron los clones de JavaScript entre las vistas del panel y las tres copias de `limpiar`.
- Se corrigió el temporizador que no se cancelaba.

---

## Mejora 6. Ajuste de stock compartido: validación y formulario

**Problema.** El ajuste manual de stock estaba escrito dos veces, en `InventarioAdmin.vue` y en `ProductoEditor.vue`: el estado del formulario, la validación, el envío y el manejo de errores. La validación tenía **mensajes distintos** para el mismo error, y las dos funciones `guardarAjuste` superaban el umbral de complejidad (CC 11). jscpd marcaba 7 líneas iguales entre las dos vistas.

**Archivos intervenidos:**

- `frontend/src/utils/inventario.js` (nuevo)
- `frontend/src/composables/useAjusteStock.js` (nuevo)
- `frontend/src/views/panel/InventarioAdmin.vue`
- `frontend/src/views/panel/ProductoEditor.vue`

**Antes:**

```js
// InventarioAdmin.vue
if (!Number.isInteger(cantidad) || cantidad === 0) errores.cantidad = 'Cantidad entera distinta de 0.'
else if (ajuste.tipo !== 'ajuste' && cantidad < 0) errores.cantidad = 'Para descontar usa "Ajuste".'
if (ajuste.tipo === 'ajuste' && !ajuste.motivo.trim()) errores.motivo = 'Explica el motivo.'

// ProductoEditor.vue: las mismas reglas con otros textos
if (!Number.isInteger(cantidad) || cantidad === 0) errores.cantidad = 'Escribe una cantidad entera distinta de 0.'
else if (ajuste.tipo !== 'ajuste' && cantidad < 0) errores.cantidad = 'Para descontar unidades usa el tipo "Ajuste".'
if (ajuste.tipo === 'ajuste' && !ajuste.motivo.trim()) errores.motivo = 'Explica el motivo del ajuste.'
```

**Después:**

```js
// utils/inventario.js
export function validarAjusteStock({ tipo, cantidad, motivo }) {
  const errores = {}
  const unidades = Number(cantidad)
  const explicacion = String(motivo ?? '').trim()

  if (!Number.isInteger(unidades) || unidades === 0) {
    errores.cantidad = 'Escribe una cantidad entera distinta de 0.'
  } else if (tipo !== 'ajuste' && unidades < 0) {
    errores.cantidad = 'Para descontar unidades usa el tipo "Ajuste".'
  }
  if (tipo === 'ajuste' && !explicacion) {
    errores.motivo = 'Explica el motivo del ajuste.'
  }

  return { errores, datos: { tipo, cantidad: unidades, motivo: explicacion || null } }
}

// composables/useAjusteStock.js: estado del formulario, envío y errores, una sola vez
export function useAjusteStock({ alGuardar, alFallar }) {
  const ajuste = reactive({ varianteId: null, tipo: 'entrada', cantidad: 1, motivo: '' })
  const erroresAjuste = ref({})
  // abrirAjuste(variante) ...
  async function guardarAjuste() {
    const { errores, datos } = validarAjusteStock(ajuste)
    erroresAjuste.value = errores
    if (Object.keys(errores).length) return
    try {
      const respuesta = await productoService.ajustarStock(ajuste.varianteId, datos)
      ajuste.varianteId = null
      await alGuardar(respuesta)
    } catch (e) {
      erroresAjuste.value = e.errores ?? {}
      if (!Object.keys(erroresAjuste.value).length) alFallar(e.message)
    }
  }
  return { ajuste, erroresAjuste, abrirAjuste, guardarAjuste }
}

// InventarioAdmin.vue: cada vista solo decide qué aviso mostrar y cómo recargar
const { ajuste, erroresAjuste, abrirAjuste: abrirFormularioAjuste, guardarAjuste } = useAjusteStock({
  alGuardar: (respuesta) => {
    aviso.value = { tipo: 'exito', texto: `${respuesta.data.sku}: stock actualizado a ${respuesta.data.stock}.` }
    cargar()
  },
  alFallar: (mensaje) => { aviso.value = { tipo: 'error', texto: mensaje } },
})
```

**Técnica de refactorización:**

- Extracción de función pura (`validarAjusteStock`).
- Extracción de composable (`useAjusteStock`) con funciones de retorno para lo que cambia entre vistas.
- Unificación de mensajes.

**Principios y buenas prácticas:**

- **DRY.**
- **SRP:** la función valida y la vista muestra y envía.
- Consistencia de la interfaz para el usuario.
- Función pura, fácil de probar.

**Justificación técnica.** Las reglas son las mismas que aplica el backend en `Variante`. Tenerlas una sola vez en el frontend evita que una pantalla acepte lo que la otra rechaza. `InventarioAdmin.test.js` comprueba las mismas reglas antes y después, y `utilidades.test.js` prueba la función y el composable nuevos. Además se revisaron las dos pantallas en el navegador con el compilado final: las dos muestran ahora los mismos mensajes.

**Beneficios obtenidos:**

- Las dos funciones `guardarAjuste` de complejidad **11** pasaron a una sola de complejidad **5**, en el composable.
- Desapareció el bloque repetido entre las dos vistas: la duplicidad de JavaScript bajó a 0,19 %.
- Un solo texto para cada error.
- `InventarioAdmin.vue` pasó de 253 a 216 líneas.

---

## Ajustes de estándares

| Cambio | Estándar | Archivos |
|---|---|---|
| El docblock del archivo va inmediatamente después de `<?php` y antes de `declare(strict_types=1)` | PSR-12 §3 (encabezado del archivo) | `backend/src/Config/routes.php`, `backend/bin/optimizar_imagenes.php` |
| `Paginacion.vue` pasa a `PaginacionListado.vue`: los componentes deben tener nombres de dos o más palabras para no chocar con elementos HTML | Guía de estilo de Vue (`vue/multi-word-component-names`, esencial) | El componente y las 6 vistas que lo usan |
| Orden de atributos en las plantillas (`id` antes de `class`, atributos antes de eventos) | Guía de estilo de Vue (`vue/attributes-order`) | `HomeView`, `CarritoFlotante`, `GraficaVentas` |

**Resultado:** 0 errores de PSR-12, 0 errores de ESLint y 12 advertencias menos de estilo de Vue.
