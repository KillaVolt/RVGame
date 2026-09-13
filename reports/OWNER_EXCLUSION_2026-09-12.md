# Owner exclusion verification

Release: rvgame-0.5.1 / simple-1.3.1, deployed to Owner Test then main on 2026-09-12. Save schema remains 2; no save reset used.

## Behavior

Owner-dashboard login marks the current browser for analytics exclusion across main and OT. The persistent browser opt-out also covers new campaigns after sign-out. Identified sessions are excluded from page views, visits, engagement, active visitors, sources, devices, browsers, operating systems, languages, hours, game actions, campaign counts/funnels, ratings and feedback. Game saves and command receipts remain usable. Rating and feedback controls explain the exclusion; the API rejects direct owner submissions.

## Authorized historical cleanup

Session 15 explicitly identified itself as the creator in its feedback. Sessions 41, 42, 43, 44, 47 and 53 matched this task's recorded campaign revisions, clock and cash signatures. All seven were validated before mutation.

| Removed records | Count |
|---|---:|
| Analytics events | 331 |
| Analytics visits | 5 |
| Feedback reports | 2 |
| Ratings | 1 |

Campaign JSON SHA-256 hashes were identical before and after cleanup. Unrelated existing session metadata was unchanged. Private maintenance used the deployed PDO helper over SSH; no credentials were printed or copied into public staging.

## Verification

- Local schema installation and `tests/owner-exclusion.php`: historical cleanup, future event suppression, fresh-session inheritance, idempotency, saved game preservation, and another player's records preserved.
- TypeScript/Vite build, PHP lint and full domain/playtest regressions passed; `git diff --check` clean.
- `tests/api-smoke.ps1` passed locally, on OT and main: exact payment, duplicate receipt, conflicting ID, stale revision, oversized rejection, unchanged save, and `OWNER_EXCLUDED` for rating/feedback. Test sessions opt out before their first request.
- `tests/owner-smoke.ps1` passed on both live channels: login gate, all eight metrics/twelve panels for 7/30/90/365 days, visible owner exclusion notice, browser marker and sign-out.
- Fourteen runtime files matched source staging; remote deployment verified its archive manifest. Both live JS/CSS bytes matched the staged SHA-256 hashes. Content returned rvgame-0.5.1.
- Authenticated in-app dashboard refreshed to zero reports/ratings and showed the exclusion notice. Main and OT feedback controls visibly disabled with the owner message. Main resumed revision 9, Day 2 07:00, G$1,703.00; OT retained revision 20, Day 3 14:00, G$840.48 and completion.
- Current Brave browser's RVGame opt-out cookie was set and its existing OT tab reloaded; the owner exclusion notice and disabled ratings/feedback were verified visibly. No authentication cookie was altered.
- Live verification after browser refresh: zero excluded-session rows in events, visits, feedback and ratings; all seven campaign revisions intact.

## Recognition boundary

Sign in once on each additional browser/device, or after clearing all site cookies. Existing flagged game cookies restore the opt-out when recognized. Unknown anonymous historical sessions are preserved: no IP, user-agent or fingerprint heuristic is used to guess ownership. Exclusion is enforced for recognized sessions, not a claim to identify a person across unrelated anonymous browsers.

Earlier 0.5.0 gameplay evidence remains in PLAYTEST_FIXES_2026-09-12.md. This maintenance release did not rerun a full 60-day browser campaign or expand the physical-device matrix.
