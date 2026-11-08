<?php

declare(strict_types=1);

namespace App\Models;

use App\Descuentos\EstrategiasDescuento;

/**
 * Cupones de descuento (contexto del patrón Strategy).
 * Aquí están las reglas comunes a todos los tipos: vigencia, usos, compra mínima,
 * redondeo al peso y que el descuento nunca supere el subtotal. Lo que cambia según
 * el tipo ("porcentaje", "valor") lo resuelve su estrategia en App\Descuentos.
 */
final class Cupon extends Model
{
    /** @return array<string, mixed>|null */
    public function buscarPorCodigo(string $codigo, bool $bloquear = false): ?array
    {
        $cupon = $this->obtenerUno(
            'SELECT * FROM cupones WHERE codigo = :codigo' . ($bloquear ? ' FOR UPDATE' : ''),
            ['codigo' => strtoupper(trim($codigo))]
        );
        return $cupon ? self::normalizar($cupon) : null;
    }

    /** @return array<string, mixed>|null */
    public function buscarPorId(int $id): ?array
    {
        $cupon = $this->obtenerUno('SELECT * FROM cupones WHERE id = :id', ['id' => $id]);
        return $cupon ? self::normalizar($cupon) : null;
    }

    /**
     * Revisa si el cupón se puede usar en una compra.
     * @return string|null Motivo por el que no aplica, o null si es válido
     */
    public function motivoInvalido(array $cupon, float $subtotal, int $clienteId): ?string
    {
        $ahora = date('Y-m-d H:i:s');

        if (!$cupon['activo']) {
            return 'Este cupón no está disponible.';
        }
        if ($cupon['inicio'] !== null && $cupon['inicio'] > $ahora) {
            return 'Este cupón todavía no está vigente.';
        }
        if ($cupon['fin'] !== null && $cupon['fin'] < $ahora) {
            return 'Este cupón ya venció.';
        }
        if ($cupon['usos_maximos'] !== null && $cupon['usos'] >= $cupon['usos_maximos']) {
            return 'Este cupón ya alcanzó su límite de usos.';
        }
        if ($subtotal < $cupon['minimo_compra']) {
            return 'Este cupón aplica para compras desde $' . number_format($cupon['minimo_compra'], 0, ',', '.') . '.';
        }
        if ($cupon['uno_por_cliente'] && $this->yaLoUso($cupon['id'], $clienteId)) {
            return 'Ya usaste este cupón en otra compra.';
        }
        return null;
    }

    public function calcularDescuento(array $cupon, float $subtotal): float
    {
        $descuento = EstrategiasDescuento::para($cupon['tipo'])->calcular($subtotal, $cupon['valor']);

        // Pesos colombianos: se redondea al peso y nunca supera el subtotal.
        return (float) min(round($descuento), $subtotal);
    }

    private function yaLoUso(int $cuponId, int $clienteId): bool
    {
        return $this->obtenerUno(
            "SELECT 1 FROM pedidos WHERE cupon_id = :cupon AND cliente_id = :cliente AND estado <> 'cancelado' LIMIT 1",
            ['cupon' => $cuponId, 'cliente' => $clienteId]
        ) !== null;
    }

    public function sumarUso(int $id): void
    {
        $this->ejecutar('UPDATE cupones SET usos = usos + 1 WHERE id = :id', ['id' => $id]);
    }

    /** Al cancelar un pedido, el uso del cupón se devuelve. */
    public function restarUso(int $id): void
    {
        $this->ejecutar('UPDATE cupones SET usos = GREATEST(usos - 1, 0) WHERE id = :id', ['id' => $id]);
    }

    /** @return list<array<string, mixed>> */
    public function todos(): array
    {
        return array_map(self::normalizar(...), $this->obtenerTodos(
            'SELECT c.*, (SELECT COUNT(*) FROM pedidos pe WHERE pe.cupon_id = c.id AND pe.estado <> \'cancelado\') AS pedidos,
                    (SELECT COALESCE(SUM(pe.descuento), 0) FROM pedidos pe WHERE pe.cupon_id = c.id AND pe.estado <> \'cancelado\') AS descuento_total
               FROM cupones c
              ORDER BY c.activo DESC, c.creado_en DESC'
        ));
    }

    public function codigoDisponible(string $codigo, ?int $exceptoId = null): bool
    {
        return $this->obtenerUno(
            'SELECT 1 FROM cupones WHERE codigo = :codigo AND id <> :excepto',
            ['codigo' => $codigo, 'excepto' => $exceptoId ?? 0]
        ) === null;
    }

    /** @param array<string, mixed> $datos */
    public function crear(array $datos): int
    {
        $this->ejecutar(
            'INSERT INTO cupones (codigo, descripcion, tipo, valor, minimo_compra, usos_maximos, uno_por_cliente, inicio, fin, activo)
             VALUES (:codigo, :descripcion, :tipo, :valor, :minimo_compra, :usos_maximos, :uno_por_cliente, :inicio, :fin, :activo)',
            self::parametros($datos)
        );
        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $datos */
    public function actualizar(int $id, array $datos): void
    {
        $this->ejecutar(
            'UPDATE cupones SET codigo = :codigo, descripcion = :descripcion, tipo = :tipo, valor = :valor,
                    minimo_compra = :minimo_compra, usos_maximos = :usos_maximos, uno_por_cliente = :uno_por_cliente,
                    inicio = :inicio, fin = :fin, activo = :activo
              WHERE id = :id',
            self::parametros($datos) + ['id' => $id]
        );
    }

    public function cambiarActivo(int $id, bool $activo): void
    {
        $this->ejecutar('UPDATE cupones SET activo = :activo WHERE id = :id', ['activo' => $activo ? 1 : 0, 'id' => $id]);
    }

    private static function parametros(array $datos): array
    {
        return [
            'codigo' => $datos['codigo'],
            'descripcion' => $datos['descripcion'],
            'tipo' => $datos['tipo'],
            'valor' => number_format($datos['valor'], 2, '.', ''),
            'minimo_compra' => number_format($datos['minimo_compra'], 2, '.', ''),
            'usos_maximos' => $datos['usos_maximos'],
            'uno_por_cliente' => $datos['uno_por_cliente'] ? 1 : 0,
            'inicio' => $datos['inicio'],
            'fin' => $datos['fin'],
            'activo' => $datos['activo'] ? 1 : 0,
        ];
    }

    private static function normalizar(array $cupon): array
    {
        $cupon['id'] = (int) $cupon['id'];
        $cupon['valor'] = (float) $cupon['valor'];
        $cupon['valor_texto'] = EstrategiasDescuento::para($cupon['tipo'])->describir($cupon['valor']);
        $cupon['minimo_compra'] = (float) $cupon['minimo_compra'];
        $cupon['usos_maximos'] = $cupon['usos_maximos'] === null ? null : (int) $cupon['usos_maximos'];
        $cupon['usos'] = (int) $cupon['usos'];
        $cupon['uno_por_cliente'] = (bool) $cupon['uno_por_cliente'];
        $cupon['activo'] = (bool) $cupon['activo'];
        if (isset($cupon['pedidos'])) {
            $cupon['pedidos'] = (int) $cupon['pedidos'];
            $cupon['descuento_total'] = (float) $cupon['descuento_total'];
        }
        return $cupon;
    }
}
