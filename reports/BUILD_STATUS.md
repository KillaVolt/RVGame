# Build status

Prepared: 2026-09-13

## Current release: rvgame-0.6.0 — main and Owner Test

Deployed to `https://starlightrv.ca/RVGame/OT/` and `https://starlightrv.ca/RVGame/` on 2026-09-13. Content `simple-2.0.0`, save schema 3. The Simple core-loop and content-depth gaps use the existing PHP game service, campaign JSON, React dashboard, and authored content file. No dependency, framework, or second endpoint was added.

PASS: TypeScript/Vite production build; all PHP syntax checks; five complete domain campaigns; playtest regressions; channel isolation; local, OT, and main HTTP payment, duplicate, conflict, stale-revision, rejected-command rollback, reload, and owner-exclusion checks. Both deployments preserved campaign rows, installed the schema, verified the release manifest, and atomically activated the build. Both clean roots and assets return successfully; their JS/CSS SHA-256 hashes match staging; stale `dist/` links redirect to the clean root; unauthenticated owner routes render only their login gates. Live Chromium proved persistent listings, regional context, weight/tanks, exact paid next-day information at revision 1, `rvgame-0.6.0` on main, and no horizontal overflow at 360 x 800.

The first final-deploy attempts were interrupted by HostGator resetting SSH between upload and activation. Main was verified still on `rvgame-0.5.1` after each ambiguous attempt. After the connection-rate cooldown, the complete uploaded archive was activated in one SSH session; manifest verification ran before installation and the server confirmed main saves were preserved.

NOT RUN: full 0.6 browser repair/resale/dream journey on either deployed channel, forced database/network failure, full accessibility journey, physical devices, and non-Chromium browsers. Schema-2 saves are preserved but intentionally receive a visible reset-required response; no migration or save reset was attempted.

## Previous deployed release: rvgame-0.5.1 — main and Owner Test

Deployed 2026-09-12 at approximately 23:32 OT / 23:33 main America/Toronto. Content `simple-1.3.1`, save schema 2. Owner collection suppression, dashboard/community filtering and historical cleanup are active. Current in-app and Brave browsers are excluded; all seven identified campaign saves were verified unchanged. Two reports, one rating, 331 events and five visits were removed by session-scoped cleanup. Unknown anonymous visitors were preserved.

PASS: TypeScript/Vite build, PHP lint, domain/playtest regressions, local owner isolation check, local/main/OT API payment/retry/rejection/reload and owner-submission rejection, main/OT authenticated dashboard rendering for all four ranges plus opt-out cookie and sign-out, both live JS/CSS SHA-256 hashes, and browser exclusion controls. Live database verification found zero excluded records in visits/events/ratings/feedback. [Detailed evidence and identification limits](OWNER_EXCLUSION_2026-09-12.md).

## Previous release: rvgame-0.5.0 — main and Owner Test

**Deployed and verified** at https://starlightrv.ca/RVGame/ and https://starlightrv.ca/RVGame/OT/ with existing saves preserved. Main final deployment was explicitly requested and completed at approximately 23:04 America/Toronto on 2026-09-12, using the unchanged OT-approved artifact. Content `simple-1.3.0`, save schema 2; package version `0.5.0`; no commit created.

Final-deployment checks: main root 200, both live JS/CSS SHA-256 hashes matched staging, content release/version matched, API payment/duplicate/conflict/stale/rejection/reload checks passed, and stale dist entry redirects passed. Authenticated main and OT owner dashboards rendered all 8 metrics and 12 panels for 7/30/90/365-day ranges; login gate and sign-out passed. No credentials or private feedback were printed.

| Area | Status | Evidence |
|---|---|---|
| Build and domain | PASS | TypeScript/Vite build, PHP lint, domain journey and playtest regressions, channel checks with assertions enabled |
| Local browser | PASS | Hands-on ending Day 4, 16:00, revision 30; two sold, G$638.05 cash, zero debt |
| Deployed OT browser | PASS | First-time-owner ending Day 3, 14:00, revision 20; two sold, G$840.48 cash, zero debt; reload retained ending |
| Save preservation | PASS | Pre-deployment 0.4.1 OT campaign resumed unchanged on 0.5.0 and completed |
| Payment/retry API | PASS | Local and OT one-cent payment, exact duplicate, conflicting ID, stale revision and oversized rejection; saved balance/revision unchanged by rejections |
| Changed mobile flows | PASS | 360×800 local and 390×844 OT status, confirmation, sale/result and focused ending; secondary controls have reserved space |
| Offers and duration | PASS | Browser relist/decline/new buyers; domain expiry boundary, reload stability, retained history and play through Day 61 |
| Deployment scope | PASS | OT first, then explicitly authorized main final deployment; same 14 hashed runtime files and remote manifest verification; no reset flag used |
| OT entry/protection | PASS | Root/assets/content 200; stale dist entries redirect; owner login gate; private files 403 |
| Native sharing | INCONCLUSIVE | User reported closing the share interface during testing; no confirmed game defect or actual delivery claim |
| Full platform/failure matrix | NOT RUN | Physical phones, non-Chromium, full accessibility journey, forced SQL/network failure; broader future Simple mechanics remain unimplemented |

The [complete resolution report](PLAYTEST_FIXES_2026-09-12.md) maps all 35 original playtest observations to changes and verification limits. It supersedes the older evidence below for this OT release. The current release is not a claim that every future SourceOfTruth acceptance item passed.

## Historical evidence before 0.5.0

| Area | Status | Evidence |
|---|---|---|
| Fresh repository source | PASS | Private GitHub repository `KillaVolt/RVGame` on `main` |
| React dashboard | PASS | Prominent persistent clock built and verified on HostGator |
| PHP domain/API | PASS | Domain journey and Owner Test API passed with rvgame-0.4.0 |
| MariaDB schema | PASS | Main/OT channel migration, isolated sessions, ratings, and feedback installed |
| Opening trip | PASS | Domain reached home; live browser first tow updated clock, markers, cash, and 88 km |
| Owner expertise | PASS | First-time Owner has paid technician inspection and no technical self-check action |
| Community controls | PASS | Owner Test loaded empty rating state; prior rating and feedback write path passed |
| Social sharing | PASS | Native share control built with stable `/RVGame/` target and copy-link fallback |
| Repair/resale/repeat loop | NOT RUN | Domain passed; rvgame-0.4.0 browser resale cycle not rerun |
| Dream camper ending | NOT RUN | Domain passed; rvgame-0.4.0 browser ending not rerun |
| Duplicate command safety | NOT RUN | Changed state transitions require recheck |
| Reload persistence | PASS | Live one-hour inspection persisted at revision 1 and 10:00 |
| Responsive width | NOT RUN | New clock layout requires desktop and 360 px recheck |
| Package links | PASS | 25 links checked, zero missing |

## Current rvgame-0.4.1 evidence

- E:\xampp8\php\php.exe tests\domain.php: PASS
- E:\xampp8\php\php.exe tests\channel.php: PASS
- npm.cmd run build: PASS
- Owner Test database install, protected config, channel-only save reset, and atomic file verification: PASS
- Owner Test browser: clean start, First-time Owner rules, corrected technician wording, empty rating state, and community controls: PASS
- HostGator Owner Test deployment: PASS at https://starlightrv.ca/RVGame/OT/
- HostGator production deployment from the same staged artifact: PASS at https://starlightrv.ca/RVGame/
- Main and Owner Test saves, ratings, and feedback were explicitly reset before release verification.
- Feedback Close works with an empty required message field in Owner Test and production.
- Share uses the standard icon and exposes Facebook, X, Bluesky, Reddit, email, native apps, and copy-link actions.

## Not run

- Full repair/resale cycle and dream ending in the rvgame-0.4.0 browser.
- Forced transaction rollback.
- Explicit stale-revision request.
- 200 percent zoom and complete keyboard-only journey.
- Firefox, WebKit, Edge, and mobile-device browser matrices.
