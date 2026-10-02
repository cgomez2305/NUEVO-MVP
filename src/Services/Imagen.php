<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Normaliza las fotos que suben los dueños (menú, lista de servicios):
 * las endereza según la orientación EXIF del celular, las reduce a un
 * lado máximo y las vuelve a guardar como JPEG.
 *
 * Por qué: una foto de celular pesa 3–6 MB y mide 4000 px. Así como
 * llega, la API de Claude la rechaza (más de 5 MB) y el lector caía
 * en silencio al catálogo de ejemplo; además se mostraba de lado en la
 * pantalla de lectura y cargaba lenta con datos móviles. Re-codificar
 * de paso borra los metadatos EXIF (incluida la ubicación GPS de quien
 * tomó la foto), que nunca deberían quedar públicos en uploads/.
 */
class Imagen
{
    /**
     * Escribe en $destino una versión JPEG enderezada y reducida de $origen.
     * Devuelve false si GD no está disponible o la imagen no se puede leer;
     * en ese caso quien llama decide si guarda el original tal cual.
     */
    public static function normalizar(string $origen, string $destino, int $ladoMax = 2000, int $calidad = 85): bool
    {
        $imagen = self::abrir($origen);
        if ($imagen === null) {
            return false;
        }

        $imagen = self::enderezar($imagen, $origen);
        $imagen = self::reducir($imagen, $ladoMax);

        return imagejpeg($imagen, $destino, $calidad);
    }

    private static function abrir(string $ruta): ?\GdImage
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }
        $datos = @file_get_contents($ruta);
        if ($datos === false) {
            return null;
        }
        $imagen = @imagecreatefromstring($datos);
        if ($imagen === false) {
            return null;
        }

        // Un PNG con transparencia pasado a JPEG queda con fondo negro:
        // se aplana sobre blanco, que es como se ve una carta impresa.
        if (!imageistruecolor($imagen) || imagecolortransparent($imagen) >= 0 || self::tieneAlfa($ruta)) {
            $plana = imagecreatetruecolor(imagesx($imagen), imagesy($imagen));
            imagefill($plana, 0, 0, imagecolorallocate($plana, 255, 255, 255));
            imagecopy($plana, $imagen, 0, 0, 0, 0, imagesx($imagen), imagesy($imagen));
            $imagen = $plana;
        }

        return $imagen;
    }

    private static function tieneAlfa(string $ruta): bool
    {
        $mime = mime_content_type($ruta) ?: '';
        return $mime === 'image/png' || $mime === 'image/webp';
    }

    /** Aplica la orientación EXIF (el celular guarda la foto "de lado" y anota cómo girarla). */
    private static function enderezar(\GdImage $imagen, string $ruta): \GdImage
    {
        if (!function_exists('exif_read_data') || (mime_content_type($ruta) ?: '') !== 'image/jpeg') {
            return $imagen;
        }
        $exif = @exif_read_data($ruta);
        $orientacion = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        $grados = match ($orientacion) {
            3, 4 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };
        if ($grados !== 0) {
            $girada = imagerotate($imagen, $grados, 0);
            if ($girada !== false) {
                $imagen = $girada;
            }
        }
        if (in_array($orientacion, [2, 4, 5, 7], true)) {
            imageflip($imagen, IMG_FLIP_HORIZONTAL);
        }

        return $imagen;
    }

    private static function reducir(\GdImage $imagen, int $ladoMax): \GdImage
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $lado = max($ancho, $alto);
        if ($lado <= $ladoMax) {
            return $imagen;
        }

        $escala = $ladoMax / $lado;
        $reducida = imagescale($imagen, max(1, (int) round($ancho * $escala)), max(1, (int) round($alto * $escala)), IMG_BICUBIC);
        return $reducida === false ? $imagen : $reducida;
    }
}
