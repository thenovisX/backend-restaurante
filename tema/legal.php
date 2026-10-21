<?php
/* Información legal. Variables: $secciones (id, titulo, html). Los textos los genera el backend. */
require __DIR__ . '/partes.php';
abrir('Información legal · ' . $s['name'], 'pagina');
?>
<main id="main" class="cont legal">
  <h1 class="titulo">Información legal</h1>
  <nav class="legal-indice" aria-label="Apartados"><?php foreach ($secciones as $sec): ?><a href="#<?= $sec['id'] ?>"><?= h($sec['titulo']) ?></a><?php endforeach; ?></nav>
  <?php foreach ($secciones as $sec): ?>
    <section id="<?= $sec['id'] ?>">
      <h2><?= h($sec['titulo']) ?></h2>
      <?= $sec['html'] ?>
    </section>
  <?php endforeach; ?>
  <p class="nota">Última actualización: <?= h(date('j/n/Y', strtotime(LEGAL_VERSION))) ?>. Texto de referencia: revísalo con un asesor para adaptarlo a tu negocio.</p>
</main>
<?php cerrar();
