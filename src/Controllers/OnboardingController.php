<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Models\Negocio;
use App\Models\Sede;
use App\Models\Producto;
use App\Models\Servicio;
use App\Models\UsoIA;
use App\Services\ExtractorMenu;
use App\Services\Imagen;

/**
 * El alta del negocio: foto del menú → la IA arma el catálogo → (horario,
 * si es de reservas) → color y llave Bre-B → abierta. Cada paso lee y
 * escribe directo sobre la sede activa, así que el dueño puede cerrar y
 * volver sin perder nada; Sede::siguientePasoOnboarding() dice dónde
 * quedó y es el guarda de cada paso.
 *
 * Solo el dueño hace el alta: un colaborador entra a operar una sede que
 * el dueño ya abrió (y no debería poder cambiar el color o la llave donde
 * entra la plata).
 */
class OnboardingController
{
    private const ORDEN_PASOS = ['foto' => 0, 'productos' => 1, 'horario' => 2, 'pago' => 3];

    /** /panel/onboarding: entrada única que lleva al paso pendiente (login, dashboard). */
    public function continuar(array $parametros): void
    {
        $negocio = $this->exigirDueno();

        if ((int) $negocio['publicada'] === 1) {
            redirigir('/panel');
        }
        redirigir('/panel/onboarding/' . Sede::siguientePasoOnboarding($negocio));
    }

    public function mostrarFoto(array $parametros): void
    {
        $negocio = $this->exigirDueno();

        ver('onboarding/foto', [
            'titulo'        => 'Foto del menú · Veci',
            'negocio'       => $negocio,
            'totalCatalogo' => $this->totalCatalogo($negocio),
            'error'         => flash_obtener('error'),
        ], 'onboarding');
    }

    public function subirFoto(array $parametros): void
    {
        $negocio = $this->exigirDueno();

        // Si el envío completo pasa de post_max_size, PHP descarta todo (ni
        // archivo ni token CSRF) y parecería un "formulario expirado".
        if ($_POST === [] && $_FILES === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            flash_set('error', 'Esa foto pesa demasiado. Tómala otra vez o mándala por WhatsApp a ti mismo y guárdala desde ahí: llega más liviana.');
            redirigir('/panel/onboarding/foto');
        }

        if (!csrf_verificar()) {
            flash_set('error', 'El formulario expiró, intenta de nuevo.');
            redirigir('/panel/onboarding/foto');
        }

        // Un archivo más grande que upload_max_filesize no llega como "muy
        // pesado" sino como un error de subida: antes todos caían en un
        // "No pudimos leer la foto" que no decía qué hacer.
        $archivo = $_FILES['foto'] ?? null;
        $codigo = is_array($archivo) ? (int) $archivo['error'] : UPLOAD_ERR_NO_FILE;
        if ($codigo !== UPLOAD_ERR_OK) {
            flash_set('error', match ($codigo) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Esa foto pesa demasiado. Tómala otra vez o mándala por WhatsApp a ti mismo y guárdala desde ahí: llega más liviana.',
                UPLOAD_ERR_PARTIAL                        => 'La foto no terminó de subir (¿se cayó la señal?). Intenta otra vez.',
                UPLOAD_ERR_NO_FILE                        => 'Elige o toma una foto primero.',
                default                                   => 'No pudimos guardar la foto. Intenta otra vez en un momento.',
            });
            redirigir('/panel/onboarding/foto');
        }

        $tiposPermitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = mime_content_type($archivo['tmp_name']) ?: '';
        if (!isset($tiposPermitidos[$mime])) {
            flash_set('error', 'Sube una imagen JPG, PNG o WEBP.');
            redirigir('/panel/onboarding/foto');
        }
        if ($archivo['size'] > 12 * 1024 * 1024) {
            flash_set('error', 'Esa foto pesa más de 12 MB. Tómala otra vez con la cámara normal (sin modo "alta resolución").');
            redirigir('/panel/onboarding/foto');
        }

        // Se guarda enderezada, reducida a 2000 px y sin metadatos EXIF (ver
        // Imagen). Si no se deja abrir, no se guarda: el original llevaría
        // sus metadatos (y lo que traiga pegado) a una carpeta pública.
        $carpeta = __DIR__ . '/../../public/uploads/menus/';
        $base = 'menu-' . $negocio['id'] . '-' . bin2hex(random_bytes(6));
        $nombreArchivo = $base . '.jpg';
        if (!Imagen::normalizar($archivo['tmp_name'], $carpeta . $nombreArchivo)) {
            flash_set('error', 'No pudimos leer esa foto. Tómala otra vez o prueba con otra imagen.');
            redirigir('/panel/onboarding/foto');
        }

        $this->borrarFotoAnterior($negocio['menu_foto'] ?? null);
        Sede::guardarFotoMenu((int) $negocio['id'], 'uploads/menus/' . $nombreArchivo);
        $_SESSION['onb_foto_por_leer'][(int) $negocio['id']] = 'uploads/menus/' . $nombreArchivo;

        redirigir('/panel/onboarding/productos');
    }

    /**
     * El paso del catálogo tiene tres caras:
     *   - leer:    hay una foto sin leer → el escáner y "Leer mi menú".
     *   - a_mano:  catálogo vacío y el dueño eligió escribirlo él (o no
     *              tiene foto, o se le acabaron las lecturas del mes).
     *   - revisar: la lista para corregir, sumar ítems u otra foto.
     */
    public function mostrarProductos(array $parametros): void
    {
        $negocio = $this->exigirDueno();
        $sedeId = (int) $negocio['id'];
        $esReservas = $negocio['tipo_negocio'] === 'reservas';
        $items = $esReservas ? Servicio::listarPorSede($sedeId) : Producto::listarPorSede($sedeId);
        $aMano = isset($_GET['a_mano']);

        if (isset($_GET['omitir_foto'])) {
            unset($_SESSION['onb_foto_por_leer'][$sedeId]);
            redirigir('/panel/onboarding/productos');
        }
        if ($items === [] && empty($negocio['menu_foto']) && !$aMano) {
            redirigir('/panel/onboarding/foto');
        }

        $porLeer = !empty($negocio['menu_foto'])
            && ($items === [] || ($_SESSION['onb_foto_por_leer'][$sedeId] ?? null) === $negocio['menu_foto']);
        $modo = $porLeer && !$aMano ? 'leer' : ($items === [] ? 'a_mano' : 'revisar');

        ver($esReservas ? 'onboarding/servicios' : 'onboarding/productos', [
            'titulo'                      => ($esReservas ? 'Tus servicios' : 'Tu carta') . ' · Veci',
            'negocio'                     => $negocio,
            $esReservas ? 'servicios' : 'productos' => $items,
            'modo'                        => $modo,
            'agregando'                   => $aMano,
            'error'                       => flash_obtener('error'),
        ], 'onboarding');
    }

    public function analizar(array $parametros): void
    {
        $negocio = $this->exigirDueno();

        if (!csrf_verificar() || empty($negocio['menu_foto'])) {
            redirigir('/panel/onboarding/productos');
        }

        $sedeId = (int) $negocio['id'];
        $negocioId = (int) $negocio['negocio_id'];
        $esReservas = $negocio['tipo_negocio'] === 'reservas';
        $rutaImagen = __DIR__ . '/../../public/' . $negocio['menu_foto'];
        $hayCatalogo = $this->totalCatalogo($negocio) > 0;

        // El plan Gratis limita cuántas lecturas reales con IA puede pedir
        // un negocio por mes (planes.limite_ia_mes; null = ilimitado). Al
        // llegar al tope no se inventa nada: se le abre el editor a mano.
        $limiteIa = $negocio['limite_ia_mes'] ?? null;
        if ($limiteIa !== null && UsoIA::contarEsteMesPorNegocio($negocioId) >= (int) $limiteIa) {
            unset($_SESSION['onb_foto_por_leer'][$sedeId]);
            flash_set('aviso', "Ya usaste tus {$limiteIa} lecturas con foto de este mes (plan Gratis). Escribe "
                . ($esReservas ? 'tus servicios' : 'tu carta') . ' aquí: toma un par de minutos. Con el plan Barrio las lecturas son ilimitadas.');
            redirigir('/panel/onboarding/productos' . ($hayCatalogo ? '' : '?a_mano=1'));
        }

        // La lectura puede tardar más que el límite por defecto de PHP.
        set_time_limit(150);
        $lectura = $esReservas ? ExtractorMenu::extraerServicios($rutaImagen) : ExtractorMenu::extraer($rutaImagen);

        if ($lectura['estado'] === 'fallo') {
            // La foto sigue "por leer": el escáner vuelve a ofrecer intentarlo.
            flash_set('error', 'No pudimos leer la foto esta vez (puede ser la conexión). Intenta otra vez en un momento, o escribe '
                . ($esReservas ? 'tus servicios' : 'tu carta') . ' a mano.');
            redirigir('/panel/onboarding/productos');
        }

        unset($_SESSION['onb_foto_por_leer'][$sedeId]);

        if ($lectura['estado'] === 'sin_llave') {
            $items = ExtractorMenu::catalogoDeEjemplo($negocio['tipo_negocio']);
        } else {
            // Lectura real (con o sin resultados): cuenta para el límite del plan.
            UsoIA::registrar($negocioId, $sedeId);
            $items = $lectura['items'];
        }

        if ($items === []) {
            flash_set('error', 'No encontramos ' . ($esReservas ? 'servicios' : 'productos')
                . ' con precio en esa foto. Prueba con una más de cerca y con buena luz, o escríbelos a mano.');
            redirigir('/panel/onboarding/foto');
        }

        $agregados = $this->agregarSinRepetir($negocio, $items);
        $cosa = $esReservas ? 'servicio' : 'producto';

        if ($agregados === 0) {
            flash_set('aviso', 'Esa foto no trajo nada nuevo: todo lo que leímos ya estaba en tu ' . ($esReservas ? 'lista.' : 'carta.'));
        } elseif ($lectura['estado'] === 'sin_llave') {
            flash_set('aviso', 'Modo de prueba: esta instalación no tiene conectada la lectura con IA, así que te dejamos '
                . ($esReservas ? 'una lista' : 'una carta') . ' de ejemplo. Cámbiala por lo tuyo antes de abrir.');
        } else {
            $sinPrecio = count(array_filter($items, fn ($item) => (int) $item['precio'] === 0));
            flash_set('ok', 'Veci leyó ' . $agregados . ' ' . $cosa . ($agregados === 1 ? '' : 's') . ' de tu foto'
                . ($hayCatalogo ? ' y ' . ($agregados === 1 ? 'lo sumó' : 'los sumó') . ' a lo que ya tenías' : '') . '.'
                . ($sinPrecio > 0 ? ' Ponle precio a los que quedaron en blanco: no se alcanzaba a leer.' : ''));
        }

        redirigir('/panel/onboarding/productos');
    }

    /**
     * Guarda de una vez todo lo que el dueño corrigió en la lista y pasa al
     * siguiente paso. Una fila sin nombre o sin precio no se salta en
     * silencio: se guardan las demás y se le dice cuál falta.
     * Solo toca filas de esta sede (el WHERE sede_id de los modelos).
     */
    public function guardarCatalogo(array $parametros): void
    {
        $negocio = $this->exigirDueno();
        $sedeId = (int) $negocio['id'];
        $esReservas = $negocio['tipo_negocio'] === 'reservas';

        if (!csrf_verificar()) {
            redirigir('/panel/onboarding/productos');
        }

        $incompletos = [];
        $filas = $_POST['items'] ?? [];
        foreach (is_array($filas) ? $filas : [] as $id => $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $actual = $esReservas ? Servicio::buscar((int) $id, $sedeId) : Producto::buscar((int) $id, $sedeId);
            if ($actual === null) {
                continue; // la quitaron desde otra pestaña
            }
            $nombre = mb_substr(trim((string) ($fila['nombre'] ?? '')), 0, 120);
            $precio = min(99_999_999, dinero_desde_texto((string) ($fila['precio'] ?? '')));
            if ($nombre === '' || $precio <= 0) {
                $incompletos[] = $nombre !== '' ? $nombre : (string) $actual['nombre'];
                continue;
            }
            if ($esReservas) {
                $duracion = max(5, min(480, (int) ($fila['duracion_min'] ?? 30)));
                Servicio::actualizar((int) $id, $sedeId, $nombre, $precio, $duracion);
                if (isset($fila['deposito_tipo'])) {
                    Servicio::actualizarDeposito((int) $id, $sedeId, (string) $fila['deposito_tipo'], dinero_desde_texto((string) ($fila['deposito_valor'] ?? '')));
                }
            } else {
                $categoria = mb_substr(trim((string) ($fila['categoria'] ?? '')), 0, 60);
                // La descripción no se edita en este paso: se conserva la que tenga.
                Producto::actualizar((int) $id, $sedeId, $nombre, $precio, $categoria !== '' ? $categoria : $actual['categoria'], $actual['descripcion'] ?? null);
            }
        }

        if ($incompletos !== []) {
            $nombres = implode(', ', array_map(fn ($n) => '«' . $n . '»', array_slice($incompletos, 0, 3)))
                . (count($incompletos) > 3 ? ' y ' . (count($incompletos) - 3) . ' más' : '');
            flash_set('error', 'Guardamos tus cambios, pero falta el precio (o el nombre) de ' . $nombres
                . '. Complétalo o quítalo de la ' . ($esReservas ? 'lista' : 'carta') . ' para seguir.');
            redirigir('/panel/onboarding/productos');
        }

        redirigir($esReservas ? '/panel/onboarding/horario' : '/panel/onboarding/pago');
    }

    public function mostrarHorario(array $parametros): void
    {
        $negocio = $this->exigirDueno();

        if ($negocio['tipo_negocio'] !== 'reservas') {
            redirigir('/panel/onboarding/pago');
        }
        $this->exigirPaso($negocio, 'horario');

        ver('onboarding/horario', [
            'titulo'  => 'Tu horario de atención · Veci',
            'negocio' => $negocio,
            'horario' => Sede::horario($negocio),
            'error'   => flash_obtener('error'),
        ], 'onboarding');
    }

    public function guardarHorario(array $parametros): void
    {
        $negocio = $this->exigirDueno();

        if (!csrf_verificar()) {
            redirigir('/panel/onboarding/horario');
        }

        // Sin ningún día abierto, mostrarPago() devolvía aquí sin decir por
        // qué: el dueño tocaba "Continuar" y volvía a la misma pantalla.
        $avisos = [];
        $horario = Sede::horarioDesdePost($_POST, $avisos);
        if ($horario === []) {
            flash_set('error', 'Abre al menos un día (con la hora de cierre después de la de apertura) para que tus clientes puedan reservar.');
            redirigir('/panel/onboarding/horario');
        }

        Sede::guardarHorario((int) $negocio['id'], $horario, Sede::intervaloDesdePost($_POST));

        // Una pausa mal puesta no se guarda a medias: se vuelve a mostrar
        // el horario (ya guardado sin ella) con el aviso, para corregirla.
        $aviso = aviso_pausas_invalidas($avisos);
        if ($aviso !== null) {
            flash_set('error', $aviso);
            redirigir('/panel/onboarding/horario');
        }

        redirigir('/panel/onboarding/pago');
    }

    public function mostrarPago(array $parametros): void
    {
        $negocio = $this->exigirDueno();
        $this->exigirPaso($negocio, 'pago');
        $esReservas = $negocio['tipo_negocio'] === 'reservas';

        // Si la llave no pasó la validación, se le devuelve lo que escribió
        // (no el valor guardado) para que lo corrija en vez de reescribirlo.
        $previa = json_decode((string) flash_obtener('llave_previa'), true);

        ver('onboarding/pago', [
            'titulo'         => 'Abre tu tienda · Veci',
            'negocio'        => $negocio,
            'tieneAnticipos' => $esReservas && Servicio::tieneAnticipoActivo((int) $negocio['id']),
            'error'          => flash_obtener('error'),
            'llavePrevia'    => is_array($previa) ? $previa : null,
        ], 'onboarding');
    }

    public function publicar(array $parametros): void
    {
        $negocio = $this->exigirDueno();

        if (!csrf_verificar()) {
            flash_set('error', 'El formulario expiró, intenta de nuevo.');
            redirigir('/panel/onboarding/pago');
        }

        // El color se guarda primero: si la llave viene mal, al volver a la
        // pantalla el dueño no pierde el que ya había elegido.
        Negocio::actualizarColor((int) $negocio['negocio_id'], (string) ($_POST['color_marca'] ?? ''));

        // Los mismos requisitos que para ver esta pantalla: un POST directo
        // (o desde una pestaña vieja) no puede abrir una tienda vacía.
        $this->exigirPaso($negocio, 'pago');
        // Ni una sede que el plan ya no cubre (el plan bajó después de crearla).
        if (!Sede::dentroDelCupo($negocio)) {
            flash_set('error', 'Tu plan actual no incluye esta sede: sube de plan o agrega una sede extra para abrir su tienda.');
            redirigir('/panel/plan');
        }

        $tipo = (string) ($_POST['llave_tipo'] ?? 'celular');
        if (!in_array($tipo, ['celular', 'cedula', 'correo'], true)) {
            $tipo = 'celular';
        }
        $escrita = (string) ($_POST['llave_valor'] ?? '');
        $llave = llave_breb_normalizada($tipo, $escrita);
        if ($llave === null) {
            flash_set('error', match ($tipo) {
                'celular' => 'Revisa la llave: un celular tiene 10 dígitos y empieza por 3.',
                'cedula'  => 'Revisa la llave: una cédula tiene entre 5 y 10 dígitos, solo números.',
                default   => 'Revisa la llave: ese correo no parece completo.',
            });
            flash_set('llave_previa', (string) json_encode(['tipo' => $tipo, 'valor' => mb_substr($escrita, 0, 120)], JSON_UNESCAPED_UNICODE));
            redirigir('/panel/onboarding/pago');
        }

        Sede::guardarLlaveBreB((int) $negocio['id'], $tipo, $llave);
        Sede::publicar((int) $negocio['id']);

        // Redirige en vez de pintar la pantalla aquí mismo: recargar
        // "¡Ya abriste!" no debe reenviar el formulario.
        redirigir('/panel/onboarding/abierta');
    }

    public function mostrarAbierta(array $parametros): void
    {
        $negocio = $this->exigirDueno();

        if ((int) $negocio['publicada'] !== 1) {
            redirigir('/panel/onboarding');
        }

        ver('onboarding/publicada', [
            'titulo'        => '¡Ya abriste! · Veci',
            'negocio'       => $negocio,
            'totalCatalogo' => $this->totalCatalogo($negocio),
        ], 'onboarding');
    }

    private function exigirDueno(): array
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);

        return $negocio;
    }

    /** Devuelve al paso pendiente si este todavía no corresponde, diciendo por qué cuando no es obvio. */
    private function exigirPaso(array $negocio, string $paso): void
    {
        $pendiente = Sede::siguientePasoOnboarding($negocio);
        if (self::ORDEN_PASOS[$pendiente] >= self::ORDEN_PASOS[$paso]) {
            return;
        }
        if ($pendiente === 'productos' && $this->totalCatalogo($negocio) > 0) {
            flash_set('error', 'Hay ' . ($negocio['tipo_negocio'] === 'reservas' ? 'servicios' : 'productos')
                . ' sin precio. Pónselo (o quítalos) para poder abrir.');
        }
        redirigir('/panel/onboarding/' . $pendiente);
    }

    private function totalCatalogo(array $negocio): int
    {
        return $negocio['tipo_negocio'] === 'reservas'
            ? Servicio::contarPorSede((int) $negocio['id'])
            : Producto::contarPorSede((int) $negocio['id']);
    }

    /**
     * Crea los ítems leídos que no estén ya en el catálogo (mismo nombre, sin
     * importar mayúsculas): leer una segunda página, o tocar "Leer" dos
     * veces, no duplica nada.
     *
     * @param array<int, array<string, mixed>> $items
     */
    private function agregarSinRepetir(array $negocio, array $items): int
    {
        $sedeId = (int) $negocio['id'];
        $esReservas = $negocio['tipo_negocio'] === 'reservas';
        $existentes = $esReservas ? Servicio::listarPorSede($sedeId) : Producto::listarPorSede($sedeId);
        $vistos = [];
        foreach ($existentes as $existente) {
            $vistos[mb_strtolower((string) $existente['nombre'])] = true;
        }

        $agregados = 0;
        foreach ($items as $item) {
            $clave = mb_strtolower($item['nombre']);
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            if ($esReservas) {
                Servicio::crear($sedeId, $item['nombre'], $item['precio'], $item['duracion_min']);
            } else {
                Producto::crear($sedeId, $item['nombre'], $item['precio'], $item['categoria'], $item['descripcion'] ?? null);
            }
            $agregados++;
        }

        return $agregados;
    }

    /** Borra del disco la foto de menú reemplazada (solo si de verdad está en uploads/menus). */
    private function borrarFotoAnterior(?string $rutaRelativa): void
    {
        if ($rutaRelativa === null || $rutaRelativa === '') {
            return;
        }
        $carpeta = realpath(__DIR__ . '/../../public/uploads/menus');
        $archivo = realpath(__DIR__ . '/../../public/' . $rutaRelativa);
        if ($carpeta !== false && $archivo !== false && str_starts_with($archivo, $carpeta . DIRECTORY_SEPARATOR)) {
            @unlink($archivo);
        }
    }
}
