<?php
/*
 * El cliente llega aquí desde el enlace del WhatsApp. Variables: $ok, $estado, $reserva, $fecha
 * (dia_semana, dia, mes, texto), $error (titulo, texto) y $ics_url (añadir al calendario).
 */
require __DIR__ . '/partes.php';
abrir(($ok ? 'Reserva confirmada' : 'Reserva') . ' · ' . $s['name'], 'pagina');
?>
<main id="main" class="cont confirmar">
<?php if ($ok): ?>
  <h1 class="titulo"><?= $estado === 'ya' ? 'Ya está confirmada' : '¡Reserva confirmada!' ?></h1>
  <p class="texto-grande">Te esperamos, <?= h(explode(' ', trim($reserva['name']))[0]) ?>.</p>
  <section class="ticket" aria-label="Datos de la reserva">
    <p class="ticket-marca"><?= h($s['name']) ?></p>
    <p class="ticket-fecha"><span><?= $fecha['dia_semana'] ?></span><b><?= $fecha['dia'] ?></b><span><?= $fecha['mes'] ?></span></p>
    <dl>
      <div><dt>Hora</dt><dd><?= h($reserva['time']) ?></dd></div>
      <div><dt>Personas</dt><dd><?= (int)$reserva['people'] ?></dd></div>
      <div class="ancho"><dt>A nombre de</dt><dd><?= h($reserva['name']) ?></dd></div>
    </dl>
    <?php if ($s['address']): ?><p class="nota"><?= h($s['address']) ?><?= $s['city'] ? ', ' . h($s['city']) : '' ?></p><?php endif; ?>
    <p class="nota">Guardamos la mesa <?= (int)$s['late_minutes'] ?> minutos. Si no puedes venir, cancelarla es gratis.</p>
  </section>
  <div class="botones">
    <a class="boton boton-acento" href="<?= h($ics_url) ?>">Añadir al calendario</a>
    <?php if ($s['address']): ?><a class="boton" href="<?= h(maps_href()) ?>" target="_blank" rel="noopener">Cómo llegar</a><?php endif; ?>
    <a class="boton" href="./#carta">Ver la carta</a>
  </div>
  <?php if ($s['phone']): ?><p class="nota">¿Algún cambio? Llámanos al <a href="<?= h(tel_href($s['phone'])) ?>"><?= h($s['phone']) ?></a>.</p><?php endif; ?>
<?php else: ?>
  <h1 class="titulo"><?= h($error['titulo']) ?></h1>
  <p class="texto-grande"><?= h($error['texto']) ?></p>
  <div class="botones">
    <a class="boton boton-acento" href="./#reservar" data-book>Hacer una reserva</a>
    <?php if ($s['phone']): ?><a class="boton" href="<?= h(tel_href($s['phone'])) ?>">Llamar</a><?php endif; ?>
  </div>
<?php endif; ?>
</main>
<?php cerrar();
