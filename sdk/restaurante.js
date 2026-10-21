/*
 * SDK de la web del restaurante. Lo carga body_end() en todas las páginas. Hace tres cosas:
 *
 * 1. Ventana de reserva: cualquier elemento con data-book la abre (no hace falta nada más).
 * 2. Cuenta del cliente: formularios con data-api="c_login|c_register|c_profile|c_delete", botones
 *    data-cancel="ID", data-confirm-res="ID", data-logout y data-resend. Ver TEMA.md.
 * 3. Instalar como app (data-install, data-install-ios, data-install-help, data-install-done).
 *
 * Además deja window.Restaurante con la API para temas que quieran hacer su propia interfaz:
 *   Restaurante.api('slots&date=2026-11-02').then(d => …)
 *   Restaurante.abrirReserva()
 *   document.addEventListener('restaurante:reserva', e => console.log(e.detail))
 */
(function () {
  'use strict';
  var R = window.RESTAURANT || {};
  var $ = function (s, el) { return (el || document).querySelector(s); };
  var $$ = function (s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); };
  var DIAS = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
  var DIAS_L = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
  var MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function iso(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  function parse(s) { var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2], 12); }
  function addDays(s, n) { var d = parse(s); d.setDate(d.getDate() + n); return iso(d); }
  function longDay(s) { var d = parse(s); return DIAS_L[d.getDay()] + ' ' + d.getDate() + ' de ' + MESES[d.getMonth()]; }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

  /** Llama a la API: api('ruta') hace GET; api('ruta', datos) hace POST con JSON. Devuelve una promesa con data. */
  function api(route, body) {
    var opts = { credentials: 'same-origin', headers: {} };
    if (body !== undefined) {
      opts.method = 'POST';
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    }
    return fetch('api/?r=' + route, opts).then(function (r) {
      return r.json().catch(function () { return { ok: false, error: 'El servidor no responde bien. Inténtalo de nuevo.' }; });
    }, function () {
      return { ok: false, error: 'Sin conexión. Revisa internet e inténtalo de nuevo.' };
    }).then(function (j) {
      if (!j.ok) throw new Error(j.error || 'Algo ha fallado');
      return j.data;
    });
  }

  function toast(msg) {
    var t = document.createElement('div');
    t.className = 'r-toast';
    t.setAttribute('role', 'status');
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(function () { t.remove(); }, 3200);
  }

  function emit(name, detail) {
    try { document.dispatchEvent(new CustomEvent('restaurante:' + name, { detail: detail })); } catch (e) { /* navegador antiguo */ }
  }

  /* ---------- Reserva en 3 pasos ---------- */
  var dlg = $('#bk');
  var B = { people: 2, date: R.today, time: '', slots: [] };

  function isClosed(day) {
    if ((R.closed || []).indexOf(day) !== -1) return true;
    var wd = String(((parse(day).getDay() + 6) % 7) + 1);
    return !((R.hours || {})[wd] || []).length;
  }

  function summary() {
    var parts = [B.people + (B.people === 1 ? ' persona' : ' personas')];
    if (B.date) parts.push(longDay(B.date));
    if (B.time) parts.push(B.time);
    $('#bk-sum').textContent = parts.join(' · ');
  }

  function showPane(n) {
    $$('.bk-pane', dlg).forEach(function (p) { p.hidden = p.dataset.pane !== String(n); });
    $('#bk-step').textContent = n <= 3 ? 'Paso ' + n + ' de 3' : 'Hecho';
    $('#bk-title').textContent = ['', 'Reservar mesa', 'Elige la hora', 'Tus datos', 'Petición enviada'][n];
    summary();
    var focusable = $('.bk-pane:not([hidden]) button, .bk-pane:not([hidden]) input', dlg);
    if (n > 1 && focusable) focusable.focus({ preventScroll: true });
    if (n === 2) loadSlots();
  }

  function renderDays() {
    var box = $('#bk-days');
    box.innerHTML = '';
    for (var i = 0; i < 14; i++) {
      var day = addDays(R.today, i);
      var d = parse(day);
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'bk-day';
      b.setAttribute('role', 'radio');
      b.dataset.day = day;
      b.disabled = isClosed(day);
      b.setAttribute('aria-checked', String(day === B.date));
      b.setAttribute('aria-label', longDay(day) + (b.disabled ? ', cerrado' : ''));
      b.innerHTML = '<small>' + (i === 0 ? 'hoy' : i === 1 ? 'mañana' : DIAS[d.getDay()]) + '</small><b>' + d.getDate() + '</b><small>' + MESES[d.getMonth()].slice(0, 3) + '</small>';
      box.appendChild(b);
    }
  }

  function pickDate(day) {
    B.date = day;
    B.time = '';
    $$('.bk-day', dlg).forEach(function (b) { b.setAttribute('aria-checked', String(b.dataset.day === day)); });
    $('#bk-date').value = day;
    summary();
  }

  function slotButtons(list) {
    return '<div class="bk-slot-grid">' + list.map(function (s) {
      var off = s.left < B.people;
      return '<button type="button" class="bk-slot" data-time="' + s.time + '" aria-pressed="' + (s.time === B.time) + '"' + (off ? ' disabled aria-label="' + s.time + ', completo"' : '') + '>' + s.time + '</button>';
    }).join('') + '</div>';
  }

  function renderSlots() {
    var box = $('#bk-slots');
    var free = B.slots.filter(function (s) { return s.left >= B.people; });
    if (!free.length) {
      box.innerHTML = '<p class="bk-msg">' + esc(B.reason || ('No queda sitio para ' + B.people + ' personas ese día.')) + ' Prueba otro día' + (R.phone ? ' o llámanos.' : '.') + '</p>';
    } else {
      var lunch = B.slots.filter(function (s) { return s.time < '17:00'; });
      var dinner = B.slots.filter(function (s) { return s.time >= '17:00'; });
      box.innerHTML = (lunch.length ? '<h3>Comida</h3>' + slotButtons(lunch) : '') + (dinner.length ? '<h3>Cena</h3>' + slotButtons(dinner) : '');
    }
    $('#bk-to3').disabled = !B.time;
  }

  function loadSlots() {
    var box = $('#bk-slots');
    box.innerHTML = '<p class="bk-msg">Buscando horas libres…</p>';
    $('#bk-to3').disabled = true;
    api('slots&date=' + encodeURIComponent(B.date)).then(function (d) {
      B.slots = d.slots;
      B.reason = d.open ? '' : d.reason + '.';
      if (B.time && !B.slots.some(function (s) { return s.time === B.time && s.left >= B.people; })) B.time = '';
      renderSlots();
    }).catch(function (e) {
      box.innerHTML = '<p class="bk-msg">' + esc(e.message) + '</p>';
    });
  }

  function resetBooking() {
    B.time = '';
    var f = $('#bk-form');
    f.notes.value = '';
    f.privacy.checked = false;
    $('#bk-err').textContent = '';
    $('#bk-send').disabled = false;
    $('#bk-send').textContent = 'Enviar petición';
  }

  function openBooking() {
    if (!dlg) return;
    if (!$('.bk-pane[data-pane="4"]', dlg).hidden) resetBooking();
    renderDays();
    if (isClosed(B.date)) {
      for (var i = 0; i < 14; i++) { var d = addDays(R.today, i); if (!isClosed(d)) { pickDate(d); break; } }
    }
    $('#bk-date').min = R.today;
    $('#bk-date').max = addDays(R.today, R.daysAhead || 60);
    $('#bk-date').value = B.date;
    if (typeof dlg.showModal === 'function') dlg.showModal(); else dlg.setAttribute('open', '');
    showPane(1);
    emit('reserva-abierta', {});
  }

  if (dlg) {
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-book]');
      if (!b) return;
      e.preventDefault();
      emit('antes-de-reservar', {});
      openBooking();
    });
    dlg.addEventListener('click', function (e) {
      if (e.target === dlg || e.target.closest('[data-close]')) { dlg.close(); return; }
      var day = e.target.closest('.bk-day');
      if (day && !day.disabled) return pickDate(day.dataset.day);
      var slot = e.target.closest('.bk-slot');
      if (slot && !slot.disabled) {
        B.time = slot.dataset.time;
        $$('.bk-slot', dlg).forEach(function (s) { s.setAttribute('aria-pressed', String(s === slot)); });
        $('#bk-to3').disabled = false;
        summary();
        return;
      }
      var nx = e.target.closest('[data-next]');
      if (nx && !nx.disabled) showPane(+nx.dataset.next);
    });
    dlg.addEventListener('change', function (e) {
      if (e.target.name === 'people') {
        B.people = +e.target.value;
        var more = $('[name=people_more]', dlg);
        if (more) { more.value = ''; more.classList.remove('is-on'); }
        summary();
      } else if (e.target.name === 'people_more' && e.target.value) {
        B.people = +e.target.value;
        $$('[name=people]', dlg).forEach(function (r) { r.checked = false; });
        e.target.classList.add('is-on');
        summary();
      } else if (e.target.id === 'bk-date' && e.target.value) {
        if (isClosed(e.target.value)) { toast('Ese día el restaurante está cerrado'); e.target.value = B.date; return; }
        pickDate(e.target.value);
      }
    });
    var editWho = $('#bk-edit-who');
    if (editWho) editWho.addEventListener('click', function () { $('#bk-fields').hidden = false; editWho.parentNode.hidden = true; });

    $('#bk-send').addEventListener('click', function () {
      var f = $('#bk-form');
      var err = $('#bk-err');
      var data = {
        people: B.people, date: B.date, time: B.time,
        name: f.name.value.trim(), phone: f.phone.value.trim(), email: f.email.value.trim(),
        notes: f.notes.value.trim(), website: f.website.value, privacy: f.privacy.checked ? 1 : 0
      };
      err.textContent = '';
      if (!data.phone && R.customer) { $('#bk-fields').hidden = false; }
      if (!R.customer || !$('#bk-fields').hidden) {
        var miss = [];
        if (!data.name) miss.push('tu nombre');
        if (!data.phone) miss.push('un teléfono móvil');
        if (miss.length) { err.textContent = 'Falta ' + miss.join(' y ') + '.'; $('#bk-fields').hidden = false; return; }
      }
      if (!data.privacy) { err.textContent = 'Marca la casilla para aceptar las políticas.'; return; }
      var btn = this;
      btn.disabled = true;
      btn.textContent = 'Enviando…';
      api('reserve', data).then(function (r) {
        $('#bk-done-t').textContent = 'Petición realizada';
        $('#bk-done-msg').textContent = r.message;
        var more = $('#bk-done-more');
        var wait = 'Esperando confirmación. Mientras no toques el enlace del WhatsApp, la reserva queda pendiente.';
        if (r.linked) more.innerHTML = 'Esperando confirmación. La verás en <a href="cuenta.php">tu cuenta</a>, donde también podrás cancelarla.';
        else if (r.email) more.textContent = wait + ' Si creas una cuenta con el mismo email, también la verás allí.';
        else more.textContent = wait + ' Como no has puesto email, esta reserva no aparecerá en ninguna cuenta.';
        showPane(4);
        emit('reserva', r);
      }).catch(function (e) {
        err.textContent = e.message;
        btn.disabled = false;
        btn.textContent = 'Enviar petición';
      });
    });

    if (location.hash === '#reservar' && /[?&]app=1/.test(location.search)) setTimeout(openBooking, 300);
  }

  /* ---------- Cuenta del cliente ---------- */
  // Pestañas: un contenedor con data-tabs y botones role="tab" con aria-controls
  $$('[data-tabs] [role=tab]').forEach(function (tab) {
    tab.addEventListener('click', function () {
      $$('[role=tab]', tab.closest('[data-tabs]')).forEach(function (t) {
        var on = t === tab;
        t.setAttribute('aria-selected', String(on));
        var panel = document.getElementById(t.getAttribute('aria-controls'));
        if (panel) panel.hidden = !on;
      });
    });
  });

  // Formularios: <form data-api="c_login"> … <p data-error></p> <button type="submit">
  $$('form[data-api]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      if (f.dataset.confirm && !confirm(f.dataset.confirm)) return;
      var data = {};
      new FormData(f).forEach(function (v, k) { data[k] = v; });
      var btn = $('button[type=submit]', f), err = $('[data-error], .form-err', f), label = btn.textContent;
      if (err) err.textContent = '';
      btn.disabled = true;
      btn.textContent = 'Un momento…';
      api(f.dataset.api, data).then(function (r) {
        emit(f.dataset.api, r);
        if (f.dataset.api === 'c_register' && r && r.pending) {
          // Registro hecho: falta abrir el enlace del email. Se muestra el bloque data-registro-ok.
          f.hidden = true;
          $$('[data-tabs]').forEach(function (t) { t.hidden = true; });
          var ok = $('[data-registro-ok]');
          if (ok) ok.hidden = false;
          $$('[data-registro-email]').forEach(function (el) { el.textContent = r.email; });
          $$('[data-resend]').forEach(function (b) { b.dataset.email = r.email; });
          return;
        }
        if (f.dataset.api === 'c_profile') {
          toast('Datos guardados');
          btn.disabled = false;
          btn.textContent = label;
          $$('input[type=password]', f).forEach(function (i) { i.value = ''; });
        } else {
          location.href = f.dataset.redirect || (f.dataset.api === 'c_delete' ? './' : 'cuenta.php');
        }
      }).catch(function (ex) {
        if (err) err.textContent = ex.message; else toast(ex.message);
        btn.disabled = false;
        btn.textContent = label;
      });
    });
  });

  $$('[data-resend]').forEach(function (b) {
    b.addEventListener('click', function () {
      b.disabled = true;
      api('c_resend', { email: b.dataset.email }).then(function () { toast('Email enviado otra vez'); }, function (e) { toast(e.message); })
        .then(function () { setTimeout(function () { b.disabled = false; }, 30000); });
    });
  });

  $$('[data-logout], #logout').forEach(function (b) {
    b.addEventListener('click', function () {
      api('c_logout', {}).then(function () { location.href = './'; }, function (e) { toast(e.message); });
    });
  });

  $$('[data-confirm-res]').forEach(function (b) {
    b.addEventListener('click', function () {
      b.disabled = true;
      api('c_confirm', { id: +b.dataset.confirmRes }).then(function () {
        toast('Reserva confirmada');
        setTimeout(function () { location.reload(); }, 700);
      }).catch(function (e) { toast(e.message); b.disabled = false; });
    });
  });

  $$('[data-cancel]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (!confirm('¿Cancelar la reserva' + (b.dataset.when ? ' del ' + b.dataset.when : '') + '?')) return;
      b.disabled = true;
      api('c_cancel', { id: +b.dataset.cancel }).then(function () {
        toast('Reserva cancelada');
        setTimeout(function () { location.reload(); }, 700);
      }).catch(function (e) { toast(e.message); b.disabled = false; });
    });
  });

  /* ---------- Instalar como app ---------- */
  var standalone = (window.matchMedia && matchMedia('(display-mode: standalone)').matches) || navigator.standalone === true;
  var installBtn = $('[data-install]'), iosTip = $('[data-install-ios]'), help = $('[data-install-help]'), done = $('[data-install-done]');
  var deferred = null;
  if (done && standalone) { done.hidden = false; if (help) help.hidden = true; }
  else if (iosTip && /iphone|ipad|ipod/i.test(navigator.userAgent)) { iosTip.hidden = false; if (help) help.hidden = true; }
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferred = e;
    if (installBtn) { installBtn.hidden = false; if (help) help.hidden = true; }
  });
  if (installBtn) installBtn.addEventListener('click', function () {
    if (!deferred) return;
    deferred.prompt();
    deferred.userChoice.then(function (c) {
      if (c.outcome === 'accepted') { installBtn.hidden = true; if (done) done.hidden = false; }
      deferred = null;
    });
  });
  window.addEventListener('appinstalled', function () { if (installBtn) installBtn.hidden = true; if (done) done.hidden = false; });

  if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost')) {
    navigator.serviceWorker.register('sw.js').catch(function () { /* sin modo app */ });
  }

  window.Restaurante = { api: api, toast: toast, abrirReserva: openBooking, datos: R };
})();
