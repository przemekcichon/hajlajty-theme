/* ============================================================
   HAJLAJTY — features/filters/assets/filters.js
   ------------------------------------------------------------
   LEPKI FILTR KLIENCKI list meczów (Faza 4A, wariant lekki). Serwer renderuje
   pełną listę stanu; ten skrypt TYLKO zawęża już wyrenderowane karty:

     • pole tekstowe — szuka po DRUŻYNACH (data-team-names, znormalizowane PL),
       zawęża też chipy drużyn (by szukana była od razu pod ręką),
     • chipy — filtr po natywnych taksonomiach (drużyna=FIFA, reszta=slug),
       OR w obrębie taksonomii, AND między taksonomiami; tekst AND z chipami,
     • wybór TRWA w sessionStorage między listami, aż go odznaczysz.

   Desktop: pasek chipów + pole w topbarze. Mobile: lupa → pełnoekranowy MODAL
   (to samo pole + siatka tych samych chipów) + pigułka aktywnego filtra. Chipy i
   pola istnieją w obu miejscach jako REALNY DOM (bez klonowania) i sterują tym
   samym stanem; CSS pokazuje właściwy tryb.

   Wzorzec jak match-lists.js: vanilla IIFE, null-safe, dane przez data-*. Brak
   paska = brak efektów (early return). Zero zależności, zero AJAX.
============================================================ */
(function () {
  "use strict";

  var bar = document.querySelector("[data-filters]");
  if (!bar) return; // Nie ten widok (single nie renderuje paska).

  var STORE_KEY = "hajlajty:filters";
  // 4A: filtrujemy TYLKO po drużynach. Rozgrywki i sezon wrócą w Fazie 5 (dopisać
  // je tu i do chipsbara); kanał świadomie nie jest filtrem publicznym.
  var TAXES = ["druzyna"];
  var CARD_KEY = { druzyna: "teams", rozgrywki: "rozgrywki", sezon: "sezon", kanal: "kanal" };

  /* ---- normalizacja: jawny słownik 1:1 z PHP (hajlajty_filters_normalize_pl) ---- */
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
  function label(chip) { return chip.textContent.replace(/\s+/g, " ").trim(); }

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
          (Array.isArray(data.tax[t]) ? data.tax[t] : []).forEach(function (v) { state.tax[t][v] = true; });
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
  // Pole + lupa żyją w topbarze (poza [data-filters]) — z document. Reszta w barze.
  var inputs = Array.prototype.slice.call(document.querySelectorAll("[data-filter-search]"));
  var clearTextBtns = Array.prototype.slice.call(document.querySelectorAll("[data-filter-clear-text]"));
  var resetBtns = Array.prototype.slice.call(bar.querySelectorAll("[data-filter-reset]"));
  var emptyMsg = bar.querySelector("[data-filter-empty]");
  var emptyMsgReset = emptyMsg ? emptyMsg.querySelector("[data-filter-reset]") : null;
  var pill = bar.querySelector("[data-filter-pill]");
  var pillText = bar.querySelector("[data-filter-pill-text]");
  var scroller = bar.querySelector("[data-filter-chips]"); // pasek desktop (pierwszy)
  var modal = bar.querySelector("[data-filter-modal]");
  var containers = Array.prototype.slice.call(document.querySelectorAll("[data-filterable]"));

  /* --------------------- RENDER STANU → DOM --------------------- */
  function syncChips() {
    chips.forEach(function (chip) {
      var on = !!(state.tax[chip.getAttribute("data-filter-tax")] || {})[chip.getAttribute("data-filter-val")];
      chip.classList.toggle("is-active", on);
      chip.setAttribute("aria-pressed", on ? "true" : "false");
    });
  }

  function syncControls() {
    clearTextBtns.forEach(function (b) { b.hidden = state.q === ""; });
    var active = anyActive();
    resetBtns.forEach(function (b) {
      if (b === emptyMsgReset) return; // chowany razem z komunikatem
      b.hidden = !active;
    });
    updatePill();
  }

  function updatePill() {
    if (!pill) return;
    var labels = [];
    var seen = Object.create(null);
    chips.forEach(function (chip) {
      var t = chip.getAttribute("data-filter-tax"), v = chip.getAttribute("data-filter-val");
      if (!(state.tax[t] && state.tax[t][v])) return;
      var key = t + "|" + v;
      if (seen[key]) return;
      seen[key] = 1;
      labels.push(label(chip));
    });
    // Pigułka podsumowuje WYBRANE drużyny/chipy. Wpisany tekst to tylko pomoc w
    // znalezieniu chipa — nie wchodzi do pigułki (filtrujemy po nazwach drużyn).
    pill.hidden = labels.length === 0;
    if (pillText) pillText.textContent = labels.join(", ");
  }

  function applyChipSearch() {
    var q = norm(state.q);
    chips.forEach(function (chip) {
      if (chip.getAttribute("data-filter-tax") !== "druzyna") return;
      // Aktywny chip zostaje widoczny mimo niedopasowania — by dało się go odznaczyć.
      var active = !!state.tax.druzyna[chip.getAttribute("data-filter-val")];
      var hide = q !== "" && !active && norm(label(chip)).indexOf(q) === -1;
      chip.classList.toggle("is-hidden", hide);
    });
    if (q !== "" && scroller) scroller.scrollLeft = 0;
  }

  function cardMatches(card) {
    if (state.q !== "") {
      var hay = card.getAttribute("data-team-names") || "";
      if (hay.indexOf(norm(state.q)) === -1) return false;
    }
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
      // Sekcję-do-ukrycia bierzemy po jawnym [data-filter-section] (terminarz: dzień),
      // a w jego braku po .section (home/archiwa) — wstecznie zgodne, bez zmian dla list.
      var section = container.closest("[data-filter-section], .section");
      if (section) section.classList.toggle("is-empty-by-filter", active && visible === 0);
    });
    if (emptyMsg) emptyMsg.hidden = !(active && totalVisible === 0);
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
      var t = chip.getAttribute("data-filter-tax"), v = chip.getAttribute("data-filter-val");
      if (!state.tax[t]) return;
      if (state.tax[t][v]) delete state.tax[t][v];
      else state.tax[t][v] = true;
      persist();
      apply();
    });
  });

  function setQuery(value) {
    state.q = value;
    inputs.forEach(function (inp) { if (inp.value !== value) inp.value = value; });
    persist();
    syncControls();
    applyChipSearch();
    applyFilter();
  }
  inputs.forEach(function (inp) {
    inp.value = state.q;
    inp.addEventListener("input", function () { setQuery(inp.value); });
  });
  clearTextBtns.forEach(function (b) {
    b.addEventListener("click", function () {
      setQuery("");
      var inp = b.parentNode ? b.parentNode.querySelector("[data-filter-search]") : null;
      if (inp) inp.focus();
    });
  });

  function resetAll() {
    state.q = "";
    TAXES.forEach(function (t) { state.tax[t] = Object.create(null); });
    inputs.forEach(function (inp) { inp.value = ""; });
    persist();
    apply();
  }
  resetBtns.forEach(function (b) { b.addEventListener("click", resetAll); });

  /* ----------------------- MODAL (mobile) -----------------------
     Pełnoekranowy dialog → pełny focus-trap: zapamiętujemy element, z którego
     otwarto (lupa), uwięziamy Tab/Shift+Tab w modalu i przywracamy fokus przy
     zamknięciu. Lista focusable liczona NA ŻYWO przy każdym Tab — ukryte chipy
     (`.is-hidden`, display:none) i ukryty „×" tekstu (`[hidden]`) mają
     offsetParent === null, więc same wypadają. */
  if (modal) {
    var lastFocus = null;
    var FOCUSABLE = 'a[href],button:not([disabled]),input,[tabindex]:not([tabindex="-1"])';
    var focusables = function () {
      return Array.prototype.slice.call(modal.querySelectorAll(FOCUSABLE)).filter(function (el) {
        return el.offsetParent !== null; // tylko widoczne
      });
    };
    var openModal = function () {
      lastFocus = document.activeElement;
      modal.classList.add("is-open");
      document.body.style.overflow = "hidden";
      var inp = modal.querySelector("[data-filter-search]");
      if (inp) setTimeout(function () { inp.focus(); }, 60);
    };
    var closeModal = function () {
      modal.classList.remove("is-open");
      document.body.style.overflow = "";
      if (lastFocus && typeof lastFocus.focus === "function") lastFocus.focus();
      lastFocus = null;
    };
    // Lupa otwierająca modal jest w topbarze (poza [data-filters]) — z document.
    Array.prototype.slice.call(document.querySelectorAll("[data-filter-open]")).forEach(function (b) {
      b.addEventListener("click", openModal);
    });
    Array.prototype.slice.call(bar.querySelectorAll("[data-filter-close],[data-filter-apply]")).forEach(function (b) {
      b.addEventListener("click", closeModal);
    });
    // Klawiatura uwięziona w modalu: Escape zamyka, Tab cyklicznie po widocznych.
    // Listener na modalu — przy uwięzionym fokusie keydown zawsze bąbelkuje tutaj.
    modal.addEventListener("keydown", function (e) {
      if (e.key === "Escape") { closeModal(); return; }
      if (e.key !== "Tab") return;
      var f = focusables();
      if (!f.length) { e.preventDefault(); return; }
      var first = f[0], last = f[f.length - 1], active = document.activeElement;
      if (e.shiftKey && active === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && active === last) { e.preventDefault(); first.focus(); }
      else if (f.indexOf(active) === -1) { e.preventDefault(); first.focus(); }
    });
  }

  /* ----------------- CHIPSY: strzałki + drag-scroll ------------- */
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
