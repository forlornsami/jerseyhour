/* JerseyHour — storefront interactions (no dependencies) */
(function () {
  'use strict';
  var JH = window.JH || { base: '', csrf: '', currency: 'Rs.' };
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var api = JH.base + '/api/cart.php';
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (m) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]; }); };
  var ICON = {
    minus: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14"/></svg>',
    plus: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>',
    trash: '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/></svg>',
    cart: '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6h15l-1.5 9h-12z"/><path d="M6 6 5 3H2"/><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/></svg>'
  };

  /* ---------- Header shadow on scroll ---------- */
  var header = $('#siteHeader');
  var onScroll = function () { if (header) header.classList.toggle('is-scrolled', window.scrollY > 8); };
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* ---------- Overlays: drawers, modals, search, filters ---------- */
  var scrim = $('#scrim');
  var openEl = null, lastFocus = null;

  function focusables(el) {
    return $$('a[href], button:not([disabled]), input:not([disabled]):not([type=hidden]), select, textarea, [tabindex]:not([tabindex="-1"])', el)
      .filter(function (n) { return n.offsetParent !== null; });
  }
  function open(id) {
    var el = document.getElementById(id);
    if (!el) return;
    if (openEl) close(true);
    lastFocus = document.activeElement;
    el.hidden = false;
    openEl = el;
    if (id !== 'searchPanel' && scrim) { scrim.hidden = false; }
    document.documentElement.style.overflow = 'hidden';
    requestAnimationFrame(function () {
      el.classList.add('is-open');
      if (scrim) scrim.classList.add('is-open');
      var f = id === 'searchPanel' ? $('input', el) : focusables(el)[0];
      if (f) f.focus({ preventScroll: true });
    });
    if (id === 'cartDrawer') loadCart();
  }
  function close(instant) {
    if (!openEl) return;
    var el = openEl;
    openEl = null;
    el.classList.remove('is-open');
    if (scrim) scrim.classList.remove('is-open');
    document.documentElement.style.overflow = '';
    var hide = function () {
      if (!el.classList.contains('filters')) el.hidden = true;
      if (scrim && !openEl) scrim.hidden = true;
    };
    if (instant === true) hide(); else setTimeout(hide, 280);
    if (lastFocus && lastFocus.focus) lastFocus.focus({ preventScroll: true });
  }
  document.addEventListener('click', function (e) {
    var o = e.target.closest('[data-open]');
    if (o) { e.preventDefault(); open(o.getAttribute('data-open')); return; }
    if (e.target.closest('[data-close]')) { e.preventDefault(); close(); return; }
    if (e.target === scrim) close();
    if (openEl && openEl.classList.contains('modal') && e.target === openEl) close();
  });
  document.addEventListener('keydown', function (e) {
    if (!openEl) return;
    if (e.key === 'Escape') { close(); return; }
    if (e.key === 'Tab') {
      var f = focusables(openEl);
      if (!f.length) return;
      var first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });

  /* ---------- Toasts ---------- */
  function toast(msg, opts) {
    opts = opts || {};
    var box = $('#toasts');
    if (!box) return;
    var t = document.createElement('div');
    t.className = 'toast' + (opts.error ? ' is-error' : '');
    t.innerHTML = (opts.image ? '<img src="' + esc(opts.image) + '" alt="">' : '') + '<span>' + esc(msg) + '</span>' +
      (opts.action ? '<a href="#" data-open="cartDrawer">' + esc(opts.action) + '</a>' : '');
    box.appendChild(t);
    setTimeout(function () { t.classList.add('out'); setTimeout(function () { t.remove(); }, 260); }, opts.error ? 4200 : 3200);
  }

  /* ---------- Cart API ---------- */
  function post(data) {
    var fd = data instanceof FormData ? data : new FormData();
    if (!(data instanceof FormData)) Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    if (!fd.has('csrf')) fd.append('csrf', JH.csrf);
    return fetch(api, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json' } })
      .then(function (r) { return r.json().catch(function () { return { ok: false, message: 'Unexpected response. Please refresh.' }; }); });
  }
  function setCount(n) {
    $$('[data-cart-count]').forEach(function (b) {
      b.textContent = n; b.hidden = !n;
      b.classList.remove('bump'); void b.offsetWidth; b.classList.add('bump');
    });
    $$('[data-cart-count-text]').forEach(function (s) { s.textContent = n ? '(' + n + ')' : ''; });
  }
  function renderCart(cart) {
    var body = $('[data-cart-body]'), foot = $('[data-cart-foot]');
    if (!body) return;
    setCount(cart.count);
    if (!cart.lines.length) {
      body.innerHTML = '<div class="dl-empty"><div class="empty-icon">' + ICON.cart + '</div><h3>Your cart is empty</h3><p class="muted">Let’s find you a jersey.</p><a class="btn btn-primary" href="' + JH.base + '/shop.php">Shop jerseys</a></div>';
      foot.hidden = true;
      return;
    }
    body.innerHTML = cart.lines.map(function (l) {
      return '<div class="dl-item" data-key="' + esc(l.key) + '">' +
        '<a href="' + esc(l.url) + '"><img src="' + esc(l.image) + '" alt="" width="72" height="90"></a>' +
        '<div><a class="dl-name" href="' + esc(l.url) + '">' + esc(l.name) + '</a>' +
        '<div class="dl-meta">' + (l.size ? 'Size ' + esc(l.size) + ' · ' : '') + esc(l.price_fmt) + '</div>' +
        '<div class="qty qty-sm"><button type="button" data-dl-step="-1" aria-label="Decrease">' + ICON.minus + '</button>' +
        '<input type="number" value="' + l.qty + '" min="0" max="' + l.max + '" aria-label="Quantity" readonly>' +
        '<button type="button" data-dl-step="1" aria-label="Increase"' + (l.qty >= l.max ? ' disabled' : '') + '>' + ICON.plus + '</button></div></div>' +
        '<div class="dl-end"><span>' + esc(l.line_fmt) + '</span><button class="dl-remove" type="button" data-dl-remove aria-label="Remove ' + esc(l.name) + '">' + ICON.trash + '</button></div>' +
        '</div>';
    }).join('');
    foot.hidden = false;
    $('[data-cart-subtotal]').textContent = cart.subtotal_fmt;
    var meter = $('[data-ship-meter]');
    if (meter) {
      if (cart.threshold > 0) {
        var pct = Math.min(100, Math.round(cart.subtotal / cart.threshold * 100));
        meter.innerHTML = '<p>' + (cart.remaining > 0 ? 'Add <strong>' + esc(cart.remaining_fmt) + '</strong> more for free delivery' : '<strong>You’ve unlocked free delivery!</strong>') + '</p><div class="meter"><span style="width:' + pct + '%"></span></div>';
        meter.hidden = false;
      } else meter.hidden = true;
    }
  }
  function loadCart() { post({ action: 'get' }).then(function (r) { if (r.cart) renderCart(r.cart); }); }

  document.addEventListener('click', function (e) {
    var step = e.target.closest('[data-dl-step]');
    var rm = e.target.closest('[data-dl-remove]');
    if (!step && !rm) return;
    var item = e.target.closest('.dl-item');
    var key = item.getAttribute('data-key');
    item.style.opacity = '.5';
    if (rm) {
      post({ action: 'remove', key: key }).then(function (r) { if (r.cart) renderCart(r.cart); refreshCartPage(); });
    } else {
      var input = $('input', item);
      var q = Math.max(0, parseInt(input.value, 10) + parseInt(step.getAttribute('data-dl-step'), 10));
      post({ action: 'update', key: key, qty: q }).then(function (r) { if (r.cart) renderCart(r.cart); refreshCartPage(); });
    }
  });
  function refreshCartPage() { if ($('[data-cart-page]') || document.body.classList.contains('checkout-page')) setTimeout(function () { location.reload(); }, 150); }

  /* ---------- Quantity steppers ---------- */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-qty] [data-step]');
    if (!b) return;
    var input = $('input', b.closest('[data-qty]'));
    var min = parseInt(input.min || '0', 10), max = parseInt(input.max || '99', 10);
    var v = Math.min(max, Math.max(min, (parseInt(input.value, 10) || 0) + parseInt(b.getAttribute('data-step'), 10)));
    if (String(v) !== input.value) { input.value = v; input.dispatchEvent(new Event('change', { bubbles: true })); }
  });
  var saveTimer;
  document.addEventListener('change', function (e) {
    if (e.target.matches('[data-autosave]')) {
      clearTimeout(saveTimer);
      var form = e.target.form;
      saveTimer = setTimeout(function () { form.submit(); }, 450);
    }
  });

  /* ---------- Product page ---------- */
  var buy = $('[data-add-to-cart]');
  if (buy) {
    var note = $('[data-stock-note]');
    var qtyInput = $('input[name=qty]', buy);
    var wa = $('[data-wa-order]');
    var sizeBox = $('.size-select', buy);
    var sizeLabel = $('[data-size-label]');
    var chosen = function () { return $('input[name=size]:checked', buy); };

    var updateSize = function () {
      var r = chosen();
      if (!r) return;
      var s = parseInt(r.getAttribute('data-stock'), 10);
      if (sizeLabel) sizeLabel.textContent = '— ' + r.value;
      if (note) {
        note.className = 'stock-note ' + (s <= 3 ? 'low' : 'ok');
        note.textContent = s <= 3 ? 'Hurry — only ' + s + ' left in ' + r.value : 'In stock — ready to ship';
      }
      if (qtyInput) { qtyInput.max = s; if (+qtyInput.value > s) qtyInput.value = s; }
      if (wa) {
        var msg = wa.getAttribute('data-wa-base') + '\nSize: ' + r.value + '\nQty: ' + (qtyInput ? qtyInput.value : 1) + '\n' + wa.getAttribute('data-wa-link');
        wa.href = wa.href.split('?')[0] + '?text=' + encodeURIComponent(msg);
      }
    };
    buy.addEventListener('change', updateSize);

    var needSize = function () {
      if (!sizeBox || chosen()) return false;
      sizeBox.classList.remove('shake'); void sizeBox.offsetWidth; sizeBox.classList.add('shake');
      if (note) { note.className = 'stock-note err'; note.textContent = 'Please select a size first'; }
      sizeBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return true;
    };

    buy.addEventListener('submit', function (e) {
      e.preventDefault();
      if (needSize()) return;
      var buyNow = e.submitter && e.submitter.hasAttribute('data-buy-now');
      var btn = buyNow ? $('[data-buy-now]', buy) : $('[data-add-btn]', buy);
      btn.classList.add('is-loading'); btn.disabled = true;
      var fd = new FormData(buy);
      post(fd).then(function (r) {
        btn.classList.remove('is-loading'); btn.disabled = false;
        if (!r.ok) { toast(r.message || 'Could not add to cart', { error: true }); return; }
        if (r.cart) { setCount(r.cart.count); renderCart(r.cart); }
        if (buyNow) { location.href = JH.base + '/checkout.php'; return; }
        var img = $('.gallery-slide img');
        toast(r.message || 'Added to cart', { image: img ? img.getAttribute('src') : '', action: 'View cart' });
      }).catch(function () {
        btn.classList.remove('is-loading'); btn.disabled = false;
        toast('Network error — please try again.', { error: true });
      });
    });

    // Sticky add-to-cart bar (mobile)
    var sticky = $('[data-sticky-buy]');
    if (sticky && 'IntersectionObserver' in window) {
      new IntersectionObserver(function (en) {
        var show = !en[0].isIntersecting && en[0].boundingClientRect.top < 0;
        sticky.classList.toggle('is-visible', show);
        sticky.setAttribute('aria-hidden', show ? 'false' : 'true');
        $('[data-sticky-add]', sticky).tabIndex = show ? 0 : -1;
      }).observe(buy);
      $('[data-sticky-add]', sticky).addEventListener('click', function () {
        if (needSize()) return;
        buy.requestSubmit ? buy.requestSubmit($('[data-add-btn]', buy)) : $('[data-add-btn]', buy).click();
      });
    }
  }

  /* ---------- Gallery ---------- */
  var gal = $('[data-gallery]');
  if (gal) {
    var track = $('[data-gallery-track]', gal);
    var thumbs = $$('[data-thumb]', gal);
    var dots = $$('.gallery-dots span', gal);
    thumbs.forEach(function (t) {
      t.addEventListener('click', function () {
        var i = +t.getAttribute('data-thumb');
        track.scrollTo({ left: track.clientWidth * i, behavior: 'smooth' });
      });
    });
    var tick;
    track.addEventListener('scroll', function () {
      clearTimeout(tick);
      tick = setTimeout(function () {
        var i = Math.round(track.scrollLeft / track.clientWidth);
        thumbs.forEach(function (t, k) { t.classList.toggle('active', k === i); t.setAttribute('aria-selected', k === i ? 'true' : 'false'); });
        dots.forEach(function (d, k) { d.classList.toggle('active', k === i); });
      }, 60);
    }, { passive: true });
  }

  /* ---------- Forms: prevent double submit ---------- */
  $$('[data-submit-once]').forEach(function (btn) {
    btn.form && btn.form.addEventListener('submit', function (e) {
      if (btn.form.hasAttribute('data-checkout') && !btn.form.checkValidity()) {
        // Let server show friendly messages; but block obvious empties client-side
        var bad = $$(':invalid', btn.form).filter(function (n) { return n.name && n.name !== 'website'; })[0];
        if (bad) { e.preventDefault(); bad.setAttribute('aria-invalid', 'true'); bad.focus(); toast('Please complete the highlighted field.', { error: true }); return; }
      }
      setTimeout(function () { btn.disabled = true; btn.classList.add('is-loading'); }, 0);
    });
  });
  $$('input[aria-invalid], textarea[aria-invalid]').forEach(function (n) {
    n.addEventListener('input', function () { n.removeAttribute('aria-invalid'); var f = n.closest('.field'); if (f) f.classList.remove('has-error'); });
  });

  /* ---------- Filters auto-submit on desktop ---------- */
  var ff = $('[data-autosubmit]');
  if (ff) {
    ff.addEventListener('change', function (e) {
      if (window.innerWidth > 900 && e.target.matches('input[type=radio], input[type=checkbox]')) ff.submit();
    });
    // Allow un-selecting a size pill by clicking it again
    $$('.size-pill input', ff).forEach(function (r) {
      r.addEventListener('click', function () {
        if (r.dataset.was === '1') { r.checked = false; r.dataset.was = ''; if (window.innerWidth > 900) ff.submit(); }
        else { $$('.size-pill input', ff).forEach(function (x) { x.dataset.was = ''; }); r.dataset.was = '1'; }
      });
      if (r.checked) r.dataset.was = '1';
    });
  }

  /* ---------- Copy order number ---------- */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-copy]');
    if (!b) return;
    var text = b.getAttribute('data-copy');
    (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject()).then(function () {
      b.textContent = 'Copied!'; setTimeout(function () { b.textContent = 'Copy'; }, 1600);
    }).catch(function () { toast(text); });
  });

  /* ---------- Reveal on scroll ---------- */
  if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    var els = $$('.section .card, .cat-tile, .promo, .visit-card, .faq');
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
    }, { rootMargin: '0px 0px -40px 0px' });
    els.forEach(function (el, i) {
      if (el.getBoundingClientRect().top < window.innerHeight) return;
      el.classList.add('reveal');
      el.style.transitionDelay = (i % 4) * 60 + 'ms';
      io.observe(el);
    });
  }
})();
