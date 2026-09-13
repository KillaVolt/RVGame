# Implementation status

Snapshot date: 2026-09-13

Latest local, Owner Test, and main release: **rvgame-0.6.0**, content `simple-2.0.0`, save schema 3. It was deployed to both channels and verified on 2026-09-13. Detailed evidence: [build status](../reports/BUILD_STATUS.md), [owner exclusion](../reports/OWNER_EXCLUSION_2026-09-12.md), and [playtest fixes](../reports/PLAYTEST_FIXES_2026-09-12.md).

This file reports known evidence. It does not grant PASS to future requirements.

## Implemented and deployed

- React/Vite/TypeScript frontend, PHP API/domain service, MariaDB persistence.
- Main and Owner Test channel isolation.
- Owner browser recognition, collection suppression and filtering across every metric; owner ratings/feedback blocked and identified historical testing removed without deleting saved campaigns.
- Financed starter already owned, explicit First-time/Hands-on preparation, G$1,500 cash, G$2,000 principal.
- First-time technician-only technical knowledge/repair path and Hands-on eligible self/DIY path.
- Visible Day plus 24-hour clock and hourly action durations.
- State-driven SVG route map, player/camper markers, three-edge 206 km opening trip.
- Eight-category knowledge-aware camper HUD.
- Persistent defects, repairs, upgrade, advertising, offers, sale, project profit, debt repayment, next-project state, and dream-camper domain completion.
- Anonymous rating, short feedback, feedback Close fix, and social sharing to the stable main URL.
- Production root internally serves the built game, while direct or stale `dist/` entry URLs normalize back to the clean game root.
- OT 0.5.0 adds exact-payment validation, action confirmation/results, mobile status/footer layout, contextual inspection/repair gates, explicit tire-failure/recovery accounting, retained history, stable vehicle references, offer decline/relist/48-hour expiry, daytime odd jobs/rest, goal help and focused completion results.
- New offers retain expiry and new ads retain asking price. Schema-2 saves remain stored but require the explicit reset path before playing `rvgame-0.6.0`.

Current public endpoints:

- Owner Test: `https://starlightrv.ca/RVGame/OT/`
- Main: `https://starlightrv.ca/RVGame/`

OT and final main 0.5.0 deployments preserved saves. A campaign created on OT 0.4.1 resumed after deployment and was played to completion. Both roots and built assets returned 200. Final main JS/CSS SHA-256 hashes matched the same artifact approved on OT; main HTTP payment, command-retry and persistence checks passed.

Owner dashboard authentication, all eight metrics and twelve panels, four date ranges and sign-out passed HTTP checks on both main and OT. Dashboard URLs are `/RVGame/owner/` and `/RVGame/OT/owner/`; login uses the existing StarCore admin credentials. Private feedback contents were not included in test output.

## Implemented locally and on both channels in rvgame-0.6.0

- Aggregate camper weight, GVWR, Bluebird tow limit, tank liters, loose load, remaining payload, kg/lb display, resulting-weight previews, and enforced towing blockers.
- Eight durable decision events with disclosed choices, single-application persistence, unrelated-action blocking, and retained work/service/wait/recovery paths.
- Six economically distinct locations, eight roads, six camper archetypes, four buyer profiles, regional service/water/demand/prices, and technician availability/price variation.
- One persistent listing per town with expiry and replenishment owned by midnight settlement.
- Current plus three prior market snapshots and paid, dated, exact next-day local forecasts.
- Buyer offers driven by buyer fit, local demand, camper condition, upgrades, and deterministic variance, with rationale and handover location shown.
- Weight-bearing upgrades and event/service/information costs recorded through the existing preview, commit, ledger, and event-log path.
- Schema-2 saves are not silently migrated. Loading one returns a visible reset-required state and an explicit reset action.

## Implemented but incompletely re-proven on the current release

- Local Hands-on browser journey completed Day 4, 16:00, revision 30, G$638.05 cash and no debt. Deployed OT First-time-owner journey completed Day 3, 14:00, revision 20, G$840.48 cash and no debt. Both sold two campers and retained the Sunbeam.
- Exact payments, duplicate receipts, conflicting IDs, stale revisions, rejected-command rollback and reload passed HTTP checks locally and on OT. Domain checks cover offer expiry and open-ended play through Day 61.
- Forced SQL failure, network interruption, 200 percent zoom, complete keyboard/screen-reader journey, physical phones and non-Chromium browsers remain unrun. Native sharing was interrupted by the user; no defect or delivery conclusion is drawn from that interaction.

## Still specified, not implemented

- Expanded campaign comparison statistics and optional sparklines.
- Full-mode subsystem wear and maintenance.

## Release consistency

- Frontend versions are pinned to the already-used lockfile versions; no dependency was added or upgraded.
- Local, Owner Test, and main package version `0.6.0` aligns with content `releaseId: rvgame-0.6.0`.
- Main and Owner Test serve the same staged JS/CSS hashes.

Do not claim unrun browser journeys or platform matrices passed merely because deployment succeeded.
