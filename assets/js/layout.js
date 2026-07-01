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

  // Klucz motywu z PHP (wp_localize_script w layout.php) — TO SAMO źródło co
  // skrypt anti-FOUC w <head>, więc toggle zapisuje pod tym samym kluczem, który
  // head odczytuje. Fallback tylko na wypadek braku localize (nie powinien wystąpić).
  var STORE = {
    theme: (window.hajlajtyLayout && window.hajlajtyLayout.themeKey) || "hajlajty:theme",
    nav: (window.hajlajtyLayout && window.hajlajtyLayout.navKey) || "hajlajty:nav-collapsed",
  };
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  /* ---------- MOTYW (wspólny klucz na całym serwisie) ----------
     PIERWSZY paint ustawia blokujący skrypt inline w <head> (anti-FOUC, P-g):
     to on czyta STORE.theme z localStorage przed renderem <body>. Tu został już
     tylko TOGGLE — czyta aktualny data-theme (ustawiony w <head>) i przełącza. */
  var root = document.documentElement;
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
    // Hamburger ma dwa tryby zależne od widoku:
    //  - widoki z TRWAŁYM menu przy ≥1100px → menu jest STAŁE: hamburger
    //    zwija/rozwija kolumnę sidebara (body.nav-collapsed; layout w CSS),
    //  - single i mobile → klasyczny drawer off-canvas (open/close ze scrimem).
    // Lista MUSI odzwierciedlać selektory z layout.css „WIDOKI Z TRWAŁYM MENU":
    // home + archiwum CPT + Strony-szablony z sekcji „Mundial 2026" (terminarz,
    // tabela grup, reprezentacje, faza pucharowa). Bez tego na tych Stronach
    // hamburger trafiał w gałąź drawera, który przy ≥1100px nic nie robi (sidebar
    // jest statyczny) — stąd „hamburger nie działa" na terminarzu/tabeli/drabince.
    var PERSISTENT_NAV = [
      "home",
      "post-type-archive",
      "hajlajty-terminarz",
      "hajlajty-tabela-rozgrywek",
      "hajlajty-reprezentacje",
      "hajlajty-faza-pucharowa",
    ];
    var isPersistentNavPage = function () {
      return PERSISTENT_NAV.some(function (c) { return document.body.classList.contains(c); });
    };
    var isPersistentNav = function () {
      return window.matchMedia("(min-width: 1100px)").matches && isPersistentNavPage();
    };
    // nav-collapsed opisuje rail — stan WYŁĄCZNIE widoków z trwałym menu. Skrypt
    // anti-FOUC w header.php odtwarza go z localStorage na KAŻDEJ stronie (przed
    // paintem), więc na single/innych widokach klasa ląduje omyłkowo, a rail-owe
    // reguły CSS (font-size:0 na etykietach itd.) „zjadałyby" tekst w drawerze.
    // Zdejmujemy ją wg TYPU strony (klasa body), NIE szerokości — preferencja w
    // localStorage zostaje nietknięta, więc na widoku z trwałym menu rail wraca.
    // To przywraca inwariant „nav-collapsed tylko na trwałym menu", na którym
    // opierają się nieoscope'owane selektory rail w layout.css.
    if (!isPersistentNavPage()) {
      document.body.classList.remove("nav-collapsed");
    }
    menuBtn.addEventListener("click", function () {
      if (isPersistentNav()) {
        var collapsed = document.body.classList.toggle("nav-collapsed");
        menuBtn.setAttribute("aria-expanded", collapsed ? "false" : "true");
        // Zapamiętaj stan zwinięcia (rail vs pełne) — preferencja per-użytkownik,
        // tak samo jak motyw. Skrypt w header.php odtwarza go przed paintem.
        try { localStorage.setItem(STORE.nav, collapsed ? "collapsed" : "expanded"); } catch (e) {}
        return;
      }
      if (sidebar.classList.contains("is-open")) closeSidebar(); else openSidebar();
    });
    // Stan zwinięcia mógł zostać odtworzony przez skrypt w header.php (klasa
    // nav-collapsed na body) — zsynchronizuj aria-expanded na starcie w trybie
    // trwałego menu (w trybie drawera aria startuje jako zamknięte i tak zostaje).
    if (isPersistentNav()) {
      menuBtn.setAttribute("aria-expanded", document.body.classList.contains("nav-collapsed") ? "false" : "true");
    }
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
