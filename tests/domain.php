<?php
declare(strict_types=1);

require dirname(__DIR__) . '/api/Game.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function act(Game $game, array $state, string $type, array $payload = []): array
{
    return $game->apply($state, $type, $payload)[0];
}

function listingId(array $state, string $archetypeId): string
{
    foreach ($state['listings'] as $listing) {
        if ($listing['camper']['archetypeId'] === $archetypeId) {
            return $listing['id'];
        }
    }
    throw new RuntimeException('Missing listing for ' . $archetypeId);
}

try {
    $game = new Game(dirname(__DIR__) . '/content/simple-mode.json');

    $options = $game->startOptions();
    check(count($options) === 2, 'Simple mode must offer two clear starting approaches.');
    $firstTimer = $game->initialState('first-timer');
    check($firstTimer['player']['tools'] === [], 'First-time owner must not receive repair tools.');
    check(in_array('roadside-coverage', $firstTimer['player']['perks'], true), 'First-time owner roadside coverage missing.');
    check($game->publicView($firstTimer)['inspection']['selfAvailable'] === false, 'First-time owner must not be offered technical self-checks.');
    $selfCheckBlocked = false;
    try {
        act($game, $firstTimer, 'inspect', ['systemId' => 'shell']);
    } catch (GameRuleException) {
        $selfCheckBlocked = true;
    }
    check($selfCheckBlocked, 'First-time owner self-check must be rejected by domain rules.');
    $firstTimerTech = act($game, $firstTimer, 'technician-inspect');
    check($firstTimerTech['finances']['cashCents'] === 138000, 'First-time owner technician inspection must cost G$120.');

    $state = $game->initialState('hands-on');
    check($state['day'] === 1, 'Campaign must start on Day 1.');
    check($state['hour'] === 9, 'Campaign must start at 09:00.');
    check($state['finances']['cashCents'] === 150000, 'Simple mode must start with G$1,500 cash.');
    check($state['finances']['loanPrincipalCents'] === 200000, 'Simple mode must start with G$2,000 principal.');
    check($state['camper']['locationId'] === 'sunset-shores', 'Starter camper location mismatch.');
    $public = $game->publicView($state);
    check(!array_key_exists('actualSystemCondition', $public['camper']), 'Public view leaked hidden actual state.');
    check(count($public['camper']['knownDefects']) === 0, 'The brief opening check must not reveal hidden starter defects.');
    check($public['camper']['hud'][0]['state'] === 'LOOKS_OK', 'The opening roadworthy check should show no obvious running-gear issue.');
    check(count($public['mapRoutes']) === 3 && $public['mapRoutes'][0]['reachable'] === true, 'Live map route graph mismatch.');
    check($public['atGarageWithCamper'] === false && $public['canAdvertise'] === false, 'Garage actions must stay unavailable before the camper is home.');

    $selfCheck = act($game, $game->initialState('hands-on'), 'inspect', ['systemId' => 'running-gear']);
    check(count($game->publicView($selfCheck)['camper']['knownDefects']) === 0, 'A self-check must not reveal technician-tier defects.');
    check($selfCheck['day'] === 1 && $selfCheck['hour'] === 10, 'A self-check must consume one hour, not a full day.');

    $diy = act($game, $game->initialState('hands-on'), 'inspect', ['systemId' => 'shell']);
    check(count($game->publicView($diy)['camper']['knownDefects']) === 1, 'A self-check must reveal a self-tier defect.');
    $diy = act($game, $diy, 'repair', ['repairId' => 'seal-refresh', 'method' => 'diy']);
    check($diy['day'] === 1 && $diy['hour'] === 16, 'A self-check plus seal repair must consume seven hours.');
    check(count(array_filter($diy['camper']['projectExpenses'], fn(array $expense): bool => $expense['type'] === 'repair')) === 0, 'A DIY repair must have no direct fee.');

    $roadside = $game->initialState('hands-on');
    $roadside = act($game, $roadside, 'travel', ['routeId' => 'sunset-pine', 'mode' => 'tow']);
    $roadside = act($game, $roadside, 'travel', ['routeId' => 'pine-cedar', 'mode' => 'tow']);
    check($game->publicView($roadside)['camper']['hud'][0]['state'] === 'TOWING_BLOCKED', 'Tire threshold must block towing.');
    $roadside = act($game, $roadside, 'repair', ['repairId' => 'roadside-tire-service', 'method' => 'technician']);
    $roadside = act($game, $roadside, 'travel', ['routeId' => 'cedar-maple', 'mode' => 'tow']);
    check($roadside['camper']['locationId'] === 'maple-junction', 'Roadside path must reach home.');

    $state = act($game, $state, 'technician-inspect');
    check(count($game->publicView($state)['camper']['knownDefects']) === 3, 'Technician inspection must reveal all existing starter issues.');
    $state = act($game, $state, 'repair', ['repairId' => 'preventive-tire-service', 'method' => 'technician']);
    $state = act($game, $state, 'travel', ['routeId' => 'sunset-pine', 'mode' => 'tow']);
    $state = act($game, $state, 'travel', ['routeId' => 'pine-cedar', 'mode' => 'tow']);
    $state = act($game, $state, 'travel', ['routeId' => 'cedar-maple', 'mode' => 'tow']);
    check($state['camper']['towedKm'] === 206, 'Opening route must total 206 canonical kilometers.');
    check($state['towVehicle']['odometerKm'] === 126606, 'Tow vehicle odometer mismatch.');
    check($game->publicView($state)['camper']['hud'][0]['state'] === 'LOOKS_OK', 'Preventive repair must suppress the tire failure.');

    $state = act($game, $state, 'repair', ['repairId' => 'seal-refresh', 'method' => 'diy']);
    $state = act($game, $state, 'advertise', ['strategy' => 'market']);
    $state = act($game, $state, 'wait');
    check(count($state['offers']) === 1, 'Advertising plus a committed day must create one persistent offer.');
    $starterOffer = $state['offers'][0];
    $state = act($game, $state, 'sell', ['offerId' => $starterOffer['id']]);
    check($state['camper'] === null && $state['saleCount'] === 1, 'Starter sale must transfer ownership once.');
    check(count($state['listings']) >= 1, 'No-camper state must offer another project.');

    while ($state['finances']['cashCents'] < 400000) {
        $state = act($game, $state, 'work');
    }
    $state = act($game, $state, 'buy', ['listingId' => listingId($state, 'pocket-pine-17')]);
    $state = act($game, $state, 'inspect', ['systemId' => 'shell']);
    $state = act($game, $state, 'repair', ['repairId' => 'seal-refresh', 'method' => 'diy']);
    $state = act($game, $state, 'repair', ['repairId' => 'interior-refresh', 'method' => 'diy']);
    $state = act($game, $state, 'advertise', ['strategy' => 'market']);
    $state = act($game, $state, 'wait');
    $state = act($game, $state, 'sell', ['offerId' => $state['offers'][0]['id']]);
    check($state['saleCount'] === 2, 'Second project must complete the repeatable resale loop.');
    check(listingId($state, 'sunbeam-23') !== '', 'Dream camper must unlock after two sales.');

    do {
        $totalDebt = array_sum([
            $state['finances']['loanPrincipalCents'],
            $state['finances']['accruedInterestCents'],
            $state['finances']['payablesCents'],
        ]);
        if ($state['finances']['cashCents'] >= $totalDebt + 500000) {
            break;
        }
        $state = act($game, $state, 'work');
    } while (true);
    $totalDebt = array_sum([
        $state['finances']['loanPrincipalCents'],
        $state['finances']['accruedInterestCents'],
        $state['finances']['payablesCents'],
    ]);
    $state = act($game, $state, 'repay', ['amountCents' => $totalDebt]);
    $state = act($game, $state, 'buy', ['listingId' => listingId($state, 'sunbeam-23')]);
    foreach (['running-gear', 'shell', 'plumbing'] as $systemId) {
        while ($state['finances']['cashCents'] < 6000) {
            $state = act($game, $state, 'work');
        }
        $state = act($game, $state, 'inspect', ['systemId' => $systemId]);
    }
    $state = act($game, $state, 'repair', ['repairId' => 'plumbing-service', 'method' => 'diy']);
    while ($state['finances']['cashCents'] < 50000) {
        $state = act($game, $state, 'work');
    }
    check($game->publicView($state)['canComplete'] === true, 'Dream keeper conditions should become eligible.');
    $state = act($game, $state, 'complete');
    check($state['status'] === 'completed', 'Explicit keeper command must complete the campaign.');

    $recovery = $game->initialState('hands-on');
    $vehicleOdometer = $recovery['towVehicle']['odometerKm'];
    $recovery = act($game, $recovery, 'recover');
    check($recovery['camper']['locationId'] === 'maple-junction', 'Recovery must move camper home.');
    check($recovery['towVehicle']['odometerKm'] === $vehicleOdometer, 'Recovery must not add tow-vehicle kilometers.');
    check($recovery['finances']['payablesCents'] >= 30000, 'Recovery payable missing.');

    $coveredRecovery = act($game, $game->initialState('first-timer'), 'recover');
    check($coveredRecovery['finances']['payablesCents'] === 15000, 'Roadside coverage must reduce the recovery payable to G$150.');

    echo "PASS domain: loadouts, hourly clock, live routes, garage gating, inspections, recovery, resale, debt, dream" . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL domain: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
