/* ============================================================
   HAJLAJTY — features/filters/assets/filters.js
   ------------------------------------------------------------
   LEPKI FILTR KLIENCKI list meczów (Faza 4A, wariant lekki). Serwer renderuje
   pełną listę stanu; ten skrypt TYLKO zawęża już wyrenderowane karty:

     • pole tekstowe — szuka po DRUŻYNACH (data-team-names, znormalizowane PL),
     • chipy — filtr po natywnych taksonomiach (drużyna=FIFA, reszta=slug),
       OR w obrębie jednej taksonomii, AND między taksonomiami; tekst AND z chipami,
     • wybór TRWA w sessionStorage — przy wejściu na inną listę filtr jest
       stosowany dalej, aż go odznaczysz (znika dopiero po zamknięciu karty).

   Wzorzec jak match-lists.js: vanilla IIFE, null-safe, dane przez data-*. Brak
   paska/kart = brak efektów (early return). Zero zależności, zero AJAX.
============================================================ */
(function () {
  "use strict";

  var bar = document.querySelector("[data-filters]");
  if (!bar) return; // Nie ten widok (single nie renderuje paska).

  var STORE_KEY = "hajlajty:filters";
  var TAXES = ["druzyna", "rozgrywki", "sezon", "kanal"];
  // Mapa: taksonomia chipa → atrybut data-* karty.
  var CARD_KEY = { druzyna: "teams", rozgrywki: "rozgrywki", sezon: "sezon", kanal: "kanal" };

  /* ---- normalizacja: jawny słownik 1:1 z PHP (hajlajty_filters_normalize_pl) ----
     lowercase + sprowadzenie polskich/łacińskich diakrytyków do ASCII. Karty mają
     nazwy już znormalizowane z serwera; tu normalizujemy wpisany tekst tak samo,
     więc obie strony porównania dają identyczny wynik (kontrakt PHP↔JS). */
  var DIACRITICS = {
    "ą": "a", "ć": "c", "ę": "e", "ł": "l", "ń": "n", "ó": "o", "ś": "s", "ź": "z", "ż": "z",
    "ç": "c", "á": "a", "à": "a", "ã": "a", "â": "a", "é": "e", "è": "e", "ê": "e",
    "í": "i", "î": "i", "ñ": "n", "ô": "o", "õ": "o", "ö": "o", "ú": "u", "ü": "u"
  };
  function norm(s) {
    return (s || "").toLowerCase().replace(/[^\x00-\x7f]/g, function (ch) {
      return Object.prototype.hasOwnProperty.call(DIACRITICS, ch) ? DIACRITICS[ch] : ch;
    });
  }

  /* ---------------------------- STAN ---------------------------- */
  var state = { q: "", tax: {} };
  TAXES.forEach(function (t) { state.tax[t] = Object.create(null); });

  function anyActive() {
    if (state.q !== "") return true;
    return TAXES.some(function (t) { return Object.keys(state.tax[t]).length > 0; });
  }

  function load() {
    try {
      var raw = sessionStorage.getItem(STORE_KEY);
      if (!raw) return;
      var data = JSON.parse(raw);
      if (data && typeof data.q === "string") state.q = data.q;
      if (data && data.tax) {
        TAXES.forEach(function (t) {
          (Array.isArray(data.tax[t]) ? data.tax[t] : []).forEach(function (v) {
            state.tax[t][v] = true;
          });
        });
      }
    } catch (e) {}
  }

  function persist() {
    try {
      var out = { q: state.q, tax: {} };
      TAXES.forEach(function (t) { out.tax[t] = Object.keys(state.tax[t]); });
      sessionStorage.setItem(STORE_KEY, JSON.stringify(out));
    } catch (e) {}
  }

  /* ----------------------- ELEMENTY UI -------------------------- */
  var chips = Array.prototype.slice.call(bar.querySelectorAll(".chip[data-filter-tax]"));
  // Pole szukania żyje w topbarze (poza [data-filters]) — bierzemy z document.
  var input = document.querySelector("[data-filter-search]");
  var clearTextBtn = document.querySelector("[data-filter-clear-text]");
  var resetBtns = Array.prototype.slice.call(bar.querySelectorAll("[data-filter-reset]"));
  var emptyMsg = bar.querySelector("[data-filter-empty]");
  var emptyMsgReset = emptyMsg ? emptyMsg.querySelector("[data-filter-reset]") : null;
  var containers = Array.prototype.slice.call(document.querySelectorAll("[data-filterable]"));

  /* --------------------- RENDER STANU → DOM --------------------- */
  function syncChips() {
    chips.forEach(function (chip) {
      var t = chip.getAttribute("data-filter-tax");
      var v = chip.getAttribute("data-filter-val");
      var on = !!(state.tax[t] && state.tax[t][v]);
      chip.classList.toggle("is-active", on);
      chip.setAttribute("aria-pressed", on ? "true" : "false");
    });
  }

  function syncControls() {
    if (clearTextBtn) clearTextBtn.hidden = state.q === "";
    var active = anyActive();
    resetBtns.forEach(function (b) {
      // Reset wewnątrz komunikatu pustego wyniku chowa/odsłania razem z komunikatem.
      if (b === emptyMsgReset) return;
      b.hidden = !active;
    });
  }

  function cardMatches(card) {
    // Tekst (po drużynach) — substring na znormalizowanych nazwach.
    if (state.q !== "") {
      var hay = card.getAttribute("data-team-names") || "";
      if (hay.indexOf(norm(state.q)) === -1) return false;
    }
    // Taksonomie — AND między taksonomiami, OR w obrębie taksonomii.
    for (var i = 0; i < TAXES.length; i++) {
      var t = TAXES[i];
      var active = Object.keys(state.tax[t]);
      if (!active.length) continue;
      var vals = (card.getAttribute("data-" + CARD_KEY[t]) || "").split(/\s+/);
      var hit = active.some(function (a) { return vals.indexOf(a) !== -1; });
      if (!hit) return false;
    }
    return true;
  }

  function applyFilter() {
    var active = anyActive();
    var totalVisible = 0;

    containers.forEach(function (container) {
      var cards = Array.prototype.slice.call(container.querySelectorAll("[data-team-names]"));
      var visible = 0;
      cards.forEach(function (card) {
        var show = cardMatches(card);
        card.classList.toggle("is-hidden-by-filter", !show);
        if (show) visible++;
      });
      totalVisible += visible;
      // Ukryj całą sekcję, gdy filtr aktywny i nic w niej nie zostało (np. na
      // home sekcja LIVE bez trafień nie świeci pustym nagłówkiem).
      var section = container.closest(".section");
      if (section) section.classList.toggle("is-empty-by-filter", active && visible === 0);
    });

    // Globalny komunikat: filtr aktywny, ale NIGDZIE nic nie pasuje.
    if (emptyMsg) emptyMsg.hidden = !(active && totalVisible === 0);
  }

  // Zawężanie chipów DRUŻYN wpisanym tekstem — żeby szukana drużyna była od razu
  // widoczna do kliknięcia (zabezpieczenia filtra), bez scrollowania paska. Chipy
  // pozostałych taksonomii zostają (szukamy po drużynach). Ukrywanie jest czysto
  // wizualne — stan aktywny chipa (filtr) trwa niezależnie.
  var teamLabel = bar.querySelector('[data-filter-group="druzyna"]');
  function applyChipSearch() {
    var q = norm(state.q);
    var anyVisible = false;
    chips.forEach(function (chip) {
      if (chip.getAttribute("data-filter-tax") !== "druzyna") return;
      // Aktywny chip zostaje widoczny mimo niedopasowania — by zawsze dało się
      // go odznaczyć (inaczej aktywny filtr „znika" pod wpisanym tekstem).
      var active = !!state.tax.druzyna[chip.getAttribute("data-filter-val")];
      var hide = q !== "" && !active && norm(chip.textContent).indexOf(q) === -1;
      chip.classList.toggle("is-hidden", hide);
      if (!hide) anyVisible = true;
    });
    if (teamLabel) teamLabel.classList.toggle("is-hidden", q !== "" && !anyVisible);
    // Po zawężeniu pokaż początek paska (dopasowania są od lewej).
    if (q !== "" && scroller) scroller.scrollLeft = 0;
  }

  function apply() {
    syncChips();
    syncControls();
    applyChipSearch();
    applyFilter();
  }

  /* ----------------------- INTERAKCJE --------------------------- */
  chips.forEach(function (chip) {
    chip.addEventListener("click", function () {
      var t = chip.getAttribute("data-filter-tax");
      var v = chip.getAttribute("data-filter-val");
      if (!state.tax[t]) return;
      if (state.tax[t][v]) delete state.tax[t][v];
      else state.tax[t][v] = true;
      persist();
      apply();
    });
  });

  if (input) {
    input.value = state.q;
    input.addEventListener("input", function () {
      state.q = input.value;
      persist();
      syncControls();
      applyChipSearch();
      applyFilter();
    });
  }

  if (clearTextBtn) {
    clearTextBtn.addEventListener("click", function () {
      state.q = "";
      if (input) { input.value = ""; input.focus(); }
      persist();
      syncControls();
      applyChipSearch();
      applyFilter();
    });
  }

  function resetAll() {
    state.q = "";
    TAXES.forEach(function (t) { state.tax[t] = Object.create(null); });
    if (input) input.value = "";
    persist();
    apply();
  }
  resetBtns.forEach(function (b) { b.addEventListener("click", resetAll); });

  /* ----------------- CHIPSY: strzałki + drag-scroll -------------
     Port z design/components/chips-drag.js, zwężony do jednego paska. */
  var scroller = bar.querySelector("[data-filter-chips]");
  if (scroller) {
    var arrows = Array.prototype.slice.call(bar.querySelectorAll("[data-filter-arrow]"));
    var syncArrows = function () {
      var max = scroller.scrollWidth - scroller.clientWidth - 1;
      arrows.forEach(function (a) {
        var prev = a.getAttribute("data-filter-arrow") === "prev";
        var show = max > 0 && (prev ? scroller.scrollLeft > 0 : scroller.scrollLeft < max);
        a.classList.toggle("is-visible", show);
      });
    };
    arrows.forEach(function (a) {
      a.addEventListener("click", function () {
        var step = Math.max(160, Math.round(scroller.clientWidth * 0.7));
        scroller.scrollBy({ left: a.getAttribute("data-filter-arrow") === "prev" ? -step : step, behavior: "smooth" });
      });
    });
    scroller.addEventListener("scroll", syncArrows);
    window.addEventListener("resize", syncArrows);
    syncArrows();

    // Drag-to-scroll (desktop): chwyć i przesuń; klik tłumiony po realnym dragu.
    var DRAG = 6, down = false, dragged = false, startX = 0, startScroll = 0;
    scroller.addEventListener("mousedown", function (e) {
      if (e.button !== 0) return;
      down = true; dragged = false; startX = e.pageX; startScroll = scroller.scrollLeft;
    });
    scroller.addEventListener("mousemove", function (e) {
      if (!down) return;
      var dx = e.pageX - startX;
      if (!dragged && Math.abs(dx) > DRAG) { dragged = true; scroller.classList.add("is-grabbing"); }
      if (dragged) { e.preventDefault(); scroller.scrollLeft = startScroll - dx; }
    });
    var endDrag = function () {
      if (!down) return;
      down = false; scroller.classList.remove("is-grabbing");
      if (dragged) setTimeout(function () { dragged = false; }, 0);
    };
    scroller.addEventListener("mouseup", endDrag);
    scroller.addEventListener("mouseleave", endDrag);
    scroller.addEventListener("click", function (e) {
      if (!dragged) return;
      e.preventDefault(); e.stopPropagation(); dragged = false;
    }, true);
  }

  /* ------------------------- START ------------------------------ */
  load();
  apply();
})();
