# Testy motywu hajlajty-theme

Lekkie, manualne testy — bez CI, bez frameworka, spójne z charakterem projektu
(prostota dla dewelopera i nastoletniego redaktora; CLAUDE.md). Dwa rodzaje:

## PHP — czyste funkcje renderu (Faza 3)
Pliki `*.eval.php` / `3a-lookups.php` sprawdzają czyste funkcje motywu
(lookups/derive/helpers). Uruchamiasz w „Open Site Shell" Locala przez WP-CLI:

```
wp eval-file wp-content/themes/hajlajty-theme/tests/3a-lookups.php
wp eval-file wp-content/themes/hajlajty-theme/tests/3a-helpers.eval.php
```

`*.verify.eval.php` to checklisty weryfikacji renderu wariantów single.

## JS — czysta logika wykrywania zdarzeń live (MVP-b)
`live-detect.test.js` testuje `assets/js/live-detect.js` — czystą funkcję
`detect()` decydującą, które zdarzenia live są NOWE między pollami (bez DOM,
bez fetcha). Sam `node:assert`, bez zależności. Uruchamiasz Nodem (jest w Local):

```
node wp-content/themes/hajlajty-theme/tests/live-detect.test.js
```

Wyjście `0` = OK; pierwszy nieudany assert przerywa z kodem ≠ 0. To pokrywa
najbardziej nietrywialny kawałek MVP-b (delta + baseline + samobój + reduce
przypadki brzegowe); warstwa DOM (odczyt/nakładanie klas) zostaje w
`live-refresh.js` i weryfikuje się runtime (scenariusz w PR/`ground-truth-mvp.md`).
