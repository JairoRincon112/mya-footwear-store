<?php

/**
 * Raíz del sitio publicado. La tienda vive en /mya/ y la API en /mya-api/,
 * así que quien entra al dominio sin ruta se envía a la tienda.
 */
header('Location: /mya/', true, 302);
exit;
