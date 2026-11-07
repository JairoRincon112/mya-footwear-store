<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Wompi;
use App\Models\Pago;
use App\Models\Pedido;

/**
 * Pagos en línea con Wompi.
 *
 * Flujo:
 *   1. POST /api/pedidos/{id}/pago      -> el servidor arma y firma los datos del pago.
 *   2. El navegador abre el widget de Wompi con esos datos y el cliente paga.
 *   3. GET  /api/pagos/verificar?id=... -> el servidor consulta a Wompi y actualiza el pedido.
 *   4. POST /api/pagos/wompi/eventos    -> Wompi avisa el resultado (webhook firmado).
 * El monto siempre sale de la base de datos, nunca del navegador.
 */
final class PagoController extends Controller
{
    /** POST /api/pedidos/{id}/pago (cliente dueño del pedido) */
    public function iniciar(Request $request): void
    {
        if (!Wompi::configurado()) {
            throw new HttpException(503, 'Los pagos en línea no están disponibles en este momento.');
        }

        $pedidos = new Pedido();
        $pedido = $pedidos->buscar($this->idDeRuta($request));
        if ($pedido === null || $pedido['cliente']['id'] !== $this->usuarioId($request)) {
            throw HttpException::noEncontrado('El pedido no existe.');
        }
        if ($pedido['estado'] !== 'pendiente') {
            throw HttpException::conflicto(
                $pedido['estado'] === 'cancelado' ? 'Este pedido está cancelado.' : 'Este pedido ya fue pagado.'
            );
        }

        // Wompi trabaja en centavos: $339.950 -> 33995000
        $montoCentavos = (int) round($pedido['total'] * 100);
        $moneda = 'COP';
        $referencia = (new Pago())->crear($pedido['id'], $montoCentavos, $moneda);

        Response::ok([
            'llave_publica' => Wompi::llavePublica(),
            'moneda' => $moneda,
            'monto_centavos' => $montoCentavos,
            'referencia' => $referencia,
            'firma_integridad' => Wompi::firmaIntegridad($referencia, $montoCentavos, $moneda),
            'sandbox' => !Wompi::esProduccion(),
            'cliente' => [
                'email' => $pedido['cliente']['email'],
                'nombre' => $pedido['nombre_contacto'],
                'telefono' => preg_replace('/\D/', '', $pedido['telefono']),
            ],
        ]);
    }

    /** GET /api/pagos/verificar?id=<transacción de Wompi> (con sesión) */
    public function verificar(Request $request): void
    {
        if (!Wompi::configurado()) {
            throw new HttpException(503, 'Los pagos en línea no están disponibles en este momento.');
        }

        $transaccion = Wompi::consultarTransaccion($request->query('id') ?? '');

        // Solo el dueño del pedido (o el personal) puede verificar el pago.
        $pagos = new Pago();
        $pago = $pagos->buscarPorReferencia((string) ($transaccion['reference'] ?? ''));
        if ($pago === null || (!$request->esPersonal() && (int) $pago['cliente_id'] !== $this->usuarioId($request))) {
            throw HttpException::noEncontrado('No encontramos ese pago.');
        }

        $resultado = $pagos->aplicarTransaccion($transaccion);
        Response::ok($resultado, self::mensaje($resultado['pago']['estado']));
    }

    /**
     * POST /api/pagos/wompi/eventos (público: lo llama Wompi)
     * Solo se aceptan eventos con la firma correcta del secreto de eventos.
     */
    public function eventos(Request $request): void
    {
        $evento = $request->cuerpo();
        if (!Wompi::eventoValido($evento)) {
            throw HttpException::noAutenticado('Firma del evento inválida.');
        }

        if (($evento['event'] ?? '') === 'transaction.updated' && is_array($evento['data']['transaction'] ?? null)) {
            (new Pago())->aplicarTransaccion($evento['data']['transaction']);
        }

        // Wompi reintenta si no recibe 200, así que eventos desconocidos también se confirman.
        Response::ok(null, 'Evento recibido.');
    }

    private static function mensaje(string $estado): string
    {
        return match ($estado) {
            'aprobado' => 'Tu pedido quedó pagado. ¡Gracias por tu compra!',
            'pendiente' => 'Tu pago está en proceso. Te avisaremos cuando se confirme.',
            'rechazado' => 'El pago fue rechazado. Puedes intentarlo de nuevo con otro medio de pago.',
            'anulado' => 'El pago fue anulado.',
            default => 'Hubo un error con el pago. Intenta de nuevo.',
        };
    }
}
