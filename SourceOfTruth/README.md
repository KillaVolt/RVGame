# RVGame Source of Truth

Prepared: 2026-09-12

`RVGame` is the provisional working title for Anthony's compact, turn-based camper-reseller game. The player begins after purchasing a financed fixer-upper at a distant campground, gets it home through travel choices and problems, decides what to inspect or repair, resells it, manages cash and debt, and repeats until a dream camper can be kept.

Open `index.html` to browse this package locally.

## Reading order

1. `AUTHORITY.md`
2. `GAME_DESIGN.md`
3. `SIMPLE_MODE.md`
4. `UI_DASHBOARD.md`
5. `ARCHITECTURE_AND_DATA.md`
6. `DRUG_WARS_REFERENCE_ANALYSIS.md`
7. `GAP_MAP_AND_ROADMAP.md`
8. `ACCEPTANCE_TESTS.md`
9. `BUILD_RUN_DEPLOY.md`
10. `CODEX_BUILD_PROMPT.md`

`FULL_MODE.md`, `IMPLEMENTATION_STATUS.md`, `SUPERSESSION_AND_SOURCES.md`, and `KNOWN_UNKNOWNS.md` provide supporting boundaries and evidence.

## Core boundary

This package defines intended behavior. The repository is the implementation. `E:\RVGame\content\simple-mode.json` is the only executable authority for authored Simple-mode values. A status document or old package never overrides code, tests, or that content file.

The decompiled Drug Wars: Underworld material is private behavioral evidence. It is not source code for RVGame and is never a public asset.

