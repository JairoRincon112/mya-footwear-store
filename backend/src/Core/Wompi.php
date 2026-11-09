<?php

declare(strict_types=1);

namespace App\Core;

use App\Config\Config;

/**
 * Cliente de la pasarela de pagos Wompi (https://docs.wompi.co).
 *
 * Las llaves se leen de backend/.env:
 *   WOMPI_ENTORNO             sandbox | produccion
 *   WOMPI_LLAVE_PUBLICA       pub_test_... (se puede mostrar en el navegador)
 *   WOMPI_LLAVE_PRIVADA       prv_test_... (solo servidor: consulta transacciones)
 *   WOMPI_SECRETO_INTEGRIDAD  firma el monto para que no se pueda alterar en el navegador
 *   WOMPI_SECRETO_EVENTOS     verifica que los avisos (webhooks) vengan de Wompi
 */
final class Wompi
{
    private const URL_SANDBOX = 'https://sandbox.wompi.co/v1';
    private const URL_PRODUCCION = 'https://production.wompi.co/v1';

    /** Estados de Wompi -> estados de la tabla pagos. */
    public const ESTADOS = [
        'PENDING' => 'pendiente',
        'APPROVED' => 'aprobado',
        'DECLINED' => 'rechazado',
        'VOIDED' => 'anulado',
        'ERROR' => 'error',
    ];

    /** true si están las llaves necesarias para cobrar y verificar. */
    public static function configurado(): bool
    {
        return Config::get('WOMPI_LLAVE_PUBLICA') !== ''
            && Config::get('WOMPI_LLAVE_PRIVADA') !== ''
            && Config::get('WOMPI_SECRETO_INTEGRIDAD') !== '';
    }

    public static function esProduccion(): bool
    {
        return Config::get('WOMPI_ENTORNO', 'sandbox') === 'produccion';
    }

    public static function llavePublica(): string
    {
        return Config::get('WOMPI_LLAVE_PUBLICA');
    }

    /**
     * Firma de integridad: SHA-256 de <referencia><monto en centavos><moneda><secreto>.
     * Wompi rechaza el pago si alguien cambia el monto o la referencia en el navegador.
     */
    public static function firmaIntegridad(string $referencia, int $montoCentavos, string $moneda): string
    {
        return hash('sha256', $referencia . $montoCentavos . $moneda . Config::get('WOMPI_SECRETO_INTEGRIDAD'));
    }

    /**
     * Consulta una transacción en Wompi (servidor a servidor, con la llave privada).
     *
     * @return array<string, mixed> Campo "data" de la respuesta: id, reference, status, amount_in_cents...
     */
    public static function consultarTransaccion(string $transaccionId): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{5,60}$/', $transaccionId)) {
            throw HttpException::validacion(['id' => 'El identificador de la transacción no es válido.']);
        }

        // WOMPI_API_URL es opcional: solo sirve para apuntar a un simulador en las pruebas.
        $base = Config::get('WOMPI_API_URL') ?: (self::esProduccion() ? self::URL_PRODUCCION : self::URL_SANDBOX);
        $url = rtrim($base, '/') . '/transactions/' . rawurlencode($transaccionId);
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5, // si Wompi no contesta en 5 s, no se deja al cliente esperando
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . Config::get('WOMPI_LLAVE_PRIVADA'),
            ],
        ]);
        $cuerpo = curl_exec($curl);
        $estado = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $errorRed = curl_error($curl);

        if ($cuerpo === false) {
            self::registrar("sin conexión al consultar $transaccionId: $errorRed");
            throw new HttpException(502, 'No pudimos comunicarnos con Wompi. Intenta de nuevo en unos segundos.' . (Config::booleano('APP_DEBUG') ? " ($errorRed)" : ''));
        }
        if ($estado === 404) {
            throw HttpException::noEncontrado('Wompi no encontró esa transacción.');
        }

        $respuesta = json_decode((string) $cuerpo, true);
        if ($estado !== 200 || !is_array($respuesta['data'] ?? null)) {
            self::registrar("respuesta inesperada (HTTP $estado) al consultar $transaccionId");
            throw new HttpException(502, 'Wompi respondió con un error al consultar la transacción.');
        }
        return $respuesta['data'];
    }

    /** Deja el fallo en el log del servidor. Nunca se escriben llaves ni datos de tarjetas. */
    private static function registrar(string $detalle): void
    {
        error_log('[Wompi] ' . $detalle);
    }

    /**
     * Verifica la firma de un evento (webhook) de Wompi.
     * checksum = SHA-256( valores de signature.properties + timestamp + secreto de eventos )
     * Las propiedades se leen del propio evento: Wompi puede cambiarlas.
     */
    public static function eventoValido(array $evento): bool
    {
        $secreto = Config::get('WOMPI_SECRETO_EVENTOS');
        $propiedades = $evento['signature']['properties'] ?? null;
        $checksum = $evento['signature']['checksum'] ?? null;
        $timestamp = $evento['timestamp'] ?? null;

        if ($secreto === '' || !is_array($propiedades) || !is_string($checksum) || !is_int($timestamp)) {
            return false;
        }

        $cadena = '';
        foreach ($propiedades as $ruta) {
            // "transaction.status" -> $evento['data']['transaction']['status']
            $valor = $evento['data'] ?? null;
            foreach (explode('.', (string) $ruta) as $parte) {
                $valor = is_array($valor) ? ($valor[$parte] ?? null) : null;
            }
            if (!is_scalar($valor)) {
                return false;
            }
            $cadena .= (string) $valor;
        }

        $esperado = hash('sha256', $cadena . $timestamp . $secreto);
        return hash_equals($esperado, strtolower($checksum));
    }
}
