/* ============================================================
   HAJLAJTY — assets/js/match-display.js
   ------------------------------------------------------------
   Logika WIDOKU meczu (ładowana tylko na single „mecz"):
   zakładki + animacja słupków statystyk + przełącznik paneli składów
   (home/away) + facade playera YouTube + odliczanie ZAPOWIEDZI (hero + mini).
   NIE portuje: #followBtn/#favBtn (Faza 4 hajlajty-user), symulacji zdarzeń
   LIVE, podbijania minuty ani przycisków demo (minuta LIVE jest statyczna,
   z PHP — odświeża się przy F5; auto-refresh = 3e). Wszystkie uchwyty null-safe.
============================================================ */
(function () {
  "use strict";

  var $  = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  /* ---------- ZAKŁADKI (Oś czasu / Składy / Statystyki) ---------- */
  // Re-query .stat za każdym wywołaniem: po auto-refreshu (3e-iii) węzły są nowe.
  function animateStats() {
    $$(".stat").forEach(function (st) {
      var h = parseFloat(st.dataset.h) || 0, a = parseFloat(st.dataset.a) || 0;
      var total = h + a || 1, hp = Math.round((h / total) * 100), ap = 100 - hp;
      var fills = $$(".stat__fill", st);
      if (fills.length < 2) return;
      requestAnimationFrame(function () { fills[0].style.width = hp + "%"; fills[1].style.width = ap + "%"; });
    });
  }

  var tabs = $$(".tabs .tab"), panels = $$(".tabpanel"), statsAnimated = false;
  function activateTab(name) {
    tabs.forEach(function (t) {
      var on = t.dataset.tab === name;
      t.classList.toggle("is-active", on);
      t.setAttribute("aria-selected", on ? "true" : "false");
    });
    panels.forEach(function (p) { p.classList.toggle("is-active", p.dataset.tab === name); });
    if (name === "stats" && !statsAnimated) { statsAnimated = true; requestAnimationFrame(animateStats); }
  }
  tabs.forEach(function (t) { t.addEventListener("click", function () { activateTab(t.dataset.tab); }); });
  // Jeśli statystyki są domyślnie aktywne (np. brak osi czasu) — animuj od razu.
  if (!statsAnimated && $(".tabpanel.is-active[data-tab='stats']")) {
    statsAnimated = true; requestAnimationFrame(animateStats);
  }
  // LIVE/NS: statystyki poza systemem zakładek (aside) — brak .tabs, więc
  // activateTab nigdy nie odpali. Statystyki są od razu widoczne → animuj na starcie.
  if (!statsAnimated && !tabs.length && $$(".stat").length) {
    statsAnimated = true; requestAnimationFrame(animateStats);
  }

  // Auto-refresh (3e-iii): po podmianie fragmentu live przez poller słupki są nowe
  // (width:0 z CSS) — przeanimuj je ponownie. Jedno źródło logiki animacji.
  document.addEventListener("hajlajty:live-updated", function () {
    requestAnimationFrame(animateStats);
  });

  /* ---------- LIVE (P-d): oś czasu jako przewijalny panel wysokości telebimu ----------
     Desktop (≥1220px): max-height kontenera listy osi = wysokość .board (telebimu),
     żeby długa oś nie rozciągała strony i mieściła się obok telebimu. Mierzymy w JS,
     bo oś żyje w prawej kolumnie o innej szerokości niż telebim — czystym CSS nie da
     się sięgnąć jego piksela. Kontener scrolla (.live-timeline-scroll) jest STABILNY
     (poza kotwicą pollera), więc wartość przeżywa podmianę; mimo to re-synchronizujemy
     po każdym odświeżeniu (telebim mógł zmienić rozmiar) i przy resize. Poza desktopem
     czyścimy max-height — oś jest wtedy zwykłą zakładką w przepływie. Null-safe. */
  var timelineScroll = $(".live-timeline-scroll");
  if (timelineScroll) {
    var desktopMq = window.matchMedia ? window.matchMedia("(min-width: 1220px)") : null;
    var syncTimelineHeight = function () {
      var board = $(".board");
      if (board && (!desktopMq || desktopMq.matches)) {
        timelineScroll.style.maxHeight = board.offsetHeight + "px";
      } else {
        timelineScroll.style.maxHeight = "";
      }
    };
    // Po zmianie breakpointu aktywna zakładka może mieć UKRYTY przycisk (na
    // desktopie „Oś czasu" znika z paska — oś jest osobnym panelem). Wtedy główna
    // kolumna zostałaby bez treści (np. user kliknął „Oś czasu" w wąskim oknie,
    // potem je poszerzył ≥1220px). Przełącz wtedy na pierwszą WIDOCZNĄ zakładkę.
    // Ogólne: single-ft nie chowa żadnego przycisku, więc tam nigdy nie zadziała.
    var ensureVisibleActiveTab = function () {
      var active = $(".tabs .tab.is-active");
      if (active && active.offsetParent === null) {
        for (var i = 0; i < tabs.length; i++) {
          if (tabs[i].offsetParent !== null) { activateTab(tabs[i].dataset.tab); break; }
        }
      }
    };
    var onViewport = function () { syncTimelineHeight(); ensureVisibleActiveTab(); };
    requestAnimationFrame(onViewport);
    window.addEventListener("resize", onViewport);
    document.addEventListener("hajlajty:live-updated", syncTimelineHeight);
  }

  /* ---------- SKŁADY: przełącznik paneli home/away ---------- */
  var lineupTabs = $$("#lineupTabs .lineup-tab");
  if (lineupTabs.length) {
    lineupTabs.forEach(function (tab) {
      tab.addEventListener("click", function () {
        var pane = tab.dataset.pane;
        lineupTabs.forEach(function (t) { t.classList.toggle("is-active", t === tab); });
        $$(".lineup-pane").forEach(function (p) { p.classList.toggle("is-active", p.dataset.pane === pane); });
      });
    });
  }

  /* ---------- ODLICZANIE (ZAPOWIEDŹ): hero + mini-liczniki ----------
     Logika 1:1 z designu zapowiedzi (poprawna pod ABSOLUTNY instant: data-kickoff
     to ISO z offsetem, np. „2026-06-15T18:00:00+00:00"). Wszystko guardowane:
     uruchamiamy interwał tylko gdy na stronie jest licznik (LIVE/skrót go nie ma). */
  function pad(n) { return n < 10 ? "0" + n : "" + n; }

  var heroBox = $("[data-countdown]");
  var hero = heroBox ? {
    target: new Date(heroBox.dataset.kickoff).getTime(), box: heroBox,
    d: $("[data-d]", heroBox), h: $("[data-h]", heroBox), m: $("[data-m]", heroBox), s: $("[data-s]", heroBox)
  } : null;

  var minis = $$("[data-mini]").map(function (el) {
    return { target: new Date(el.dataset.kickoff).getTime(), el: el, val: $("[data-mini-val]", el) };
  });

  function fmtMini(diff) {
    var sec = Math.floor(diff / 1000);
    var days = Math.floor(sec / 86400);
    var hrs  = Math.floor((sec % 86400) / 3600);
    var min  = Math.floor((sec % 3600) / 60);
    if (days >= 1) return "za " + days + (days === 1 ? " dzień" : " dni");
    return pad(hrs) + ":" + pad(min) + " h";
  }

  function tickCountdown() {
    var now = Date.now();
    if (hero) {
      var diff = Math.max(0, hero.target - now); // diff<=0 → hero zatrzymany na 00:00:00.
      var sec = Math.floor(diff / 1000);
      if (hero.d) hero.d.textContent = pad(Math.floor(sec / 86400));
      if (hero.h) hero.h.textContent = pad(Math.floor((sec % 86400) / 3600));
      if (hero.m) hero.m.textContent = pad(Math.floor((sec % 3600) / 60));
      if (hero.s) hero.s.textContent = pad(sec % 60);
      hero.box.classList.toggle("is-soon", diff > 0 && diff < 86400000);
    }
    minis.forEach(function (c) {
      if (!c.val) return;
      var diff = Math.max(0, c.target - now);
      c.val.textContent = diff <= 0 ? "trwa" : fmtMini(diff); // diff<=0 → „trwa".
      c.el.classList.toggle("is-soon", diff > 0 && diff < 86400000);
    });
  }
  if (hero || minis.length) {
    tickCountdown();
    setInterval(tickCountdown, 1000);
  }

  /* ---------- PLAYER: facade → osadzony iframe YouTube ---------- */
  var player = $("#player"), playBtn = $("#playBtn");
  if (player && playBtn) {
    playBtn.addEventListener("click", function () {
      var id = player.dataset.yt;
      if (!id) return;
      var ifr = document.createElement("iframe");
      ifr.src = "https://www.youtube-nocookie.com/embed/" + id + "?autoplay=1&rel=0&modestbranding=1";
      ifr.title = player.dataset.title || "Skrót meczu";
      ifr.allow = "accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share";
      ifr.setAttribute("allowfullscreen", "");
      player.classList.add("is-playing");
      player.appendChild(ifr);
    });
  }

  /* ---------- WRÓĆ: cofnij do poprzedniej strony (nie zawsze home) ----------
     Progressive enhancement: href=home_url('/') zostaje jako fallback (brak JS
     LUB brak historii w serwisie). Gdy istnieje historia z TEJ SAMEJ domeny —
     przejmujemy klik i robimy history.back(). Wejście bezpośrednie / z nowej
     karty / z zewnętrznej strony → referer nie pasuje → link prowadzi na home. */
  var backLink = $(".back-link");
  if (backLink && history.length > 1 && document.referrer) {
    var sameOrigin = false;
    try { sameOrigin = new URL(document.referrer).origin === location.origin; } catch (e) { sameOrigin = false; }
    if (sameOrigin) {
      backLink.addEventListener("click", function (e) {
        e.preventDefault();
        history.back();
      });
    }
  }
})();
