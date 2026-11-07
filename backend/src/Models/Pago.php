<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Wompi;

/**
 * Intentos de pago de los pedidos (tabla pagos).
 * Un pago aprobado por Wompi marca el pedido como "pagado".
 */
final class Pago extends Model
{
    /**
     * Registra un intento de pago nuevo y devuelve su referencia única.
     * Formato: MYA-<pedido>-<12 caracteres aleatorios>.
     */
    public function crear(int $pedidoId, int $montoCentavos, string $moneda = 'COP'): string
    {
        $referencia = sprintf('MYA-%d-%s', $pedidoId, strtoupper(bin2hex(random_bytes(6))));
        $this->ejecutar(
            'INSERT INTO pagos (pedido_id, referencia, monto_centavos, moneda) VALUES (:pedido, :referencia, :monto, :moneda)',
            ['pedido' => $pedidoId, 'referencia' => $referencia, 'monto' => $montoCentavos, 'moneda' => $moneda]
        );
        return $referencia;
    }

    /** @return array<string, mixed>|null Pago con el cliente dueño del pedido */
    public function buscarPorReferencia(string $referencia): ?array
    {
        return $this->obtenerUno(
            'SELECT pa.*, pe.cliente_id, pe.estado AS estado_pedido
               FROM pagos pa
               JOIN pedidos pe ON pe.id = pa.pedido_id
              WHERE pa.referencia = :referencia',
            ['referencia' => $referencia]
        );
    }

    /** Último intento de pago de un pedido (para mostrarlo en el detalle). */
    public function ultimoDePedido(int $pedidoId): ?array
    {
        $fila = $this->obtenerUno(
            'SELECT referencia, estado, metodo, transaccion_id, creado_en, actualizado_en
               FROM pagos WHERE pedido_id = :pedido ORDER BY id DESC LIMIT 1',
            ['pedido' => $pedidoId]
        );
        return $fila;
    }

    /**
     * Aplica el resultado de una transacción de Wompi (venga de la verificación o del webhook).
     * - Busca el pago por la referencia y lo bloquea (FOR UPDATE) para que dos avisos
     *   simultáneos no lo procesen dos veces.
     * - Si el monto o la moneda no coinciden con lo que se firmó, lo marca como error.
     * - Si queda aprobado y el pedido sigue pendiente, el pedido pasa a "pagado".
     *
     * @param array<string, mixed> $transaccion Datos de Wompi (id, reference, status, amount_in_cents, currency...)
     * @return array{pago: array<string, mixed>, pedido_id: int, estado_pedido: string, revisar: bool}|null
     *         null si la referencia no es de esta tienda
     */
    public function aplicarTransaccion(array $transaccion): ?array
    {
        return Database::transaccion(function () use ($transaccion): ?array {
            $pago = $this->obtenerUno(
                'SELECT * FROM pagos WHERE referencia = :referencia FOR UPDATE',
                ['referencia' => (string) ($transaccion['reference'] ?? '')]
            );
            if ($pago === null) {
                return null;
            }

            $pedido = $this->obtenerUno('SELECT id, estado FROM pedidos WHERE id = :id FOR UPDATE', ['id' => (int) $pago['pedido_id']]);
            $estado = Wompi::ESTADOS[$transaccion['status'] ?? ''] ?? 'error';
            $montoCorrecto = (int) ($transaccion['amount_in_cents'] ?? -1) === (int) $pago['monto_centavos']
                && ($transaccion['currency'] ?? '') === $pago['moneda'];
            if (!$montoCorrecto) {
                $estado = 'error'; // alguien alteró el monto: nunca se da por pagado
            }

            // Un pago aprobado no vuelve atrás por un aviso viejo que llegue después.
            if ($pago['estado'] !== 'aprobado') {
                $this->ejecutar(
                    'UPDATE pagos
                        SET estado = :estado, transaccion_id = :transaccion, metodo = :metodo, respuesta = :respuesta
                      WHERE id = :id',
                    [
                        'estado' => $estado,
                        'transaccion' => isset($transaccion['id']) ? (string) $transaccion['id'] : null,
                        'metodo' => isset($transaccion['payment_method_type']) ? substr((string) $transaccion['payment_method_type'], 0, 30) : null,
                        'respuesta' => json_encode($transaccion, JSON_UNESCAPED_UNICODE),
                        'id' => (int) $pago['id'],
                    ]
                );
            } else {
                $estado = 'aprobado';
            }

            $estadoPedido = $pedido['estado'];
            if ($estado === 'aprobado' && $estadoPedido === 'pendiente') {
                $this->ejecutar("UPDATE pedidos SET estado = 'pagado' WHERE id = :id", ['id' => (int) $pedido['id']]);
                $estadoPedido = 'pagado';
            }

            return [
                'pago' => ['referencia' => $pago['referencia'], 'estado' => $estado, 'metodo' => $transaccion['payment_method_type'] ?? null],
                'pedido_id' => (int) $pedido['id'],
                'estado_pedido' => $estadoPedido,
                // Cobrado pero el pedido ya estaba cancelado: el personal debe devolver el dinero.
                'revisar' => $estado === 'aprobado' && $estadoPedido === 'cancelado',
            ];
        });
    }
}
