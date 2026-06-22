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

  // --- OVERLAY ZDARZEŃ (pełnoekranowy telebim) ----------------------------
  // Kolejka FIFO: zdarzenia grają po kolei (nie nakładają się). Dane efektu czytamy
  // z data-ev-* elementu osi; overlay-skeleton żyje w .board (podmieniany przy pollu),
  // więc query NA ŻYWO przy każdym odtworzeniu.
  var OVERLAY_MS = 3500;  // czas widoczności overlayu.
  var OVERLAY_GAP = 250;  // odstęp między kolejnymi w kolejce.
  var evQueue = [];
  var evBusy = false;

  function setText(scope, sel, txt) {
    var el = scope.querySelector(sel);
    if (el) el.textContent = txt || "";
  }

  function readEventData(el, kind) {
    return {
      kind: kind || el.getAttribute("data-ev-kind") || "",
      player: el.getAttribute("data-ev-player") || "",
      team: el.getAttribute("data-ev-team") || "",
      flag: el.getAttribute("data-ev-flag") || "",
      min: el.getAttribute("data-ev-min") || "",
      color: el.getAttribute("data-ev-color") || "",
      label: el.getAttribute("data-ev-label") || "",
      cardkind: el.getAttribute("data-ev-cardkind") || "",
      inName: el.getAttribute("data-ev-in") || "",
      outName: el.getAttribute("data-ev-out") || ""
    };
  }

  function overlayDone() {
    evBusy = false;
    setTimeout(pumpOverlay, OVERLAY_GAP);
  }

  function showOverlay(ev) {
    var cls = ev.kind === "card" ? "card" : (ev.kind === "sub" ? "sub" : "goal");
    var el = document.querySelector(".board .ev--" + cls);
    if (!el) { overlayDone(); return; }

    if (ev.kind === "goal") {
      // Barwa overlayu = kolor koszulki strzelca z API; brak → fallback --accent (CSS).
      if (ev.color) el.style.setProperty("--ev-color", ev.color);
      else el.style.removeProperty("--ev-color");
      setText(el, "[data-ev-goal-player]", ev.player || ev.team);
      setText(el, "[data-ev-goal-min]", ev.min ? " · " + ev.min : "");
    } else if (ev.kind === "card") {
      var gfx = el.querySelector("[data-ev-card-gfx]");
      if (gfx) gfx.className = "card-graphic " + (ev.cardkind === "red" ? "red" : "yellow");
      setText(el, "[data-ev-card-lab]", ev.label);
      setText(el, "[data-ev-card-who]", ev.player);
      var flagEl = el.querySelector("[data-ev-card-flag]");
      if (flagEl) {
        if (ev.flag) { flagEl.src = ev.flag; flagEl.hidden = false; }
        else { flagEl.hidden = true; }
      }
      setText(el, "[data-ev-card-team]", ev.team + (ev.min ? " · " + ev.min : ""));
    } else if (ev.kind === "sub") {
      setText(el, "[data-ev-sub-in]", ev.inName || "—");
      setText(el, "[data-ev-sub-out]", ev.outName || "—");
    } else {
      overlayDone();
      return;
    }

    // Restart animacji: zdejmij klasę, wymuś reflow, nałóż ponownie.
    el.classList.remove("is-active");
    void el.offsetWidth;
    el.classList.add("is-active");
    setTimeout(function () { el.classList.remove("is-active"); overlayDone(); }, OVERLAY_MS);
  }

  function pumpOverlay() {
    if (evBusy) return;
    var ev = evQueue.shift();
    if (!ev) return;
    evBusy = true;
    showOverlay(ev);
  }

  function enqueueOverlay(ev) {
    if (!ev || !ev.kind) return;
    evQueue.push(ev);
    pumpOverlay();
  }

  // Element osi po sygnaturze (porównanie atrybutu — bez eskejpowania selektora).
  function findEventEl(sig) {
    var items = document.querySelectorAll(".tl-item[data-ev]");
    for (var i = 0; i < items.length; i++) {
      if (items[i].getAttribute("data-ev") === sig) return items[i];
    }
    return null;
  }

  // WEJŚCIE: odtwórz JEDEN raz gola z ostatnich 4 min (board niesie data-ev-autoplay).
  // Czytane TYLKO na starcie — po swapach nie wracamy do historii.
  var autoplaySig = primary.getAttribute("data-ev-autoplay");
  if (autoplaySig) {
    var apEl = findEventEl(autoplaySig);
    if (apEl) enqueueOverlay(readEventData(apEl, "goal"));
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

    // Nowe zdarzenia osi → pełnoekranowy overlay (kolejkowany, gra po kolei).
    var anyNew = false;
    var sig;
    for (sig in r.newSigs) { if (Object.prototype.hasOwnProperty.call(r.newSigs, sig)) { anyNew = true; break; } }
    if (anyNew) {
      var items = document.querySelectorAll(".tl-item[data-ev]");
      Array.prototype.forEach.call(items, function (el) {
        var kind = r.newSigs[el.getAttribute("data-ev")];
        if (kind === "goal" || kind === "card" || kind === "sub") {
          enqueueOverlay(readEventData(el, kind));
        }
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
