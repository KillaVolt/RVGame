# Dashboard and interaction

## Direction

Use the private Drug Wars: Underworld dashboard only as information-architecture evidence: dense simultaneous context, direct actions, local market and inventory adjacency, and immediate results. Build an original modern interface. Do not copy artwork, labels, icons, colors, chrome, or proprietary assets.

Reject the spacious tab/page pattern that hides current money, time, location, camper condition, and available action from each other.

## Desktop arrangement

```text
+-----------------------------------------------------------------------+
| RVGame | Day/Time | Location | Cash | Debt | Objective | Units | Save |
+-------------------+---------------------------------------------------+
| LIVE MAP/ROUTES   | CAMPER HUD | Distance | Weight | Project summary |
| locations, roads, +--------------------------+------------------------+
| player, Bluebird, | LOCAL / MARKET           | PROJECT / ACTIONS      |
| camper, selection | listings, service, work, | defects, repair, ad,   |
| and route preview | demand and information   | offers, sale and debt  |
+-------------------+--------------------------+------------------------+
| DURABLE RESULT / PENDING EVENT / PROJECT LEDGER / SHORT HISTORY       |
+-----------------------------------------------------------------------+
```

At 1440 px, the status strip, map, HUD, both working panes, and newest result should be visible without primary tab navigation.

## Persistent status

Always show a high-contrast Day plus 24-hour clock, hours until midnight, location, cash, total debt, objective, current project or `No current project`, API/save state, distance unit, and current camper weight/limit when towing matters.

## Live map

Render the public route graph as original SVG. Roads and markers come from state. Selecting a connected location highlights the route and previews mode, distance, cost, hours, weight, known blockers, and midnight crossings. Player/Bluebird and camper markers may be separate. Map pixels never calculate game distance.

## Contextual actions

Put an action beside the state it changes. Disabled controls explain the actual blocker.

- First-time owner sees technician options, not technical self-check buttons.
- Hands-on owner sees eligible free-time self-checks and DIY methods.
- Garage work and sale controls appear only when physical requirements are met.
- Weight-sensitive loading and towing show current, resulting, and maximum values.
- A pending event replaces unrelated time-consuming actions until resolved.
- Every irreversible action shows direct cost and hours before commit.

Current playtest actions use a native confirmation dialog followed by a saved-result dialog showing time, money/debt changes and the next objective. Custom debt payments must remain editable when blank and cannot silently clamp to a different amount. Arrival clears route selection. Completion focuses a results screen and removes further economic/travel controls.

## Results, history, and information

The newest durable result remains visible and names start/end time, money/debt changes, distance, weight changes, discoveries, event choice, repair result, and sale proceeds versus project profit. No optimistic success appears before the API commits.

A compact history panel may show the last three local demand/service snapshots and recent project transactions. Do not build a charting framework until text/sparklines prove insufficient.

The current release exposes earlier retained road-log entries and completed-sale expense breakdowns. Market snapshots remain future work. History already removed by an older release cannot be recovered. Mobile Share/Help controls occupy reserved space below the scrolling play area.

## Community controls

Keep `Help improve RVGame` and `Share` visible but quiet. Feedback asks only category plus a short message; name and email are never required. Close never submits or triggers validation. Share offers Facebook, X, Bluesky, Reddit, email, native apps where available, and copy link. Every share target uses `https://starlightrv.ca/RVGame/?new=1`, which starts a fresh anonymous session and returns the player to the clean root URL. Owner Test and build paths are never shared.

Do not add donations until Anthony chooses a destination. Never collect card details locally or expose a private payment email.

## Responsive and accessible behavior

- Tablet may collapse the map while status and HUD remain visible.
- Phone uses one working pane at a time beneath a compact persistent status strip.
- No horizontal page overflow at 360 px.
- Use semantic controls, labels, visible focus, keyboard access, touch-sized targets, text plus color, dialog focus management, scalable text, and reduced-motion support.
- Use warm workshop/campground colors and an original expressive type treatment; avoid generic app-dashboard styling.
