/* ============================================================
   HAJLAJTY — assets/js/layout.js
   ------------------------------------------------------------
   GLOBALNA logika powłoki, reużywana na każdym widoku (3b/3c/3d):
   przełącznik motywu (localStorage), sidebar off-canvas + scrim,
   dyskretne scrollbary. Wyekstrahowane z monolitu „Skrót Meczu".
   Logika WIDOKU meczu (player/zakładki/składy) jest osobno w
   match-display.js. Wszystkie uchwyty null-safe — plik ładuje się
   globalnie, a nie każda strona ma każdy element.
============================================================ */
(function () {
  "use strict";

  var STORE = { theme: "hajlajty:theme" };
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  /* ---------- MOTYW (wspólny klucz na całym serwisie) ---------- */
  var root = document.documentElement;
  try { var saved = localStorage.getItem(STORE.theme); if (saved) root.setAttribute("data-theme", saved); } catch (e) {}
  var themeBtn = $("#themeBtn");
  if (themeBtn) {
    themeBtn.addEventListener("click", function () {
      var next = root.getAttribute("data-theme") === "dark" ? "light" : "dark";
      root.setAttribute("data-theme", next);
      try { localStorage.setItem(STORE.theme, next); } catch (e) {}
    });
  }

  /* ---------- SIDEBAR (off-canvas + scrim) ---------- */
  var sidebar = $("#sidebar"), scrim = $("#scrim"), menuBtn = $("#menuBtn");
  if (sidebar && scrim && menuBtn) {
    var openSidebar = function () {
      sidebar.classList.add("is-open"); scrim.hidden = false;
      requestAnimationFrame(function () { scrim.classList.add("is-open"); });
      menuBtn.setAttribute("aria-expanded", "true"); document.body.style.overflow = "hidden";
    };
    var closeSidebar = function () {
      sidebar.classList.remove("is-open"); scrim.classList.remove("is-open");
      menuBtn.setAttribute("aria-expanded", "false"); document.body.style.overflow = "";
      setTimeout(function () { if (!scrim.classList.contains("is-open")) scrim.hidden = true; }, 260);
    };
    menuBtn.addEventListener("click", openSidebar);
    var closeBtn = $("#sidebarClose");
    if (closeBtn) closeBtn.addEventListener("click", closeSidebar);
    scrim.addEventListener("click", closeSidebar);
    document.addEventListener("keydown", function (e) { if (e.key === "Escape") closeSidebar(); });
    $$(".sidebar .nav-link").forEach(function (a) { a.addEventListener("click", closeSidebar); });
  }

  /* ---------- DYSKRETNE SCROLLBARY ---------- */
  function discreteScroll(el) {
    if (!el) return; var timer = null;
    el.addEventListener("scroll", function () {
      el.classList.add("is-scrolling");
      if (timer) clearTimeout(timer);
      timer = setTimeout(function () { el.classList.remove("is-scrolling"); }, 650);
    }, { passive: true });
  }
  [sidebar, $("#content")].forEach(discreteScroll);

  /* ---------- Wejście sekcji (delikatne reveal — identyczna mechanika) ---------- */
  root.classList.add("js-anim");
})();
