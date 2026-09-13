# Authority and conflict resolution

## Canonical locations

- Repository: `E:\RVGame`
- Design package: `E:\RVGame\SourceOfTruth`
- Executable Simple content: `E:\RVGame\content\simple-mode.json`
- Private visual references: `E:\RVGame\references\private`
- Private decompilation evidence: `E:\RVGame\Decompile\drug-wars-underworld-re`
- Deployment staging: `E:\RVGame\Deploy`

## Authority order

Use the narrowest applicable authority:

1. This file decides precedence and scope.
2. `SIMPLE_MODE.md` owns required Simple-mode behavior.
3. `FULL_MODE.md` owns future Full-mode behavior.
4. `GAME_DESIGN.md` owns the shared product promise and loop.
5. `UI_DASHBOARD.md` owns interaction and information hierarchy.
6. `ARCHITECTURE_AND_DATA.md` owns runtime boundaries and data contracts.
7. `E:\RVGame\content\simple-mode.json` owns exact implemented names, IDs, prices, durations, thresholds, routes, and balance values.
8. `ACCEPTANCE_TESTS.md` defines required proof.
9. `OWNER_ANALYTICS.md` owns private analytics scope and privacy boundaries.
10. `IMPLEMENTATION_STATUS.md` reports evidence only; it cannot change requirements.

When a broad document disagrees with a narrower owner, the narrower owner wins. Do not compromise silently or introduce a second field, fallback, rules engine, or content file.

## Current versus desired behavior

The package intentionally includes requirements not yet implemented. `GAP_MAP_AND_ROADMAP.md` and `IMPLEMENTATION_STATUS.md` distinguish them. A documented requirement is not evidence that the game contains it.

## Historical material

Everything under `E:\Games Family\RV_Tech_Codex_Package*` and `E:\Games Family\Game_Family_Research_and_Design.html` is historical input. It was reviewed, but it no longer has authority. Do not ask Codex to merge it into current behavior.

## Non-negotiable rules

- This is a fresh product direction, not compatibility work for an older RV prototype.
- `RVGame` remains the exact provisional title until Anthony changes it.
- Simple mode is the public playtest priority and must provide the complete repeatable reseller loop.
- The opening trip is the first playable slice, not the whole game.
- PHP owns authoritative commands. React renders public state and sends intent. MariaDB owns durable state.
- Money is integer cents. Distance is integer kilometers. Weight is integer kilograms. Unit choices are presentation only.
- Generated camper instances, defects, events, listings, offers, and command outcomes persist and never reroll on refresh.
- No silent browser-storage fallback, save migration, default content, duplicate state owner, or success response after failed persistence.
- Private screenshots, executable binaries, decompiled code, credentials, and verbatim proprietary assets/data never enter deployment output. They may be analyzed privately and used to derive original RVGame mechanics, balance hypotheses, data structures, and interface lessons.
- Do not claim a test passed unless it ran against the identified build.
