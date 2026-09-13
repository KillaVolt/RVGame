# Acceptance tests

Allowed results: `PASS`, `FAIL`, `NOT RUN`, `BLOCKED`. A requirement existing in prose is not a pass.

## Package and authority

- `PKG-01`: `SourceOfTruth/index.html` links every canonical document and major private evidence location.
- `PKG-02`: every relative link resolves.
- `PKG-03`: no active document names an old package as authority or calls the project Tin Can Dreams/Campground Cash.
- `PKG-04`: historical folders are recorded as superseded and remain unmodified.
- `PKG-05`: executable Simple values have one owner: `content/simple-mode.json`.

## Campaign creation and opening

- `MVP-01`: a new campaign requires First-time or Hands-on preparation; no default is silently selected.
- `MVP-02`: Day 1 begins at 09:00 with player, Bluebird, and Rusty Lantern at Sunset Shores.
- `MVP-03`: G$1,500 cash, G$2,000 principal, and G$3,500 acquisition reconcile without a second purchase charge.
- `MVP-04`: seller is gone and the brief walk-around found nothing obvious without revealing hidden defects.
- `MVP-05`: First-time has no technical self-check/DIY command; Hands-on has only content-eligible checks/repairs.
- `MVP-06`: technician inspection charges and advances time once, reveals existing defects, and never creates one.

## Commands and persistence

- `MVP-07`: successful command increments revision exactly once.
- `MVP-08`: exact duplicate returns its receipt without repeating money, time, distance, weight, event, or generation.
- `MVP-09`: conflicting command reuse and stale revision change nothing.
- `MVP-10`: failed transaction leaves pre-command state authoritative and reports failure.
- `MVP-11`: reload reproduces campaign, defects, listings, offers, pending event, histories, distance, weight, and tanks.

## Travel, distance, weight, and events

- `MVP-12`: opening route totals exactly 206 canonical km.
- `MVP-13`: solo travel leaves camper and camper distance unchanged.
- `MVP-14`: km/mi and kg/lb switches change display only.
- `MVP-15`: current weight and remaining payload derive exactly from dry mass, tank liters, upgrades, and loose load.
- `MVP-16`: towing is rejected above camper or Bluebird limit and succeeds after an eligible load/tank reduction.
- `MVP-17`: unresolved starter tire threshold blocks towing; preventive service suppresses it.
- `MVP-18`: event generation is deterministic and persisted; reopen never rerolls.
- `MVP-19`: event choice applies disclosed effects once and cannot chain another event.
- `MVP-20`: every harmful event path retains wait, work, service, or recovery.
- `MVP-21`: normal paths can reach Maple Junction without a softlock.

## Market, project, and sale

- `MVP-22`: each location's authored service/demand/information difference changes actual choices or values.
- `MVP-23`: listings are persistent instances and replenish only through authoritative settlement.
- `MVP-24`: current plus three previous local snapshots survive reload.
- `MVP-25`: purchased information reveals only its declared fact/forecast and charges once.
- `MVP-26`: one owned camper maximum is enforced.
- `MVP-27`: garage-only repair/upgrade/advertise/sale actions reject wrong location.
- `MVP-28`: repairs change only declared targets; upgrades add declared value/appeal/weight.
- `MVP-29`: offers do not reroll on panel open and reflect eligible buyer/demand rules.
- `MVP-30`: an imperfect camper has an honest as-is buyer path.
- `MVP-31`: sale transfers ownership once and shows proceeds separately from project profit/loss.
- `MVP-32`: sale does not auto-pay debt and installed upgrades leave with the camper.
- `MVP-33`: no-camper state preserves mobility, work, garage, cash, debt, history, and next purchase.

## Settlement, debt, and completion

- `MVP-34`: recurring costs, interest, expiry, generation, and snapshots run once per midnight crossed.
- `MVP-35`: planning/rejected/duplicate actions never settle a day.
- `MVP-36`: repayment applies to interest before principal.
- `MVP-37`: recovery works at zero cash, creates the disclosed payable, and does not repair.
- `MVP-38`: dream listing is reachable through normal play and is not free.
- `MVP-39`: keeper eligibility requires home, condition, weight, zero liabilities, and reserve.
- `MVP-40`: completion requires explicit confirmation and then rejects further economic commands.

## Interface and community

- `UI-01`: at 1440 px, status, live map, HUD, market/local, project/actions, and result are simultaneously visible.
- `UI-02`: 360 px has no horizontal page overflow.
- `UI-03`: Day/time, cash/debt, objective, save state, and relevant weight limit remain easy to find.
- `UI-04`: controls are keyboard reachable and state never relies on color alone.
- `UI-05`: feedback closes without validation and asks only category plus message.
- `UI-06`: every share option targets `https://starlightrv.ca/RVGame/?new=1` and never OT.
- `UI-07`: production root returns `200` without a redirect and loads its built assets while the visible URL remains `/RVGame/`.
- `UI-08`: direct requests for `/RVGame/dist/` or `/RVGame/dist/index.html` redirect to `/RVGame/`; OT equivalents redirect to `/RVGame/OT/`.
- `UI-09`: a browser with an existing campaign that opens the shared entry receives a new anonymous session and the campaign setup screen; a normal root visit resumes.
- `UI-10`: recognized social preview crawlers receive the Open Graph document directly at `?new=1`; a human request to the same URL still starts fresh and redirects to the clean root.
- `OWNER-01`: unauthenticated owner-dashboard requests reveal no analytics.
- `OWNER-02`: valid existing owner credentials open only the requested main or OT channel dashboard.
- `OWNER-03`: dashboard ranges, KPIs, trend graph, source/device/browser/OS/language graphs, funnel, actions, ratings, and feedback render with empty and populated data.
- `OWNER-04`: page views and events are channel-isolated, bot-filterable, and contain no raw IP, raw user-agent, or full-referrer columns.

## Build and release

- `BUILD-01`: pinned dependency install and TypeScript/Vite production build pass.
- `BUILD-02`: PHP syntax, domain checks, content validation, and database schema setup pass.
- `BUILD-03`: real browser opening journey passes.
- `BUILD-04`: real browser full resale cycle passes.
- `BUILD-05`: real browser dream-camper completion passes.
- `BUILD-06`: Owner Test is deployed and checked before final deployment.
- `BUILD-07`: deployment contains only staged runtime files and no secrets/private evidence.
- `BUILD-08`: report identifies commit/release, environment, commands, PASS/FAIL/NOT RUN/BLOCKED, and unrun tests.

Simple mode cannot be called fully tested while any applicable `MVP` or `BUILD` item is `FAIL`, `BLOCKED`, or `NOT RUN`.

## Playtest regression checks added in 0.5.0

`tests/playtest-regressions.php` includes the existing domain journey and checks exact/invalid payments, job hours and rest, explicit breakdown results, remote/completed inspection gates, repaired evidence, relist/decline/expiry, prior saved terms, new-bill explanation, history retention, stable vehicle identity and open-ended Day 61 behavior.

`tests/api-smoke.ps1` uses its own new anonymous session to verify payment, duplicate receipt, conflicting ID, stale revision, rejected-command rollback and reload through HTTP. Pass `-BaseUrl https://starlightrv.ca/RVGame/OT` for OT. It never resets an existing campaign.

Current outcomes and unrun cases belong in `reports/BUILD_STATUS.md` and `reports/PLAYTEST_FIXES_2026-09-12.md`; the original playtest finding IDs are separate from the UI acceptance IDs above.
