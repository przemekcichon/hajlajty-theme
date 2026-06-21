/* ============================================================
   HAJLAJTY — assets/js/live-detect.js
   ------------------------------------------------------------
   CZYSTA logika wykrywania przyrostu zdarzeń live (MVP-b). Wydzielona z
   live-refresh.js, żeby najbardziej nietrywialny kawałek (delta między pollami)
   był TESTOWALNY bez DOM/fetcha. Zero efektów ubocznych: dane wejście → decyzja.
   live-refresh.js robi I/O (czyta DOM, nakłada klasy); TU tylko decyzja „co nowe".

   UMD: w przeglądarce dopina się do window.hajlajtyLiveDetect; w Node (testy)
   eksportuje przez module.exports. Bez zależności.

   KONTRAKT detect(prevSeen, prevScore, events, score):
     prevSeen  : mapa { sig: true } sygnatur zdarzeń znanych z poprzedniego stanu.
     prevScore : { home, away } — ostatni wynik (liczba lub null, gdy nieznany).
     events    : [ { sig, kind } ] — bieżące zdarzenia z fragmentu (kind:
                 goal|card|sub|'' ; '' = bez efektu, np. missed_penalty/VAR).
     score     : { home, away } — bieżący wynik (liczba lub null).
   ZWRACA:
     newSigs   : mapa { sig: kind } zdarzeń NOWYCH względem prevSeen (do animacji).
     bumps     : tablica stron ('home'/'away'), których wynik WZRÓSŁ liczbowo.
     nextSeen  : prevSeen ∪ wszystkie bieżące sygnatury (baseline na następny tick).
     nextScore : wynik do zapamiętania (bieżący tam, gdzie znany; inaczej poprzedni).

   BASELINE (brak fałszywych efektów na starcie): pierwsze wywołanie z
   prevSeen={} i prevScore={home:null,away:null} zwróci newSigs=wszystko i
   bumps=[] (null nie bije) — WOŁAJĄCY ignoruje wynik pierwszego wywołania i
   bierze tylko nextSeen/nextScore jako punkt odniesienia. Animuje dopiero
   PRZYROST w kolejnych tickach. Bump tylko gdy nowa i stara wartość to liczby
   i nowa > stara (samobój wychodzi data-driven: rośnie wynik przeciwnika).
============================================================ */
(function (root, factory) {
  "use strict";
  var api = factory();
  if (typeof module !== "undefined" && module.exports) {
    module.exports = api; // Node / testy (CommonJS).
  }
  if (root) {
    root.hajlajtyLiveDetect = api; // Przeglądarka (global dla live-refresh.js).
  }
})(typeof self !== "undefined" ? self : (typeof window !== "undefined" ? window : null), function () {
  "use strict";

  function isNum(v) {
    return typeof v === "number" && !isNaN(v);
  }

  function detect(prevSeen, prevScore, events, score) {
    prevSeen = prevSeen || {};

    // Kopia prevSeen → nextSeen (bez mutacji wejścia).
    var nextSeen = {};
    for (var key in prevSeen) {
      if (Object.prototype.hasOwnProperty.call(prevSeen, key)) {
        nextSeen[key] = true;
      }
    }

    // Zdarzenia NOWE = sygnatury nieobecne w prevSeen.
    var newSigs = {};
    (events || []).forEach(function (ev) {
      if (!ev || !ev.sig) {
        return;
      }
      if (nextSeen[ev.sig]) {
        return;
      }
      nextSeen[ev.sig] = true;
      newSigs[ev.sig] = ev.kind || "";
    });

    // Wzrost wyniku per strona (tylko realny przyrost liczbowy).
    var bumps = [];
    var nextScore = {
      home: prevScore ? prevScore.home : null,
      away: prevScore ? prevScore.away : null,
    };
    ["home", "away"].forEach(function (side) {
      var nv = score ? score[side] : null;
      var ov = prevScore ? prevScore[side] : null;
      if (isNum(nv) && isNum(ov) && nv > ov) {
        bumps.push(side);
      }
      if (isNum(nv)) {
        nextScore[side] = nv;
      }
    });

    return { newSigs: newSigs, bumps: bumps, nextSeen: nextSeen, nextScore: nextScore };
  }

  return { detect: detect };
});
