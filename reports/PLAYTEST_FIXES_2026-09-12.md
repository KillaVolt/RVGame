# RVGame playtest fixes — rvgame-0.5.0

Released to Owner Test on 2026-09-12 at approximately 22:37 America/Toronto. Content: `simple-1.3.0`; save schema: 2.

Target: https://starlightrv.ca/RVGame/OT/

Final-deployment addendum: the user subsequently authorized final deployment. At approximately 23:04 America/Toronto the unchanged OT-approved artifact was deployed to https://starlightrv.ca/RVGame/ with main saves preserved. Main asset SHA-256, API/payment/retry/persistence and clean-entry checks passed. Authenticated owner dashboards at `/RVGame/owner/` and `/RVGame/OT/owner/` passed all four date ranges, eight metrics, twelve panels and sign-out. The OT-only statements below describe the earlier release checkpoint; see `BUILD_STATUS.md` for current deployment state.

The original evidence remains in [the live playtest](<E:/Games Family/RVGame-live-playtest-2026-09-12.md>). Its IDs below are playtest IDs, separate from similarly named SourceOfTruth acceptance IDs. This release fixes the reported control and feedback failures and makes the specified rule clarifications. It does not implement the entire future Simple-mode roadmap or establish that unfamiliar customers will understand every screen.

## Release and preservation

- `scripts/deploy-live.ps1` deployed the 14 staged runtime files through Pageant SSH, verified the release manifest on the host, activated OT, and reported `OK preserved ot playtest saves`.
- No `-Final` or `-ResetSaves` option was used. Source, tests, reports, Decompile, dependencies and private configuration were excluded from public staging.
- The previous Deploy directory was retained. Current source/build files and all staged runtime files matched by SHA-256 before upload. The manifest is in ignored local `output/ot-050-staging-manifest.json`.
- Main still serves its previous asset names. Its root HTML SHA-256 matched the pre-deployment baseline exactly: `315C4AAF930127C14B9F0FDDC2CBCB5F6498A4F0504B3831398FED99FB900288`.
- A First-time-owner campaign created on OT 0.4.1 immediately before deployment resumed on 0.5.0 with the same Day 1, 09:00, revision 0, cash G$1,500 and debt G$2,000. It was then played to completion. Existing campaigns were not reset or migrated.
- Earlier advertisements without a recorded asking price and earlier offers without an expiry retain those terms, with explicit UI descriptions. Relisting establishes new terms. Already-discarded history cannot be reconstructed; earlier sales that retained totals only are labelled accordingly.

## Finding-by-finding resolution

Evidence: **Browser-local** means the built XAMPP game; **Browser-OT** means the deployed Owner Test game; **Domain/API** means runnable PHP or HTTP checks. Source-reviewed branches are identified rather than presented as browser executions.

| Playtest ID | Change | Verification / limit |
|---|---|---|
| UI-01 | Persistent mobile cash, debt, location, objective, clock and save status. | Browser-local 360×800 and Browser-OT 390×844 screenshots; desktop 1280×900. |
| PAY-01 | Custom payments reject amounts above cash or debt. Exact preview includes cash/debt remaining. Maximum is a separate explicitly priced action. | Browser-local oversized input disabled; exact repayment and one-cent API checks; oversized HTTP rejection preserved revision/balance locally and on OT. |
| UI-02 | Native confirmation dialog before economic/time actions; no commit on Cancel. Result is shown after the saved response. | Browser-local Cancel retained revision 0; both complete browser journeys used confirmations and results. |
| UI-03 | Route copy and preview say driving alone leaves the camper at its named location. The result confirms where it stayed. | Browser-local Cedar Ridge → Maple Junction solo and return. |
| UI-04 | Shared domain availability rejects remote checks; UI disables inspections and repairs and names the camper's location. Ordinary action errors are no longer labelled Save/API errors. | Browser-local remote state; domain rejection checks. |
| UI-05 | Empty, zero, negative, malformed, excessive or over-precision custom amounts cannot submit. API requires positive integer cents within both balances. | Browser-local empty/excessive state; Domain/API invalid values. |
| UI-06 | Opening, help, condition evidence, route warnings and inspection previews explain that quick checks can miss hidden faults. | Browser-local initial screen, tow previews and technician discovery. |
| UI-07 | Arrival clears route selection and requires a deliberate next destination. | Browser-local opening road sequence and solo return. |
| UI-08 | Known running-gear faults show risk; an actual towing blocker explicitly requires repair or recovery. | Browser-local fault/breakdown route copy; domain guards. |
| UI-09 | Repair/system names and result descriptions use player-facing names, correct duration plurals and “by you”/“by a technician.” | Both browser journeys exercised the affected result families; technical build/revision details remain in the diagnostic footer. |
| UI-10 | Next-midnight costs/interest and principal, accrued interest, bills and daily rate are visible. Confirmations flag midnight crossings. | Browser-local and Browser-OT midnight repairs; debt panel. |
| UI-11 | Repaired-system evidence describes repaired known issues instead of attributing that condition to an earlier inspection. | Domain assertion; Browser-local DIY result and restored HUD. |
| UI-12 | Recovery preview/result, offer/sale results and sales ledger explicitly exclude campaign overhead from project profit. Recovery bills are visible in debt. | Browser-OT G$150 recovery increased bills/debt and was disclosed separately from the starter's G$830 project profit. This follows current GAME_DESIGN accounting. |
| UI-13 | Already completed, remote and unaffordable inspections have explicit disabled reasons. | Browser-local and Browser-OT full-inspection completion; domain source/guards for affordability. |
| UI-14 | Upgrade shows value effect, possible loss, interior prerequisite and Installed status. | Browser-local installation; Browser-OT Pocket Pine prerequisite disabled inline; source-reviewed Installed label. |
| UI-15 | Existing offers are explicitly fixed. Relist withdraws them and uses current condition; decline makes room for the next buyer. | Browser-local old offer survived DIY; relist produced G$5,022.28; decline then waiting produced G$5,376.08. Domain durable-offer checks. |
| UI-16 | Advertisements retain asking price/time; repeated Advertise is disabled and server-rejected. Relist is a separate action. | Both browser journeys; domain duplicate-ad rejection. |
| UI-17 | Sale produces a visible result dialog with gross proceeds, project result, cash/debt and next objective. | Browser-local sale screenshot at 360 px; Browser-OT sales. |
| UI-18 | Listing, purchase preview, owned project and completed sale use the same camper instance reference; repeat models say Different vehicle. | Additional reference mismatch discovered and corrected during validation. Browser-OT Pocket Pine reference `3026dcf4` persisted through purchase and sale; domain assertion. |
| UI-19 | Opening/help introduce the Sunbeam; goal checklist exposes reserve, debt, location and required conditions. Second-sale result announces its unlock. | Both browser journeys reached the unlock and checklist conditions. |
| UI-20 | Completion is previewed explicitly, then shows a focused results screen. Economic/travel panels disappear; saved history and restart remain. | Browser-local Day 4 and Browser-OT Day 3 endings; OT reload retained the focused ending at revision 20. |
| UI-21 | Mobile objective remains visible; opening gives the goal and important rules; How to play / Your goal explains the next actions and completion requirements. | Browser-local help opened with all requirements checked. This is a small orientation fix, not a new step-by-step tutorial or a usability study with unfamiliar players. |
| UI-22 | A separate 64 px footer area reserves room for Share and Help; game scrolling stays above it. | Browser-local 360×800 sale/debt/ledger screens; Browser-OT 390×844 ending. |
| UI-23 | Currency consistently has two decimal places; unit-change copy no longer says canonical storage. | Both browser journeys; source-reviewed unit text. |
| UI-24 | Midnight result explicitly states cash paid and the unpaid amount becoming a bill. | Domain cash-shortfall boundary; browser daily/debt breakdown. |
| UI-25 | Expandable earlier history and completed-sales ledger; new sales retain itemized direct expenses. Future history is no longer truncated to 80 entries. | Browser-local 25 older entries plus two itemized sales; Browser-OT 15 older entries plus two sales; domain 91-entry retention. Old discarded entries remain unrecoverable. |
| UI-26 | Tire-threshold arrival explicitly reports Tire failure, blocked towing, repair options and recovery's limitation. | Browser-local second uninspected tow reproduced the failure and result; towing disabled afterward. |
| UI-27 | Completed inspection and repaired-issue guidance now reflects the current state. | Browser-local after technician plus DIY; Browser-OT after paid repairs. |
| UI-28 | Workshop tire service versus Roadside tire service; location requirement and call-out price distinction shown inline. | Browser-local Cedar Ridge: workshop G$70 disabled, roadside G$90 worked. Browser-OT workshop G$70 worked at Maple Junction. |
| UI-29 | Payment is controlled as editable text; blank stays blank and submission disables. | Browser-local selected text deleted with Backspace, then empty field/disabled Pay confirmed. |
| UI-30 | Work is an explicitly accepted local odd job, with a named employer, concrete non-specialist task and completion payment. Starts are available 08:00–15:00; an eight-hour job makes a second same-day job unavailable. Rest reaches the next opening. | Browser-local job/rest; domain closed-hours/repeat rejection and long-run income recovery. Jobs remain deliberately repeatable each morning; no hiring/payroll simulator added. |
| UI-31 | Wait is unavailable while a usable offer already exists; UI explains accept/decline/relist. New offers expire after 48 game hours; a time action replaces expired offers. | Browser-local wait disabled with offer; decline/wait and relist/wait worked. Domain exact expiry boundary/replacement and reload stability. |
| UI-32 | Flavor references actual Local jobs; positive cash names a paid task; camper-roof flavor requires camper ownership. | Browser-local/OT text; domain repeated no-camper actions suppress roof event. Passive flavor is still a small authored pool, not the future event-choice system. |
| UI-33 | Opening/help explicitly state an open-ended journey with no 60-day deadline; choosing the keeper ends it. | Current SourceOfTruth rule preserved. Domain played through Day 61 with active status; this release's complete browser journeys ended earlier. The original 0.4.1 full-60-day run remains separate evidence. |
| UI-34 | Reclassified as inconclusive: the user reported closing the share interface during testing. Cancelled/failed native requests now have explanatory text; unsupported native sharing stays hidden. | More apps returned visible Shared status locally and on OT, but user intervention prevents a defect conclusion or a delivery claim. Live social links target the main fresh-entry URL. Physical-phone behavior and cancellation/error branches remain unverified. No social post or feedback was submitted. |

## Browser campaign records

| Build/environment | Ending | Scope |
|---|---|---|
| 0.5.0 local, Hands-on | Day 4, 16:00; revision 30; two sold; G$638.05 cash; zero debt; Sunbeam retained | Careless two-tow failure, solo separation, roadside repair, technician inspection, DIY roof/battery/interior/water, upgrade, relist/decline, exact payoff, work/rest, mobile sale and ending. Small reference/copy/focus refinements were built afterward; reload verified the final focus change. |
| 0.5.0 deployed OT, First-time owner, resumed pre-deploy save | Day 3, 14:00; revision 20; two sold; G$840.48 cash; zero debt; Sunbeam retained | Covered recovery, paid inspection, workshop tire, paid roof/battery/interior/water, two resales, instance continuity, payoff including recovery bill, dream purchase/ending and reload. |

## Runnable verification

- `npm.cmd run build`: PASS (TypeScript + Vite production build).
- `E:\xampp8\php\php.exe tests\playtest-regressions.php`: PASS; includes the full `tests/domain.php` journey.
- `E:\xampp8\php\php.exe -d zend.assertions=1 -d assert.exception=1 tests\channel.php`: PASS.
- PHP syntax checks for API, owner and test PHP: PASS. Local `database/setup.php`: PASS, without reset.
- `tests/api-smoke.ps1`: PASS locally and with `-BaseUrl https://starlightrv.ca/RVGame/OT`. Fresh isolated sessions test one-cent payment, duplicate receipt, conflicting command ID, stale revision, oversized-payment rejection and unchanged persisted state.
- OT root/JS/CSS/content: HTTP 200; served assets `index-ByX-qnVA.js`, `index-Cs88FQRo.css`.
- OT stale `dist/` and `dist/index.html` entries redirect to clean `/RVGame/OT/`.
- Unauthenticated owner request displays the login form rather than analytics; private database/access files return HTTP 403.
- Feedback closes with an empty required message; no message or rating was sent.
- `git diff --check`: PASS.

No dependency was added or upgraded. Existing lockfile versions were pinned; package and content release identity now agree. The existing fee test was corrected to assert the inspection expense separately from optional random event income.

## Remaining boundaries

No claim of exhaustive random-seed/order coverage, physical iOS/Android testing, Firefox/WebKit, 200% browser zoom, full keyboard/screen-reader journey, forced SQL failure or network interruption. A rejected domain command rolling back is verified; an injected database failure is not. Owner login protection was checked without logging in or inspecting private analytics.

The future SourceOfTruth requirements for weight/tanks, decision events, richer local economies, market history/intel, alternate roads and Full mode remain unimplemented. Thin late-game variety is still a content/design limitation; removing the dead-end wait and overnight wage loop does not turn this small camper ladder into 60 days of unique content. Renewed casual-player testing should judge whether the added orientation and daily odd jobs are sufficiently clear.

All source changes remain in the working tree; no commit was requested. Existing unrelated work was preserved. The completed OT campaign is left open in the browser with the viewport override reset.
