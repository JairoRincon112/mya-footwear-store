<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Config;
use App\Core\Correo;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Jwt;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\RecuperacionPassword;
use App\Models\Usuario;

/**
 * Inicio de sesión, registro de clientes y datos del usuario actual.
 */
final class AuthController extends Controller
{
    /** Nombre de cada rol para los mensajes al usuario. */
    private const NOMBRE_ROL = ['admin' => 'administrador', 'vendedor' => 'vendedor', 'cliente' => 'cliente'];

    /** POST /api/auth/login { email, password, rol? } */
    public function login(Request $request): void
    {
        $datos = Validator::validar($request->cuerpo(), [
            'email' => 'requerido|email|max:150',
            'password' => 'requerido|texto|max:72',
            // Opcional: rol que eligió la persona en el login ("Ingresar como...")
            'rol' => 'en:' . implode(',', Usuario::ROLES),
        ]);

        $usuarios = new Usuario();
        $usuario = $usuarios->buscarParaLogin($datos['email']);

        // Si el correo no existe se verifica contra un hash ficticio: así la respuesta
        // tarda lo mismo y no revela qué correos están registrados.
        $hash = $usuario['password_hash'] ?? '$2y$10$hfYFiMdv84i6.mIaJl392.ULdG38nYoORI9XkFU3x3SlrT/AWg39q';
        $passwordCorrecta = password_verify($datos['password'], $hash);

        if ($usuario === null || !$passwordCorrecta) {
            throw HttpException::noAutenticado('Correo o contraseña incorrectos.');
        }
        if (!$usuario['activo']) {
            throw HttpException::prohibido('Tu cuenta está desactivada. Comunícate con la tienda.');
        }

        // Si eligió con qué rol entrar, la cuenta debe tener ese rol: no se entrega token.
        // (Solo se revisa con la contraseña ya correcta, para no revelar el rol de nadie.)
        $rolElegido = $datos['rol'] ?? null;
        if ($rolElegido !== null && $rolElegido !== $usuario['rol']) {
            throw HttpException::prohibido(
                'Esta cuenta no es de ' . self::NOMBRE_ROL[$rolElegido] . '. Es una cuenta de '
                . self::NOMBRE_ROL[$usuario['rol']] . ': elige esa opción en "Ingresar como".'
            );
        }

        if (password_needs_rehash($usuario['password_hash'], PASSWORD_DEFAULT)) {
            $usuarios->actualizarHash($usuario['id'], $datos['password']);
        }

        unset($usuario['password_hash']);
        Response::ok($this->sesion($usuario), 'Bienvenido, ' . $usuario['nombre'] . '.');
    }

    /** POST /api/auth/registro — el registro público solo crea clientes. */
    public function registro(Request $request): void
    {
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

        Response::creado($this->sesion($usuarios->buscarPorId($id)), 'Tu cuenta fue creada.');
    }

    /** GET /api/auth/yo — datos del usuario dueño del token. */
    public function yo(Request $request): void
    {
        Response::ok($request->usuario);
    }

    /** PUT /api/auth/perfil — el usuario edita su nombre, correo y teléfono. */
    public function actualizarPerfil(Request $request): void
    {
        $datos = Validator::validar($request->cuerpo(), [
            'nombre' => 'requerido|texto|min:2|max:120',
            'email' => 'requerido|email|max:150',
            'telefono' => 'telefono',
        ]);

        $id = $this->usuarioId($request);
        $usuarios = new Usuario();
        if ($usuarios->existeEmail($datos['email'], $id)) {
            throw HttpException::validacion(['email' => 'Ya existe una cuenta con este correo.']);
        }

        $usuarios->actualizarPerfil($id, $datos['nombre'], $datos['email'], $datos['telefono'] ?? null);
        Response::ok($usuarios->buscarPorId($id), 'Tus datos fueron actualizados.');
    }

    /** PUT /api/auth/password — cambia la contraseña verificando primero la actual. */
    public function cambiarPassword(Request $request): void
    {
        $datos = Validator::validar($request->cuerpo(), [
            'password_actual' => 'requerido|texto|max:72',
            'password_nueva' => 'requerido|texto|min:8|max:72',
        ]);

        $id = $this->usuarioId($request);
        $usuarios = new Usuario();
        if (!password_verify($datos['password_actual'], (string) $usuarios->hashDe($id))) {
            throw HttpException::validacion(['password_actual' => 'La contraseña actual no es correcta.']);
        }
        if ($datos['password_actual'] === $datos['password_nueva']) {
            throw HttpException::validacion(['password_nueva' => 'La nueva contraseña debe ser diferente a la actual.']);
        }

        $usuarios->actualizarHash($id, $datos['password_nueva']);
        (new RecuperacionPassword())->invalidarTodos($id);
        Response::ok(null, 'Tu contraseña fue cambiada.');
    }

    /**
     * POST /api/auth/recuperar { email }
     * Siempre responde lo mismo, exista o no el correo, para no revelar qué cuentas existen.
     */
    public function solicitarRecuperacion(Request $request): void
    {
        $datos = Validator::validar($request->cuerpo(), ['email' => 'requerido|email|max:150']);
        $respuesta = 'Si el correo está registrado, te enviamos un enlace para crear una nueva contraseña. Revisa tu bandeja de entrada.';

        $usuario = (new Usuario())->buscarPorEmailActivo($datos['email']);
        $recuperaciones = new RecuperacionPassword();

        if ($usuario !== null && $recuperaciones->solicitudesRecientes($usuario['id']) < RecuperacionPassword::MAXIMO_POR_HORA) {
            $token = $recuperaciones->crear($usuario['id']);
            $enlace = rtrim(Config::get('FRONTEND_URL', 'http://localhost/mya'), '/') . '/restablecer?token=' . $token;
            $minutos = RecuperacionPassword::MINUTOS_VIGENCIA;

            Correo::enviar(
                $usuario['email'],
                'Recupera tu contraseña - MyA FOOTWEAR STORE',
                "Hola {$usuario['nombre']},\n\n"
                . "Recibimos una solicitud para cambiar la contraseña de tu cuenta.\n"
                . "Abre este enlace para crear una nueva (vence en $minutos minutos y solo sirve una vez):\n\n"
                . "$enlace\n\n"
                . "Si no fuiste tú, ignora este correo: tu contraseña no cambiará.\n\n"
                . "MyA FOOTWEAR STORE"
            );
        }

        Response::ok(null, $respuesta);
    }

    /** POST /api/auth/restablecer { token, password } */
    public function restablecerPassword(Request $request): void
    {
        $datos = Validator::validar($request->cuerpo(), [
            'token' => 'requerido|texto|max:128',
            'password' => 'requerido|texto|min:8|max:72',
        ]);

        $recuperaciones = new RecuperacionPassword();
        $usuarioId = $recuperaciones->usuarioDelToken($datos['token']);
        if ($usuarioId === null) {
            throw HttpException::validacion(
                ['token' => 'El enlace no es válido o ya venció. Solicita uno nuevo.'],
                'No se pudo cambiar la contraseña.'
            );
        }

        Database::transaccion(function () use ($recuperaciones, $usuarioId, $datos): void {
            (new Usuario())->actualizarHash($usuarioId, $datos['password']);
            $recuperaciones->invalidarTodos($usuarioId);
        });

        Response::ok(null, 'Tu contraseña fue cambiada. Ya puedes iniciar sesión.');
    }

    /** @param array<string, mixed> $usuario */
    private function sesion(array $usuario): array
    {
        return [
            'token' => Jwt::generar(['sub' => $usuario['id'], 'rol' => $usuario['rol']]),
            'expira_en' => Jwt::duracion(),
            'usuario' => $usuario,
        ];
    }
}
