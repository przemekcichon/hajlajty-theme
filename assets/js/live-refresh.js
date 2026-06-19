/* ============================================================
   HAJLAJTY — assets/js/live-refresh.js
   ------------------------------------------------------------
   Auto-refresh widoku LIVE pojedynczego meczu (3e-iii, rekomendacja B1).
   Co ~30 s pobiera WYRENDEROWANY FRAGMENT HTML z REST
   (/wp-json/hajlajty/v1/mecz/{id}/live) i podmienia w DOM kotwice
   .hajlajty-live (telebim / oś / statystyki) — po ID, jeden do jednego.

   ZERO równoległego renderera: markup robi PHP (ten sam partial co single).
   Sygnał „czy dalej pollować" siedzi w HTML (data-live): gdy fragment wraca z
   data-live="0" (status ≠ live, np. po FT), nakładamy go ostatni raz i KOŃCZYMY
   interwał. Po podmianie statystyk wołamy match-display.js (event
   `hajlajty:live-updated`), żeby przeanimował słupki — bez duplikacji logiki.

   NIE robi teatru z designu: minuta/wynik pochodzą z odświeżonego fragmentu,
   nie z symulacji w JS. Wszystkie uchwyty null-safe; brak kotwicy = brak pollingu.
============================================================ */
(function () {
  "use strict";

  var INTERVAL_MS = 30000; // ~30 s (D3.8) — front nie goni kadencji API (15 s).
  var MAX_FAILS = 5;       // tyle błędów fetcha z rzędu → cichy stop (D-E).

  // Kotwica „prymarna" (telebim) niesie endpoint i stan live. Brak = nie ta strona.
  var primary = document.querySelector(".hajlajty-live[data-endpoint]");
  if (!primary) return;

  var endpoint = primary.getAttribute("data-endpoint");
  if (!endpoint) return;

  // Start tylko gdy mecz jest faktycznie live (data-live="1"). Po FT nie ruszamy.
  if (primary.getAttribute("data-live") !== "1") return;

  var timer = null;
  var fails = 0;

  function stop() {
    if (timer) { clearInterval(timer); timer = null; }
  }

  // Podmienia kotwice z odebranego HTML; zwraca stan data-live ("1"/"0"/null).
  function applyFragment(html) {
    var box = document.createElement("div");
    box.innerHTML = html;

    var incoming = Array.prototype.slice.call(box.querySelectorAll(".hajlajty-live"));
    if (!incoming.length) return null;

    var live = null;
    incoming.forEach(function (node) {
      var target = node.id ? document.getElementById(node.id) : null;
      if (!target) return; // Brak kotwicy w DOM (sekcja nieobecna od startu) — pomiń.

      // Nie odgrywaj animacji wejścia (.reveal) przy każdym odświeżeniu.
      Array.prototype.slice.call(node.querySelectorAll(".reveal")).forEach(function (el) {
        el.classList.remove("reveal");
      });

      if (live === null) live = node.getAttribute("data-live");
      target.replaceWith(node);
    });

    return live;
  }

  function tick() {
    if (document.hidden) return; // Karta w tle — nie marnuj żądań (nie liczone jako błąd).

    fetch(endpoint, { cache: "no-store", headers: { "X-Requested-With": "fetch" } })
      .then(function (res) {
        if (!res.ok) throw new Error("HTTP " + res.status);
        return res.text();
      })
      .then(function (html) {
        fails = 0;
        var live = applyFragment(html);
        // Po podmianie statystyk: niech match-display.js przeanimuje słupki.
        document.dispatchEvent(new CustomEvent("hajlajty:live-updated"));
        if (live === "0") stop(); // Stan końcowy nałożony — koniec pollingu.
      })
      .catch(function () {
        // Cichy pominięty cykl; po serii błędów z rzędu — stop (D-E).
        if (++fails >= MAX_FAILS) stop();
      });
  }

  timer = setInterval(tick, INTERVAL_MS);
})();
