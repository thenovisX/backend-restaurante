<?php
declare(strict_types=1);

/*
 * Herramientas para los temas (el diseño de cada web, en web/tema/).
 * El tema pinta el HTML como quiera; desde aquí se ponen las piezas que la web necesita para
 * funcionar y cumplir la ley: SEO, aviso de cookies, iconos de la app, ventana de reserva y SDK.
 */


/** Versión del SDK: súbela si cambias algo de web/sdk/ para que los navegadores lo descarguen de nuevo. */
const SDK_V = '1';

/** Versión de los archivos del tema (la define el tema en tema.php; si no, 1). */
function tema_v(): string
{
    return defined('TEMA_VERSION') ? (string)TEMA_VERSION : '1';
}

/** Ruta de un archivo del tema con su versión, para usar en href/src: asset('estilo.css') → tema/assets/estilo.css?v=… */
function asset(string $file): string
{
    return 'tema/assets/' . ltrim($file, '/') . '?v=' . tema_v();
}

/** Color de un rol del tema (fondo, texto, acento…), con un valor por defecto. */
function tema_color(string $rol, string $def = '#000000'): string
{
    $c = defined('TEMA_COLORES') ? TEMA_COLORES : [];
    return preg_match('/^#[0-9a-f]{6}$/i', $c[$rol] ?? '') ? $c[$rol] : $def;
}

/**
 * Pinta una página del tema: carga web/tema/<vista>.php con las variables que se le pasen.
 * Siempre están disponibles $s (ajustes del restaurante) y $cliente (cliente con sesión o null).
 */
function tema_render(string $vista, array $vars = []): never
{
    if (!preg_match('/^[a-z0-9_-]+$/', $vista)) throw new RuntimeException('Vista no válida');
    $file = dirname(__DIR__) . '/tema/' . $vista . '.php';
    if (!is_file($file)) throw new RuntimeException("Falta el archivo del tema: tema/$vista.php");
    if (!headers_sent()) header('Cache-Control: no-cache, must-revalidate, max-age=0');
    $s = settings();
    $cliente = current_customer();
    extract($vars, EXTR_SKIP);
    require $file;
    exit;
}

/* ---------- Enlaces útiles ---------- */

function tel_href(string $t): string
{
    return 'tel:' . preg_replace('/[^\d+]/', '', $t);
}

function maps_href(): string
{
    $s = settings();
    if ($s['maps_url'] !== '') return social_url('', $s['maps_url']);
    return 'https://www.google.com/maps/search/?api=1&query=' . urlencode(trim($s['name'] . ' ' . $s['address'] . ' ' . $s['city']));
}

function wa_number(string $t): string
{
    $n = preg_replace('/\D/', '', $t);
    return strlen($n) === 9 ? '34' . $n : $n;
}

/** Enlace de WhatsApp del restaurante ('' si no tiene). */
function wa_href(string $text = ''): string
{
    $n = wa_number(settings()['whatsapp']);
    return $n === '' ? '' : 'https://wa.me/' . $n . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

/** Redes sociales configuradas: ['Instagram' => url, …] (solo las que tienen valor). */
function redes(): array
{
    $s = settings();
    return array_filter([
        'Instagram' => social_url('instagram', $s['instagram']),
        'Facebook' => social_url('facebook', $s['facebook']),
        'TikTok' => social_url('tiktok', $s['tiktok']),
        'TripAdvisor' => social_url('tripadvisor', $s['tripadvisor']),
    ]);
}

/**
 * Horario para pintar: [['dia' => 'Lunes', 'texto' => '13:00–16:00', 'cocina' => '13:30–15:30', 'hoy' => bool], …]
 * 'cocina' solo viene si hay horario de cocina activado y ese día es distinto del de apertura ('' si no).
 */
function horario(): array
{
    $s = settings();
    $out = [];
    foreach (WEEKDAYS as $n => $dia) {
        $open = $s['hours'][$n] ?? [];
        $kit = $s['kitchen_hours'][$n] ?? [];
        $cocina = $s['kitchen_on'] && $open && $kit != $open ? ranges_text($kit) : '';
        $out[] = ['dia' => $dia, 'texto' => ranges_text($open), 'cocina' => $cocina === 'Cerrado' ? 'cerrada' : $cocina, 'hoy' => (string)$n === date('N')];
    }
    return $out;
}

/** ¿Abre hoy? Devuelve el texto del horario de hoy o 'Cerrado'. */
function horario_hoy(): string
{
    $s = settings();
    if (in_array(today(), $s['closed_dates'], true)) return 'Cerrado hoy';
    return ranges_text($s['hours'][date('N')] ?? []);
}

/** Políticas de reserva como lista: [['titulo' => …, 'texto' => …], …] (cada línea del panel es «Título: texto»). */
function politicas(): array
{
    $out = [];
    foreach (preg_split('/\R/', (string)settings()['policies']) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        [$t, $txt] = array_pad(explode(':', $line, 2), 2, '');
        $out[] = $txt === '' ? ['titulo' => '', 'texto' => trim($t)] : ['titulo' => trim($t), 'texto' => trim($txt)];
    }
    return $out;
}

/** Icono de un alérgeno (dibujos en sdk/alergenos.svg). */
function al_icon(string $key, int $size = 22, string $label = ''): string
{
    $aria = $label === '' ? 'aria-hidden="true"' : 'role="img" aria-label="' . h($label) . '"';
    return '<svg class="al-ic" width="' . $size . '" height="' . $size . '" ' . $aria . '>'
        . ($label !== '' ? '<title>' . h($label) . '</title>' : '')
        . '<use href="sdk/alergenos.svg?v=' . SDK_V . '#al-' . h($key) . '"></use></svg>';
}

/* ---------- Piezas obligatorias de cada página ---------- */

/**
 * Va dentro de <head>, después de <title>. Pone: descripción y canonical para Google, verificación de
 * Search Console, datos estructurados del restaurante (en la portada), modo de consentimiento de Google,
 * aviso de cookies, manifest e iconos de la app, y los estilos base del SDK.
 */
function head_tags(string $description = ''): void
{
    $s = settings();
    $script = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
    $home = $script === 'index.php';
    $hero = photo_abs(array_key_first(tema_fotos()) ?? '', 1200);
    ?>
<meta name="description" content="<?= h($description ?: ($s['seo_description'] ?: trim($s['name'] . ' · ' . $s['tagline'] . ($s['city'] ? ' en ' . $s['city'] : '') . '. Reserva mesa online y consulta la carta.'))) ?>">
<link rel="canonical" href="<?= h(base_url() . ($home ? '/' : '/' . $script)) ?>">
<?php if ($s['gsc_verify'] !== ''): ?><meta name="google-site-verification" content="<?= h($s['gsc_verify']) ?>">
<?php endif; ?>
<meta property="og:title" content="<?= h($s['name']) ?>">
<meta property="og:description" content="<?= h($s['tagline']) ?>">
<?php if ($hero): ?><meta property="og:image" content="<?= h($hero) ?>">
<?php endif; ?>
<meta name="theme-color" content="<?= h(tema_color('fondo')) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?= h($s['name']) ?>">
<link rel="manifest" href="manifest.php">
<link rel="icon" href="icon.php?s=192" type="image/png">
<link rel="apple-touch-icon" href="icon.php?s=180">
<script>
  window.SITE_TRACKING = <?= json_encode(['ga' => $s['ga_id'], 'gtm' => $s['gtm_id'], 'pixel' => $s['pixel_id']], JSON_HEX_TAG) ?>;
  window.dataLayer = window.dataLayer || [];
  function gtag() { dataLayer.push(arguments); }
  gtag('consent', 'default', { analytics_storage: 'denied', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', wait_for_update: 500 });
</script>
<script src="sdk/consent.js?v=<?= SDK_V ?>" defer></script>
<link rel="stylesheet" href="sdk/restaurante.css?v=<?= SDK_V ?>">
<?php if ($home): ?>
<script type="application/ld+json"><?= json_encode(array_filter([
    '@context' => 'https://schema.org', '@type' => 'Restaurant', 'name' => $s['name'],
    'address' => trim($s['address'] . ', ' . $s['city'], ', '), 'telephone' => $s['phone'],
    'acceptsReservations' => true, 'url' => base_url(), 'image' => $hero,
    'description' => $s['seo_description'] ?: $s['tagline'],
    'openingHoursSpecification' => array_merge(...array_map(fn($d) => array_map(fn($r) => [
        '@type' => 'OpeningHoursSpecification',
        'dayOfWeek' => ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'][(int)$d],
        'opens' => $r[0], 'closes' => $r[1],
    ], $s['hours'][$d] ?? []), array_keys(WEEKDAYS))),
    'sameAs' => array_values(redes()),
    'hasMenu' => base_url() . '/#carta',
]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php endif;
}

/**
 * Va justo antes de </body>. Pone la ventana de reserva (se abre con cualquier elemento data-book),
 * los datos del restaurante para el JavaScript (window.RESTAURANT) y el SDK.
 * Opciones: ['reserva' => false] para no incluir la ventana de reserva (si el tema hace la suya).
 */
function body_end(array $opts = []): void
{
    $s = settings();
    $c = current_customer();
    if ($opts['reserva'] ?? true) include __DIR__ . '/reserva-dialogo.php';
    $data = ['today' => today(), 'daysAhead' => (int)$s['days_ahead'], 'maxPeople' => (int)$s['max_people'], 'name' => $s['name'],
        'hours' => $s['hours'], 'closed' => $s['closed_dates'], 'phone' => $s['phone'], 'customer' => customer_out($c)];
    ?>
<script>window.RESTAURANT = <?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<script src="sdk/restaurante.js?v=<?= SDK_V ?>" defer></script>
    <?php
}

/** Enlace que abre el aviso de cookies para cambiar la elección (obligatorio tenerlo en el pie). */
function cookies_link(string $text = 'Configurar cookies', string $class = ''): string
{
    return '<a href="legal.php#cookies" data-cookies' . ($class ? ' class="' . h($class) . '"' : '') . '>' . h($text) . '</a>';
}

/** Precio en formato español: 12,50 € */
function precio(float $n): string
{
    return number_format($n, 2, ',', '.') . ' €';
}
