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
      var fa = consumer.getAttribute("data-feeder-a");
      var fb = consumer.getAttribute("data-feeder-b");
      if (!fa || !fb) return;
      var feedA = byNo[fa];
      var feedB = byNo[fb];
      if (!feedA || !feedB) return;

      // Tylko feederzy z SĄSIEDNIEJ kolumny (mecz o 3. miejsce, którego feederzy są
      // 2 kolumny wstecz, zostaje bez linii — nie przecinamy kolumny finału).
      var col = parseInt(consumer.getAttribute("data-col"), 10);
      if (parseInt(feedA.getAttribute("data-col"), 10) !== col - 1) return;
      if (parseInt(feedB.getAttribute("data-col"), 10) !== col - 1) return;

      var c = rel(consumer, base);
      var a = rel(feedA, base);
      var b = rel(feedB, base);
      var midX = (Math.max(a.right, b.right) + c.left) / 2;

      // Dwa łokcie feederów → pionowa belka na midX → poziome wejście do konsumenta.
      var d =
        "M" + a.right + "," + a.midY + "H" + midX +
        "M" + b.right + "," + b.midY + "H" + midX +
        "M" + midX + "," + a.midY + "V" + b.midY +
        "M" + midX + "," + c.midY + "H" + c.left;

      var path = document.createElementNS(SVGNS, "path");
      path.setAttribute("class", "bracket__line");
      path.setAttribute("d", d);
      svg.appendChild(path);
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
})();
