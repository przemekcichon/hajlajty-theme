/* ============================================================
   HAJLAJTY — assets/js/match-lists.js
   ------------------------------------------------------------
   Odliczanie na KARTACH ZAPOWIEDZI (lista + strona główna). Mały, samodzielny,
   bez zależności. Czyta absolutny instant z [data-kickoff] (ISO z offsetem, np.
   „2026-06-15T18:00:00+00:00"), tyka dni/godz/min/sek do kickoffu i — po jego
   przekroczeniu — pokazuje „Pierwszy gwizdek".

   NIE portuje: ulubionych/dzwonka (Faza 4), filtrów/chipsów (Faza 4A), żadnego
   pollingu/AJAX (auto-refresh = 3e). Wszystkie uchwyty null-safe; brak kart z
   odliczaniem = brak interwału.
============================================================ */
(function () {
  "use strict";

  function pad(n) { return n < 10 ? "0" + n : "" + n; }

  var boxes = Array.prototype.slice.call(document.querySelectorAll("[data-countdown]"));

  var counters = boxes.map(function (box) {
    var host = box.closest("[data-kickoff]") || box; // data-kickoff żyje na karcie.
    var iso  = host.getAttribute("data-kickoff");
    if (!iso) return null;
    return {
      target: new Date(iso).getTime(),
      box: box,
      d: box.querySelector("[data-d]"),
      h: box.querySelector("[data-h]"),
      m: box.querySelector("[data-m]"),
      s: box.querySelector("[data-s]"),
      done: false
    };
  }).filter(Boolean);

  if (!counters.length) return;

  function tick() {
    var now = Date.now();
    counters.forEach(function (c) {
      var diff = c.target - now;

      if (diff <= 0) {
        // Po pierwszym gwizdku — terminalny stan karty (zostaje do nawigacji).
        if (!c.done) {
          c.done = true;
          c.box.classList.remove("is-soon");
          c.box.classList.add("is-live");
          c.box.innerHTML = '<span class="card__kickoff-now">Pierwszy gwizdek</span>';
        }
        return;
      }

      var sec = Math.floor(diff / 1000);
      if (c.d) c.d.textContent = pad(Math.floor(sec / 86400));
      if (c.h) c.h.textContent = pad(Math.floor((sec % 86400) / 3600));
      if (c.m) c.m.textContent = pad(Math.floor((sec % 3600) / 60));
      if (c.s) c.s.textContent = pad(sec % 60);
      c.box.classList.toggle("is-soon", diff < 86400000); // < 24h → akcent.
    });
  }

  tick();
  setInterval(tick, 1000);
})();
