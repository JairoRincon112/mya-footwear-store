<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\HttpException;

/**
 * Devoluciones de productos de pedidos entregados (tabla devoluciones).
 *
 * Reglas:
 *  - Solo el dueño del pedido la solicita, y solo si el pedido está "entregado".
 *  - Plazo: hasta DIAS_PLAZO días después de la entrega.
 *  - No se puede devolver más de lo comprado en esa línea, contando las
 *    devoluciones que ya están en curso o recibidas.
 *  - Flujo: solicitada -> aprobada -> recibida, o solicitada -> rechazada.
 *    Al rechazar hay que explicar el motivo; al recibir, el stock vuelve al inventario.
 */
final class Devolucion extends Model
{
    public const TIPOS = ['garantia', 'talla_incorrecta', 'defectuoso', 'producto_equivocado', 'no_satisface'];
    public const ESTADOS = ['solicitada', 'aprobada', 'rechazada', 'recibida'];
    public const DIAS_PLAZO = 30;

    /** Estado actual => estados a los que puede pasar. */
    public const TRANSICIONES = [
        'solicitada' => ['aprobada', 'rechazada'],
        'aprobada' => ['recibida'],
        'rechazada' => [],
        'recibida' => [],
    ];

    /**
     * Registra una solicitud de devolución del cliente.
     *
     * @return int id de la devolución
     */
    public function solicitar(int $clienteId, int $pedidoId, int $detalleId, string $tipo, int $cantidad, string $descripcion): int
    {
        return Database::transaccion(function () use ($clienteId, $pedidoId, $detalleId, $tipo, $cantidad, $descripcion): int {
            $pedido = $this->obtenerUno(
                'SELECT id, cliente_id, estado, COALESCE(actualizado_en, creado_en) AS entregado_en
                   FROM pedidos WHERE id = :id FOR UPDATE',
                ['id' => $pedidoId]
            );
            if ($pedido === null || (int) $pedido['cliente_id'] !== $clienteId) {
                throw HttpException::noEncontrado('El pedido no existe.');
            }
            if ($pedido['estado'] !== 'entregado') {
                throw HttpException::conflicto('Solo puedes pedir una devolución cuando el pedido ya fue entregado.');
            }
            if (strtotime((string) $pedido['entregado_en']) < strtotime('-' . self::DIAS_PLAZO . ' days')) {
                throw HttpException::conflicto('El plazo para pedir devoluciones de este pedido ya terminó (' . self::DIAS_PLAZO . ' días después de la entrega).');
            }

            $linea = $this->obtenerUno(
                'SELECT id, cantidad FROM pedido_detalle WHERE id = :detalle AND pedido_id = :pedido',
                ['detalle' => $detalleId, 'pedido' => $pedidoId]
            );
            if ($linea === null) {
                throw HttpException::validacion(['detalle_id' => 'Ese producto no pertenece al pedido.']);
            }

            $disponible = (int) $linea['cantidad'] - $this->cantidadEnDevolucion($detalleId);
            if ($disponible <= 0) {
                throw HttpException::validacion(['cantidad' => 'Ya pediste la devolución de todas las unidades de este producto.']);
            }
            if ($cantidad > $disponible) {
                throw HttpException::validacion(['cantidad' => "Solo puedes devolver hasta $disponible " . ($disponible === 1 ? 'unidad' : 'unidades') . ' de este producto.']);
            }

            $this->ejecutar(
                'INSERT INTO devoluciones (pedido_id, detalle_id, cliente_id, tipo, cantidad, descripcion)
                 VALUES (:pedido, :detalle, :cliente, :tipo, :cantidad, :descripcion)',
                [
                    'pedido' => $pedidoId,
                    'detalle' => $detalleId,
                    'cliente' => $clienteId,
                    'tipo' => $tipo,
                    'cantidad' => $cantidad,
                    'descripcion' => $descripcion,
                ]
            );
            return (int) $this->db->lastInsertId();
        });
    }

    /**
     * Cambia el estado respetando TRANSICIONES. Al pasar a "recibida" el producto
     * vuelve al inventario mediante Variante (movimiento "devolucion").
     */
    public function cambiarEstado(int $id, string $nuevoEstado, ?string $respuesta, int $usuarioId): void
    {
        Database::transaccion(function () use ($id, $nuevoEstado, $respuesta, $usuarioId): void {
            $devolucion = $this->obtenerUno(
                'SELECT dv.id, dv.estado, dv.cantidad, dv.pedido_id, d.variante_id
                   FROM devoluciones dv
                   JOIN pedido_detalle d ON d.id = dv.detalle_id
                  WHERE dv.id = :id FOR UPDATE',
                ['id' => $id]
            );
            if ($devolucion === null) {
                throw HttpException::noEncontrado('La devolución no existe.');
            }
            if (!in_array($nuevoEstado, self::TRANSICIONES[$devolucion['estado']], true)) {
                throw HttpException::validacion(['estado' => sprintf(
                    'Una devolución %s no puede pasar a %s.',
                    $devolucion['estado'],
                    $nuevoEstado
                )]);
            }
            if ($nuevoEstado === 'rechazada' && trim((string) $respuesta) === '') {
                throw HttpException::validacion(['respuesta' => 'Explica al cliente por qué se rechaza la devolución.']);
            }

            $this->ejecutar(
                'UPDATE devoluciones
                    SET estado = :estado, respuesta = COALESCE(:respuesta, respuesta), gestionado_por = :usuario
                  WHERE id = :id',
                ['estado' => $nuevoEstado, 'respuesta' => $respuesta, 'usuario' => $usuarioId, 'id' => $id]
            );

            if ($nuevoEstado === 'recibida') {
                (new Variante())->reingresarPorDevolucion(
                    (int) $devolucion['variante_id'],
                    (int) $devolucion['cantidad'],
                    $usuarioId,
                    (int) $devolucion['pedido_id'],
                    $id
                );
            }
        });
    }

    /**
     * @param array{estado?: ?string, tipo?: ?string, q?: ?string} $filtros
     * @return array{items: list<array<string, mixed>>, total: int, por_revisar: int}
     */
    public function listar(array $filtros, int $pagina, int $porPagina): array
    {
        $condiciones = [];
        $parametros = [];
        if (!empty($filtros['estado'])) {
            $condiciones[] = 'dv.estado = :estado';
            $parametros['estado'] = $filtros['estado'];
        }
        if (!empty($filtros['tipo'])) {
            $condiciones[] = 'dv.tipo = :tipo';
            $parametros['tipo'] = $filtros['tipo'];
        }
        if (!empty($filtros['q'])) {
            $numero = ltrim($filtros['q'], '#');
            if (ctype_digit($numero)) {
                $condiciones[] = '(dv.id = :numero OR dv.pedido_id = :numero_pedido)';
                $parametros['numero'] = (int) $numero;
                $parametros['numero_pedido'] = (int) $numero;
            } else {
                $condiciones[] = '(u.nombre LIKE :busqueda OR u.email LIKE :busqueda_email OR d.descripcion LIKE :busqueda_producto)';
                $parametros['busqueda'] = '%' . addcslashes($filtros['q'], '%_\\') . '%';
                $parametros['busqueda_email'] = $parametros['busqueda'];
                $parametros['busqueda_producto'] = $parametros['busqueda'];
            }
        }

        $where = $condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones);
        $total = (int) $this->obtenerUno(
            "SELECT COUNT(*) AS total {$this->desde()} $where",
            $parametros
        )['total'];
        $filas = $this->obtenerTodos(
            "SELECT {$this->columnas()} {$this->desde()} $where
              ORDER BY FIELD(dv.estado, 'solicitada', 'aprobada', 'recibida', 'rechazada'), dv.creado_en DESC, dv.id DESC
              LIMIT :limite OFFSET :desplazamiento",
            $parametros + ['limite' => $porPagina, 'desplazamiento' => ($pagina - 1) * $porPagina]
        );

        return [
            'items' => array_map([$this, 'formatear'], $filas),
            'total' => $total,
            'por_revisar' => $this->contarPorRevisar(),
        ];
    }

    /** @return list<array<string, mixed>> Devoluciones de un pedido (para el detalle del pedido). */
    public function dePedido(int $pedidoId): array
    {
        return array_map([$this, 'formatear'], $this->obtenerTodos(
            "SELECT {$this->columnas()} {$this->desde()} WHERE dv.pedido_id = :pedido ORDER BY dv.id DESC",
            ['pedido' => $pedidoId]
        ));
    }

    /** @return array<string, mixed>|null */
    public function buscar(int $id): ?array
    {
        $fila = $this->obtenerUno("SELECT {$this->columnas()} {$this->desde()} WHERE dv.id = :id", ['id' => $id]);
        return $fila === null ? null : $this->formatear($fila);
    }

    /** Solicitudes nuevas o aprobadas que esperan el producto. */
    public function contarPorRevisar(): int
    {
        return (int) $this->obtenerUno(
            "SELECT COUNT(*) AS total FROM devoluciones WHERE estado IN ('solicitada', 'aprobada')"
        )['total'];
    }

    /** Unidades de una línea que ya están en devolución (sin contar las rechazadas). */
    private function cantidadEnDevolucion(int $detalleId): int
    {
        return (int) $this->obtenerUno(
            "SELECT COALESCE(SUM(cantidad), 0) AS total FROM devoluciones
              WHERE detalle_id = :detalle AND estado <> 'rechazada'",
            ['detalle' => $detalleId]
        )['total'];
    }

    private function columnas(): string
    {
        return 'dv.id, dv.pedido_id, dv.detalle_id, dv.tipo, dv.cantidad, dv.descripcion, dv.estado, dv.respuesta,
                dv.creado_en, dv.actualizado_en, d.descripcion AS producto, d.cantidad AS comprada,
                u.id AS cliente_id, u.nombre AS cliente, u.email AS cliente_email, g.nombre AS gestor';
    }

    private function desde(): string
    {
        return 'FROM devoluciones dv
                JOIN pedido_detalle d ON d.id = dv.detalle_id
                JOIN usuarios u ON u.id = dv.cliente_id
                LEFT JOIN usuarios g ON g.id = dv.gestionado_por';
    }

    /** @param array<string, mixed> $fila */
    private function formatear(array $fila): array
    {
        return [
            'id' => (int) $fila['id'],
            'pedido_id' => (int) $fila['pedido_id'],
            'detalle_id' => (int) $fila['detalle_id'],
            'producto' => $fila['producto'],
            'comprada' => (int) $fila['comprada'],
            'tipo' => $fila['tipo'],
            'cantidad' => (int) $fila['cantidad'],
            'descripcion' => $fila['descripcion'],
            'estado' => $fila['estado'],
            'estados_siguientes' => self::TRANSICIONES[$fila['estado']],
            'respuesta' => $fila['respuesta'],
            'creado_en' => $fila['creado_en'],
            'actualizado_en' => $fila['actualizado_en'],
            'cliente' => ['id' => (int) $fila['cliente_id'], 'nombre' => $fila['cliente'], 'email' => $fila['cliente_email']],
            'gestionado_por' => $fila['gestor'],
        ];
    }
}
