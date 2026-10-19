/* js/theme.js — light / dark mode switch (plain JavaScript, no libraries).
   Loaded in <head> so the saved theme is applied BEFORE the page paints (no flash). */
(function () {
  var KEY = 'fresh_theme', root = document.documentElement;

  function saved() { try { return localStorage.getItem(KEY); } catch (e) { return null; } }
  function systemPref() {
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }
  function apply(theme) {
    root.setAttribute('data-theme', theme);
    var dark = theme === 'dark';
    var btns = document.querySelectorAll('[data-theme-toggle]');
    for (var i = 0; i < btns.length; i++) {
      btns[i].textContent = dark ? '☀️' : '🌙';
      btns[i].setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
      btns[i].setAttribute('title', dark ? 'Light mode' : 'Dark mode');
      btns[i].setAttribute('aria-pressed', dark ? 'true' : 'false');
    }
  }

  apply(saved() || systemPref());                       // first paint
  document.addEventListener('DOMContentLoaded', function () { apply(root.getAttribute('data-theme')); });

  document.addEventListener('click', function (e) {
    var t = e.target.closest ? e.target.closest('[data-theme-toggle]') : null;
    if (!t) return;
    var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    try { localStorage.setItem(KEY, next); } catch (err) {}
    apply(next);
  });
})();
