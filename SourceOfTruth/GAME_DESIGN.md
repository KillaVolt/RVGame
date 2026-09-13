# Game design

## Product promise

You already bought the camper. The seller is gone. Now make the deal work.

RVGame is a compact management game about buying questionable towable campers, transporting them, learning what is actually wrong, deciding which work is financially sensible, selling them, handling debt and working cash, and eventually keeping a camper worth the journey.

It borrows the fast decision cadence and simultaneous information of classic trading games. It does not copy Drug Wars names, fiction, artwork, audio, prices, tables, code, or exact interface.

## Opening

The campaign begins on Day 1 at 09:00 at Sunset Shores. The financed Rusty Lantern 19 is already owned and the purchase ledger is already settled. A brief ordinary walk-around found no obvious danger; this is not an inspection certificate and reveals no hidden technical defect.

Before play, the player chooses one preparation:

- First-time owner: safety supplies and roadside coverage, no technical self-checks or DIY repairs.
- Hands-on owner: basic tools, free time-cost technical self-checks, and selected DIY repairs.

The player may hook up and leave, pay a technician for deeper information, work for cash, buy eligible preventive service, or use recovery. The first objective is always visible: `GET THE CAMPER HOME`.

## Complete loop

1. Own or find one persistent camper opportunity.
2. Inspect it, buy information, or accept uncertainty.
3. Travel to the camper and tow it through a route network.
4. Respond to travel events, condition consequences, time, cost, and weight constraints.
5. Bring the project to the Maple Junction garage.
6. Repair only defects worth addressing and install only upgrades worth their cost and weight.
7. Advertise and advance time to receive persistent buyer offers.
8. Sell as-is or improved.
9. Show gross proceeds separately from actual project profit or loss.
10. Choose whether to repay debt or retain working cash.
11. Buy the next project without losing mobility or access to income.
12. Repeat until the dream camper is owned at home, acceptable, debt-free, and affordable to keep.

## Strategic decisions

- Is a distant low asking price still attractive after travel, service, and risk?
- Is technician information worth more than uncertainty?
- Is time-saving professional work worth its cash cost?
- Is a known defect worth fixing for the likely buyer?
- Will an upgrade increase buyer appeal enough to cover cost and added weight?
- Is a lower offer now better than waiting through another settlement?
- How much cash can safely leave the next-project fund to reduce debt?
- Is this camper a flip or the keeper?

Full restoration must not always be optimal. A project buyer must provide an honest as-is exit.

## Movement and ownership

The player always travels with the permanent Simple-mode tow vehicle, Bluebird. Player, tow vehicle, camper, garage, listing, and buyer are separate entities.

- Solo travel moves player and Bluebird; a parked camper stays behind.
- Towing requires player, Bluebird, and camper to be co-located and moves all three.
- Recovery brings the current project and player to Maple Junction, creates a disclosed payable, and does not repair the camper.
- Maple Junction garage and Bluebird cannot be sold in Simple mode.
- One owned project camper is allowed at a time.

## Time and settlement

Time is a 24-hour game clock with no wall-clock progress and no fixed campaign deadline. Viewing, selecting, changing display units, advertising, buying, and repaying debt take no time unless their content explicitly says otherwise. Travel, inspection, repairs, upgrades, work, waiting, recovery, and handover use explicit hours.

Every successful time-consuming command uses one authoritative sequence:

1. Validate command identity, revision, ownership, location, blockers, and affordability.
2. Apply its direct effect and distance/weight changes.
3. Advance the disclosed hours.
4. Create or resolve at most one eligible event.
5. For every midnight crossed, charge daily costs, accrue interest, refresh local opportunities, expire/generate offers, and append history exactly once.
6. Recalculate objective and completion eligibility.
7. Validate invariants and persist state plus receipt atomically.
8. Return the durable result.

Rejected and exact duplicate commands change nothing.

## Money

Cash never becomes negative. Discretionary actions fail when unaffordable. Allowed unavoidable shortfalls become explicit payables.

Project profit or loss is:

`sale proceeds - acquisition - project travel - inspections - repairs - upgrades - selling costs`

Daily living costs, loan interest, and recovery payables remain separately visible campaign overhead. Debt repayment applies to accrued interest before principal. A sale never repays debt automatically.

## Tone and safety

The tone is grounded, warm, mildly funny, and scrappy rather than cynical. The main game should remain broadly family-friendly.

Alcohol and adult cannabis may later appear as optional RV-lifestyle context because campground culture is part of the setting. If introduced, they should support believable social, budget, time, reputation, or next-day tradeoffs rather than become the game's identity. Never reward impaired driving or towing, depict use by minors, provide consumption instructions, or imply that every RVer participates. Any such content must be separable for audience rating, jurisdiction, and player preference.

All costs, defects, service intervals, weights, capacities, and procedures are fictional game balance. The game is not repair, appraisal, towing, propane, electrical, substance-use, or roadworthiness advice.
