<?php

namespace App\Services\Geo;

use RuntimeException;

/**
 * Fallo recuperable de un proveedor de geodatos (timeout, cuota, respuesta
 * inesperada). Los controladores lo traducen a un 503 con mensaje en español.
 */
class GeoException extends RuntimeException {}
