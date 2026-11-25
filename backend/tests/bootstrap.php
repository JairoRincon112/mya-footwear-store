<?php

declare(strict_types=1);

/*
 * Arranque de las pruebas: autoload de Composer y configuración del backend/.env local
 * (base de datos y JWT_SECRET). Las pruebas de integración llaman a la API que sirve
 * XAMPP en http://localhost/mya-api; se puede cambiar con la variable MYA_API_URL.
 */

use App\Config\Config;

require dirname(__DIR__) . '/vendor/autoload.php';

Config::cargar(dirname(__DIR__) . '/.env');
date_default_timezone_set(Config::get('APP_TIMEZONE', 'America/Bogota'));
