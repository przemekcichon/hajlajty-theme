/* ============================================================
   HAJLAJTY — assets/js/bracket.js
   ------------------------------------------------------------
   Linie łączące drabinki fazy pucharowej: dla każdego meczu rysuje łokcie do jego
   DWÓCH feederów z poprzedniej rundy (graf z data-no / data-feeder-a/-b, emitowany
   przez render). Rysunek = nakładka SVG liczona z REALNYCH pozycji komórek
   (getBoundingClientRect), więc działa przy dowolnej wysokości kolumn i responsywnie.

   Dlaczego JS, a nie czyste CSS: pionowa belka łącząca parę feederów rozpina się
   między DWIEMA sąsiednimi komórkami, a jej wysokość zależy od wysokości kolumny
   (liczba meczów rundy) — w CSS nie do wyrażenia bez stałych wysokości. SVG liczone
   z layoutu daje idealne linie i samo się przelicza (ResizeObserver + resize + load).

   Null-safe, vanilla IIFE, zero zależności — jak match-lists.js / filters.js.
   Ładowany TYLKO na widoku drabinki (warunkowy enqueue w match-lists.php).
============================================================ */
(function () {
  "use strict";

  var bracket = document.querySelector(".bracket");
  if (!bracket || !document.querySelector(".bracket-cell[data-feeder-a]")) return;

  var SVGNS = "http://www.w3.org/2000/svg";
  var svg = document.createElementNS(SVGNS, "svg");
  svg.setAttribute("class", "bracket__lines");
  svg.setAttribute("aria-hidden", "true");
  bracket.insertBefore(svg, bracket.firstChild);

  function cells() {
    return Array.prototype.slice.call(bracket.querySelectorAll(".bracket-cell"));
  }

  // Pozycja komórki względem kontenera .bracket (niezależna od scrolla — SVG jest
  // dzieckiem .bracket i przewija się razem z nim).
  function rel(el, base) {
    var r = el.getBoundingClientRect();
    return {
      left: r.left - base.left,
      right: r.right - base.left,
      midY: r.top - base.top + r.height / 2,
    };
  }

  // Łokieć od feedera F do konsumenta C, kierunek z geometrii (układ dwustronny):
  //  - F na LEWO od C → wychodzi z prawej krawędzi F, wchodzi w lewą krawędź C,
  //  - F na PRAWO od C → wychodzi z lewej krawędzi F, wchodzi w prawą krawędź C.
  // Dla pary feederów z tej samej strony dwa łokcie łączą się pionowo na midX (belka);
  // dla Finału (feederzy z dwóch stron) każdy rysuje własny łokieć do środka.
  function elbow(F, C) {
    if (F.right <= C.left) {
      var ml = (F.right + C.left) / 2;
      return "M" + F.right + "," + F.midY + "H" + ml + "V" + C.midY + "H" + C.left;
    }
    if (F.left >= C.right) {
      var mr = (F.left + C.right) / 2;
      return "M" + F.left + "," + F.midY + "H" + mr + "V" + C.midY + "H" + C.right;
    }
    return ""; // kolumny się nakładają w poziomie — pomiń.
  }

  function draw() {
    var base = bracket.getBoundingClientRect();
    var w = bracket.scrollWidth;
    var h = bracket.scrollHeight;
    svg.setAttribute("width", w);
    svg.setAttribute("height", h);
    svg.setAttribute("viewBox", "0 0 " + w + " " + h);
    while (svg.firstChild) svg.removeChild(svg.firstChild);

    // Indeks komórek po numerze meczu.
    var byNo = {};
    cells().forEach(function (c) {
      var n = c.getAttribute("data-no");
      if (n) byNo[n] = c;
    });

    cells().forEach(function (consumer) {
      var col = parseInt(consumer.getAttribute("data-col"), 10);
      var C = rel(consumer, base);
      ["data-feeder-a", "data-feeder-b"].forEach(function (attr) {
        var fn = consumer.getAttribute(attr);
        if (!fn) return;
        var feeder = byNo[fn];
        if (!feeder) return;
        // Tylko feeder z SĄSIEDNIEJ kolumny (|Δcol|=1). Mecz o 3. miejsce nie ma
        // atrybutów feeder (render je pomija) → zostaje niepołączony.
        if (Math.abs(parseInt(feeder.getAttribute("data-col"), 10) - col) !== 1) return;
        var d = elbow(rel(feeder, base), C);
        if (!d) return;
        var path = document.createElementNS(SVGNS, "path");
        path.setAttribute("class", "bracket__line");
        path.setAttribute("d", d);
        svg.appendChild(path);
      });
    });
  }

  var raf = null;
  function schedule() {
    if (raf) cancelAnimationFrame(raf);
    raf = requestAnimationFrame(draw);
  }

  schedule();
  window.addEventListener("resize", schedule);
  window.addEventListener("load", schedule); // po doczytaniu flag/fontów (pozycje finalne)
  if (window.ResizeObserver) {
    // Reaguje na zmiany layoutu nie-resize'owe: zwijanie sidebara (hamburger),
    // doczytanie fontów, przeładowanie kart filtrem.
    new ResizeObserver(schedule).observe(bracket);
  }

  /* ---------------------------------------------------------------------------
     STICKY poziomy pasek przewijania — przyklejony do DOŁU EKRANU. Strona scrolluje
     się normalnie w pionie; natywny poziomy pasek siedzi u dołu wysokiej drabinki
     (daleko), więc na małym ekranie trzeba było zjechać na dół, przewinąć w prawo i
     wrócić do góry. Proxy-pasek (position:fixed bottom:0) ze scrollLeft zsynchronizowanym
     z .bracket-scroll usuwa ten problem: przewijasz w prawo z dowolnej wysokości.
     --------------------------------------------------------------------------- */
  var scroller = bracket.parentNode; // .bracket-scroll (pozioma przewijarka)
  if (scroller && /bracket-scroll/.test(scroller.className || "")) {
    var proxy = document.createElement("div");
    proxy.className = "bracket-xscroll";
    proxy.hidden = true;
    var sizer = document.createElement("div");
    sizer.className = "bracket-xscroll__sizer";
    proxy.appendChild(sizer);
    document.body.appendChild(proxy);

    // Dwustronna synchronizacja scrollLeft (flaga przeciw pętli sprzężenia).
    var lock = false;
    proxy.addEventListener("scroll", function () {
      if (lock) return;
      lock = true; scroller.scrollLeft = proxy.scrollLeft; lock = false;
    });
    scroller.addEventListener("scroll", function () {
      if (lock) return;
      lock = true; proxy.scrollLeft = scroller.scrollLeft; lock = false;
    }, { passive: true });

    var syncBar = function () {
      var hasOverflow = scroller.scrollWidth - scroller.clientWidth > 1;
      var r = scroller.getBoundingClientRect();
      var vh = window.innerHeight || document.documentElement.clientHeight;
      // Pokaż, gdy: jest co przewijać w poziomie, drabinka jest w widoku, a jej DÓŁ
      // jest poniżej dołu ekranu (czyli natywny pasek jest poza widokiem). Przy dojechaniu
      // do dołu drabinki proxy znika — wtedy widać natywny pasek na jej dole.
      var show = hasOverflow && r.top < vh && r.bottom > vh;
      proxy.hidden = !show;
      if (!show) return;
      proxy.style.left = r.left + "px";
      proxy.style.width = r.width + "px";
      sizer.style.width = scroller.scrollWidth + "px";
      if (!lock) { lock = true; proxy.scrollLeft = scroller.scrollLeft; lock = false; }
    };

    syncBar();
    // Capture na document łapie scroll DOWOLNEGO elementu (scroll nie bąbelkuje) —
    // okna ORAZ #content (pionowy scroller app-shell). Bez tego pasek nie nadążałby
    // przy przewijaniu pionu w układzie ze stałym sidebarem.
    document.addEventListener("scroll", syncBar, { capture: true, passive: true });
    window.addEventListener("resize", syncBar);
    window.addEventListener("load", syncBar);
    if (window.ResizeObserver) new ResizeObserver(syncBar).observe(bracket);

    /* ---- Chwytanie myszą (panoramowanie): poziom = scroller, pion = element pionu ----
       Tak jak palcem na komórce — łapiesz drabinkę i ciągniesz w dowolnym kierunku.
       Próg DRAG odróżnia przeciągnięcie od kliknięcia karty (po dragu blokujemy click).
       Tylko mysz: dotyk korzysta z natywnego przewijania (wzór: filters.js chipy).

       PION: w app-shell (sidebar stały, ≥1100px) przewija się #content (layout.css),
       NIE window — więc szukamy realnego elementu pionowego scrolla idąc w górę DOM;
       brak → okno (mobile/drawer). */
    var DRAG = 5;
    var down = false, dragged = false, sx = 0, sy = 0, sLeft = 0, sTop = 0, vEl = null;
    var winTop = function () { return window.pageYOffset || document.documentElement.scrollTop || 0; };
    var pickVScroller = function () {
      var el = scroller.parentNode;
      while ( el && el.nodeType === 1 && el !== document.body ) {
        var oy = getComputedStyle( el ).overflowY;
        if ( ( oy === "auto" || oy === "scroll" ) && el.scrollHeight - el.clientHeight > 1 ) {
          return el;
        }
        el = el.parentNode;
      }
      return null; // null = okno/strona
    };
    scroller.addEventListener("mousedown", function (e) {
      if (e.button !== 0) return;
      down = true; dragged = false;
      sx = e.clientX; sy = e.clientY;
      sLeft = scroller.scrollLeft;
      vEl = pickVScroller();
      sTop = vEl ? vEl.scrollTop : winTop();
    });
    document.addEventListener("mousemove", function (e) {
      if (!down) return;
      var dx = e.clientX - sx, dy = e.clientY - sy;
      if (!dragged && Math.max(Math.abs(dx), Math.abs(dy)) > DRAG) {
        dragged = true;
        scroller.classList.add("is-grabbing");
      }
      if (dragged) {
        e.preventDefault();
        scroller.scrollLeft = sLeft - dx;            // poziom: przewijarka drabinki
        if ( vEl ) { vEl.scrollTop = sTop - dy; }     // pion: element scrolla (np. #content)
        else { window.scrollTo( 0, sTop - dy ); }     // pion: okno (mobile/drawer)
      }
    });
    var endDrag = function () {
      if (!down) return;
      down = false;
      scroller.classList.remove("is-grabbing");
      if (dragged) setTimeout(function () { dragged = false; }, 0);
    };
    document.addEventListener("mouseup", endDrag);
    // Po przeciągnięciu NIE otwieraj karty (capture, zanim click dojdzie do <a>).
    scroller.addEventListener("click", function (e) {
      if (!dragged) return;
      e.preventDefault(); e.stopPropagation(); dragged = false;
    }, true);
  }
})();
