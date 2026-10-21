<?php
declare(strict_types=1);
/* Página a la que llega el cliente desde el enlace del WhatsApp para confirmar su reserva. */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/pagina.php';
if (!installed()) { header('Location: install.php'); exit; }

$s = settings();
$id = (int)($_GET['r'] ?? 0);
$token = (string)($_GET['t'] ?? '');

// Archivo para añadir la reserva al calendario del móvil (.ics)
if (isset($_GET['ics'])) {
    $row = q('SELECT * FROM reservations WHERE id = ?', [$id])->fetch();
    if (!$row || !preg_match('/^[a-f0-9]{32}$/', $token) || !hash_equals(dec($row['verify_token']), $token) || $row['status'] !== 'confirmada') {
        http_response_code(404);
        exit('Reserva no encontrada');
    }
    $r = res_out($row);
    $start = strtotime($r['date'] . ' ' . $r['time']);
    $fmt = fn(int $t) => gmdate('Ymd\THis\Z', $t);
    $esc = fn(string $v) => str_replace(["\\", ';', ',', "\n"], ["\\\\", '\;', '\,', '\n'], $v);
    $loc = trim($s['address'] . ', ' . $s['city'], ', ');
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="reserva-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($s['name'])) . '.ics"');
    echo implode("\r\n", [
        'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//' . $esc($s['name']) . '//Reservas//ES', 'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        'UID:reserva-' . $r['id'] . '@' . preg_replace('/[^a-z0-9.-]/i', '', $_SERVER['HTTP_HOST'] ?? 'web'),
        'DTSTAMP:' . $fmt(time()),
        'DTSTART:' . $fmt($start),
        'DTEND:' . $fmt($start + 2 * 3600),
        'SUMMARY:' . $esc('Reserva en ' . $s['name'] . ' (' . $r['people'] . ' pers.)'),
        'LOCATION:' . $esc($loc),
        'DESCRIPTION:' . $esc('Mesa para ' . $r['people'] . ' a nombre de ' . $r['name'] . '.' . ($s['phone'] ? ' Teléfono del restaurante: ' . $s['phone'] : '')),
        'BEGIN:VALARM', 'TRIGGER:-PT2H', 'ACTION:DISPLAY', 'DESCRIPTION:' . $esc('Reserva en ' . $s['name']), 'END:VALARM',
        'END:VEVENT', 'END:VCALENDAR', '',
    ]);
    exit;
}

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
[$state, $r] = confirm_by_customer($id, $token);
$ok = in_array($state, ['confirmada', 'ya'], true);

$errores = [
    'cancelada' => ['Esta reserva está cancelada', 'Si quieres venir, haz una reserva nueva: tardas un minuto.'],
    'rechazada' => ['No pudimos atender esta reserva', 'Prueba con otro día u otra hora.'],
    'pasada'    => ['Este enlace ha caducado', 'La fecha de la reserva ya ha pasado.'],
    'invalido'  => ['Este enlace no funciona', 'Puede que se haya copiado incompleto. Ábrelo otra vez desde el WhatsApp o haz una reserva nueva.'],
];
$fecha = null;
if ($ok) {
    $ts = strtotime($r['date'] . ' ' . $r['time']);
    $dias = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
    $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $fecha = ['dia_semana' => $dias[(int)date('N', $ts)], 'dia' => (int)date('j', $ts), 'mes' => $meses[(int)date('n', $ts)], 'texto' => fmt_date_es($r['date'])];
}

tema_render('confirmar', [
    'estado' => $state,                       // confirmada, ya, cancelada, rechazada, pasada, invalido
    'ok' => $ok,                              // true si la reserva está confirmada (confirmada o ya)
    'reserva' => $ok ? $r : null,             // id, name, date, time, people…
    'fecha' => $fecha,                        // dia_semana, dia, mes, texto
    'error' => $ok ? null : ['titulo' => $errores[$state][0], 'texto' => $errores[$state][1]],
    'ics_url' => $ok ? 'confirmar.php?ics=1&r=' . (int)$r['id'] . '&t=' . $token : '',
]);
