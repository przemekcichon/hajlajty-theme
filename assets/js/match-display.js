/* ============================================================
   HAJLAJTY — assets/js/match-display.js
   ------------------------------------------------------------
   Logika WIDOKU meczu (ładowana tylko na single „mecz"):
   zakładki + animacja słupków statystyk + facade playera YouTube.
   Przełącznik paneli składów (home/away) dochodzi w E5. NIE portuje
   #followBtn (Faza 4) ani #favBtn (globalny, poza 3b). Reużywa
   mechaniki 1:1 z monolitu. Wszystkie uchwyty null-safe.
============================================================ */
(function () {
  "use strict";

  var $  = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  /* ---------- ZAKŁADKI (Oś czasu / Składy / Statystyki) ---------- */
  var stats = $$(".stat");
  function animateStats() {
    stats.forEach(function (st) {
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
})();
