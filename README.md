# MyA FOOTWEAR STORE

Tienda en línea de calzado (Adidas, Nike y Puma) con catálogo, variantes por talla y color, carrito y panel de administración.

| Capa | Tecnología |
|---|---|
| Frontend (Vista) | Vue 3 + Vite, Vue Router, Pinia, Axios |
| Backend (API REST JSON) | PHP 8.3 puro, sin frameworks, arquitectura MVC (Composer solo para el autoload PSR-4) |
| Base de datos | MariaDB / MySQL (XAMPP) |
| Autenticación | JWT HS256 implementado a mano + roles `admin`, `vendedor`, `cliente` |

## Estructura

```
public/                   Carpeta que publica Apache (XAMPP en local y el hosting en internet)
  index.php               Envía la raíz del dominio a /mya/
  mya/                    App de Vue ya compilada (la genera "npm run build"; se versiona)
  mya-api/index.php       Router frontal: toda petición a la API entra aquí
  mya-api/.htaccess       Redirige las rutas a index.php
  mya-api/img/productos/  Imágenes del catálogo inicial
  mya-api/uploads/        Imágenes subidas desde el panel (no se versionan)
backend/
  src/Config/             Config.php (lee .env o variables de entorno) y routes.php (tabla de rutas)
  src/Core/               Router, Request, Response, Database, Validator, Jwt, HttpException, Correo, Imagen
  bin/                    Scripts de consola (optimizar imágenes)
  storage/correos/        Correos guardados en modo MAIL_DRIVER=log (no se versionan)
  src/Middleware/         CorsMiddleware, AuthMiddleware (sesión y roles)
  src/Models/             Acceso a datos con PDO y consultas preparadas
  src/Controllers/        Reciben la petición, validan y llaman al modelo
  .env.example            Plantilla de configuración (copiar como .env)
frontend/
  src/views/              Pantallas (Home, Catálogo, Producto, Contacto, Login, Panel)
  src/components/         Header, Footer, Tarjeta de producto, Carrito
  src/stores/             Pinia: auth, carrito, catálogo
  src/services/           Llamadas a la API con Axios
  src/router/             Rutas y protección por rol
database/
  schema.sql              Tablas, llaves primarias y foráneas, índices
  seed.sql                Datos de prueba (45 productos reales, 584 variantes, 13 pedidos, ofertas y cupones)
  migraciones/            Cambios para actualizar una base existente sin perder datos
```

### Flujo MVC de una petición

```
Vue (Vista) --Axios--> public/mya-api/index.php --> CorsMiddleware --> Router
   --> AuthMiddleware (token y rol) --> Controlador (valida) --> Modelo (PDO) --> MySQL
   <-- JSON { ok, data, mensaje } <-- Response
```

## Instalación en XAMPP (Windows)

Requisitos: XAMPP con Apache y MySQL encendidos, [Node.js LTS](https://nodejs.org) y [Composer](https://getcomposer.org).
La extensión **GD** de PHP debe estar activa (en `C:\xampp\php\php.ini`, la línea `extension=gd` sin `;` al inicio; luego reinicia Apache).

```powershell
# 1. Base de datos
cd "C:\Users\Usuario\Desktop\MyA FOOTWEAR STORE"
cmd /c "C:\xampp\mysql\bin\mysql.exe -u root < database\schema.sql"
cmd /c "C:\xampp\mysql\bin\mysql.exe -u root < database\seed.sql"

# 2. Backend
cd backend
copy .env.example .env      # luego edita .env: pon una JWT_SECRET larga y aleatoria
composer dump-autoload --optimize

# 3. Publicar la tienda y la API en Apache (enlaces de carpeta dentro de htdocs)
New-Item -ItemType Junction -Path C:\xampp\htdocs\mya-api -Target "$PWD\..\public\mya-api"
New-Item -ItemType Junction -Path C:\xampp\htdocs\mya -Target "$PWD\..\public\mya"

# 4. Frontend
cd ..\frontend
npm install
copy .env.example .env   # y pon tu clave de Web3Forms (ver "Formulario de contacto")
```

Comprueba la API en <http://localhost/mya-api/api/salud>.

## Ejecutar

**Modo desarrollo** (recarga automática al editar):

```powershell
cd frontend
npm run dev
```

Abre <http://localhost:5173>.

**Versión compilada servida por Apache**:

```powershell
cd frontend
npm run build      # deja la app compilada en public/mya
```

Abre <http://localhost/mya/>. Antes de subir cambios del frontend a GitHub hay que compilar,
porque el hosting publica `public/mya` tal como está en el repositorio.

## Usuarios de prueba

| Rol | Correo | Contraseña |
|---|---|---|
| Administrador | admin@myafootwear.com | Admin123* |
| Vendedor | vendedor@myafootwear.com | Vendedor123* |
| Vendedor | santiago.herrera@myafootwear.com | Vendedor123* |
| Cliente | cliente@example.com | Cliente123* |
| Cliente | andres.perez@example.com | Cliente123* |

Son contraseñas de demostración: cámbialas antes de publicar el sitio. Chrome puede mostrar un aviso de "contraseña filtrada" porque son débiles.

## Permisos por rol

| Acción | Invitado | Cliente | Vendedor | Admin |
|---|:-:|:-:|:-:|:-:|
| Ver catálogo, carrito, contacto | ✔ | ✔ | ✔ | ✔ |
| Comprar, ver y cancelar (si está pendiente) sus pedidos | | ✔ | | |
| Panel: pedidos, inventario, mensajes, reportes | | | ✔ | ✔ |
| Ver productos y ajustar stock | | | ✔ | ✔ |
| Crear/editar productos, imágenes y variantes | | | | ✔ |
| Gestionar usuarios | | | | ✔ |

## Cómo funciona una compra

1. El cliente agrega variantes (talla + color) al carrito, que vive en el navegador (Pinia + localStorage).
2. En **Finalizar compra** escribe los datos de envío; el frontend envía solo `variante_id` y `cantidad`.
3. El backend, en **una transacción**, bloquea cada variante (`SELECT … FOR UPDATE`), verifica el stock,
   toma el precio de la base de datos, guarda el pedido y su detalle, descuenta el stock y registra
   un movimiento de inventario `venta`. Si falta stock en cualquier línea, no se guarda nada (409).
4. El pedido queda **pendiente**. El vendedor lo avanza: pendiente → pagado → enviado → entregado.
5. Se puede **cancelar** solo antes de enviarse (el cliente, mientras esté pendiente). Al cancelar, el stock
   vuelve al inventario como movimiento `devolucion`.

El stock de cada variante siempre es igual a la suma de sus movimientos de inventario.

## Endpoints

### Módulo 1: autenticación y productos

| Método | Ruta | Acceso |
|---|---|---|
| GET | `/api/salud` | Público |
| POST | `/api/auth/login` (`email`, `password`, `rol` opcional: si se envía y la cuenta no tiene ese rol responde 403 y no entrega token) | Público |
| POST | `/api/auth/registro` (crea siempre un cliente) | Público |
| GET | `/api/auth/yo` | Con sesión |
| GET | `/api/marcas`, `/api/tallas`, `/api/colores` | Público |
| GET | `/api/productos?marca=&q=&talla=&color=&orden=&pagina=&por_pagina=&estado=` | Público (`estado` solo personal) |
| GET | `/api/productos/{id o slug}` | Público |
| POST | `/api/productos` | Admin |
| PUT | `/api/productos/{id}` | Admin |
| DELETE | `/api/productos/{id}` (desactiva, no borra) | Admin |
| POST | `/api/productos/{id}/imagenes` (multipart: `imagen`, `color_id`) | Admin |
| DELETE | `/api/imagenes/{id}` | Admin |
| POST | `/api/productos/{id}/variantes` (`color_id`, `talla_ids[]`, `stock`) | Admin |
| PUT | `/api/variantes/{id}` (`sku`, `activo`) | Admin |
| PATCH | `/api/variantes/{id}/stock` (`tipo`, `cantidad`, `motivo`) | Admin, vendedor |
| POST | `/api/contacto` | Público |

### Módulo 2: pedidos e inventario

| Método | Ruta | Acceso |
|---|---|---|
| POST | `/api/pedidos` (`nombre_contacto`, `telefono`, `direccion`, `ciudad`, `notas`, `items[{variante_id, cantidad}]`) | Cliente |
| GET | `/api/pedidos/mios` | Cliente |
| GET | `/api/pedidos/{id}` | Dueño del pedido o personal |
| PATCH | `/api/pedidos/{id}/cancelar` | Cliente (solo pendientes) |
| GET | `/api/pedidos?estado=&q=&desde=&hasta=&pagina=` | Admin, vendedor |
| PATCH | `/api/pedidos/{id}/estado` (`estado`) | Admin, vendedor |
| GET | `/api/inventario?q=&marca=&bajo_stock=1&pagina=` | Admin, vendedor |
| GET | `/api/variantes/{id}/movimientos` | Admin, vendedor |

### Módulo 3: usuarios, mensajes y reportes

| Método | Ruta | Acceso |
|---|---|---|
| GET | `/api/usuarios?q=&rol=&activo=&pagina=` | Admin |
| POST | `/api/usuarios` | Admin |
| PUT | `/api/usuarios/{id}` (contraseña opcional) | Admin |
| PATCH | `/api/usuarios/{id}/activo` | Admin |
| GET | `/api/contacto?leido=0\|1&pagina=` | Admin, vendedor |
| PATCH | `/api/contacto/{id}/leido` | Admin, vendedor |
| GET | `/api/reportes/resumen` | Admin, vendedor |

### Mejoras: perfil, recuperación de contraseña, ofertas y cupones

| Método | Ruta | Acceso |
|---|---|---|
| PUT | `/api/auth/perfil` (`nombre`, `email`, `telefono`) | Con sesión |
| PUT | `/api/auth/password` (`password_actual`, `password_nueva`) | Con sesión |
| POST | `/api/auth/recuperar` (`email`) — responde igual exista o no la cuenta | Público |
| POST | `/api/auth/restablecer` (`token`, `password`) | Público |
| POST | `/api/pedidos/cotizar` (`items`, `cupon`) — vista previa con ofertas y cupón, no guarda nada | Cliente |
| GET | `/api/productos?oferta=1&orden=descuento` | Público |
| GET | `/api/cupones` | Admin |
| POST | `/api/cupones` · PUT `/api/cupones/{id}` · PATCH `/api/cupones/{id}/activo` | Admin |

`POST /api/pedidos` acepta además `cupon`. Los productos aceptan `precio_oferta`, `oferta_inicio` y `oferta_fin`.

### Pagos en línea (Wompi)

| Método | Ruta | Acceso |
|---|---|---|
| POST | `/api/pedidos/{id}/pago` — datos firmados para abrir el widget (monto, referencia, firma) | Cliente dueño del pedido |
| GET | `/api/pagos/verificar?id=<transacción>` — consulta a Wompi y marca el pedido como pagado | Con sesión (dueño o personal) |
| POST | `/api/pagos/wompi/eventos` — webhook de Wompi; solo acepta eventos con firma válida | Público (firmado) |

`GET /api/pedidos/{id}` incluye `pago` con el último intento (`estado`, `metodo`, `referencia`).

Ejemplo con PowerShell:

```powershell
$login = Invoke-RestMethod -Method Post -Uri http://localhost/mya-api/api/auth/login `
  -ContentType 'application/json' -Body '{"email":"admin@myafootwear.com","password":"Admin123*"}'
$token = $login.data.token
Invoke-RestMethod -Uri "http://localhost/mya-api/api/productos?marca=nike&por_pagina=3" -Headers @{ Authorization = "Bearer $token" }
```

## Seguridad aplicada

- Contraseñas con `password_hash()` y `password_verify()`; mensaje de login idéntico exista o no el correo.
- JWT firmado con HMAC-SHA256; se rechazan tokens alterados, vencidos o con `alg: none`. El rol se relee de la base de datos en cada petición.
- Consultas preparadas con PDO (`EMULATE_PREPARES = false`); el orden y los filtros nunca se concatenan desde el usuario.
- Validación en el backend (`Validator`) y en el frontend (formularios).
- CORS limitado a los orígenes de `CORS_ORIGINS`.
- Subida de imágenes: se revisa el tipo real del archivo, nombre aleatorio, tamaño máximo y la carpeta `uploads/` no ejecuta PHP.
- `.env` fuera de la carpeta pública y fuera del repositorio.
- Pedidos: el precio lo pone el servidor, el stock se valida con bloqueo de fila, un cliente no puede ver ni cancelar pedidos ajenos (responde 404).
- Usuarios: un administrador no puede desactivarse ni quitarse su rol; un usuario desactivado pierde el acceso de inmediato aunque su token siga vigente.

## Ofertas y cupones

- **Oferta por producto:** precio rebajado con fecha de inicio y fin opcionales. El precio vigente se calcula
  en SQL, así que la oferta empieza y termina sola. El cliente ve el precio anterior tachado y el % de descuento.
- **Cupones:** porcentaje (1-90 %) o valor fijo, con compra mínima, límite de usos, vigencia y opción de un uso
  por cliente. Se validan en el servidor dentro de la misma transacción del pedido; si el pedido se cancela,
  el uso del cupón se devuelve.
- Cupones de prueba: `BIENVENIDA10` (10 %, uno por cliente), `MYA50MIL` ($50.000 desde $400.000),
  `BLACKFRIDAY` (programado) y `VERANO2025` (vencido).

## Recuperación de contraseña y correos

El cliente pide un enlace en **¿Olvidaste tu contraseña?**. El enlace caduca en 60 minutos, sirve una sola vez
y en la base de datos solo se guarda el hash SHA-256 del token. Máximo 3 solicitudes por hora por cuenta.

`MAIL_DRIVER` en `backend/.env` define cómo se envían los correos:

- `log` (por defecto): el correo se guarda como archivo `.eml` en `backend/storage/correos/`. Ábrelo con el Bloc de
  notas y copia el enlace. Sirve para desarrollar sin configurar nada.
- `mail`: envío real con el `sendmail` de XAMPP. Para usar Gmail:
  1. En tu cuenta de Google activa la verificación en 2 pasos y crea una **contraseña de aplicación**.
  2. En `C:\xampp\sendmail\sendmail.ini`:
     ```ini
     smtp_server=smtp.gmail.com
     smtp_port=587
     smtp_ssl=tls
     auth_username=tucorreo@gmail.com
     auth_password=la-contraseña-de-aplicación
     force_sender=tucorreo@gmail.com
     ```
  3. En `C:\xampp\php\php.ini`, sección `[mail function]`:
     `sendmail_path = "\"C:\xampp\sendmail\sendmail.exe\" -t"`
  4. En `backend/.env`: `MAIL_DRIVER=mail` y `MAIL_FROM=tucorreo@gmail.com`. Reinicia Apache.

## Formulario de contacto (Web3Forms)

Al enviar el formulario de `/contacto` pasan dos cosas:

1. **Se guarda en la base de datos** con `POST /api/contacto` (la API PHP valida los datos);
   el personal lo lee en *Panel → Mensajes*.
2. **Llega al correo de la tienda** con la API de [Web3Forms](https://web3forms.com)
   (`POST https://api.web3forms.com/submit`), desde `frontend/src/services/web3formsService.js`.

La clave va en `frontend/.env`, que no se sube al repositorio:

```env
VITE_WEB3FORMS_KEY=tu-clave-de-acceso
```

- La clave se crea gratis en <https://web3forms.com> escribiendo el correo donde se quieren
  recibir los mensajes; Web3Forms la envía a ese correo.
- Después de cambiar el `.env` hay que recompilar: `npm run build` (o reiniciar `npm run dev`).
- Web3Forms se llama desde el navegador porque **el plan gratuito no acepta peticiones desde
  un servidor** (eso requiere plan de pago y lista blanca de IP). Su clave está hecha para
  usarse en el navegador: solo sirve para enviar mensajes a tu correo, no para leerlos.
- Si la clave está vacía, el mensaje solo se guarda en la base de datos.
- Hay un campo oculto `botcheck` contra bots y el asunto del correo incluye el motivo elegido.

## Pagos con Wompi

El cliente paga su pedido pendiente con el widget oficial de [Wompi](https://wompi.com)
(tarjeta, PSE, Nequi o Bancolombia). La lógica está en `backend/src/Core/Wompi.php`,
`Models/Pago.php` y `Controllers/PagoController.php`.

**Configuración** en `backend/.env` (las llaves están en <https://comercios.wompi.co> → *Desarrolladores*):

```env
WOMPI_ENTORNO=sandbox            # sandbox (pruebas) o produccion
WOMPI_LLAVE_PUBLICA=pub_test_...
WOMPI_LLAVE_PRIVADA=prv_test_...
WOMPI_SECRETO_INTEGRIDAD=test_integrity_...
WOMPI_SECRETO_EVENTOS=test_events_...
```

No hay que recompilar el frontend: la llave pública la entrega la API. Si faltan llaves, la API responde
503 y el botón muestra "Los pagos en línea no están disponibles".

**Flujo**

1. En *Mis pedidos → pedido pendiente*, el cliente pulsa **Pagar con Wompi**.
2. `POST /api/pedidos/{id}/pago`: el servidor toma el total **de la base de datos**, crea una referencia
   única (`MYA-<pedido>-<aleatorio>`) en la tabla `pagos` y calcula la firma de integridad:
   `SHA-256(referencia + monto_en_centavos + "COP" + secreto_de_integridad)`. Así nadie puede cambiar el
   monto desde el navegador.
3. El navegador abre el widget de Wompi con esos datos y el cliente paga.
4. Al terminar, la página `/pago/resultado?id=<transacción>` llama a `GET /api/pagos/verificar`.
   El servidor consulta la transacción en Wompi **con la llave privada** y, si está `APPROVED` y el monto
   coincide con el firmado, pasa el pedido a **pagado**. Si el pago queda en proceso (PSE, Nequi), la página
   vuelve a consultar cada 4 segundos.
5. En producción Wompi también avisa por webhook a `POST /api/pagos/wompi/eventos`. El servidor verifica
   `signature.checksum = SHA-256(valores de signature.properties + timestamp + secreto_de_eventos)` y aplica
   el mismo resultado. Configura la URL de eventos en el panel de Wompi cuando la API esté publicada en
   internet (en `localhost` Wompi no puede llamarla; por eso existe el paso 4).

**Datos de prueba (sandbox, sin dinero real)**

| Medio | Aprobado | Rechazado |
|---|---|---|
| Tarjeta | `4242 4242 4242 4242` | `4111 1111 1111 1111` |
| Nequi | `3991111111` | `3992222222` |

Con tarjeta sirve cualquier fecha futura y cualquier CVC de 3 dígitos. En PSE se elige el banco de pruebas
que aprueba o el que rechaza.

**Protecciones**: el monto lo fija el servidor; un pago con monto distinto al firmado queda en `error` y
nunca marca el pedido como pagado; el pago se procesa con bloqueo de fila (`FOR UPDATE`) para que la
verificación y el webhook no lo apliquen dos veces; un pago aprobado no retrocede por un aviso viejo; solo
el dueño del pedido puede iniciar o verificar su pago; la llave privada y los secretos nunca salen del servidor.

## Imágenes

Las imágenes que sube el administrador se reducen a máximo 1200 px, se recomprimen, se corrige la rotación
de las fotos de celular y se les quitan los metadatos (GPS, cámara), todo con GD. Para optimizar las que ya
estaban en el catálogo:

```powershell
cd backend
php bin/optimizar_imagenes.php
```

Solo reemplaza una imagen si la versión nueva pesa al menos un 5 % menos.

## Publicar para pruebas (túnel de Cloudflare)

Para que otras personas (por ejemplo, el profesor) prueben la tienda desde internet sin contratar
hosting, se usa un túnel gratuito de Cloudflare desde el XAMPP de este computador:

1. Doble clic en `herramientas/iniciar-servidor-publico.bat`.
2. Muestra una dirección del tipo `https://algo-aleatorio.trycloudflare.com/mya/` (también queda en
   `herramientas/direccion-publica.txt`). Esa es la dirección para compartir.
3. Para cerrarlo: `herramientas/detener-servidor-publico.bat`.

Requisito: `cloudflared.exe` en `%USERPROFILE%\cloudflared\` (descarga oficial:
<https://github.com/cloudflare/cloudflared/releases>, archivo `cloudflared-windows-amd64.exe`).

Seguridad: el túnel apunta al puerto `127.0.0.1:8081` de Apache, que está configurado en
`C:\xampp\apache\conf\extra\httpd-vhosts.conf` para servir **solo** `/mya` y `/mya-api`.
phpMyAdmin, el panel de XAMPP y los archivos ocultos (`.env`, `.htaccess`) responden 403 por ese
puerto. El frontend llama a la API con la ruta relativa `/mya-api` (`frontend/.env.production`),
así que el mismo compilado sirve en local, en el túnel o en un hosting.

Limitaciones: la dirección cambia cada vez que se inicia, solo funciona con el computador encendido
y no tiene garantía de disponibilidad. Para la entrega definitiva conviene un hosting con PHP 8.3,
MySQL, SSL y `mod_rewrite`. Con el túnel abierto, el webhook de Wompi se puede configurar en
`https://<dirección>/mya-api/api/pagos/wompi/eventos`.

## Publicar en Kyra Cloud (hosting)

Tienda publicada: <https://mya-footwear-store.kyracloud.com> (redirige a `/mya/`). El subdominio se
elige en la pestaña **Nginx / HTTPS** → *Subdominio gratis* → *Cambiar*.

Hay dos servicios en Kyra Cloud:

| Servicio | Tipo | Qué es |
|---|---|---|
| `mya-footwear-store` | Hosting Web PHP | Apache, PHP 8.4 con GD y pdo_mysql, Composer, sin Node. Publica `/var/www/html/public` |
| `mya-db` | MariaDB 11 | Base `mya_footwear`, usuario `mya`. Host interno en la red privada: `mya-db-0969203a` |

La tienda queda en `https://<servidor>/mya/` y la API en `https://<servidor>/mya-api/`.

**Configuración: `backend/.env` en el servidor.** Tiene las mismas claves de `backend/.env.example`
con los datos del hosting (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `JWT_SECRET`, `CORS_ORIGINS`,
`FRONTEND_URL`, `WOMPI_*`…). Para cambiar algo (por ejemplo poner las llaves de Wompi):
**Archivos** → `backend` → `.env` → editar y guardar. Se aplica en la siguiente petición, sin reiniciar.
El archivo no está en git, los zips no lo tocan y no se puede abrir desde internet (está fuera de
`public/`).

> **No uses la pestaña *Variables de Entorno* de Kyra.** En el Hosting Web el panel no puede leer las
> variables (su API responde 404 al pedirlas), así que siempre se ve vacío, y **Guardar reemplaza
> todas** por lo que haya en pantalla. La API ya no depende de ellas: `Config.php` lee primero
> `backend/.env` y solo usa `getenv()` para lo que no esté en el archivo.

### Actualizar la tienda publicada

La vinculación con GitHub del panel no funciona en el Hosting Web (muestra "vinculado" pero no lo
guarda), así que se actualiza subiendo un .zip:

1. Haz commit y `git push` de tus cambios.
2. Doble clic en `herramientas/empaquetar-despliegue.bat`. Compila el frontend y crea
   `herramientas/mya-footwear-store.zip` con el último commit. Si la compilación cambió archivos,
   te pide hacer commit primero.
3. En Kyra: servidor `mya-footwear-store` → **Archivos** → en la raíz, **Subir Archivo** → el zip →
   **Confirmar subida**.
4. Pestaña **Terminal**:

   ```bash
   cd /var/www/html && unzip -oq mya-footwear-store.zip && rm mya-footwear-store.zip
   cd backend && composer install --no-dev --optimize-autoloader --no-interaction
   ```

Esto no toca la base de datos, las variables de entorno ni las imágenes subidas desde el panel.
Si cambias la estructura de la base, aplica la migración nueva desde la Terminal.

### Cargar o reiniciar la base de datos en el hosting

En la **Terminal** de `mya-footwear-store` (usa la contraseña de `backend/.env`, no hay que
escribirla). **Borra y vuelve a crear todas las tablas** con los datos de prueba:

```bash
cd /var/www/html && php -r 'require "backend/vendor/autoload.php";use App\Config\Config as C;C::cargar("backend/.env");$p=new PDO("mysql:host=".C::get("DB_HOST").";port=3306;charset=utf8mb4",C::get("DB_USER"),C::get("DB_PASS"),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$p->exec("SET time_zone=\x27-05:00\x27");foreach(["database/schema.sql","database/seed.sql"] as $f){$s=$p->query(file_get_contents($f));do{}while($s->nextRowset());echo "$f OK\n";}'
```

El `SET time_zone` hace que las fechas del seed queden en hora de Colombia.

## Actualizar una base de datos existente

Si ya tenías la base creada con una versión anterior y no quieres perder datos, aplica las migraciones en orden:

```powershell
cmd /c "C:\xampp\mysql\bin\mysql.exe -u root < database\migraciones\002_ofertas_cupones_recuperacion.sql"
cmd /c "C:\xampp\mysql\bin\mysql.exe -u root < database\migraciones\003_pagos_wompi.sql"
```

## Restaurar los datos de prueba

Vuelve a ejecutar `schema.sql` y `seed.sql` (paso 1 de la instalación). El seed trae 13 pedidos con fechas
relativas al día en que se carga, así que la gráfica de reportes siempre muestra los últimos 30 días con datos.
