# RVGame

Provisional-title fresh build of Anthony's camper-reseller game.

The player starts after paying for an already-purchased financed fixer-upper at Sunset Shores. A brief self-check found nothing obvious and the seller is gone. The player first chooses roadside protection or a basic toolkit. Hands-on owners can spend time on basic self-checks; first-time owners pay a technician for technical inspection. The compact dashboard uses a live route graph and a 24-hour clock; garage upgrades and selling remain unavailable until the camper is home.

## Stack

- React + Vite + TypeScript UI
- PHP 8.2 authoritative game rules/API
- MariaDB persistence through PDO
- Canonical runtime content at content/simple-mode.json

No old game code or old-save compatibility is included.

## Repository layout

- Full working repository: E:\RVGame
- HostGator runtime staging only: E:\RVGame\Deploy
- Deployment helpers: E:\RVGame\scripts
- XAMPP web path: E:\xampp8\htdocs\RVGame (junction to the working repository)

## Local setup

1. Start Apache and MariaDB in XAMPP.
2. Run E:\xampp8\php\php.exe E:\RVGame\database\setup.php.
3. Run npm.cmd install.
4. Run npm.cmd run build.
5. Open http://localhost/RVGame/.

For Vite development, run npm.cmd run dev and open http://localhost:5173.

## Checks

- E:\xampp8\php\php.exe E:\RVGame\tests\domain.php
- npm.cmd run build
- Playwright CLI against the XAMPP URL

See reports/BUILD_STATUS.md for evidence. A green frontend build does not prove database, API, persistence, or browser gameplay.

## Live deployment channels

- `deploy live` means Owner Test at `/RVGame/OT/`.
- Owner Test uses a separate cookie and channel-tagged save rows.
- `final deploy` is the only instruction that may update `/RVGame/`.
- Ratings and short feedback are separated by channel; no email address is requested.
