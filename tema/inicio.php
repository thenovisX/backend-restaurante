<?php
/*
 * Portada del tema Base. Variables: $s (ajustes), $cliente (o null), $carta (categorías con platos y alérgenos).
 * Funciones útiles: photo_img(), horario_hoy(), politicas(), al_icon(), precio(). Ver TEMA.md.
 */
require __DIR__ . '/partes.php';
$alNames = array_column($carta['allergens'], 'name', 'key');
$usados = [];
foreach ($carta['categories'] as $cat) foreach ($cat['dishes'] as $d) foreach ($d['allergens'] as $k) $usados[$k] = true;
abrir($s['name'] . ($s['tagline'] ? ' · ' . $s['tagline'] : ''), 'inicio');
?>
<main id="main">
  <section class="portada">
    <div class="portada-foto"><?= photo_img('portada', 1600, '100vw', 'fetchpriority="high"') ?></div>
    <div class="cont portada-txt">
      <p class="etiqueta"><?= h(horario_hoy() === 'Cerrado' || horario_hoy() === 'Cerrado hoy' ? 'Hoy cerrado' : 'Hoy: ' . horario_hoy()) ?></p>
      <h1><?= h($s['name']) ?></h1>
      <?php if ($s['tagline']): ?><p class="portada-frase"><?= h($s['tagline']) ?></p><?php endif; ?>
      <div class="botones">
        <a class="boton boton-acento" href="#reservar" data-book>Reservar mesa</a>
        <a class="boton boton-claro" href="#carta">Ver la carta</a>
      </div>
    </div>
  </section>

  <section class="seccion" id="nosotros">
    <div class="cont dos">
      <div>
        <p class="etiqueta">Nosotros</p>
        <h2 class="titulo"><?= h($s['tagline'] ?: $s['name']) ?></h2>
        <p class="texto-grande"><?= nl2br(h($s['description'] ?: 'Escribe aquí la presentación del restaurante desde la app: Ajustes → Datos del restaurante.')) ?></p>
      </div>
      <figure class="foto-marco"><?= photo_img('sala', 1000, '(max-width: 800px) 100vw, 45vw') ?></figure>
    </div>
  </section>

  <section class="seccion seccion-papel" id="carta">
    <div class="cont">
      <p class="etiqueta">La carta</p>
      <h2 class="titulo">Para compartir o para ti solo</h2>
      <?php if (!$carta['categories']): ?>
        <p>La carta aparecerá aquí en cuanto se añadan platos desde la app.</p>
      <?php else: ?>
        <nav class="carta-nav" aria-label="Secciones de la carta">
          <?php foreach ($carta['categories'] as $cat): ?><a href="#cat-<?= $cat['id'] ?>"><?= h($cat['name']) ?></a><?php endforeach; ?>
        </nav>
        <div class="carta">
          <?php foreach ($carta['categories'] as $cat): ?>
            <section class="carta-sec" id="cat-<?= $cat['id'] ?>">
              <h3><?= h($cat['name']) ?></h3>
              <ul>
                <?php foreach ($cat['dishes'] as $d): ?>
                  <li class="plato<?= $d['featured'] ? ' plato-destacado' : '' ?>">
                    <?php if ($d['photo']): ?><img class="plato-foto" src="<?= h($d['photo']) ?>" alt="" loading="lazy"><?php endif; ?>
                    <div class="plato-txt">
                      <p class="plato-linea"><span class="plato-nombre"><?= h($d['name']) ?></span><span class="plato-precio"><?= precio($d['price']) ?></span></p>
                      <?php if ($d['featured']): ?><p class="plato-marca">Recomendado de la casa</p><?php endif; ?>
                      <?php if ($d['description']): ?><p class="plato-desc"><?= h($d['description']) ?></p><?php endif; ?>
                      <?php if ($d['allergens']): ?><p class="plato-al"><?php foreach ($d['allergens'] as $k): ?><?= al_icon($k, 22, $alNames[$k] ?? $k) ?><?php endforeach; ?></p><?php endif; ?>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            </section>
          <?php endforeach; ?>
        </div>
        <?php if ($usados): ?>
          <details class="alergenos">
            <summary>Qué significa cada icono de alérgenos</summary>
            <ul>
              <?php foreach ($carta['allergens'] as $a): if (empty($usados[$a['key']])) continue; ?>
                <li><?= al_icon($a['key'], 28) ?><span><strong><?= h($a['name']) ?></strong> <?= h($a['desc']) ?></span></li>
              <?php endforeach; ?>
            </ul>
            <p class="nota">Si tienes alguna alergia o intolerancia, díselo siempre al personal antes de pedir.</p>
          </details>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </section>

  <section class="seccion" id="reservar">
    <div class="cont dos dos-centro">
      <div>
        <p class="etiqueta">Reservas</p>
        <h2 class="titulo">Reserva tu mesa en un minuto</h2>
        <p>Elige día, hora y cuántos sois. Te enviaremos un WhatsApp para confirmar la reserva. Cancelar es siempre gratis.</p>
        <a class="boton boton-acento" href="#reservar" data-book>Reservar mesa</a>
      </div>
      <div class="politicas">
        <?php foreach (politicas() as $p): ?>
          <details><summary><?= h($p['titulo'] ?: 'Información') ?></summary><p><?= h($p['texto']) ?></p></details>
        <?php endforeach; ?>
        <details><summary>Si llegas tarde</summary><p>Guardamos la mesa <?= (int)$s['late_minutes'] ?> minutos desde la hora reservada.</p></details>
      </div>
    </div>
  </section>

  <section class="seccion seccion-papel app" id="app">
    <div class="cont">
      <p class="etiqueta">En tu móvil</p>
      <h2 class="titulo">Ten <?= h($s['name']) ?> a un toque</h2>
      <p>Instala la web como una app para reservar y ver la carta más rápido.</p>
      <button class="boton boton-acento" type="button" data-install hidden>Instalar la app</button>
      <p data-install-help class="nota">En Android o en el ordenador, abre el menú del navegador y elige «Instalar».</p>
      <p data-install-ios hidden class="nota">En iPhone: pulsa Compartir y luego «Añadir a pantalla de inicio».</p>
      <p data-install-done hidden class="nota">Ya la tienes instalada.</p>
    </div>
  </section>
</main>
<?php cerrar();
