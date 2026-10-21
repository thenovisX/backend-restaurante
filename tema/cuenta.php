<?php
/*
 * Cuenta del cliente. Variables: $cliente (null si no ha entrado), $reservas (upcoming/past), $activada,
 * $enlace_caducado, $registro. El comportamiento lo pone el SDK con atributos data-*: ver TEMA.md.
 */
require __DIR__ . '/partes.php';
$estados = ['pendiente' => 'Petición enviada: esperando al restaurante', 'esperando' => 'Falta que la confirmes', 'confirmada' => 'Confirmada'];
abrir(($cliente ? 'Mi cuenta' : 'Entrar') . ' · ' . $s['name'], 'pagina');
?>
<main id="main" class="cont cuenta">
<?php if (!$cliente): ?>
  <div class="dos">
    <div>
      <h1 class="titulo">Tu cuenta</h1>
      <?php if ($enlace_caducado): ?><p class="aviso">El enlace de activación no es válido o ha caducado. Vuelve a crear la cuenta o pide otro enlace.</p><?php endif; ?>
      <p class="texto-grande">Reserva sin escribir tus datos cada vez, mira tus próximas reservas y cancélalas tú mismo cuando quieras, sin coste.</p>
    </div>
    <div class="caja">
      <div class="pestanas" data-tabs role="tablist">
        <button type="button" role="tab" aria-controls="f-entrar" aria-selected="<?= $registro ? 'false' : 'true' ?>">Entrar</button>
        <button type="button" role="tab" aria-controls="f-registro" aria-selected="<?= $registro ? 'true' : 'false' ?>">Crear cuenta</button>
      </div>
      <form class="form" id="f-entrar" data-api="c_login" <?= $registro ? 'hidden' : '' ?> novalidate>
        <label>Email <input name="email" type="email" autocomplete="email" required></label>
        <label>Contraseña <input name="password" type="password" autocomplete="current-password" required></label>
        <p class="error" data-error role="alert"></p>
        <button class="boton boton-acento" type="submit">Entrar</button>
        <p class="nota">¿Has olvidado la contraseña? Escríbenos<?= $s['email'] ? ' a <a href="mailto:' . h($s['email']) . '">' . h($s['email']) . '</a>' : '' ?> desde el email de tu cuenta y te ayudamos.</p>
      </form>
      <form class="form" id="f-registro" data-api="c_register" <?= $registro ? '' : 'hidden' ?> novalidate>
        <label>Nombre <input name="name" autocomplete="name" maxlength="80" required></label>
        <label>Email <input name="email" type="email" autocomplete="email" maxlength="120" required></label>
        <label>Teléfono <span>opcional, para avisarte si hay algún cambio</span><input name="phone" type="tel" autocomplete="tel" maxlength="30"></label>
        <label>Contraseña <span>mínimo 8 caracteres</span><input name="password" type="password" autocomplete="new-password" minlength="8" required></label>
        <label class="trampa" aria-hidden="true">No rellenar <input name="website" tabindex="-1" autocomplete="off"></label>
        <label class="casilla"><input type="checkbox" name="privacy" value="1"><span>Acepto las <a href="legal.php#condiciones" target="_blank">condiciones de uso</a> y he leído la <a href="legal.php#privacidad" target="_blank">política de privacidad</a>.</span></label>
        <p class="error" data-error role="alert"></p>
        <button class="boton boton-acento" type="submit">Crear cuenta</button>
        <p class="nota">Te enviaremos un email para activar la cuenta. Las reservas que hayas hecho con este mismo email aparecerán en tu cuenta al activarla; las que hiciste sin email o con otro no se pueden añadir. No te enviaremos publicidad.</p>
        <p class="nota pequena"><strong>Protección de datos.</strong> Responsable: <?= h($s['owner_name'] ?: $s['name']) ?>. Finalidad: gestionar tu cuenta y tus reservas. No cedemos tus datos a nadie. Puedes descargarlos o borrar la cuenta desde aquí mismo, y ejercer el resto de derechos escribiendo a <?= h($s['email'] ?: 'nuestro contacto') ?>. <a href="legal.php#privacidad" target="_blank">Más información</a>.</p>
      </form>
      <div data-registro-ok hidden>
        <h2>Revisa tu email</h2>
        <p>Te hemos enviado un enlace a <strong data-registro-email></strong> para activar tu cuenta. Caduca en 48 horas. Si no lo ves, mira en la carpeta de spam.</p>
        <button class="boton" type="button" data-resend>Enviar el email otra vez</button>
      </div>
    </div>
  </div>
<?php else: ?>
  <div class="cuenta-cab">
    <h1 class="titulo">Hola, <?= h(explode(' ', $cliente['name'])[0]) ?></h1>
    <?php if ($activada): ?><p class="aviso aviso-ok">Cuenta activada. Ya puedes reservar sin escribir tus datos.</p><?php endif; ?>
    <a class="boton boton-acento" href="#reservar" data-book>Nueva reserva</a>
  </div>

  <section class="cuenta-sec">
    <h2>Próximas reservas</h2>
    <?php if (!$reservas['upcoming']): ?><p>No tienes reservas pendientes. <a href="#reservar" data-book>Reserva una mesa</a>.</p><?php endif; ?>
    <ul class="lista-res">
      <?php foreach ($reservas['upcoming'] as $r): ?>
        <li>
          <div>
            <p class="res-cuando"><?= h(ucfirst($r['when'])) ?></p>
            <p class="nota"><?= $r['people'] ?> <?= $r['people'] === 1 ? 'persona' : 'personas' ?> · <span class="estado estado-<?= $r['status'] ?>"><?= $estados[$r['status']] ?? $r['status'] ?></span></p>
            <?php if ($r['notes']): ?><p class="nota"><?= h($r['notes']) ?></p><?php endif; ?>
          </div>
          <div class="botones">
            <?php if ($r['status'] === 'esperando'): ?><button class="boton boton-acento" type="button" data-confirm-res="<?= $r['id'] ?>">Confirmar</button><?php endif; ?>
            <?php if ($r['can_cancel']): ?><button class="boton" type="button" data-cancel="<?= $r['id'] ?>" data-when="<?= h($r['when']) ?>">Cancelar</button><?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="nota">Aquí salen las reservas hechas con tu cuenta o con el email <?= h($cliente['email']) ?>. Las que hiciste sin email o con otro email no aparecen.</p>
  </section>

  <?php if ($reservas['past']): ?>
    <section class="cuenta-sec">
      <h2>Historial</h2>
      <ul class="lista-res lista-pasadas">
        <?php foreach (array_slice($reservas['past'], 0, 20) as $r): ?>
          <li><div><p class="res-cuando"><?= h(ucfirst($r['when'])) ?></p><p class="nota"><?= $r['people'] ?> pers. · <?= ['rechazada' => 'No pudimos atenderla', 'cancelada' => 'Cancelada'][$r['status']] ?? 'Pasada' ?></p></div></li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <section class="cuenta-sec">
    <h2>Mis datos</h2>
    <form class="form" data-api="c_profile" novalidate>
      <div class="fila">
        <label>Nombre <input name="name" value="<?= h($cliente['name']) ?>" autocomplete="name" required></label>
        <label>Teléfono <input name="phone" type="tel" value="<?= h($cliente['phone']) ?>" autocomplete="tel"></label>
      </div>
      <label>Email <input name="email" type="email" value="<?= h($cliente['email']) ?>" autocomplete="email" required></label>
      <p class="nota">Para cambiar el email, escribe también tu contraseña actual en «Cambiar contraseña».</p>
      <details>
        <summary>Cambiar contraseña</summary>
        <div class="fila">
          <label>Contraseña actual <input name="password" type="password" autocomplete="current-password"></label>
          <label>Nueva contraseña <input name="new_password" type="password" autocomplete="new-password" minlength="8"></label>
        </div>
      </details>
      <p class="error" data-error role="alert"></p>
      <button class="boton" type="submit">Guardar cambios</button>
    </form>
  </section>

  <section class="cuenta-sec">
    <h2>Tus datos y tu sesión</h2>
    <div class="botones">
      <a class="boton" href="api/?r=c_export" download="mis-datos.json">Descargar mis datos</a>
      <button class="boton" type="button" data-logout>Cerrar sesión</button>
    </div>
    <details class="borrar">
      <summary>Borrar mi cuenta</summary>
      <form class="form" data-api="c_delete" data-confirm="¿Seguro que quieres borrar tu cuenta? No se puede deshacer." novalidate>
        <p class="nota">Borramos tus datos de acceso. Tus reservas pendientes siguen en pie: si quieres anularlas, cancélalas antes.</p>
        <label>Escribe tu contraseña para confirmar <input name="password" type="password" autocomplete="current-password"></label>
        <p class="error" data-error role="alert"></p>
        <button class="boton boton-peligro" type="submit">Borrar mi cuenta</button>
      </form>
    </details>
  </section>
<?php endif; ?>
</main>
<?php cerrar();
