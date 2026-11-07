<?php

declare(strict_types=1);

/**
 * Rutas de la API: método + URL -> controlador, con sus middlewares.
 * Toda la API cuelga de /api y responde JSON.
 */

use App\Controllers\AuthController;
use App\Controllers\CatalogoController;
use App\Controllers\ContactoController;
use App\Controllers\CuponController;
use App\Controllers\InventarioController;
use App\Controllers\PagoController;
use App\Controllers\PedidoController;
use App\Controllers\ProductoController;
use App\Controllers\ReporteController;
use App\Controllers\UsuarioController;
use App\Controllers\VarianteController;
use App\Core\Router;
use App\Middleware\AuthMiddleware;

return static function (Router $router): void {
    $soloAdmin = [AuthMiddleware::roles('admin')];
    $personal = [AuthMiddleware::roles('admin', 'vendedor')];
    $conSesion = [AuthMiddleware::autenticado()];
    $sesionOpcional = [AuthMiddleware::opcional()];

    $router->get('/api/salud', [CatalogoController::class, 'salud']);

    // Autenticación
    $router->post('/api/auth/login', [AuthController::class, 'login']);
    $router->post('/api/auth/registro', [AuthController::class, 'registro']);
    $router->get('/api/auth/yo', [AuthController::class, 'yo'], $conSesion);

    // Catálogo (público)
    $router->get('/api/marcas', [CatalogoController::class, 'marcas']);
    $router->get('/api/tallas', [CatalogoController::class, 'tallas']);
    $router->get('/api/colores', [CatalogoController::class, 'colores']);
    $router->get('/api/productos', [ProductoController::class, 'index'], $sesionOpcional);
    $router->get('/api/productos/{producto}', [ProductoController::class, 'mostrar'], $sesionOpcional);

    // Administración de productos (admin)
    $router->post('/api/productos', [ProductoController::class, 'crear'], $soloAdmin);
    $router->put('/api/productos/{id}', [ProductoController::class, 'actualizar'], $soloAdmin);
    $router->delete('/api/productos/{id}', [ProductoController::class, 'eliminar'], $soloAdmin);
    $router->post('/api/productos/{id}/imagenes', [ProductoController::class, 'subirImagen'], $soloAdmin);
    $router->delete('/api/imagenes/{id}', [ProductoController::class, 'eliminarImagen'], $soloAdmin);

    // Variantes e inventario
    $router->post('/api/productos/{id}/variantes', [VarianteController::class, 'crear'], $soloAdmin);
    $router->put('/api/variantes/{id}', [VarianteController::class, 'actualizar'], $soloAdmin);
    $router->patch('/api/variantes/{id}/stock', [VarianteController::class, 'ajustarStock'], $personal);

    // Contacto: el público escribe, el personal lee
    $router->post('/api/contacto', [ContactoController::class, 'crear']);
    $router->get('/api/contacto', [ContactoController::class, 'index'], $personal);
    $router->patch('/api/contacto/{id}/leido', [ContactoController::class, 'marcarLeido'], $personal);

    // ---------------- Módulo 2: pedidos e inventario ----------------
    $soloCliente = [AuthMiddleware::roles('cliente')];
    // /api/pedidos/mios va antes de /api/pedidos/{id} para que "mios" no se tome como un id.
    $router->get('/api/pedidos/mios', [PedidoController::class, 'mios'], $soloCliente);
    $router->post('/api/pedidos', [PedidoController::class, 'crear'], $soloCliente);
    $router->get('/api/pedidos', [PedidoController::class, 'index'], $personal);
    $router->get('/api/pedidos/{id}', [PedidoController::class, 'mostrar'], $conSesion);
    $router->patch('/api/pedidos/{id}/estado', [PedidoController::class, 'cambiarEstado'], $personal);
    $router->patch('/api/pedidos/{id}/cancelar', [PedidoController::class, 'cancelarPropio'], $soloCliente);

    $router->get('/api/inventario', [InventarioController::class, 'index'], $personal);
    $router->get('/api/variantes/{id}/movimientos', [InventarioController::class, 'movimientos'], $personal);

    // ---------------- Módulo 3: usuarios y reportes ----------------
    $router->get('/api/usuarios', [UsuarioController::class, 'index'], $soloAdmin);
    $router->post('/api/usuarios', [UsuarioController::class, 'crear'], $soloAdmin);
    $router->put('/api/usuarios/{id}', [UsuarioController::class, 'actualizar'], $soloAdmin);
    $router->patch('/api/usuarios/{id}/activo', [UsuarioController::class, 'cambiarActivo'], $soloAdmin);

    $router->get('/api/reportes/resumen', [ReporteController::class, 'resumen'], $personal);

    // ---------------- Mejoras: perfil, recuperación, cupones ----------------
    $router->put('/api/auth/perfil', [AuthController::class, 'actualizarPerfil'], $conSesion);
    $router->put('/api/auth/password', [AuthController::class, 'cambiarPassword'], $conSesion);
    $router->post('/api/auth/recuperar', [AuthController::class, 'solicitarRecuperacion']);
    $router->post('/api/auth/restablecer', [AuthController::class, 'restablecerPassword']);

    $router->post('/api/pedidos/cotizar', [PedidoController::class, 'cotizar'], $soloCliente);

    $router->get('/api/cupones', [CuponController::class, 'index'], $soloAdmin);
    $router->post('/api/cupones', [CuponController::class, 'crear'], $soloAdmin);
    $router->put('/api/cupones/{id}', [CuponController::class, 'actualizar'], $soloAdmin);
    $router->patch('/api/cupones/{id}/activo', [CuponController::class, 'cambiarActivo'], $soloAdmin);

    // ---------------- Pagos en línea (Wompi) ----------------
    $router->post('/api/pedidos/{id}/pago', [PagoController::class, 'iniciar'], $soloCliente);
    $router->get('/api/pagos/verificar', [PagoController::class, 'verificar'], $conSesion);
    $router->post('/api/pagos/wompi/eventos', [PagoController::class, 'eventos']); // webhook: lo valida la firma
};
