# Build, run, and deploy

## Local prerequisites

- Node/npm compatible with the repository lockfile.
- XAMPP PHP and MariaDB.
- Repository at `E:\RVGame`.
- Local web root mapping that serves the repository as `/RVGame/`.

Use the checked-in lockfile. Dependencies are pinned to the versions used for rvgame-0.5.0. Do not update them during an unrelated feature.

## Local build

From `E:\RVGame`:

```powershell
npm ci
npm run build
E:\xampp8\php\php.exe database\setup.php
E:\xampp8\php\php.exe tests\playtest-regressions.php
E:\xampp8\php\php.exe -d zend.assertions=1 -d assert.exception=1 tests\channel.php
# PowerShell 7; creates its own anonymous test campaign, without resetting existing saves:
./tests/api-smoke.ps1
```

Use `npm run dev` for Vite development. Open `http://localhost/RVGame/` for the XAMPP production build. The root PHP entry serves `dist/index.html` internally with `dist/` as the asset base; it must not redirect the browser to the build path.

Run real browser journeys only after the smallest automated checks pass. Record exactly what was and was not run in `reports/BUILD_STATUS.md`.

## Deployment boundary

Only `E:\RVGame\Deploy` is deployable. Source, tests, node packages, reports, SQL installer, SourceOfTruth, Playable, Decompile, and private references never enter that staging folder. The owner dashboard is runtime code and may be staged; its access configuration is injected directly on the server and never staged.

Required public runtime currently consists of the root entry/rules, `dist`, API PHP files/rules, and `content/simple-mode.json`. The deployment script injects protected database configuration from the existing host configuration; credentials are never copied into Git or Deploy.

## Terms

- `deploy live`: deploy the staged build to Owner Test at `/RVGame/OT/` first.
- `final deploy`: update `/RVGame/` only after Anthony approves the Owner Test build or explicitly asks for an immediate final fix.

Commands:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\deploy-live.ps1
powershell -ExecutionPolicy Bypass -File scripts\deploy-live.ps1 -Final
```

The deployer uses the saved PuTTY session and Pageant key from `E:\.secrets.env`. It transfers one release archive over SSH, verifies every staged file with SHA-256 on the server, and only then activates the selected channel. FTP is not an approved RVGame deployment path.

Deployment preserves saves unless `-ResetSaves` is explicitly included. Never reset saves by implication.

## Release checks

1. Confirm the staged runtime is from the intended source/build.
2. Deploy Owner Test.
3. Verify root response, asset loading, API health, campaign open/resume, and changed journey.
4. Record Owner Test evidence.
5. Obtain approval unless final deployment was explicitly requested.
6. Deploy the same staged artifact with `-Final`.
7. Verify `https://starlightrv.ca/RVGame/` returns `200` without redirect and loads assets/API.
8. Verify direct `/RVGame/dist/` and `/RVGame/dist/index.html` requests redirect to `/RVGame/` so old links cannot leave the build path visible.
9. Verify the shared `?new=1` entry rotates the anonymous browser session and returns to the clean root.
10. Verify unauthenticated owner access is blocked and authenticated analytics render without disclosing credentials.
11. Report save preservation/reset truth and every unrun check.

Do not print `E:\.secrets.env`, remote credentials, database passwords, session tokens, feedback contents, or private evidence.
