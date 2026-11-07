<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Modelo de la tabla productos.
 *
 * Un producto (ej. "Superstar") tiene varias VARIANTES (talla + color), y el stock
 * se controla en cada variante. El stock total de un producto es la suma de sus variantes.
 */
final class Producto extends Model
{
    /**
     * La oferta está vigente si hay precio de oferta y hoy está dentro de sus fechas
     * (una fecha vacía significa "sin límite"). Se calcula en SQL para que el catálogo,
     * el orden por precio y los pedidos usen siempre el mismo criterio.
     */
    public const SQL_OFERTA_VIGENTE = '(p.precio_oferta IS NOT NULL
        AND (p.oferta_inicio IS NULL OR p.oferta_inicio <= NOW())
        AND (p.oferta_fin IS NULL OR p.oferta_fin >= NOW()))';

    /** Precio que paga el cliente hoy. */
    public const SQL_PRECIO_VIGENTE = 'CASE WHEN ' . self::SQL_OFERTA_VIGENTE . ' THEN p.precio_oferta ELSE p.precio END';

    /** Ordenamientos permitidos: el usuario elige la clave, nunca escribe SQL. */
    private const ORDENES = [
        'recientes' => 'p.creado_en DESC, p.id DESC',
        'nombre' => 'p.nombre ASC',
        'precio_asc' => 'precio_vigente ASC, p.id ASC',
        'precio_desc' => 'precio_vigente DESC, p.id ASC',
        'descuento' => 'en_oferta DESC, (p.precio - precio_vigente) / p.precio DESC, p.id ASC',
        'catalogo' => 'p.id ASC',
    ];

    /**
     * Lista paginada con filtros.
     *
     * @param array{marca?: ?string, q?: ?string, talla?: ?int, color?: ?int, orden?: ?string, estado?: string, oferta?: bool} $filtros
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function listar(array $filtros, int $pagina, int $porPagina): array
    {
        $condiciones = [];
        $parametros = [];

        // estado: 'activos' (público), 'inactivos' o 'todos' (solo personal de la tienda)
        $estado = $filtros['estado'] ?? 'activos';
        if ($estado === 'activos') {
            $condiciones[] = 'p.activo = 1';
        } elseif ($estado === 'inactivos') {
            $condiciones[] = 'p.activo = 0';
        }

        if (!empty($filtros['marca'])) {
            $condiciones[] = 'm.slug = :marca';
            $parametros['marca'] = $filtros['marca'];
        }

        if (!empty($filtros['q'])) {
            // Se escapan % y _ para que se busquen como texto literal.
            $condiciones[] = 'p.nombre LIKE :busqueda';
            $parametros['busqueda'] = '%' . addcslashes($filtros['q'], '%_\\') . '%';
        }

        // Filtros por talla/color: solo productos que tengan esa variante con stock.
        if (!empty($filtros['talla'])) {
            $condiciones[] = 'EXISTS (SELECT 1 FROM variantes vt
                                       WHERE vt.producto_id = p.id AND vt.talla_id = :talla
                                         AND vt.activo = 1 AND vt.stock > 0)';
            $parametros['talla'] = $filtros['talla'];
        }

        if (!empty($filtros['color'])) {
            $condiciones[] = 'EXISTS (SELECT 1 FROM variantes vc
                                       WHERE vc.producto_id = p.id AND vc.color_id = :color
                                         AND vc.activo = 1 AND vc.stock > 0)';
            $parametros['color'] = $filtros['color'];
        }

        if (!empty($filtros['oferta'])) {
            $condiciones[] = self::SQL_OFERTA_VIGENTE;
        }

        $where = $condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones);
        $orden = self::ORDENES[$filtros['orden'] ?? ''] ?? self::ORDENES['catalogo'];

        $total = (int) $this->obtenerUno(
            "SELECT COUNT(*) AS total FROM productos p JOIN marcas m ON m.id = p.marca_id $where",
            $parametros
        )['total'];

        $filas = $this->obtenerTodos(
            'SELECT p.id, p.nombre, p.slug, p.precio, p.precio_oferta, p.oferta_fin, p.activo,
                    ' . self::SQL_PRECIO_VIGENTE . ' AS precio_vigente,
                    ' . self::SQL_OFERTA_VIGENTE . " AS en_oferta,
                    m.id AS marca_id, m.nombre AS marca_nombre, m.slug AS marca_slug,
                    (SELECT i.ruta FROM producto_imagenes i
                      WHERE i.producto_id = p.id ORDER BY i.orden, i.id LIMIT 1) AS imagen,
                    (SELECT COALESCE(SUM(v.stock), 0) FROM variantes v
                      WHERE v.producto_id = p.id AND v.activo = 1) AS stock_total,
                    (SELECT COUNT(*) FROM variantes v2 WHERE v2.producto_id = p.id) AS total_variantes
               FROM productos p
               JOIN marcas m ON m.id = p.marca_id
               $where
              ORDER BY $orden
              LIMIT :limite OFFSET :desplazamiento",
            $parametros + ['limite' => $porPagina, 'desplazamiento' => ($pagina - 1) * $porPagina]
        );

        return ['items' => array_map(self::normalizarResumen(...), $filas), 'total' => $total];
    }

    /**
     * Detalle completo: marca, imágenes y variantes con su talla, color y stock.
     *
     * @return array<string, mixed>|null
     */
    public function buscar(int|string $idOSlug, bool $incluirInactivos): ?array
    {
        $columna = is_int($idOSlug) ? 'p.id' : 'p.slug';
        $producto = $this->obtenerUno(
            "SELECT p.id, p.nombre, p.slug, p.descripcion, p.precio, p.activo, p.creado_en,
                    p.precio_oferta, p.oferta_inicio, p.oferta_fin,
                    " . self::SQL_PRECIO_VIGENTE . ' AS precio_vigente,
                    ' . self::SQL_OFERTA_VIGENTE . " AS en_oferta,
                    m.id AS marca_id, m.nombre AS marca_nombre, m.slug AS marca_slug
               FROM productos p
               JOIN marcas m ON m.id = p.marca_id
              WHERE $columna = :valor",
            ['valor' => $idOSlug]
        );

        if ($producto === null || (!$producto['activo'] && !$incluirInactivos)) {
            return null;
        }

        $id = (int) $producto['id'];

        $imagenes = array_map(static fn (array $imagen): array => [
            'id' => (int) $imagen['id'],
            'ruta' => $imagen['ruta'],
            'color_id' => $imagen['color_id'] === null ? null : (int) $imagen['color_id'],
            'orden' => (int) $imagen['orden'],
        ], $this->obtenerTodos(
            'SELECT id, ruta, color_id, orden FROM producto_imagenes
              WHERE producto_id = :id ORDER BY orden, id',
            ['id' => $id]
        ));

        $soloActivas = $incluirInactivos ? '' : 'AND v.activo = 1';
        $variantes = array_map(static fn (array $variante): array => [
            'id' => (int) $variante['id'],
            'sku' => $variante['sku'],
            'stock' => (int) $variante['stock'],
            'activo' => (bool) $variante['activo'],
            'talla' => ['id' => (int) $variante['talla_id'], 'valor' => self::formatearTalla($variante['talla_valor'])],
            'color' => ['id' => (int) $variante['color_id'], 'nombre' => $variante['color_nombre'], 'hex' => $variante['color_hex']],
        ], $this->obtenerTodos(
            "SELECT v.id, v.sku, v.stock, v.activo,
                    t.id AS talla_id, t.valor AS talla_valor,
                    c.id AS color_id, c.nombre AS color_nombre, c.hex AS color_hex
               FROM variantes v
               JOIN tallas t ON t.id = v.talla_id
               JOIN colores c ON c.id = v.color_id
              WHERE v.producto_id = :id $soloActivas
              ORDER BY c.nombre, t.orden",
            ['id' => $id]
        ));

        $stockTotal = array_sum(array_map(
            static fn (array $variante): int => $variante['activo'] ? $variante['stock'] : 0,
            $variantes
        ));

        return [
            'id' => $id,
            'nombre' => $producto['nombre'],
            'slug' => $producto['slug'],
            'descripcion' => $producto['descripcion'],
            // "precio" es lo que se paga hoy; "precio_regular" es el precio sin oferta.
            'precio' => (float) $producto['precio_vigente'],
            'precio_regular' => (float) $producto['precio'],
            'en_oferta' => (bool) $producto['en_oferta'],
            'precio_oferta' => $producto['precio_oferta'] === null ? null : (float) $producto['precio_oferta'],
            'oferta_inicio' => $producto['oferta_inicio'],
            'oferta_fin' => $producto['oferta_fin'],
            'activo' => (bool) $producto['activo'],
            'creado_en' => $producto['creado_en'],
            'marca' => [
                'id' => (int) $producto['marca_id'],
                'nombre' => $producto['marca_nombre'],
                'slug' => $producto['marca_slug'],
            ],
            'imagenes' => $imagenes,
            'variantes' => $variantes,
            'stock_total' => $stockTotal,
        ];
    }

    public function existe(int $id): bool
    {
        return $this->obtenerUno('SELECT 1 FROM productos WHERE id = :id', ['id' => $id]) !== null;
    }

    /** @return array{id: int, slug: string}|null */
    public function buscarBasico(int $id): ?array
    {
        $fila = $this->obtenerUno('SELECT id, slug FROM productos WHERE id = :id', ['id' => $id]);
        return $fila ? ['id' => (int) $fila['id'], 'slug' => $fila['slug']] : null;
    }

    public function slugDisponible(string $slug, ?int $exceptoId = null): bool
    {
        return $this->obtenerUno(
            'SELECT 1 FROM productos WHERE slug = :slug AND id <> :excepto',
            ['slug' => $slug, 'excepto' => $exceptoId ?? 0]
        ) === null;
    }

    /** @param array<string, mixed> $datos marca_id, nombre, slug, descripcion, precio, precio_oferta, oferta_inicio, oferta_fin, activo */
    public function crear(array $datos): int
    {
        $this->ejecutar(
            'INSERT INTO productos (marca_id, nombre, slug, descripcion, precio, precio_oferta, oferta_inicio, oferta_fin, activo)
             VALUES (:marca_id, :nombre, :slug, :descripcion, :precio, :precio_oferta, :oferta_inicio, :oferta_fin, :activo)',
            self::parametrosEscritura($datos)
        );
        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $datos */
    public function actualizar(int $id, array $datos): void
    {
        $this->ejecutar(
            'UPDATE productos
                SET marca_id = :marca_id, nombre = :nombre, slug = :slug, descripcion = :descripcion,
                    precio = :precio, precio_oferta = :precio_oferta, oferta_inicio = :oferta_inicio,
                    oferta_fin = :oferta_fin, activo = :activo
              WHERE id = :id',
            self::parametrosEscritura($datos) + ['id' => $id]
        );
    }

    /** Los productos no se borran (pueden estar en pedidos): se desactivan. */
    public function cambiarEstado(int $id, bool $activo): void
    {
        $this->ejecutar('UPDATE productos SET activo = :activo WHERE id = :id', ['activo' => $activo ? 1 : 0, 'id' => $id]);
    }

    private static function parametrosEscritura(array $datos): array
    {
        return [
            'marca_id' => $datos['marca_id'],
            'nombre' => $datos['nombre'],
            'slug' => $datos['slug'],
            'descripcion' => $datos['descripcion'],
            // DECIMAL se envía como texto para no perder precisión.
            'precio' => number_format($datos['precio'], 2, '.', ''),
            'precio_oferta' => $datos['precio_oferta'] === null ? null : number_format($datos['precio_oferta'], 2, '.', ''),
            'oferta_inicio' => $datos['oferta_inicio'],
            'oferta_fin' => $datos['oferta_fin'],
            'activo' => $datos['activo'] ? 1 : 0,
        ];
    }

    private static function normalizarResumen(array $fila): array
    {
        return [
            'id' => (int) $fila['id'],
            'nombre' => $fila['nombre'],
            'slug' => $fila['slug'],
            'precio' => (float) $fila['precio_vigente'],
            'precio_regular' => (float) $fila['precio'],
            'en_oferta' => (bool) $fila['en_oferta'],
            'oferta_fin' => $fila['en_oferta'] ? $fila['oferta_fin'] : null,
            'activo' => (bool) $fila['activo'],
            'imagen' => $fila['imagen'],
            'stock_total' => (int) $fila['stock_total'],
            'total_variantes' => (int) $fila['total_variantes'],
            'marca' => ['id' => (int) $fila['marca_id'], 'nombre' => $fila['marca_nombre'], 'slug' => $fila['marca_slug']],
        ];
    }
}
