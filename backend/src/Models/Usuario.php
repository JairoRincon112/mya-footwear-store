<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Modelo de la tabla usuarios (administradores, vendedores y clientes).
 * La contraseña nunca se guarda en texto plano: se usa password_hash() (bcrypt).
 */
final class Usuario extends Model
{
    public const ROLES = ['admin', 'vendedor', 'cliente'];

    /** Columnas que se pueden devolver al frontend (nunca el hash). */
    private const COLUMNAS_PUBLICAS = 'id, nombre, email, telefono, rol, activo, creado_en';

    /** @return array<string, mixed>|null */
    public function buscarPorId(int $id): ?array
    {
        $usuario = $this->obtenerUno(
            'SELECT ' . self::COLUMNAS_PUBLICAS . ' FROM usuarios WHERE id = :id',
            ['id' => $id]
        );
        return $usuario ? self::normalizar($usuario) : null;
    }

    /**
     * Incluye el hash de la contraseña: solo para verificar el login.
     *
     * @return array<string, mixed>|null
     */
    public function buscarParaLogin(string $email): ?array
    {
        $usuario = $this->obtenerUno(
            'SELECT ' . self::COLUMNAS_PUBLICAS . ', password_hash FROM usuarios WHERE email = :email',
            ['email' => $email]
        );
        return $usuario ? self::normalizar($usuario) : null;
    }

    /** @return array<string, mixed>|null Usuario activo con ese correo (sin el hash) */
    public function buscarPorEmailActivo(string $email): ?array
    {
        $usuario = $this->obtenerUno(
            'SELECT ' . self::COLUMNAS_PUBLICAS . ' FROM usuarios WHERE email = :email AND activo = 1',
            ['email' => $email]
        );
        return $usuario ? self::normalizar($usuario) : null;
    }

    public function existeEmail(string $email, ?int $exceptoId = null): bool
    {
        return $this->obtenerUno(
            'SELECT 1 FROM usuarios WHERE email = :email AND id <> :excepto',
            ['email' => $email, 'excepto' => $exceptoId ?? 0]
        ) !== null;
    }

    /**
     * Lista paginada para el panel del administrador.
     *
     * @param array{q?: ?string, rol?: ?string, activo?: ?bool} $filtros
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function listar(array $filtros, int $pagina, int $porPagina): array
    {
        $condiciones = [];
        $parametros = [];

        if (!empty($filtros['q'])) {
            $condiciones[] = '(u.nombre LIKE :busqueda OR u.email LIKE :busqueda_email)';
            $parametros['busqueda'] = '%' . addcslashes($filtros['q'], '%_\\') . '%';
            $parametros['busqueda_email'] = $parametros['busqueda'];
        }
        if (!empty($filtros['rol'])) {
            $condiciones[] = 'u.rol = :rol';
            $parametros['rol'] = $filtros['rol'];
        }
        if (isset($filtros['activo'])) {
            $condiciones[] = 'u.activo = :activo';
            $parametros['activo'] = $filtros['activo'] ? 1 : 0;
        }

        $where = $condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones);
        $total = (int) $this->obtenerUno("SELECT COUNT(*) AS total FROM usuarios u $where", $parametros)['total'];

        $filas = $this->obtenerTodos(
            "SELECT u.id, u.nombre, u.email, u.telefono, u.rol, u.activo, u.creado_en,
                    (SELECT COUNT(*) FROM pedidos pe WHERE pe.cliente_id = u.id) AS total_pedidos
               FROM usuarios u
               $where
              ORDER BY FIELD(u.rol, 'admin', 'vendedor', 'cliente'), u.nombre
              LIMIT :limite OFFSET :desplazamiento",
            $parametros + ['limite' => $porPagina, 'desplazamiento' => ($pagina - 1) * $porPagina]
        );

        $items = array_map(static function (array $fila): array {
            $fila = self::normalizar($fila);
            $fila['total_pedidos'] = (int) $fila['total_pedidos'];
            return $fila;
        }, $filas);

        return ['items' => $items, 'total' => $total];
    }

    /** @param array{nombre: string, email: string, rol: string, telefono: ?string, password: ?string} $datos */
    public function actualizar(int $id, array $datos): void
    {
        $this->ejecutar(
            'UPDATE usuarios SET nombre = :nombre, email = :email, rol = :rol, telefono = :telefono WHERE id = :id',
            ['nombre' => $datos['nombre'], 'email' => $datos['email'], 'rol' => $datos['rol'], 'telefono' => $datos['telefono'], 'id' => $id]
        );
        if (!empty($datos['password'])) {
            $this->actualizarHash($id, $datos['password']);
        }
    }

    /** El propio usuario edita sus datos (no puede cambiar su rol). */
    public function actualizarPerfil(int $id, string $nombre, string $email, ?string $telefono): void
    {
        $this->ejecutar(
            'UPDATE usuarios SET nombre = :nombre, email = :email, telefono = :telefono WHERE id = :id',
            ['nombre' => $nombre, 'email' => $email, 'telefono' => $telefono, 'id' => $id]
        );
    }

    /** Hash actual de la contraseña (para verificar la contraseña actual antes de cambiarla). */
    public function hashDe(int $id): ?string
    {
        $fila = $this->obtenerUno('SELECT password_hash FROM usuarios WHERE id = :id', ['id' => $id]);
        return $fila['password_hash'] ?? null;
    }

    public function cambiarActivo(int $id, bool $activo): void
    {
        $this->ejecutar('UPDATE usuarios SET activo = :activo WHERE id = :id', ['activo' => $activo ? 1 : 0, 'id' => $id]);
    }

    public function crear(string $nombre, string $email, string $password, string $rol, ?string $telefono = null): int
    {
        $this->ejecutar(
            'INSERT INTO usuarios (nombre, email, password_hash, rol, telefono)
             VALUES (:nombre, :email, :hash, :rol, :telefono)',
            [
                'nombre' => $nombre,
                'email' => $email,
                'hash' => password_hash($password, PASSWORD_DEFAULT),
                'rol' => $rol,
                'telefono' => $telefono,
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    /** Actualiza el hash si PHP recomienda un algoritmo o costo más nuevo. */
    public function actualizarHash(int $id, string $password): void
    {
        $this->ejecutar(
            'UPDATE usuarios SET password_hash = :hash WHERE id = :id',
            ['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $id]
        );
    }

    /** @param array<string, mixed> $usuario */
    private static function normalizar(array $usuario): array
    {
        $usuario['id'] = (int) $usuario['id'];
        $usuario['activo'] = (bool) $usuario['activo'];
        return $usuario;
    }
}
