# Gap map and roadmap

Status terms: `IMPLEMENTED`, `PARTIAL`, `MISSING`, `FUTURE`, `REJECTED`.

This map distinguishes current evidence from desired behavior as of 2026-09-13. `IMPLEMENTATION_STATUS.md` records the proof boundary.

## Gap map

| Area | Current state | Target | Status | Priority |
|---|---|---|---|---|
| Clean public URL | Root now serves the built game without redirecting to `dist` | Address bar remains `https://starlightrv.ca/RVGame/` | IMPLEMENTED | P0 |
| Dense dashboard | Status, map, HUD, actions, and log exist together | Continue tightening without hiding context | IMPLEMENTED | Maintain |
| Live map | Six locations and eight state-driven roads provide alternate routes and local identity | Tune route choices from playtest evidence | IMPLEMENTED | P2 |
| Day/24-hour clock | Visible and authoritative | Keep beside every time-cost action | IMPLEMENTED | Maintain |
| Opening ownership | Purchase complete, seller gone, financed starter owned | Preserve | IMPLEMENTED | Maintain |
| Preparation | First-time and Hands-on choices exist | Add only evidence-backed equipment choices | IMPLEMENTED | Maintain |
| Expertise rules | First-time uses technicians; Hands-on gets eligible DIY/self checks | Preserve server enforcement | IMPLEMENTED | Maintain |
| Travel costs/distance | Eight roads retain the exact 206 km opening path and add non-linear choices | Tune local route tradeoffs from evidence | IMPLEMENTED | P2 |
| Weight/tanks/payload | Authoritative aggregate kg/liter model, limits, controls, previews, and towing blockers | Tune authored values from evidence | IMPLEMENTED | P1 |
| Camper HUD | Eight knowledge-aware systems plus derived weight/rating/payload status | Preserve without duplicating health | IMPLEMENTED | P1 extension |
| Defect persistence | Existing defects persist and inspection reveals them | Preserve | IMPLEMENTED | Maintain |
| Travel events | Eight deterministic, durable decision events with previews and recovery paths | Tune frequency and choices from evidence | IMPLEMENTED | P1 |
| Location economy | Distinct work, routes, service, water, demand, prices, and information by town | Tune values from evidence | IMPLEMENTED | P1 |
| Recurring market | One persistent camper instance per town with expiry and midnight replenishment | Tune supply from evidence | IMPLEMENTED | P1 |
| Market history/intel | Current plus three prior snapshots and exact paid next-day local forecasts | Tune information value from evidence | IMPLEMENTED | P1 |
| Repair decisions | Known defects, DIY/technician methods, location availability, price variation, and service credits | Tune obstruction from evidence | IMPLEMENTED | P2 extension |
| Upgrade timing | Garage gate, value, buyer-fit tags, and added weight are enforced | Tune return on investment from evidence | IMPLEMENTED | P1 |
| Buyer offers | Persistent offers use buyer profile, local demand, condition, upgrades, and deterministic variance with visible rationale | Tune offer spread from evidence | IMPLEMENTED | P1 |
| Profit/accounting | Proceeds and project profit are distinct | Add campaign comparison statistics | IMPLEMENTED | P2 extension |
| Debt | Principal, interest/payables, manual repayment | Single cash balance is sufficient; no bank clone needed | IMPLEMENTED | Maintain |
| No-camper recovery | Work, travel, listings, next purchase remain | Preserve | IMPLEMENTED | Maintain |
| Dream ending | Local Hands-on and deployed OT First-time journeys completed on 0.5.0; focused mobile results and reload verified | Preserve and retest on behavior changes | IMPLEMENTED | Maintain |
| Feedback/rating/share | Dead-simple feedback, rating, social sharing | Add donation only after destination decision | IMPLEMENTED | Maintain |
| Dependency versions | Existing lockfile versions remain pinned; local package and content release are rvgame-0.6.0 | Preserve release identity | IMPLEMENTED | Maintain |
| Full subsystem simulation | Design only | Build only after Simple evidence | FUTURE | P3 |
| Vehicles/dealership/multiplayer | Not present | Not needed for Simple | REJECTED | None |
| Legacy activation/high scores | Not present | Never reproduce | REJECTED | None |

## P0: truth and release integrity

1. Use this package as the only design authority.
2. Pin known-working frontend dependency versions.
3. Use one release ID across package metadata, build report, and deployment evidence.
4. Run the full browser resale and dream-camper journeys on the current build.
5. Retain the clean-root URL regression check.

## P1: make the loop feel like the reference without copying it

1. Implement the Simple weight/tank/payload contract.
2. Replace passive-only events with the minimum durable decision set in `SIMPLE_MODE.md`.
3. Give locations economic identity through services, buyer demand, prices, and information.
4. Replenish persistent listings at midnight and preserve three snapshots of local demand/service history.
5. Feed buyer offers from location demand, camper state, upgrades, and deterministic variance.
6. Update project previews/ledger for weight-sensitive upgrades and new direct costs.

P1 is one coherent playtest milestone. Do not build a generic market engine: author the smallest content set that proves location choice, information value, event choice, and weight tradeoffs.

Completed and deployed to Owner Test and main in `rvgame-0.6.0`. Balance acceptance remains a separate decision.

## P2: content depth after playtest evidence

- Alternate roads and meaningful route choices: implemented in `rvgame-0.6.0`.
- More camper archetypes, buyer profiles, services, and event variants: implemented in `rvgame-0.6.0`.
- Technician availability and price variation: implemented in `rvgame-0.6.0`.
- Add compact project/campaign statistics and optional sparklines.
- Tune one value at a time from feedback and durable playtest evidence.

## P3: Full mode decision gate

Implement Full mode only if players repeatedly ask for technical depth and Simple retention shows the reseller loop is fun. Start with one subsystem family and cross-language-ready fixtures. Do not begin Unity, a second rules engine, or detailed load engineering by default.
