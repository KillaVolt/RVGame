# Architecture and data contracts

## Stack and ownership

| Concern | Owner |
|---|---|
| Rendering, selection, user intent | React + TypeScript + Vite |
| Rules, validation, deterministic outcomes | PHP game service |
| Durable campaign and command receipts | MariaDB through PDO |
| Exact authored Simple values | `content/simple-mode.json` |
| Presentation | Plain CSS |
| Design requirements | `SourceOfTruth` |

Do not add a framework, state library, ORM, event bus, or second API endpoint until the existing code cannot express a required behavior clearly.

React never authoritatively calculates cash, debt, ownership, camper state, condition, event outcome, time, distance, weight, listing, or offer.

## Repository shape

```text
E:\RVGame
  SourceOfTruth\
  src\
  api\
  content\simple-mode.json
  database\schema.sql
  database\setup.php
  tests\
  reports\BUILD_STATUS.md
  references\private\
  Decompile\
  Playable\
  Deploy\
```

`Decompile`, `Playable`, and `references/private` are private reference material. `Decompile/tools` is excluded from Git. None is copied to `Deploy` or `dist`.

## Command API

One endpoint is enough for the current game: `api/index.php`.

- `GET` returns public campaign state, creation options, and channel rating summary.
- `POST new` creates a campaign using an explicit preparation/loadout ID.
- `POST command` applies one typed command.
- `POST reset` deletes only the caller's channel campaign after confirmation.
- `POST rate` replaces the caller's anonymous 1-to-5 rating.
- `POST feedback` stores category plus a short message.

The public share entry uses `?new=1`. It expires only the channel's anonymous game and analytics visit cookies, then redirects to the clean root. It does not delete the prior database record or affect another browser. Normal root visits retain resume behavior.

Mutating envelope:

```json
{
  "action": "command",
  "commandId": "browser-generated UUID",
  "expectedRevision": 12,
  "type": "travel",
  "payload": {"routeId": "sunset-pine", "mode": "tow"}
}
```

Repair payloads name `diy` or `technician`; there is no method fallback. Event resolution names the pending event instance and selected choice. Load/tank commands name exact intended deltas.

Failures return `ok: false`, a stable code, a safe message, and current revision when available. HTTP, content, invariant, or database failure never returns success.

## Atomic mutation

1. Begin transaction.
2. Lock the campaign.
3. Return the stored result for an exact duplicate command.
4. Reject reused command ID with different payload or stale revision.
5. Validate and apply one domain transition.
6. Run state invariants.
7. Update campaign JSON and revision.
8. Insert command receipt.
9. Commit.
10. Return durable success.

No mutation occurs twice.

## State ownership

The campaign JSON is the sole writable owner of Simple game state. Required concepts include campaign/version/status, deterministic seed/ordinal, day/hour, player, Bluebird, camper or null, finances, listings, offers, pending event, sold projects, goal, histories, and event log.

Canonical values:

- money: integer cents;
- time: integer day/hour;
- distance: integer kilometers;
- weight: integer kilograms;
- tank volume: integer liters;
- percentages and multipliers: integer basis points.

Camper dry mass, rating, tank liters, loose load, installed-upgrade weight, and the Bluebird limit are canonical inputs. Current weight and remaining payload are derived rather than stored.

Actual condition and unknown defects remain server-side. Public state contains player knowledge and derived HUD only.

## Determinism and history

Random-looking generation uses SHA-256 over stable state such as ruleset, campaign seed, subsystem, day/hour, location, entity, and ordinal. Outcomes are persisted. Reload, panel open, sorting, rejected commands, and duplicate retries never reroll.

The shared time-advance path owns settlement and time-based offer processing. Midnight settles recurring costs, interest, market snapshots, and listing replenishment. Offer expiry and generation run after committed time advances, including actions that do not cross midnight. Reopening public state does not mutate offers or listings. Do not reproduce settlement in travel, work, repair, or UI code.

## Content

`content/simple-mode.json` remains the only executable Simple content file. Add new required sections there when implementing weight, richer events, local services, demand, or histories. PHP validates all IDs, references, ranges, graph connectivity, nonnegative values, recoverability, and unit semantics at load time.

Do not copy exact values into TypeScript or SourceOfTruth. Documentation may describe the current baseline for humans, but executable behavior reads the JSON.

`rulesetId` and `contentVersion` are persisted. Incompatible saves fail visibly with an explicit reset option; they are not silently migrated.

## Sessions and public input

Use random HttpOnly SameSite cookies with only token hashes stored. Main and Owner Test use separate cookies and channel-scoped rows. POST requires JSON and `X-RVGame`. Use PDO prepared statements. Validate feedback category/length, rating range, commands, and every client-provided ID.

## Dependency and release discipline

Current local, Owner Test, and main identity is `rvgame-0.6.0`, from content `releaseId`; package version is `0.6.0`. Frontend dependencies are pinned to the existing lockfile versions, with no dependency upgrade in this release. Keep package metadata, content release ID, and release evidence aligned.

## Future engine boundary

Engine-portable assets are documented rules, stable IDs, plain JSON concepts, API contracts, and golden fixtures. PHP and React source do not magically port to Unity. A Unity implementation either calls the API or reimplements rules and proves parity against shared fixtures.
