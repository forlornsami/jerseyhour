/* JerseyHour admin — small progressive enhancements */
(function () {
  'use strict';
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  /* Sidebar (mobile) */
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-sb-open]')) document.body.classList.add('sb-open');
    if (e.target.closest('[data-sb-close]')) document.body.classList.remove('sb-open');
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') document.body.classList.remove('sb-open'); });

  /* Clickable table rows */
  document.addEventListener('click', function (e) {
    var tr = e.target.closest('tr[data-href]');
    if (!tr || e.target.closest('a, button, input, select, form, summary')) return;
    if (e.metaKey || e.ctrlKey) window.open(tr.getAttribute('data-href')); else location.href = tr.getAttribute('data-href');
  });

  /* Confirm dangerous actions */
  document.addEventListener('submit', function (e) {
    var f = e.target.closest('[data-confirm]');
    if (f && !window.confirm(f.getAttribute('data-confirm'))) { e.preventDefault(); e.stopImmediatePropagation(); }
  }, true);

  /* Close "more" menus when clicking elsewhere */
  document.addEventListener('click', function (e) {
    $$('details.menu[open]').forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute('open'); });
  });

  /* Auto-dismiss success flashes */
  $$('[data-autodismiss].alert-ok').forEach(function (a) {
    setTimeout(function () { a.style.transition = 'opacity .4s, transform .4s'; a.style.opacity = '0'; a.style.transform = 'translateY(-6px)'; setTimeout(function () { a.remove(); }, 400); }, 4000);
  });

  /* Toast */
  function toast(msg) {
    var box = $('#toasts'); if (!box) return;
    var t = document.createElement('div'); t.className = 'toast'; t.textContent = msg; box.appendChild(t);
    setTimeout(function () { t.style.opacity = '0'; setTimeout(function () { t.remove(); }, 300); }, 2200);
  }

  /* Copy */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-copy]'); if (!b) return;
    var txt = b.getAttribute('data-copy');
    if (navigator.clipboard) navigator.clipboard.writeText(txt).then(function () { toast('Copied to clipboard'); });
  });

  /* Unsaved-changes guard */
  $$('form[data-dirty-guard]').forEach(function (f) {
    var dirty = false, note = $('[data-dirty-note]');
    var mark = function () { dirty = true; if (note) { note.textContent = 'Unsaved changes'; note.classList.add('warn'); } };
    f.addEventListener('input', mark); f.addEventListener('change', mark);
    f.addEventListener('submit', function () { dirty = false; var b = $('button[type=submit]', f); if (b) { setTimeout(function () { b.disabled = true; b.textContent = 'Saving…'; }, 0); } });
    window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
    f._markDirty = mark;
  });

  /* Slug preview */
  var src = $('[data-slug-source]'), tgt = $('[data-slug-target]'), prev = $('[data-slug-preview]');
  if (src && tgt && prev) {
    var slugify = function (s) { return s.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, ''); };
    var upd = function () { prev.textContent = slugify(tgt.value) || slugify(src.value) || '…'; };
    src.addEventListener('input', upd); tgt.addEventListener('input', upd);
  }

  /* Sizes & stock table */
  var st = $('[data-size-table]');
  if (st) {
    var total = $('[data-stock-total]');
    var recalc = function () {
      var t = 0; $$('input[name="size_stock[]"]', st).forEach(function (i) { t += Math.max(0, parseInt(i.value, 10) || 0); });
      if (total) total.textContent = t;
    };
    st.addEventListener('input', recalc);
    st.addEventListener('click', function (e) {
      var b = e.target.closest('[data-st]');
      if (b) {
        var i = $('input', b.parentNode);
        i.value = Math.max(0, (parseInt(i.value, 10) || 0) + parseInt(b.getAttribute('data-st'), 10));
        i.dispatchEvent(new Event('input', { bubbles: true }));
      }
      var r = e.target.closest('[data-size-remove]');
      if (r) { r.closest('.size-row').remove(); recalc(); var f = st.closest('form'); if (f && f._markDirty) f._markDirty(); }
    });
    var addBtn = $('[data-size-add]');
    if (addBtn) addBtn.addEventListener('click', function () {
      var tpl = $('#sizeRowTpl'); st.appendChild(tpl.content.cloneNode(true));
      var rows = $$('.size-row', st); $('input', rows[rows.length - 1]).focus();
    });
    var fillBtn = $('[data-size-fill]');
    if (fillBtn) fillBtn.addEventListener('click', function () {
      var v = window.prompt('Set stock for every size to:', '10');
      if (v === null) return;
      v = Math.max(0, parseInt(v, 10) || 0);
      $$('input[name="size_stock[]"]', st).forEach(function (i) { i.value = v; });
      st.dispatchEvent(new Event('input', { bubbles: true }));
    });
  }

  /* Image manager: reorder existing, preview & remove new uploads, drag-drop files */
  var grid = $('[data-img-grid]');
  if (grid) {
    var input = $('[data-img-input]', grid);
    var drop = $('[data-img-drop]', grid);
    var dt = ('DataTransfer' in window) ? new DataTransfer() : null;
    var form = grid.closest('form');
    var dirty = function () { if (form && form._markDirty) form._markDirty(); };
    var count = function () { return $$('.img-item', grid).length; };

    var renderNew = function () {
      $$('.img-item.is-new', grid).forEach(function (n) { n.remove(); });
      if (!dt) return;
      Array.prototype.forEach.call(dt.files, function (file, idx) {
        var d = document.createElement('div');
        d.className = 'img-item is-new';
        var img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        img.alt = '';
        d.appendChild(img);
        var tag = document.createElement('span'); tag.className = 'img-tag'; tag.textContent = 'New'; d.appendChild(tag);
        var b = document.createElement('button'); b.type = 'button'; b.className = 'img-remove'; b.textContent = '×'; b.setAttribute('aria-label', 'Remove photo');
        b.addEventListener('click', function () {
          var ndt = new DataTransfer();
          Array.prototype.forEach.call(dt.files, function (f, i) { if (i !== idx) ndt.items.add(f); });
          dt = ndt; input.files = dt.files; renderNew(); dirty();
        });
        d.appendChild(b);
        grid.insertBefore(d, drop);
      });
      markCover();
    };
    var addFiles = function (files) {
      if (!dt) return;
      Array.prototype.forEach.call(files, function (f) {
        if (!/^image\//.test(f.type)) return;
        if (count() + 1 > 10) { toast('Maximum 10 photos per product'); return; }
        dt.items.add(f);
        dt.files.length > 0 && renderNew();
      });
      input.files = dt.files; renderNew(); dirty();
    };
    var markCover = function () {
      $$('.img-item', grid).forEach(function (n, i) { n.classList.toggle('is-cover', i === 0); });
    };
    input.addEventListener('change', function () {
      if (!dt) return; // old browsers: plain upload
      var files = Array.prototype.slice.call(input.files);
      input.files = dt.files; // restore, then add
      addFiles(files);
    });
    ['dragenter', 'dragover'].forEach(function (ev) {
      drop.addEventListener(ev, function (e) { if (e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types, 'Files') > -1) { e.preventDefault(); drop.classList.add('is-over'); } });
    });
    ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function () { drop.classList.remove('is-over'); }); });
    drop.addEventListener('drop', function (e) { if (e.dataTransfer.files.length) { e.preventDefault(); addFiles(e.dataTransfer.files); } });

    grid.addEventListener('click', function (e) {
      var r = e.target.closest('[data-img-remove]');
      if (r) { r.closest('.img-item').remove(); markCover(); dirty(); }
    });

    // Drag to reorder existing images
    var dragging = null;
    grid.addEventListener('dragstart', function (e) {
      var it = e.target.closest('.img-item:not(.is-new)');
      if (!it) return;
      dragging = it; it.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
      try { e.dataTransfer.setData('text/plain', ''); } catch (x) {}
    });
    grid.addEventListener('dragend', function () { if (dragging) dragging.classList.remove('dragging'); dragging = null; markCover(); });
    grid.addEventListener('dragover', function (e) {
      if (!dragging) return;
      e.preventDefault();
      var over = e.target.closest('.img-item:not(.is-new)');
      if (!over || over === dragging) return;
      var rect = over.getBoundingClientRect();
      var after = (e.clientX - rect.left) > rect.width / 2;
      grid.insertBefore(dragging, after ? over.nextSibling : over);
      dirty();
    });
    markCover();
  }

  /* Settings nav scrollspy */
  var sn = $('.settings-nav');
  if (sn && 'IntersectionObserver' in window) {
    var links = $$('a', sn);
    var io = new IntersectionObserver(function (en) {
      en.forEach(function (x) {
        if (x.isIntersecting) links.forEach(function (l) { l.classList.toggle('active', l.getAttribute('href') === '#' + x.target.id); });
      });
    }, { rootMargin: '-30% 0px -60% 0px' });
    $$('.settings-group').forEach(function (g) { io.observe(g); });
  }
})();
