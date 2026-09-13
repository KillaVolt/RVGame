# Simple mode

## Definition of complete

Simple mode is a complete playtest game with shallow technical simulation and real durable state. It is complete only when a player can finish the opening trip, sell the starter, purchase and sell later projects, repay debt, obtain the dream camper, and explicitly complete the campaign.

## Canonical starting state

Exact values live in `E:\RVGame\content\simple-mode.json`. The current baseline is:

- Day 1, 09:00, Sunset Shores.
- Rusty Lantern 19 already purchased for G$3,500 and already owned.
- G$1,500 spendable cash.
- G$2,000 loan principal.
- Permanent Bluebird tow vehicle and Maple Junction garage.
- Player, Bluebird, and camper co-located.
- No preparation chosen automatically.

Never deduct the starter purchase again.

## Preparation choice

First-time owner brings a safety kit and roadside coverage. Technical self-check and DIY repair commands are unavailable. The player can still view the camper, known symptoms, obvious presentation, HUD, history, and action previews at no charge and no time. Technical knowledge comes from a paid technician. Recovery creates the reduced configured payable.

Hands-on owner brings the basic toolkit. A content-eligible system self-check costs no money and consumes one hour. A content-eligible DIY repair costs no direct labor fee and consumes its disclosed hours. Safety-specialist work remains technician-only.

A technician inspection is optional, costs the configured G$120, consumes four hours, and reveals every existing defect supported by the current Simple content. Inspection reveals facts; it never creates damage.

## Camper HUD

Every owned camper shows:

1. Running Gear and Towing
2. Roof, Body and Seals
3. 12V Battery and Charging
4. 120V Shore Power
5. Propane
6. Water and Plumbing
7. Appliances and HVAC
8. Interior and Presentation

Derived display states are `UNKNOWN`, `LOOKS OK`, `WATCH`, `FAULT`, and `TOWING BLOCKED`. `REPAIRED` is a recent-result badge, not a second health value. `LOOKS OK` means current game evidence found no known unresolved issue; it never means certified safe.

## Distance and units

Canonical distance is integer kilometers. Bluebird odometer, camper distance towed while owned, transported distance, and any explicit distance-since-service counter persist. The player may choose kilometers or miles at any time. Miles are display conversion only and never alter thresholds or saved canonical values.

The opening route is Sunset Shores to Pine Lake to Cedar Ridge to Maple Junction, totaling 206 km. The starter tire defect becomes towing-blocking after its authored unresolved threshold. Preventive service removes that eligibility; the game does not substitute another guaranteed breakdown.

## Weight

Simple mode must use one understandable weight envelope instead of abstract cargo slots as the capacity authority.

Canonical fields:

- camper dry weight in integer kilograms;
- camper gross vehicle weight rating in integer kilograms;
- Bluebird maximum trailer weight in integer kilograms;
- fresh, grey, and black tank capacities and current liters;
- installed-upgrade weight and loose load weight in integer kilograms.

Derived values:

- `tankWeightKg = freshLitres + greyLitres + blackLitres` using the Simple game approximation of 1 kg per liter;
- `currentCamperWeightKg = dryWeightKg + tankWeightKg + installedUpgradeWeightKg + looseLoadWeightKg`;
- `remainingPayloadKg = grossVehicleWeightRatingKg - currentCamperWeightKg`.

The UI shows estimated empty, current, and all-tanks-full weight plus remaining trailer payload. Towing is blocked when current camper weight exceeds either the camper rating or Bluebird's authored trailer limit. Tank level changes and load changes must use explicit actions and persist. Basic tools ride in Bluebird and do not silently consume camper payload. Tongue weight, axle-by-axle loading, and detailed tow-vehicle payload are Full-mode candidates, not Simple-mode inventions.

Metric display uses km/kg and optional imperial display uses mi/lb. Conversion is presentation only.

## Live map and travel

The map is a state-driven SVG route graph. It renders authored locations and connected roads, current player/Bluebird position, camper position, reachable routes, selection, and blockers. Pixel geometry never determines route distance.

Before towing, show destination, distance, cash cost, hours, current camper weight, limits, known blocker, and hours until midnight. Solo travel keeps the camper parked. Recovery is available even at zero cash, adds its disclosed payable, consumes time, and does not repair defects.

## Events

Events are durable state, not toast messages or refresh-time rolls.

- At most one event starts per committed action.
- Eligibility uses location, action, camper state, known defects, preparation, time, and deterministic random input.
- A pending decision blocks another time-consuming command until resolved.
- Each choice previews known immediate money/time/location effects.
- Outcomes persist and cannot reroll on reopen.
- Resolved defects suppress dependent failures.
- Every harmful event retains a work, service, wait, or recovery path.
- An event cannot recursively start another event.

Minimum playtest event coverage:

- one condition-linked travel problem with slow/technician/recovery responses;
- one weather delay with wait versus cautious-progress responses;
- one local service or parts opportunity;
- one buyer lead that changes a persistent offer or demand signal;
- one paid-information opportunity;
- one odd-job opportunity;
- one harmless flavor event;
- one positive discovery.

The deterministic starter tire threshold remains separate from the random pool.

## Home project actions

Repairs, upgrades, advertising, offers, and sale are available only where their physical requirements are met. Garage-only work requires player and camper together at Maple Junction. Every irreversible action previews exact direct cost, hours, target, and known effect.

A repair changes only its named defect/system. An upgrade stays with the camper at sale, adds authored value/appeal and weight, and is never guaranteed to return its cost.

## Market and information

Each listing represents one persistent camper instance with location, ask, seller claims, actual defects, player knowledge, and expiry. Reopening or sorting never regenerates it.

Every location exposes a compact local context: available projects, technician/service availability, current buyer demand, work, and connected routes. Local differences must affect at least price, availability, service, information, or buyer demand; names alone are insufficient.

Simple mode needs a short market history, not a stock-trading simulator: current demand plus the previous three midnight snapshots for relevant camper tags and service prices. Basic current information is free. Better forecasts or defect/value clues may cost cash or a contact favor and must state what was purchased.

## Advertising, offers, and sale

One active advertisement is allowed per owned camper. Advertising itself takes no time. Offers appear only through a committed time transition or event and remain stable until expiry or sale.

The current playtest saves an asking price based on known condition. Repairs do not change an existing offer. Explicit Relist withdraws offers and updates the asking price; Decline removes one offer while keeping the ad. New offers last 48 game hours. Wait is disabled while a usable offer exists. Older saved offers without expiry keep their recorded terms until declined, relisted or sold; the UI identifies that absence rather than inventing a deadline.

Buyer profiles may value price, presentation, repaired towing condition, layout, or project tolerance. At least one buyer can purchase an imperfect camper honestly. Offers show amount, expiry, rationale, and handover requirement.

Sale transfers ownership exactly once and shows gross proceeds, acquisition price, direct expenses by category, project profit/loss, remaining cash, and remaining debt. Installed upgrades leave with the camper. Debt is not auto-paid.

## Between projects and completion

After selling the only camper, the player retains Bluebird, garage, cash, debt, settings, history, work, travel, and listings. Every current town offers a named, non-specialist odd job: employer, task, pay and duration are shown before acceptance. Starts are available from 08:00 to 15:00; the eight-hour job prevents consecutive same-day shifts. Closed jobs provide Rest until the next opening, with normal midnight bills. This daily income route and recovery payables prevent ordinary zero-cash softlocks without unexplained overnight employment.

There is no 60-day deadline. The opening and help explicitly describe the open-ended journey and voluntary ending.

The dream camper can be kept only when it is owned at Maple Junction, has no towing blocker, meets required HUD conditions, fits weight limits, all liabilities are zero, and the configured cash reserve remains. Eligibility shows `Make This One Home`; completion occurs only after the player chooses it.

## Not in Simple mode

- Component-by-component wear or real repair procedures.
- Multiple owned campers, dealership property, or tow-vehicle shopping.
- Axle, tongue-weight, tire-load, or hitch engineering simulation.
- Alcohol or cannabis mechanics; the first public playtest remains broadly family-friendly.
- Player accounts, multiplayer, ads, payment processing, or paid advantages.
- Unity, 3D scenes, or old-save migration.
