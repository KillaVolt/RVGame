<?php
declare(strict_types=1);

require __DIR__ . '/domain.php';

function rejected(Game $game, array $state, string $type, array $payload = []): void
{
    try {
        $game->apply($state, $type, $payload);
    } catch (GameRuleException) {
        return;
    }
    throw new RuntimeException('Expected rejection: ' . $type);
}

$game = new Game(dirname(__DIR__) . '/content/simple-mode.json');
$start = $game->initialState('hands-on');
foreach ([0, -1, 99999999, 1.5, '100'] as $amount) rejected($game, $start, 'repay', ['amountCents' => $amount]);
$penny = act($game, $start, 'repay', ['amountCents' => 1]);
check($penny['finances']['cashCents'] === 149999 && $penny['finances']['loanPrincipalCents'] === 199999, 'Exact cent payment failed.');

$worked = act($game, $start, 'work');
check(!$game->publicView($worked)['work']['available'], 'Consecutive same-day jobs must close.');
rejected($game, $worked, 'work');
$rested = act($game, $worked, 'rest');
check($rested['day'] === 2 && $rested['hour'] === 8, 'Rest must reach next job opening.');
check(str_contains($worked['eventLog'][1]['text'], 'Sunset Shores park host'), 'Work must explain employer/task.');
check($game->publicView($rested)['work']['available'], 'Rest must retain income recovery path.');

$broken = act($game, $start, 'travel', ['routeId' => 'sunset-pine', 'mode' => 'tow']);
$broken = act($game, $broken, 'travel', ['routeId' => 'pine-cedar', 'mode' => 'tow']);
check(str_contains(implode(' ', array_column($broken['eventLog'], 'text')), 'Tire failure'), 'Breakdown must be explicit in durable result.');
rejected($game, $broken, 'travel', ['routeId' => 'cedar-maple', 'mode' => 'tow']);
$away = act($game, $broken, 'travel', ['routeId' => 'cedar-maple', 'mode' => 'solo']);
$awayView = $game->publicView($away);
check(str_contains($awayView['inspection']['technicianReason'], 'Cedar Ridge'), 'Remote technician must explain camper location.');
check(str_contains($awayView['camper']['hud'][0]['selfReason'], 'Cedar Ridge'), 'Remote self-check must be disabled for same reason.');
rejected($game, $away, 'technician-inspect');
rejected($game, $away, 'inspect', ['systemId' => 'running-gear']);

$inspected = act($game, $start, 'technician-inspect');
check($game->publicView($inspected)['inspection']['technicianReason'] === 'Full inspection complete.', 'Repeated technician state is wrong.');
rejected($game, $inspected, 'technician-inspect');
$repaired = act($game, $inspected, 'repair', ['repairId' => 'seal-refresh', 'method' => 'diy']);
check(str_contains($game->publicView($repaired)['camper']['hud'][1]['evidence'], 'repaired'), 'Repair evidence must supersede inspection wording.');

$market = act($game, $start, 'recover');
$market = act($game, $market, 'advertise');
check(isset($market['ad']['askCents']), 'Advert must save its asking price.');
rejected($game, $market, 'advertise');
$market = act($game, $market, 'wait');
$originalOffer = $market['offers'][0];
check(isset($originalOffer['expiresAtHour']), 'New offers need durable expiry.');
rejected($game, $market, 'wait');
$roundTrip = json_decode(json_encode($market, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
check($game->publicView($roundTrip)['offers'] === $game->publicView($market)['offers'], 'Read/reload must not reroll offers.');
$market = act($game, $market, 'technician-inspect');
$market = act($game, $market, 'repair', ['repairId' => 'preventive-tire-service', 'method' => 'technician']);
check($market['offers'][0]['id'] === $originalOffer['id'], 'Repairs must not silently withdraw a buyer.');
$market = act($game, $market, 'relist');
check($market['offers'] === [], 'Explicit relist must withdraw the old offer.');
$market = act($game, $market, 'wait');
check($market['offers'][0]['id'] !== $originalOffer['id'], 'Relist must lead to a fresh offer.');
check($market['offers'][0]['amountCents'] > $originalOffer['amountCents'], 'Fixing a major towing issue should improve this test valuation.');
$market = act($game, $market, 'decline-offer', ['offerId' => $market['offers'][0]['id']]);
check($game->publicView($market)['canWait'], 'Declining must provide a next-buyer path.');
$market = act($game, $market, 'wait');
$expired = $market;
$expiry = $expired['offers'][0]['expiresAtHour'];
$expired['day'] = intdiv($expiry, 24) + 1;
$expired['hour'] = $expiry % 24;
check($game->publicView($expired)['offers'][0]['expired'], 'Expiry boundary must be visible.');
rejected($game, $expired, 'sell', ['offerId' => $expired['offers'][0]['id']]);
$renewed = act($game, $expired, 'wait');
check($renewed['offers'][0]['id'] !== $expired['offers'][0]['id'], 'Expired offers must renew through time, not reload.');

// Missing optional offer terms must stay visibly absent rather than being fabricated on read.
$earlier = $market;
unset($earlier['offers'][0]['expiresAtHour'], $earlier['ad']['askCents']);
check($game->publicView($earlier)['offers'][0]['expiresAtHour'] === null, 'Earlier offer must explicitly report absent expiry.');
check(!isset($game->publicView($earlier)['ad']['askCents']), 'Earlier ad must not fabricate an asking price.');
act($game, $earlier, 'relist');

$broke = $start;
$broke['finances']['cashCents'] = 922;
$broke['hour'] = 23;
$broke = act($game, $broke, 'inspect', ['systemId' => 'shell']);
check(str_contains(implode(' ', array_column($broke['eventLog'], 'text')), 'became a bill'), 'Midnight shortfall must explain new debt.');

$long = $start;
for ($i = 0; $i < 90; $i++) $long = act($game, $long, 'set-unit', ['unit' => $i % 2 ? 'km' : 'mi']);
check(count($long['eventLog']) === 91, 'History must not silently discard old transactions.');
while ($long['day'] < 61) $long = earn($game, $long);
check($long['status'] === 'active', 'The open-ended journey must remain playable after Day 60.');

$sold = act($game, $market, 'sell', ['offerId' => $market['offers'][0]['id']]);
check(isset($game->publicView($sold)['soldCampers'][0]['expenses']), 'New sales must retain expense detail.');
$listing = array_values(array_filter($game->publicView($sold)['listings'], fn(array $item): bool => $item['locationId'] === $sold['player']['locationId']))[0];
$bought = act($game, $sold, 'buy', ['listingId' => $listing['id']]);
check($game->publicView($bought)['camper']['instanceId'] === $listing['instanceId'], 'Vehicle reference must survive purchase.');
$beforeNoCamper = count($sold['eventLog']);
for ($i = 0; $i < 20; $i++) $sold = earn($game, $sold);
check(!str_contains(implode(' ', array_column(array_slice($sold['eventLog'], $beforeNoCamper), 'text')), 'Rain taps the camper roof'), 'Camper events must not fire without a camper.');

echo "PASS playtest regressions: exact payments, daytime jobs, breakdown feedback, inspection gates, offers/relisting/expiry, saved terms, bills, history and Day 61\n";
