/* js/app.js — small progressive-enhancement helpers used across the app.
   No libraries. Adds: toasts, AJAX cart updates, loading states on submit,
   coupon preview, and number formatting on the cart page. */

(function () {
  'use strict';

  /* ---------- Toast ---------- */
  function toast(message, kind, timeout) {
    var stack = document.querySelector('[data-toast-stack]');
    if (!stack) return;
    var el = document.createElement('div');
    el.className = 'toast' + (kind ? ' ' + kind : '');
    var icon = kind === 'error' ? '⚠️' : (kind === 'info' ? 'ℹ️' : '✅');
    el.innerHTML =
      '<span class="t-icon">' + icon + '</span>' +
      '<span class="t-body"></span>' +
      '<button class="t-close" aria-label="Close">×</button>';
    el.querySelector('.t-body').textContent = message;
    stack.appendChild(el);
    function kill() {
      el.classList.add('leaving');
      setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 250);
    }
    el.querySelector('.t-close').addEventListener('click', kill);
    setTimeout(kill, timeout || 3500);
  }
  window.freshToast = toast;

  /* Surface PHP flashed toast via <meta name="flash-toast" content="msg|kind"> */
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('meta[name="flash-toast"]').forEach(function (m) {
      var parts = (m.getAttribute('content') || '').split('|');
      toast(parts[0] || '', parts[1] || 'success', 4000);
    });
  });

  /* ---------- Loading state on submit buttons ---------- */
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (form.method && form.method.toUpperCase() !== 'POST') return;
    if (form.dataset.noLoading === '1') return;
    var btn = form.querySelector('button[type="submit"]');
    if (!btn || btn.disabled) return;
    // Don't show spinner on tiny qty forms (let them redirect)
    if (btn.classList.contains('qty-stepper') || form.classList.contains('qty-form')) return;
    form.dataset._origBtnText = btn.textContent;
    btn.classList.add('is-loading');
    btn.disabled = true;
    // Restore after a moment if navigation didn't happen (e.g. validation failed)
    setTimeout(function () {
      if (btn && document.body.contains(btn)) {
        btn.classList.remove('is-loading');
        btn.disabled = false;
      }
    }, 4000);
  });

  /* ---------- AJAX cart (qty +/- on shop, product detail, cart page) ---------- */
  function postForm(form, cb, submitter) {
    var data = new FormData(form);
    // FormData(form) does not include the clicked submit button (e.g. name="action" value="add")
    if (submitter && submitter.name) data.append(submitter.name, submitter.value);
    // Always send a flag so the server can return JSON instead of redirecting.
    data.append('ajax', '1');
    var xhr = new XMLHttpRequest();
    // getAttribute: form.action is shadowed by a <button name="action">
    xhr.open('POST', form.getAttribute('action') || window.location.href, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) return;
      try {
        var json = JSON.parse(xhr.responseText);
        cb(null, json);
      } catch (err) {
        cb(err);
      }
    };
    xhr.send(data);
  }

  function refreshCartBadge(count) {
    var badge = document.querySelector('[data-cart-count]');
    if (!badge) return;
    badge.textContent = count;
    badge.style.display = count > 0 ? 'inline-flex' : 'none';
  }

  function refreshLineTotals(state) {
    // state = { items: [{id, qty, line_total}], subtotal, delivery, total, count }
    if (!state) return;
    refreshCartBadge(state.count);
    document.querySelectorAll('[data-cart-subtotal]').forEach(function (el) {
      el.textContent = state.subtotal_fmt;
    });
    document.querySelectorAll('[data-cart-delivery]').forEach(function (el) {
      el.textContent = state.delivery_fmt;
    });
    document.querySelectorAll('[data-cart-total]').forEach(function (el) {
      el.textContent = state.total_fmt;
    });
    // Update each row's qty + line total.
    (state.items || []).forEach(function (it) {
      document.querySelectorAll('[data-row-product="' + it.id + '"]').forEach(function (row) {
        var q = row.querySelector('[data-row-qty]');
        if (q) q.textContent = it.qty;
        var lt = row.querySelector('[data-row-linetotal]');
        if (lt) lt.textContent = it.line_total_fmt;
      });
    });
    // Update free-delivery progress.
    var dp = document.querySelector('[data-dp-fill]');
    if (dp && state.threshold) {
      var pct = Math.min(100, Math.round((state.subtotal / state.threshold) * 100));
      dp.style.width = pct + '%';
    }
  }

  // Generic "ajax-cart" form: any <form class="ajax-cart"> posts to api/cart.php.
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.classList.contains('ajax-cart')) return;
    e.preventDefault();
    postForm(form, function (err, json) {
      if (err || !json || !json.ok) {
        toast((json && json.message) || 'Could not update cart.', 'error');
        return;
      }
      if (json.message) toast(json.message, 'success', 2000);
      refreshLineTotals(json.state);
      // Remove row if qty == 0
      (json.state.items || []).forEach(function (it) {
        if (it.qty === 0) {
          document.querySelectorAll('[data-row-product="' + it.id + '"]').forEach(function (r) {
            r.remove();
          });
        }
      });
      // If cart is empty, reload so the empty state shows.
      if (json.state.count === 0 && document.body.classList.contains('page-cart')) {
        setTimeout(function () { window.location.reload(); }, 600);
      }
    }, e.submitter);
  });

  /* ---------- Coupon preview (checkout) ---------- */
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.classList.contains('coupon-form')) return;
    e.preventDefault();
    var input = form.querySelector('input[name="coupon_code"]');
    if (!input) return;
    var code = input.value.trim();
    if (!code) { toast('Enter a coupon code.', 'error'); return; }
    var xhr = new XMLHttpRequest();
    xhr.open('POST', form.getAttribute('action') || window.location.href, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    var data = new FormData(form);
    data.append('ajax', '1');
    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) return;
      try {
        var json = JSON.parse(xhr.responseText);
        if (json.ok) {
          toast(json.message || 'Coupon applied.', 'success', 1500);
          // Reload so the discount row + "applied" pill render server-side.
          setTimeout(function () { window.location.reload(); }, 700);
        } else {
          toast(json.message || 'Coupon could not be applied.', 'error');
        }
      } catch (err) {
        toast('Could not apply coupon.', 'error');
      }
    };
    xhr.send(data);
  });

  /* ---------- Confirm before destructive actions ---------- */
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-confirm]');
    if (!t) return;
    var msg = t.getAttribute('data-confirm') || 'Are you sure?';
    if (!window.confirm(msg)) { e.preventDefault(); }
  });

  /* ---------- Live search results count (decorative) + autocomplete ---------- */
  var searchInput = document.querySelector('[data-autocomplete]');
  if (searchInput) {
    searchInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && searchInput.value.trim() === '') {
        e.preventDefault();
      }
    });
    initAutocomplete(searchInput);
  }

  function initAutocomplete(input) {
    var form     = input.closest('form');
    var suggest  = form ? form.querySelector('[data-suggest]') : null;
    if (!suggest) return;
    // FRESH_BASE is set by head_meta() in includes/header.php.
    var base     = window.FRESH_BASE || '';
    var apiPath  = base + 'api/search.php';

    var timer    = null;
    var items    = [];
    var activeIdx = -1;

    function hide() {
      suggest.hidden = true;
      suggest.innerHTML = '';
      activeIdx = -1;
    }
    function show(html) {
      suggest.innerHTML = html;
      suggest.hidden = false;
      items = suggest.querySelectorAll('.ss-item');
      activeIdx = -1;
    }

    input.addEventListener('input', function () {
      var q = input.value.trim();
      if (timer) clearTimeout(timer);
      if (q.length < 2) { hide(); return; }
      timer = setTimeout(function () {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', apiPath + '?q=' + encodeURIComponent(q), true);
        xhr.onreadystatechange = function () {
          if (xhr.readyState !== 4) return;
          if (xhr.status !== 200) return;
          try {
            var rows = JSON.parse(xhr.responseText);
            if (!rows.length) {
              show('<div class="ss-empty">No products match "' + escapeHtml(q) + '"</div>');
              return;
            }
            var html = rows.map(function (r) {
              var iconHtml = r.image
                ? '<img src="' + (base + r.image) + '" alt="">'
                : r.icon;
              var price = 'Rs. ' + Number(r.price).toLocaleString('en-US', {maximumFractionDigits:0});
              var stock = r.in_stock ? '' : '<span class="ss-out">Out of stock</span>';
              // r.url is relative to project root (e.g. "product.php?id=5"); prepend base.
              return '<a class="ss-item" href="' + base + r.url + '">' +
                       '<div class="ss-icon">' + iconHtml + '</div>' +
                       '<div class="ss-info">' +
                         '<div class="ss-name">' + escapeHtml(r.name) + '</div>' +
                         '<div class="ss-meta">' + escapeHtml(r.category) + ' · ' + escapeHtml(r.unit) + '</div>' +
                       '</div>' +
                       '<div class="ss-price">' + price + '</div>' +
                       stock +
                     '</a>';
            }).join('');
            html += '<div class="ss-footer">Press Enter to see all results</div>';
            show(html);
          } catch (err) { /* ignore */ }
        };
        xhr.send();
      }, 200);
    });

    // Keyboard navigation.
    input.addEventListener('keydown', function (e) {
      if (suggest.hidden || !items.length) return;
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeIdx = (activeIdx + 1) % items.length;
        updateActive();
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIdx = (activeIdx - 1 + items.length) % items.length;
        updateActive();
      } else if (e.key === 'Escape') {
        hide();
      } else if (e.key === 'Enter' && activeIdx >= 0) {
        e.preventDefault();
        if (items[activeIdx]) window.location.href = items[activeIdx].href;
      }
    });
    function updateActive() {
      items.forEach(function (el, i) {
        el.classList.toggle('active', i === activeIdx);
      });
    }

    // Hide on outside click.
    document.addEventListener('click', function (e) {
      if (!form.contains(e.target)) hide();
    });
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
    });
  }

  /* ---------- Password strength meter (register/profile) ---------- */
  var pw = document.querySelector('input[name="password"], input[name="new_password"]');
  if (pw && pw.form) {
    pw.addEventListener('input', function () {
      var v = pw.value || '';
      var score = 0;
      if (v.length >= 8) score++;
      if (/[A-Z]/.test(v)) score++;
      if (/[a-z]/.test(v)) score++;
      if (/[0-9]/.test(v)) score++;
      if (/[^A-Za-z0-9]/.test(v)) score++;
      var hint = pw.parentNode.querySelector('.pw-hint');
      if (!hint) {
        hint = document.createElement('div');
        hint.className = 'pw-hint';
        hint.style.fontSize = '0.75rem';
        hint.style.marginTop = '4px';
        pw.parentNode.appendChild(hint);
      }
      var labels = ['Too weak', 'Weak', 'Fair', 'Good', 'Strong'];
      var colors = ['var(--berry)', 'var(--berry)', 'var(--orange-dark)', 'var(--green)', 'var(--green-dark)'];
      hint.textContent = v ? 'Strength: ' + labels[score] : '';
      hint.style.color = colors[score];
    });
  }

  /* ---------- Service worker registration (PWA / offline) ---------- */
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      // FRESH_BASE is set by head_meta() in includes/header.php — it's the relative
      // path from this page to the project root ('' for root pages, '../' for subfolders).
      var base = window.FRESH_BASE || '';
      // Compute the absolute scope path: strip the filename from the current URL
      // and append the base. e.g. /fresh-grocery/customer/dashboard.php
      //  -> /fresh-grocery/customer/  + ../  -> /fresh-grocery/
      var here = location.pathname.replace(/\/[^/]*$/, '/');
      var scopePath = here + base;
      // Normalise any "../" segments in the scope path.
      var parts = scopePath.split('/');
      var stack = [];
      for (var i = 0; i < parts.length; i++) {
        if (parts[i] === '..') stack.pop();
        else if (parts[i] !== '' && parts[i] !== '.') stack.push(parts[i]);
      }
      var scope = stack.length ? '/' + stack.join('/') + '/' : '/';
      var swUrl = scope + 'sw.js';
      navigator.serviceWorker.register(swUrl, { scope: scope }).catch(function (err) {
        if (window.console) console.warn('SW registration failed:', err);
      });
    });
  }

  /* ---------- PWA install prompt (beforeinstallprompt) ---------- */
  var deferredPrompt = null;
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferredPrompt = e;
    showPwaBanner();
  });

  function showPwaBanner() {
    if (document.querySelector('.pwa-banner')) return;
    if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) return; // already installed
    var banner = document.createElement('div');
    banner.className = 'pwa-banner';
    banner.innerHTML =
      '<span class="pb-icon">🥬</span>' +
      '<div class="pb-text"><strong>Install Fresh</strong> for faster access and offline shopping.</div>' +
      '<div class="pb-actions">' +
        '<button class="btn btn-primary btn-sm" data-pwa-install>Install</button>' +
        '<button class="btn btn-ghost btn-sm" data-pwa-dismiss>Later</button>' +
      '</div>';
    document.body.appendChild(banner);
    banner.querySelector('[data-pwa-install]').addEventListener('click', function () {
      if (!deferredPrompt) return;
      deferredPrompt.prompt();
      deferredPrompt.userChoice.then(function () {
        deferredPrompt = null;
        banner.remove();
      });
    });
    banner.querySelector('[data-pwa-dismiss]').addEventListener('click', function () {
      banner.remove();
      try { localStorage.setItem('fresh_pwa_dismissed', '1'); } catch (e) {}
    });
  }

  // Respect a previous "Later" dismissal (24h cooldown).
  try {
    var dismissed = localStorage.getItem('fresh_pwa_dismissed');
    if (dismissed) {
      var age = Date.now() - parseInt(dismissed, 10);
      if (age < 24 * 60 * 60 * 1000) {
        window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); });
      } else {
        localStorage.removeItem('fresh_pwa_dismissed');
      }
    }
  } catch (e) {}

  // Replace the timestamp-based dismiss with a real one when user clicks Later.
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-pwa-dismiss]')) {
      try { localStorage.setItem('fresh_pwa_dismissed', String(Date.now())); } catch (err) {}
    }
  });
})();
