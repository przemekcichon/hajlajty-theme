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

   EFEKTY ZDARZEŃ (MVP-b): ten plik robi WYŁĄCZNIE I/O — czyta DOM (sygnatury
   zdarzeń z data-ev/data-ev-kind, wynik z .board__nums .n[data-side]) i nakłada
   klasy animacji (live-effects.css). DECYZJĘ „co jest nowe" podejmuje czysta,
   testowalna funkcja hajlajtyLiveDetect.detect() (live-detect.js) — bez DOM.
   Baseline z PIERWSZEGO renderu nie animuje historii; animujemy tylko przyrost.
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

  // --- Wykrywanie przyrostu zdarzeń (MVP-b): czysta decyzja w live-detect.js ---
  // Brak modułu (np. nie wczytany) → efekty off, ale polling działa dalej (null-safe).
  var DETECT = window.hajlajtyLiveDetect || null;
  var seen = {};
  var score = { home: null, away: null };

  // I/O: bieżące sygnatury zdarzeń z DOM (po swapie węzły są nowe — re-query).
  function readEvents() {
    var out = [];
    var items = document.querySelectorAll(".tl-item[data-ev]");
    Array.prototype.forEach.call(items, function (el) {
      var sig = el.getAttribute("data-ev");
      if (sig) out.push({ sig: sig, kind: el.getAttribute("data-ev-kind") || "" });
    });
    return out;
  }

  // I/O: liczba z węzła telebimu ("–"/puste → null; w trakcie gry to 0,1,2…).
  function scoreOf(side) {
    var el = document.querySelector('.board__nums .n[data-side="' + side + '"]');
    if (!el) return null;
    var t = (el.textContent || "").trim();
    return /^\d+$/.test(t) ? parseInt(t, 10) : null;
  }

  function readScore() {
    return { home: scoreOf("home"), away: scoreOf("away") };
  }

  // Baseline na starcie: bieżące zdarzenia + wynik to stan odniesienia (bez
  // animacji). Bierzemy tylko nextSeen/nextScore z pierwszej decyzji.
  if (DETECT) {
    var base = DETECT.detect({}, { home: null, away: null }, readEvents(), readScore());
    seen = base.nextSeen;
    score = base.nextScore;
  }

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

  // Po swapie: czysta decyzja → I/O (nakładanie klas). Klasy dekoracyjne —
  // reduce-motion zeruje animacje w CSS, więc tu bez guardu na motion.
  function animateDeltas() {
    if (!DETECT) return;

    var r = DETECT.detect(seen, score, readEvents(), readScore());
    seen = r.nextSeen;
    score = r.nextScore;

    // Nowe zdarzenia osi → klasa wg kind na świeżym węźle (animuje raz).
    var anyNew = false;
    var sig;
    for (sig in r.newSigs) { if (Object.prototype.hasOwnProperty.call(r.newSigs, sig)) { anyNew = true; break; } }
    if (anyNew) {
      var items = document.querySelectorAll(".tl-item[data-ev]");
      Array.prototype.forEach.call(items, function (el) {
        var kind = r.newSigs[el.getAttribute("data-ev")];
        if (kind === undefined) return;
        if (kind === "goal") el.classList.add("is-ev-goal");
        else if (kind === "card") el.classList.add("is-ev-card");
        else if (kind === "sub") el.classList.add("is-ev-sub");
      });
    }

    // Wzrost wyniku → bump na zmienionej liczbie.
    r.bumps.forEach(function (side) {
      var el = document.querySelector('.board__nums .n[data-side="' + side + '"]');
      if (el) el.classList.add("is-bump");
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
