<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Config;
use App\Core\HttpException;
use App\Core\Imagen;
use App\Core\Request;
use App\Core\Response;
use App\Core\Texto;
use App\Core\Validator;
use App\Models\Color;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\ProductoImagen;
use finfo;

/**
 * Catálogo de productos (público) y su administración (solo admin).
 */
final class ProductoController extends Controller
{
    /** Tipos de imagen aceptados (se verifica el contenido real del archivo, no su extensión). */
    private const TIPOS_IMAGEN = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /** Carpeta pública donde se guardan las imágenes subidas por el administrador. */
    private const CARPETA_SUBIDAS = 'uploads/productos';

    /** Carpeta que publica Apache para la API (public/mya-api en la raíz del proyecto). */
    private static function carpetaPublica(): string
    {
        return dirname(__DIR__, 3) . '/public/mya-api';
    }

    /**
     * GET /api/productos?marca=&q=&talla=&color=&orden=&pagina=&por_pagina=&estado=
     * El público solo ve productos activos; el personal puede pedir estado=todos|inactivos.
     */
    public function index(Request $request): void
    {
        $pagina = max(1, (int) ($request->query('pagina') ?? 1));
        $porPagina = min(60, max(1, (int) ($request->query('por_pagina') ?? 12)));

        $estado = 'activos';
        if ($request->esPersonal() && in_array($request->query('estado'), ['todos', 'inactivos'], true)) {
            $estado = $request->query('estado');
        }

        $filtros = [
            'oferta' => $request->query('oferta') === '1',
            'marca' => $request->query('marca') ?: null,
            'q' => mb_substr($request->query('q') ?? '', 0, 100) ?: null,
            'talla' => ctype_digit($request->query('talla') ?? '') ? (int) $request->query('talla') : null,
            'color' => ctype_digit($request->query('color') ?? '') ? (int) $request->query('color') : null,
            'orden' => $request->query('orden'),
            'estado' => $estado,
        ];

        $resultado = (new Producto())->listar($filtros, $pagina, $porPagina);

        Response::ok([
            'items' => $resultado['items'],
            'total' => $resultado['total'],
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'paginas' => (int) ceil($resultado['total'] / $porPagina),
        ]);
    }

    /** GET /api/productos/{producto} — acepta el id o el slug. */
    public function mostrar(Request $request): void
    {
        $valor = $request->parametro('producto');
        $idOSlug = ctype_digit($valor) ? (int) $valor : $valor;

        $producto = (new Producto())->buscar($idOSlug, $request->esPersonal());
        if ($producto === null) {
            throw HttpException::noEncontrado('El producto no existe o no está disponible.');
        }

        Response::ok($producto);
    }

    /** POST /api/productos */
    public function crear(Request $request): void
    {
        $datos = $this->validarProducto($request);
        $productos = new Producto();
        $datos['slug'] = $this->slugUnico($productos, $datos['nombre']);

        $id = $productos->crear($datos);

        Response::creado($productos->buscar($id, true), 'Producto creado.');
    }

    /** PUT /api/productos/{id} */
    public function actualizar(Request $request): void
    {
        $id = $this->idDeRuta($request);
        $productos = new Producto();
        if (!$productos->existe($id)) {
            throw HttpException::noEncontrado('El producto no existe.');
        }

        $datos = $this->validarProducto($request);
        $datos['slug'] = $this->slugUnico($productos, $datos['nombre'], $id);
        $productos->actualizar($id, $datos);

        Response::ok($productos->buscar($id, true), 'Producto actualizado.');
    }

    /** DELETE /api/productos/{id} — desactiva el producto (no lo borra). */
    public function eliminar(Request $request): void
    {
        $id = $this->idDeRuta($request);
        $productos = new Producto();
        if (!$productos->existe($id)) {
            throw HttpException::noEncontrado('El producto no existe.');
        }

        $productos->cambiarEstado($id, false);
        Response::ok($productos->buscar($id, true), 'Producto desactivado. Ya no aparece en la tienda.');
    }

    /** POST /api/productos/{id}/imagenes (multipart/form-data: imagen, color_id opcional) */
    public function subirImagen(Request $request): void
    {
        $productoId = $this->idDeRuta($request);
        if (!(new Producto())->existe($productoId)) {
            throw HttpException::noEncontrado('El producto no existe.');
        }

        $datos = Validator::validar($request->cuerpo(), ['color_id' => 'entero']);
        $colorId = $datos['color_id'] ?? null;
        if ($colorId !== null && (new Color())->buscarPorId($colorId) === null) {
            throw HttpException::validacion(['color_id' => 'El color no existe.']);
        }

        $archivo = $request->archivo('imagen');
        if ($archivo === null || $archivo['error'] === UPLOAD_ERR_NO_FILE) {
            throw HttpException::validacion(['imagen' => 'Selecciona una imagen.']);
        }
        if ($archivo['error'] === UPLOAD_ERR_INI_SIZE || $archivo['error'] === UPLOAD_ERR_FORM_SIZE) {
            throw HttpException::validacion(['imagen' => 'La imagen es demasiado grande.']);
        }
        if ($archivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
            throw HttpException::validacion(['imagen' => 'No se pudo recibir la imagen. Intenta de nuevo.']);
        }

        $maximoMb = Config::entero('UPLOAD_MAX_MB', 5);
        if ($archivo['size'] > $maximoMb * 1024 * 1024) {
            throw HttpException::validacion(['imagen' => "La imagen no puede pesar más de $maximoMb MB."]);
        }

        // Se revisa el contenido real del archivo para evitar subir scripts disfrazados de imagen.
        $tipo = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
        $extension = self::TIPOS_IMAGEN[$tipo] ?? null;
        if ($extension === null) {
            throw HttpException::validacion(['imagen' => 'Solo se permiten imágenes JPG, PNG o WEBP.']);
        }

        $carpeta = self::carpetaPublica() . '/' . self::CARPETA_SUBIDAS;
        if (!is_dir($carpeta) && !mkdir($carpeta, 0775, true) && !is_dir($carpeta)) {
            throw new \RuntimeException('No se pudo crear la carpeta de imágenes.');
        }

        // Nombre aleatorio: no se usa el nombre original del archivo.
        // La imagen se reduce (máx. 1200 px) y se recomprime antes de guardarla.
        $nombreArchivo = bin2hex(random_bytes(16)) . '.' . $extension;
        Imagen::optimizar($archivo['tmp_name'], "$carpeta/$nombreArchivo", $tipo);

        $imagenes = new ProductoImagen();
        $id = $imagenes->crear($productoId, self::CARPETA_SUBIDAS . "/$nombreArchivo", $colorId);

        Response::creado($imagenes->buscarPorId($id), 'Imagen agregada.');
    }

    /** DELETE /api/imagenes/{id} */
    public function eliminarImagen(Request $request): void
    {
        $imagenes = new ProductoImagen();
        $imagen = $imagenes->buscarPorId($this->idDeRuta($request));
        if ($imagen === null) {
            throw HttpException::noEncontrado('La imagen no existe.');
        }

        $imagenes->eliminar($imagen['id']);

        // Solo se borran del disco las imágenes subidas desde el panel (no las del catálogo inicial).
        if (str_starts_with($imagen['ruta'], self::CARPETA_SUBIDAS . '/')) {
            $archivo = self::carpetaPublica() . '/' . $imagen['ruta'];
            if (is_file($archivo)) {
                unlink($archivo);
            }
        }

        Response::ok(null, 'Imagen eliminada.');
    }

    /** @return array<string, mixed> */
    private function validarProducto(Request $request): array
    {
        $datos = Validator::validar($request->cuerpo(), [
            'marca_id' => 'requerido|entero',
            'nombre' => 'requerido|texto|min:2|max:150',
            'descripcion' => 'texto|max:2000',
            'precio' => 'requerido|numerico|minimo:1|maximo:99999999',
            'precio_oferta' => 'numerico|minimo:1|maximo:99999999',
            'oferta_inicio' => 'fecha_hora',
            'oferta_fin' => 'fecha_hora',
            'activo' => 'booleano',
        ]);

        if (!(new Marca())->existe($datos['marca_id'])) {
            throw HttpException::validacion(['marca_id' => 'La marca seleccionada no existe.']);
        }

        // Reglas de la oferta (también están como CHECK en la base de datos).
        $oferta = isset($datos['precio_oferta']) ? round($datos['precio_oferta'], 2) : null;
        $inicio = $oferta === null ? null : ($datos['oferta_inicio'] ?? null);
        $fin = $oferta === null ? null : ($datos['oferta_fin'] ?? null);
        if ($oferta !== null && $oferta >= $datos['precio']) {
            throw HttpException::validacion(['precio_oferta' => 'El precio de oferta debe ser menor que el precio regular.']);
        }
        if ($inicio !== null && $fin !== null && $inicio >= $fin) {
            throw HttpException::validacion(['oferta_fin' => 'La oferta debe terminar después de empezar.']);
        }

        return [
            'marca_id' => $datos['marca_id'],
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'] ?? null,
            'precio' => round($datos['precio'], 2),
            'precio_oferta' => $oferta,
            'oferta_inicio' => $inicio,
            'oferta_fin' => $fin,
            'activo' => $datos['activo'] ?? true,
        ];
    }

    /** Genera un slug a partir del nombre; si ya existe agrega -2, -3, ... */
    private function slugUnico(Producto $productos, string $nombre, ?int $exceptoId = null): string
    {
        $base = Texto::slug($nombre);
        $slug = $base;
        for ($numero = 2; !$productos->slugDisponible($slug, $exceptoId); $numero++) {
            $slug = "$base-$numero";
        }
        return $slug;
    }
}
