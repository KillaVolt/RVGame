<?php
declare(strict_types=1);

require dirname(__DIR__) . '/api/Game.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rawAct(Game $game, array $state, string $type, array $payload = []): array
{
    return $game->apply($state, $type, $payload)[0];
}

function act(Game $game, array $state, string $type, array $payload = []): array
{
    try {
        $state = rawAct($game, $state, $type, $payload);
    } catch (GameRuleException $error) {
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['line'] ?? 0;
        throw new RuntimeException('Unexpected ' . $type . ' at test line ' . $caller . ' approach ' . ($state['player']['approachId'] ?? '?') . ' ' . json_encode($payload, JSON_THROW_ON_ERROR) . ' rejection: ' . $error->getMessage(), 0, $error);
    }
    while ($state['pendingEvent'] !== null) {
        $event = $game->publicView($state)['pendingEvent'];
        $choices = array_values(array_filter($event['choices'], fn(array $choice): bool => $choice['available']));
        usort($choices, fn(array $left, array $right): int => [$left['cashCostCents'], $left['hours']] <=> [$right['cashCostCents'], $right['hours']]);
        $state = rawAct($game, $state, 'resolve-event', ['eventInstanceId' => $event['instanceId'], 'choiceId' => $choices[0]['id']]);
    }
    return $state;
}

function earn(Game $game, array $state): array
{
    if (!$game->publicView($state)['work']['available']) $state = act($game, $state, 'rest');
    return act($game, $state, 'work');
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

function listingByArchetype(array $state, string $archetypeId): ?array
{
    foreach ($state['listings'] as $listing) {
        if ($listing['camper']['archetypeId'] === $archetypeId) return $listing;
    }
    return null;
}

function ruleRejected(Game $game, array $state, string $type, array $payload = []): bool
{
    try {
        rawAct($game, $state, $type, $payload);
    } catch (GameRuleException) {
        return true;
    }
    return false;
}

function travelTo(Game $game, array $state, string $destination, string $mode): array
{
    $view = $game->publicView($state);
    $start = $view['player']['locationId'];
    if ($start === $destination) return $state;
    $queue = [[$start, []]];
    $seen = [$start => true];
    while ($queue !== []) {
        [$at, $path] = array_shift($queue);
        foreach ($view['mapRoutes'] as $route) {
            if (!in_array($at, [$route['a'], $route['b']], true)) continue;
            $next = $route['a'] === $at ? $route['b'] : $route['a'];
            if (isset($seen[$next])) continue;
            $nextPath = [...$path, $route['id']];
            if ($next === $destination) {
                foreach ($nextPath as $routeId) $state = act($game, $state, 'travel', ['routeId' => $routeId, 'mode' => $mode]);
                return $state;
            }
            $seen[$next] = true;
            $queue[] = [$next, $nextPath];
        }
    }
    throw new RuntimeException('No path to ' . $destination);
}

try {
    $game = new Game(dirname(__DIR__) . '/content/simple-mode.json');

    $options = $game->startOptions();
    check(count($options) === 2, 'Simple mode must offer two clear starting approaches.');
    $firstTimer = $game->initialState('first-timer');
    check($firstTimer['player']['tools'] === [], 'First-time owner must not receive repair tools.');
    $oldSave = $firstTimer;
    $oldSave['schemaVersion'] = 2;
    try {
        $game->publicView($oldSave);
        throw new RuntimeException('An old save must require an explicit reset.');
    } catch (GameRuleException $error) {
        check($error->ruleCode === 'SAVE_VERSION_UNSUPPORTED', 'Old-save rejection must remain diagnosable.');
    }
    check(in_array('roadside-coverage', $firstTimer['player']['perks'], true), 'First-time owner roadside coverage missing.');
    check($game->publicView($firstTimer)['inspection']['selfAvailable'] === false, 'First-time owner must not be offered technical self-checks.');
    $selfCheckBlocked = false;
    try {
        rawAct($game, $firstTimer, 'inspect', ['systemId' => 'shell']);
    } catch (GameRuleException) {
        $selfCheckBlocked = true;
    }
    check($selfCheckBlocked, 'First-time owner self-check must be rejected by domain rules.');
    $firstTimerTechCost = $game->publicView($firstTimer)['inspection']['technicianCostCents'];
    $firstTimerTech = act($game, $firstTimer, 'technician-inspect');
    check($firstTimerTech['camper']['projectExpenses'][1] === ['type' => 'inspection', 'amountCents' => $firstTimerTechCost], 'First-time owner technician inspection must record the displayed local fee independently of event income.');

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
    check(count($public['mapRoutes']) === 8 && $public['mapRoutes'][0]['reachable'] === true, 'Expanded live map route graph mismatch.');
    check($public['atGarageWithCamper'] === false && $public['canAdvertise'] === false, 'Garage actions must stay unavailable before the camper is home.');
    check($public['camper']['weight']['currentKg'] === 1970 && $public['camper']['weight']['remainingPayloadKg'] === 530, 'Initial weight must derive from dry mass, tank litres and loose load.');
    check($public['towVehicle']['maxTrailerWeightKg'] === 2800, 'Bluebird tow limit missing.');
    check(count($public['listings']) === 6 && count(array_unique(array_column($public['listings'], 'locationId'))) === 6, 'Every town must begin with one persistent project listing.');
    check(count(array_unique(array_column($public['listings'], 'archetypeId'))) >= 4, 'Initial settled market must expose the expanded project variety.');

    $unitOnly = rawAct($game, $state, 'set-unit', ['unit' => 'mi']);
    check($game->publicView($unitOnly)['camper']['weight']['currentKg'] === 1970, 'Unit display change must not mutate canonical weight.');
    $drained = act($game, $state, 'set-tank', ['tankId' => 'fresh', 'targetLitres' => 0]);
    check($game->publicView($drained)['camper']['weight']['currentKg'] === 1880, 'Draining 90 litres must remove exactly 90 kg.');
    $filled = act($game, $drained, 'set-tank', ['tankId' => 'fresh', 'targetLitres' => 140]);
    check($game->publicView($filled)['camper']['weight']['currentKg'] === 2020, 'Fresh fill must restore exact litre weight.');
    check($filled['finances']['cashCents'] === $drained['finances']['cashCents'] - 2800, 'Fresh fill must charge the authored per-litre cost.');

    $overweight = $state;
    $overweight['towVehicle']['maxTrailerWeightKg'] = 1960;
    check(ruleRejected($game, $overweight, 'travel', ['routeId' => 'sunset-pine', 'mode' => 'tow']), 'Overweight tow must be rejected.');
    $overweight = rawAct($game, $overweight, 'set-loose-load', ['targetKg' => 50]);
    check($game->publicView($overweight)['camper']['weight']['currentKg'] === 1945, 'Loose-load reduction must change derived tow weight.');
    $overweight = rawAct($game, $overweight, 'travel', ['routeId' => 'sunset-pine', 'mode' => 'tow']);
    check($overweight['player']['locationId'] === 'pine-lake', 'Reducing load below the tow limit must restore travel.');

    $intel = $game->initialState('hands-on');
    $initialListingIds = array_column($intel['listings'], 'id');
    $intelCost = $game->publicView($intel)['market']['local']['intel']['costCents'];
    $intel = rawAct($game, $intel, 'buy-intel');
    check($intel['finances']['cashCents'] === 150000 - $intelCost && $intel['intelReports'][0]['forecastDay'] === 2, 'Paid intel must charge once and reveal the scheduled next-day fact.');
    check(ruleRejected($game, $intel, 'buy-intel'), 'The same location/day forecast must not be sold twice.');
    check(array_column($intel['listings'], 'id') === $initialListingIds, 'Information purchase must not reroll persistent listings.');
    $intel = act($game, $intel, 'work');
    $intel = act($game, $intel, 'rest');
    check(count($intel['marketHistory']) === 2, 'Crossing one midnight must append one market snapshot.');
    check(array_column($intel['listings'], 'id') === $initialListingIds, 'Listings must remain stable before their settled expiry.');
    $listingCycle = $game->initialState('hands-on');
    $oldListingIds = array_column($listingCycle['listings'], 'id');
    while ($listingCycle['day'] < 6) $listingCycle = earn($game, $listingCycle);
    check(count($listingCycle['marketHistory']) === 4, 'Market history must retain current plus three previous midnight snapshots.');
    check(array_column($listingCycle['listings'], 'id') !== $oldListingIds, 'Expired listings must be replaced only by settled market refresh.');
    check(count(array_unique(array_column($listingCycle['listings'], 'locationId'))) === 6, 'Replenishment must retain one persistent listing per town.');

    $regional = travelTo($game, $game->initialState('hands-on'), 'cedar-ridge', 'tow');
    check(str_contains($game->publicView($regional)['inspection']['technicianReason'], 'No technician'), 'Cedar Ridge must enforce its unavailable technician market.');
    check($game->publicView($state)['market']['local']['demand'] !== $game->publicView($regional)['market']['local']['demand'], 'Locations must expose materially different demand.');

    $upgradeWeight = act($game, $game->initialState('hands-on'), 'recover');
    $upgradeWeight = act($game, $upgradeWeight, 'inspect', ['systemId' => 'interior']);
    $beforeUpgradeKg = $game->publicView($upgradeWeight)['camper']['weight']['currentKg'];
    $upgradeWeight = act($game, $upgradeWeight, 'upgrade', ['upgradeId' => 'cozy-nook']);
    check($game->publicView($upgradeWeight)['camper']['weight']['currentKg'] === $beforeUpgradeKg + 45, 'Upgrade must add its authored value, appeal and 45 kg weight.');

    $decision = $game->initialState('hands-on');
    $decision['pendingEvent'] = ['instanceId' => 'event-test', 'eventId' => 'rough-road-rattle', 'day' => 1, 'hour' => 9];
    check(ruleRejected($game, $decision, 'work'), 'A pending decision must block another action.');
    $decision = rawAct($game, $decision, 'resolve-event', ['eventInstanceId' => 'event-test', 'choiceId' => 'slow-check']);
    check($decision['pendingEvent'] === null && $decision['hour'] === 11, 'Event choice must persist once and advance only its disclosed time.');
    check($decision['camper']['defects'][0]['known'] === true, 'Condition-linked event choice must reveal its declared evidence.');
    check(ruleRejected($game, $decision, 'resolve-event', ['eventInstanceId' => 'event-test', 'choiceId' => 'slow-check']), 'Resolved event must not apply twice.');

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
    $offerView = $game->publicView($state)['offers'][0];
    check(count($offerView['rationale']) >= 3 && $offerView['handoverLocationName'] === 'Maple Junction', 'Buyer offer must retain demand, condition, profile rationale and handover.');
    $starterOffer = $state['offers'][0];
    $state = act($game, $state, 'sell', ['offerId' => $starterOffer['id']]);
    check($state['camper'] === null && $state['saleCount'] === 1, 'Starter sale must transfer ownership once.');
    check(count($state['listings']) >= 1, 'No-camper state must offer another project.');

    $localListing = array_values(array_filter($state['listings'], fn(array $listing): bool => $listing['locationId'] === 'maple-junction'))[0];
    while ($state['finances']['cashCents'] < $localListing['askCents']) {
        $state = earn($game, $state);
    }
    $state = act($game, $state, 'buy', ['listingId' => $localListing['id']]);
    $state = act($game, $state, 'advertise', ['strategy' => 'market']);
    $state = act($game, $state, 'wait');
    $state = act($game, $state, 'sell', ['offerId' => $state['offers'][0]['id']]);
    check($state['saleCount'] === 2, 'Second project must complete the repeatable resale loop.');
    while (listingByArchetype($state, 'sunbeam-23') === null) $state = earn($game, $state);
    check(listingByArchetype($state, 'sunbeam-23') !== null, 'Dream camper must enter the settled market after two sales.');

    do {
        $totalDebt = array_sum([
            $state['finances']['loanPrincipalCents'],
            $state['finances']['accruedInterestCents'],
            $state['finances']['payablesCents'],
        ]);
        if ($state['finances']['cashCents'] >= $totalDebt + 700000) {
            break;
        }
        $state = earn($game, $state);
    } while (true);
    $totalDebt = array_sum([
        $state['finances']['loanPrincipalCents'],
        $state['finances']['accruedInterestCents'],
        $state['finances']['payablesCents'],
    ]);
    $state = act($game, $state, 'repay', ['amountCents' => $totalDebt]);
    $dreamListing = listingByArchetype($state, 'sunbeam-23');
    $state = travelTo($game, $state, $dreamListing['locationId'], 'solo');
    $state = act($game, $state, 'buy', ['listingId' => $dreamListing['id']]);
    $state = travelTo($game, $state, 'maple-junction', 'tow');
    foreach (['running-gear', 'shell', 'plumbing'] as $systemId) {
        while ($state['finances']['cashCents'] < 6000) {
            $state = earn($game, $state);
        }
        $state = act($game, $state, 'inspect', ['systemId' => $systemId]);
    }
    $state = act($game, $state, 'repair', ['repairId' => 'plumbing-service', 'method' => 'diy']);
    while ($state['finances']['cashCents'] < 50000) {
        $state = earn($game, $state);
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
