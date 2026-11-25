<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Usuario;

/**
 * Administración de usuarios (solo admin): crear vendedores, administradores y clientes,
 * editarlos y activarlos o desactivarlos. Los usuarios no se borran.
 */
final class UsuarioController extends Controller
{
    /** GET /api/usuarios?q=&rol=&activo=&pagina= */
    public function index(Request $request): void
    {
        $pagina = max(1, (int) ($request->query('pagina') ?? 1));
        $porPagina = min(50, max(1, (int) ($request->query('por_pagina') ?? 15)));
        $rol = $request->query('rol');
        $activo = $request->query('activo');

        $resultado = (new Usuario())->listar([
            'q' => mb_substr($request->query('q') ?? '', 0, 100) ?: null,
            'rol' => in_array($rol, Usuario::ROLES, true) ? $rol : null,
            'activo' => in_array($activo, ['0', '1'], true) ? $activo === '1' : null,
        ], $pagina, $porPagina);

        Response::ok($resultado + ['pagina' => $pagina, 'por_pagina' => $porPagina, 'paginas' => (int) ceil($resultado['total'] / $porPagina)]);
    }

    /** POST /api/usuarios */
    public function crear(Request $request): void
    {
        $datos = Validator::validar($request->cuerpo(), Usuario::REGLAS_CUENTA + self::reglaRol());

        $usuarios = new Usuario();
        $id = $usuarios->crear($datos, $datos['rol']);
        Response::creado($usuarios->buscarPorId($id), 'Usuario creado.');
    }

    /** PUT /api/usuarios/{id} — la contraseña es opcional (solo si se quiere cambiar). */
    public function actualizar(Request $request): void
    {
        $id = $this->idDeRuta($request);
        $usuarios = new Usuario();
        $usuario = $usuarios->buscarPorId($id);
        if ($usuario === null) {
            throw HttpException::noEncontrado('El usuario no existe.');
        }

        // Al editar, la contraseña es opcional: solo se envía si se quiere cambiar.
        $reglas = array_replace(Usuario::REGLAS_CUENTA, ['password' => Usuario::REGLA_PASSWORD]) + self::reglaRol();
        $datos = Validator::validar($request->cuerpo(), $reglas);

        // Evita que el administrador se quite a sí mismo el acceso al panel.
        if ($id === $this->usuarioId($request) && $datos['rol'] !== 'admin') {
            throw HttpException::validacion(['rol' => 'No puedes quitarte el rol de administrador.']);
        }
        $usuarios->asegurarEmailDisponible($datos['email'], $id);

        $usuarios->actualizar($id, [
            'nombre' => $datos['nombre'],
            'email' => $datos['email'],
            'rol' => $datos['rol'],
            'telefono' => $datos['telefono'] ?? null,
            'password' => $datos['password'] ?? null,
        ]);

        Response::ok($usuarios->buscarPorId($id), 'Usuario actualizado.');
    }

    /** PATCH /api/usuarios/{id}/activo { "activo": false } */
    public function cambiarActivo(Request $request): void
    {
        $id = $this->idDeRuta($request);
        $usuarios = new Usuario();
        if ($usuarios->buscarPorId($id) === null) {
            throw HttpException::noEncontrado('El usuario no existe.');
        }

        $datos = Validator::validar($request->cuerpo(), ['activo' => 'requerido|booleano']);
        if ($id === $this->usuarioId($request) && !$datos['activo']) {
            throw HttpException::validacion(['activo' => 'No puedes desactivar tu propia cuenta.']);
        }

        $usuarios->cambiarActivo($id, $datos['activo']);
        Response::ok($usuarios->buscarPorId($id), $datos['activo'] ? 'Usuario activado.' : 'Usuario desactivado. Ya no podrá iniciar sesión.');
    }

    /** @return array{rol: string} */
    private static function reglaRol(): array
    {
        return ['rol' => 'requerido|en:' . implode(',', Usuario::ROLES)];
    }
}
