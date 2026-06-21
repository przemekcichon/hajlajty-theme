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

   EFEKTY ZDARZEŃ (MVP-b): po każdej podmianie wykrywamy PRZYROST względem
   poprzedniego stanu i nakładamy klasy animacji (live-effects.css):
   - nowe zdarzenie na osi (data-ev, data-ev-kind) → is-ev-goal/card/sub,
   - wzrost wyniku (.board__nums .n[data-side]) → is-bump.
   Sygnatury są z DANYCH JUŻ we fragmencie (bez nowego pola serwerowego). Baseline
   z PIERWSZEGO renderu nie animuje historii — animujemy tylko przyrost w sesji.
   Reduce-motion obsługuje CSS (animacje wyłączone); podmiana działa dalej.

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

  // --- Stan dla wykrywania przyrostu zdarzeń (MVP-b) ---
  // seen: zbiór sygnatur zdarzeń już obecnych (klucz data-ev). score: ostatni wynik.
  // Inicjalizacja z PIERWSZEGO renderu = baseline (historia nie animuje się).
  var seen = {};
  var score = { home: null, away: null };

  // Liczba z węzła telebimu ("–"/puste → null; w trakcie gry to 0,1,2…).
  function scoreOf(side) {
    var el = document.querySelector('.board__nums .n[data-side="' + side + '"]');
    if (!el) return null;
    var t = (el.textContent || "").trim();
    return /^\d+$/.test(t) ? parseInt(t, 10) : null;
  }

  // Zapamiętaj wszystkie obecne sygnatury zdarzeń (bez animowania).
  function markSeen() {
    var items = document.querySelectorAll(".tl-item[data-ev]");
    Array.prototype.forEach.call(items, function (el) {
      var sig = el.getAttribute("data-ev");
      if (sig) seen[sig] = true;
    });
  }

  // Baseline na starcie: bieżące zdarzenia + wynik to stan odniesienia.
  markSeen();
  score.home = scoreOf("home");
  score.away = scoreOf("away");

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

  // Po podmianie: animuj TYLKO przyrost. Nowe zdarzenia na osi + wzrost wyniku.
  // Klasy są dekoracyjne — reduce-motion zeruje animacje w CSS, więc tu bez guardu.
  function animateDeltas() {
    // Nowe zdarzenia osi czasu.
    var items = document.querySelectorAll(".tl-item[data-ev]");
    Array.prototype.forEach.call(items, function (el) {
      var sig = el.getAttribute("data-ev");
      if (!sig || seen[sig]) return; // znane / baseline — bez efektu.
      seen[sig] = true;
      switch (el.getAttribute("data-ev-kind")) {
        case "goal": el.classList.add("is-ev-goal"); break;
        case "card": el.classList.add("is-ev-card"); break;
        case "sub":  el.classList.add("is-ev-sub");  break;
        default: break; // missed_penalty / var / inne — bez efektu.
      }
    });

    // Wzrost wyniku → bump na zmienionej liczbie (tylko realny przyrost liczbowy).
    ["home", "away"].forEach(function (side) {
      var nv = scoreOf(side);
      var ov = score[side];
      if (nv !== null && ov !== null && nv > ov) {
        var el = document.querySelector('.board__nums .n[data-side="' + side + '"]');
        if (el) el.classList.add("is-bump");
      }
      if (nv !== null) score[side] = nv; // aktualizuj baseline (też gdy spadek/korekta).
    });
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
        animateDeltas(); // Po swapie: węzły są nowe — nakładamy efekt na przyrost.
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

  // Po powrocie do karty odśwież OD RAZU (nie czekaj do końca interwału). W tle
  // tick() i tak pomija żądanie, a przeglądarka dławi interwał — bez tego wejście
  // na kartę pokazywałoby stan sprzed nawet ~30 s.
  document.addEventListener("visibilitychange", function () {
    if (!document.hidden && timer) tick();
  });
})();
