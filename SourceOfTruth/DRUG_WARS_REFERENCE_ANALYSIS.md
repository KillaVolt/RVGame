# Drug Wars: Underworld behavioral reference analysis

## Purpose and boundary

The private decompiled game is behavioral evidence for RVGame's decision cadence, transaction boundaries, information density, travel settlement, local markets, and progression. It is not a code donor or public asset source.

Static analysis was performed against `E:\RVGame\Decompile\drug-wars-underworld-re`. The legacy executable was not run. The evidence set contains a 32-bit native Windows PE, imports/headers, Ghidra project, 666 exported function bodies with zero export failures reported, reconstructed C, strings, logs, and prior research notes. Symbols are stripped, so generic routine names and exact intent remain partly inferential.

The detailed evidence report remains at `E:\RVGame\Decompile\drug-wars-underworld-re\output\RVGAME_STATIC_ANALYSIS.md`.

## Confirmed behavioral structure

### Transactions

- Buy has a preflight routine (`FUN_00401000`) and a commit routine (`FUN_004018f0`). Commit rechecks affordability, local availability, and capacity rather than trusting the dialog.
- Sell has corresponding preflight (`FUN_004011d0`) and commit (`FUN_00401d50`) behavior. Commit updates money, inventory, trade history/statistics, and encounter eligibility.
- Cash and health mutations route through central routines (`FUN_00435680`, `FUN_004358e0`) rather than scattered direct UI writes.

RVGame lesson: previews are explanations, not authority. Every action must validate again at the one server mutation boundary and return one durable result.

### Travel and day progression

- City-map selection and travel routing are distinct (`FUN_00453f10`, `FUN_00405ce0`).
- Vehicle travel can delay a day transition across multiple moves (`FUN_004325f0`).
- A central transition (`FUN_004328e0`) snapshots histories, advances the day, applies finance/health and recurring costs, refreshes markets, processes helpers/attrition/events, and checks ending state.

RVGame lesson: route selection, travel execution, and midnight settlement are separate concepts. One midnight owner prevents double interest, stale markets, and inconsistent histories.

### Market and information

- Market generation covers every city/product combination (`FUN_00478de0`) and UI refresh is separate (`FUN_004792c0`).
- Embedded interfaces and strings support city-specific availability, bank/loan/interest, vehicles, hospital/store services, informants, helpers, encounters, market and availability histories, charts, statistics, scores, and editable data sets.
- A roughly 2 percent sting path appears in trade commit subject to the helper's exact bounds and a wealth gate; that probability is evidence, not an RVGame balance target.

RVGame lesson: locations matter because prices, stock, information, services, risks, and opportunities differ. Refreshing the UI does not create the market. Information itself can be purchased and improved.

### Interface

The main dashboard keeps day/location, money/debt, map/navigation, market, inventory, actions, and results visible together. Secondary windows handle focused tasks while the primary state remains legible.

RVGame lesson: preserve simultaneous context and short decision-to-result cycles. Do not recreate Windows chrome or crowd every future system onto the first screen.

## What maps directly to RVGame

| Underworld structure | RVGame interpretation |
|---|---|
| City/product market matrix | Location/camper/service/buyer-demand matrix |
| Inventory capacity | Camper weight envelope and limited carried project supplies |
| Buy/sell commits | Purchase, repair, upgrade, and sale commands with commit-time revalidation |
| Bank/loan pressure | Visible cash, principal, interest, payables, and manual repayment |
| Travel encounters | RV-specific travel choices, defects, weather, service, work, and leads |
| Informants | Paid market, defect, service, or buyer information |
| Vehicle progression | Deferred; Bluebird remains permanent in Simple mode |
| Health/status | Camper systems HUD, towing blockers, and weight status |
| Market charts/history | Three-snapshot local demand/service history |
| Statistics/high score | Project ledger, completed-sales history, final journey summary |

## What may be learned and reused

- Privately inspect all available code, tables, strings, screens, and behavior to understand state ownership, formulas, transaction flow, pacing, information hierarchy, and balance relationships.
- Reuse general mechanics, facts, constraints, data concepts, and lessons through independently written RVGame rules and content.
- Transform observed values into RV-specific balance hypotheses rather than assuming legacy numbers fit the new economy.

## What must not ship verbatim

- Protected crime/drug fiction, terminology, event text, city/product tables, art, icons, audio, window chrome, executable/decompiled code, registry behavior, activation system, or network score implementation.
- The legacy registration/perpetual-edition gate.
- The embedded remote high-score posting path and WinINet behavior.
- Arbitrary scarcity or punishment merely because the original has it.
- Complexity whose only purpose was desktop-era implementation or monetization.

## High-value design gaps exposed by the comparison

This was the 2026-09-12 baseline. Local `rvgame-0.6.0` closes items 1, 2, 3, 5, 7, 8, and 9 through original RV-domain content and the existing authoritative command/settlement path. Item 4 now has aggregate tank/load/upgrade weight choices but deliberately keeps tools as a Simple-mode preparation choice. Campaign comparison statistics in item 6 and further dashboard compression in item 10 remain future tuning work.

1. Current RVGame events are mostly passive result lines; Underworld's pressure comes from decisions that interrupt an otherwise simple loop.
2. Current locations form a readable route but have too little economic identity. Prices, service, demand, and information need local variation.
3. Current listings are a small authored ladder rather than a recurring location market with recent history.
4. Tools are a starting flag, not a compact equipment/weight decision system.
5. Camper mass, tank contents, payload, and tow limit are absent.
6. Project accounting exists, but campaign statistics and comparison history are thin.
7. Buyer behavior is present but does not yet provide the breadth of market choice, timing, and information pressure visible in the reference.
8. The current map supports direct travel but the route network is linear, limiting route strategy.
9. Daily settlement exists, but market refresh and local-opportunity generation are not yet rich enough to make advancing time economically tense.
10. The reference separates core context from focused secondary actions more effectively than a long action list can.

## Confidence limits

Function purpose was inferred from call flow, strings, state access, and surrounding behavior. Exact formulas, random helper bounds, all encounter conditions, and every edition-specific branch are not fully proven. These unknowns do not block the RVGame design because the transferable value is the behavioral topology, not binary parity.
