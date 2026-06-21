/* ============================================================
   HAJLAJTY — tests/live-detect.test.js
   ------------------------------------------------------------
   Testy CZYSTEJ logiki wykrywania przyrostu zdarzeń live (MVP-b),
   wydzielonej do assets/js/live-detect.js. Bez frameworka i bez zależności —
   sam `node:assert` (działa na Node 16 z Locala). Spójne z lekką konwencją
   pozostałych testów w tests/ (manualne skrypty, nie CI).

   URUCHOMIENIE:
     node tests/live-detect.test.js
   Wyjście 0 = wszystko OK; pierwszy nieudany assert przerywa z kodem ≠ 0.
============================================================ */
"use strict";

var assert = require("assert");
var D = require("../assets/js/live-detect.js");

var passed = 0;
function test(name, fn) {
  fn();
  passed += 1;
  console.log("  ✓ " + name);
}

// Baseline: prevSeen={} + prevScore=null → caller IGNORUJE newSigs/bumps i bierze
// tylko nextSeen/nextScore. Sprawdzamy kontrakt (newSigs raportowane, bumps puste).
test("baseline: brak prevSeen → wszystko w nextSeen, bumps puste (prevScore null)", function () {
  var r = D.detect({}, { home: null, away: null },
    [{ sig: "goal|home|10||1", kind: "goal" }],
    { home: 1, away: 0 });
  assert.deepStrictEqual(r.bumps, []);                          // null nie bije
  assert.strictEqual(r.newSigs["goal|home|10||1"], "goal");
  assert.strictEqual(r.nextSeen["goal|home|10||1"], true);
  assert.strictEqual(r.nextScore.home, 1);
  assert.strictEqual(r.nextScore.away, 0);
});

test("brak zmian: znana sygnatura + ten sam wynik → zero efektów", function () {
  var seen = { "goal|home|10||1": true };
  var r = D.detect(seen, { home: 1, away: 0 },
    [{ sig: "goal|home|10||1", kind: "goal" }],
    { home: 1, away: 0 });
  assert.deepStrictEqual(r.newSigs, {});
  assert.deepStrictEqual(r.bumps, []);
});

test("nowy gol: nowa sygnatura goal + bump strony, której wynik wzrósł", function () {
  var seen = { "goal|home|10||1": true };
  var r = D.detect(seen, { home: 1, away: 0 },
    [{ sig: "goal|home|10||1", kind: "goal" }, { sig: "goal|home|55||7", kind: "goal" }],
    { home: 2, away: 0 });
  assert.strictEqual(r.newSigs["goal|home|55||7"], "goal");
  assert.deepStrictEqual(r.bumps, ["home"]);
  assert.strictEqual(r.nextScore.home, 2);
});

test("nowa kartka: newSig card, BEZ bumpa (wynik bez zmian)", function () {
  var seen = { "x": true }; // niepuste prevSeen → liczy się tylko delta
  var r = D.detect(seen, { home: 0, away: 0 },
    [{ sig: "yellow|away|33||9", kind: "card" }],
    { home: 0, away: 0 });
  assert.strictEqual(r.newSigs["yellow|away|33||9"], "card");
  assert.deepStrictEqual(r.bumps, []);
});

test("samobój: event po stronie away, ale rośnie wynik home → bump home (data-driven)", function () {
  var base = D.detect({}, { home: 0, away: 0 }, [], { home: 0, away: 0 });
  var r = D.detect(base.nextSeen, base.nextScore,
    [{ sig: "own_goal|away|70||5", kind: "goal" }],
    { home: 1, away: 0 });
  assert.strictEqual(r.newSigs["own_goal|away|70||5"], "goal");
  assert.deepStrictEqual(r.bumps, ["home"]); // strona bumpa z WYNIKU, nie ze strony eventu
});

test("missed_penalty / VAR: kind '' → newSig z pustym kind (caller nie animuje)", function () {
  var r = D.detect({ "y": true }, { home: 1, away: 1 },
    [{ sig: "missed_penalty|home|80||3", kind: "" }, { sig: "var|away|81||4", kind: "" }],
    { home: 1, away: 1 });
  assert.strictEqual(r.newSigs["missed_penalty|home|80||3"], "");
  assert.strictEqual(r.newSigs["var|away|81||4"], "");
  assert.deepStrictEqual(r.bumps, []);
});

test("wynik z null baseline NIE bije (wczesna faza / NS)", function () {
  var r = D.detect({}, { home: null, away: null }, [], { home: 0, away: 0 });
  assert.deepStrictEqual(r.bumps, []);
  assert.strictEqual(r.nextScore.home, 0);
});

test("śmieci wejścia ignorowane (brak ev/sig)", function () {
  var r = D.detect({}, { home: 0, away: 0 },
    [null, {}, { sig: "" }, { sig: "goal|home|1||2", kind: "goal" }],
    { home: 0, away: 0 });
  assert.strictEqual(Object.keys(r.newSigs).length, 1);
  assert.strictEqual(r.newSigs["goal|home|1||2"], "goal");
});

test("nie mutuje wejścia (prevSeen nietknięte)", function () {
  var seen = { "a": true };
  D.detect(seen, { home: 0, away: 0 }, [{ sig: "b", kind: "goal" }], { home: 0, away: 0 });
  assert.deepStrictEqual(seen, { "a": true }); // bez 'b'
});

test("oba gole naraz: dwa bumpy", function () {
  var r = D.detect({ "old": true }, { home: 0, away: 0 },
    [{ sig: "goal|home|5||1", kind: "goal" }, { sig: "goal|away|6||2", kind: "goal" }],
    { home: 1, away: 1 });
  assert.deepStrictEqual(r.bumps.sort(), ["away", "home"]);
});

console.log("\nlive-detect: " + passed + " passed");
