<?php

declare(strict_types=1);

namespace App\Core;

use App\Config\Config;

/**
 * Envío de correos sin librerías.
 *
 * MAIL_DRIVER en el .env decide cómo se envía:
 *   - log  (por defecto): el correo se guarda como archivo en backend/storage/correos/.
 *          Sirve para desarrollar y probar sin configurar un servidor de correo.
 *   - mail: usa la función mail() de PHP. En XAMPP hay que configurar sendmail
 *          (por ejemplo con Gmail); los pasos están en el README.
 */
final class Correo
{
    public static function enviar(string $para, string $asunto, string $texto): bool
    {
        $remitente = Config::get('MAIL_FROM', 'no-responder@myafootwear.com');
        $nombreRemitente = Config::get('MAIL_FROM_NAME', 'MyA FOOTWEAR STORE');

        // Se evita la inyección de cabeceras: ningún dato puede traer saltos de línea.
        if (preg_match('/[\r\n]/', $para . $asunto . $remitente . $nombreRemitente)) {
            return false;
        }

        $cabeceras = [
            'From: ' . mb_encode_mimeheader($nombreRemitente, 'UTF-8') . " <$remitente>",
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];
        $asuntoCodificado = mb_encode_mimeheader($asunto, 'UTF-8');

        if (Config::get('MAIL_DRIVER', 'log') === 'mail') {
            $enviado = mail($para, $asuntoCodificado, $texto, implode("\r\n", $cabeceras));
            if (!$enviado) {
                error_log("No se pudo enviar el correo a $para. Revisa la configuración de sendmail.");
            }
            return $enviado;
        }

        return self::guardarEnArchivo($para, $asunto, $texto, $cabeceras);
    }

    /** @param list<string> $cabeceras */
    private static function guardarEnArchivo(string $para, string $asunto, string $texto, array $cabeceras): bool
    {
        $carpeta = dirname(__DIR__, 2) . '/storage/correos';
        if (!is_dir($carpeta) && !mkdir($carpeta, 0775, true) && !is_dir($carpeta)) {
            return false;
        }

        $contenido = implode("\r\n", $cabeceras) . "\r\nTo: $para\r\nSubject: $asunto\r\nDate: " . date('r') . "\r\n\r\n$texto\r\n";
        $archivo = sprintf('%s/%s-%s.eml', $carpeta, date('Ymd-His'), bin2hex(random_bytes(4)));

        return file_put_contents($archivo, $contenido) !== false;
    }
}
