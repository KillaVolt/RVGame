# Owner analytics

## Purpose

`/RVGame/owner/` is a private, first-party analytics dashboard for Anthony. Owner Test has a separate dashboard under `/RVGame/OT/owner/`. Main and OT data never mix.

## Access

The dashboard uses the existing StarCore admin username and password from `E:\.secrets.env`. Deployment writes only the username and a one-way password hash to a protected server file. The plaintext password is not stored in the repository, staging folder, browser code, or dashboard database.

Owner sessions are HttpOnly, Secure on HTTPS, SameSite Strict, scoped to the owner path, and expire after eight hours of inactivity. Pages send no-store, noindex, frame-denial, content-type, referrer, and content-security headers.

## Collected analytics

Owner testing is excluded from every metric and from ratings/feedback in release 0.5.1. Authenticated owner-dashboard access marks the browser with an HttpOnly `rvgame_owner_testing` opt-out cookie shared across main and OT. Existing recognized game sessions are flagged with `rvgame_sessions.analytics_excluded`; their analytics, ratings and feedback are removed while game saves remain intact. Collection guards and dashboard/community queries both enforce the flag. Owner feedback controls explain the exclusion and are disabled; direct submissions return `OWNER_EXCLUDED`.

The marker survives new campaigns and owner sign-out. Sign in to the owner dashboard once on every browser/device used for testing, and again after clearing site cookies. Previously flagged game sessions restore the marker when recognized. Anonymous historical sessions that cannot be attributed to the owner remain unclassified; no IP or browser fingerprint matching is used. The opt-out cookie grants no dashboard access.

On 2026-09-12, seven positively identified creator/playtest sessions were excluded and 331 events, five visits, two reports and one rating were removed. All seven campaign JSON hashes remained unchanged. Current in-app and Brave browsers were marked; live verification found zero excluded rows in the four analytics/community tables. See [owner exclusion evidence](../reports/OWNER_EXCLUSION_2026-09-12.md).

- First-party anonymous visitor/session ID
- Visit start and last activity time
- Page-view and game-event counts
- Referral host category and optional UTM labels
- Coarse device, browser, operating-system, and language categories
- Bot/crawler classification
- Campaign starts and authoritative game command types
- Ratings and submitted feedback already collected by the game

The dashboard provides 7, 30, 90, and 365-day views, active-now estimate, visitors, visits, page views, engagement duration estimate, bounce estimate, campaign starts, average rating, daily trend graph, campaign funnel, source/device/browser/OS/language/hour/action/rating graphs, and latest feedback.

Live verification on 2026-09-12 after the 0.5.0 final deployment: authenticated main and OT dashboard requests rendered all eight metrics and twelve panels for each date range. Login gates and sign-out passed. `tests/owner-smoke.ps1` reproduces this check using `RVGAME_OWNER_USERNAME` and `RVGAME_OWNER_PASSWORD` environment variables without printing credentials or feedback. This is HTTP rendering/access evidence, not a full visual or statistical-accuracy audit.

## Privacy boundary

Do not store raw IP addresses, raw user-agent strings, full referring URLs, names, emails, account identities, advertising identifiers, cross-site profiles, or fingerprint data. Analytics are approximate operational evidence, not surveillance. The system does not use third-party analytics scripts or send play data to an analytics vendor.

Analytics collection begins when this schema is deployed. It does not reconstruct historical page views. Existing campaign, rating, and feedback totals can still appear where queried from their authoritative tables.
