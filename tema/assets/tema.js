/* JavaScript del tema Base: solo el menú del móvil. La reserva y la cuenta las hace el SDK (sdk/restaurante.js). */
(function () {
  'use strict';
  var btn = document.querySelector('[data-menu]');
  var nav = document.getElementById('menu');
  if (!btn || !nav) return;
  function set(open) {
    nav.classList.toggle('abierto', open);
    btn.setAttribute('aria-expanded', String(open));
  }
  btn.addEventListener('click', function () { set(!nav.classList.contains('abierto')); });
  nav.addEventListener('click', function (e) { if (e.target.closest('a')) set(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') set(false); });
  // Al abrir la reserva se cierra el menú
  document.addEventListener('restaurante:antes-de-reservar', function () { set(false); });
})();
