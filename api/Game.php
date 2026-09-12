<?php
declare(strict_types=1);

final class GameRuleException extends RuntimeException
{
    public function __construct(public readonly string $ruleCode, string $message)
    {
        parent::__construct($message);
    }
}

final class Game
{
    private const BUILD_ID = 'rvgame-0.4.1';
    private const SCHEMA_VERSION = 2;
    private array $content;

    public function __construct(string $contentPath)
    {
        $raw = file_get_contents($contentPath);
        if ($raw === false) {
            throw new RuntimeException('Canonical game content could not be read.');
        }
        $content = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        foreach (['contentVersion', 'rulesetId', 'start', 'inspection', 'daily', 'recovery', 'work', 'market', 'locations', 'routes', 'systems', 'repairs', 'camperTemplates'] as $required) {
            if (!array_key_exists($required, $content)) {
                throw new RuntimeException('Canonical content is missing ' . $required . '.');
            }
        }
        $this->content = $content;
    }

    public function startOptions(): array
    {
        return array_map(fn(array $loadout): array => array_intersect_key($loadout, array_flip([
            'id', 'name', 'tagline', 'description', 'equipment', 'benefit',
        ])), $this->content['start']['loadouts']);
    }

    public function initialState(string $loadoutId): array
    {
        $start = $this->content['start'];
        $loadout = $this->find($start['loadouts'], $loadoutId);
        $template = $this->find($this->content['camperTemplates'], $start['starterCamperId']);
        $camper = $this->makeCamper($template, $start['locationId'], $start['starterPurchaseCents']);
        foreach ($start['briefCheckedSystems'] as $systemId) {
            $camper['inspectionLevels'][$systemId] = 'brief';
        }

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'rulesetId' => $this->content['rulesetId'],
            'contentVersion' => $this->content['contentVersion'],
            'campaignId' => $this->uuid(),
            'revision' => 0,
            'status' => 'active',
            'seed' => bin2hex(random_bytes(12)),
            'day' => $start['day'],
            'hour' => $start['hour'],
            'player' => [
                'locationId' => $start['locationId'],
                'distanceUnit' => 'km',
                'approachId' => $loadout['id'],
                'approachName' => $loadout['name'],
                'tools' => $loadout['tools'],
                'perks' => $loadout['perks'],
            ],
            'towVehicle' => [
                'name' => $start['towVehicleName'],
                'locationId' => $start['locationId'],
                'odometerKm' => $start['towVehicleOdometerKm'],
            ],
            'finances' => [
                'cashCents' => $start['cashCents'],
                'loanPrincipalCents' => $start['loanPrincipalCents'],
                'accruedInterestCents' => 0,
                'payablesCents' => 0,
            ],
            'camper' => $camper,
            'listings' => [],
            'offers' => [],
            'ad' => null,
            'soldCampers' => [],
            'saleCount' => 0,
            'randomOrdinal' => 0,
            'eventLog' => [[
                'day' => 1,
                'hour' => $start['hour'],
                'text' => 'You paid, the seller left, and your brief roadworthy check found nothing obvious. Maple Junction is three stops away.',
                'tone' => 'story',
            ]],
        ];
    }

    public function apply(array $state, string $type, array $payload): array
    {
        $this->requireCurrentSchema($state);
        if ($state['status'] !== 'active' && $type !== 'set-unit') {
            throw new GameRuleException('CAMPAIGN_COMPLETE', 'This campaign is already complete.');
        }

        $startClock = $this->clock($state);
        $hours = 0;
        $summary = '';

        switch ($type) {
            case 'travel':
                [$summary, $hours] = $this->travel($state, $payload);
                break;
            case 'inspect':
                [$summary, $hours] = $this->inspect($state, $payload);
                break;
            case 'technician-inspect':
                [$summary, $hours] = $this->technicianInspect($state);
                break;
            case 'repair':
                [$summary, $hours] = $this->repair($state, $payload);
                break;
            case 'upgrade':
                [$summary, $hours] = $this->upgrade($state, $payload);
                break;
            case 'advertise':
                $this->requireGarage($state);
                $state['ad'] = ['strategy' => (string) ($payload['strategy'] ?? 'market'), 'day' => $state['day'], 'hour' => $state['hour']];
                $summary = 'The camper is advertised from the garage. Offers arrive as time advances.';
                break;
            case 'wait':
                if ($state['camper'] === null || $state['ad'] === null) {
                    throw new GameRuleException('NO_ACTIVE_AD', 'Advertise a camper before waiting for offers.');
                }
                $summary = 'You waited for buyers.';
                $hours = (int) $this->content['market']['waitHours'];
                break;
            case 'sell':
                [$summary, $hours] = $this->sell($state, $payload);
                break;
            case 'buy':
                $summary = $this->buy($state, $payload);
                break;
            case 'work':
                $gross = (int) $this->content['work']['grossCents'];
                $state['finances']['cashCents'] += $gross;
                $summary = 'Work shift paid ' . $this->money($gross) . '.';
                $hours = (int) $this->content['work']['hours'];
                break;
            case 'recover':
                [$summary, $hours] = $this->recover($state);
                break;
            case 'repay':
                $summary = $this->repay($state, (int) ($payload['amountCents'] ?? 0));
                break;
            case 'set-unit':
                $unit = $payload['unit'] ?? '';
                if (!in_array($unit, ['km', 'mi'], true)) {
                    throw new GameRuleException('INVALID_UNIT', 'Distance unit must be km or mi.');
                }
                $state['player']['distanceUnit'] = $unit;
                $summary = 'Distance display changed to ' . $unit . '. Canonical kilometers were unchanged.';
                break;
            case 'complete':
                if (!$this->canComplete($state)) {
                    throw new GameRuleException('GOAL_NOT_READY', 'The dream camper conditions are not complete yet.');
                }
                $state['status'] = 'completed';
                $summary = 'You made the Sunbeam home. The campaign is complete.';
                break;
            default:
                throw new GameRuleException('UNKNOWN_COMMAND', 'Unknown command type.');
        }

        if ($hours > 0) {
            $extras = $this->advanceTime($state, (int) $hours, $type);
            $summary = 'Time advanced: ' . $startClock . ' to ' . $this->clock($state) . '. ' . $summary;
            if ($extras !== []) {
                $summary .= ' ' . implode(' ', $extras);
            }
        }

        $state['revision']++;
        $state['eventLog'][] = ['day' => $state['day'], 'hour' => $state['hour'], 'text' => $summary, 'tone' => $type];
        $state['eventLog'] = array_slice($state['eventLog'], -80);
        $this->assertInvariants($state);

        return [$state, ['summary' => $summary]];
    }

    public function publicView(array $state): array
    {
        $this->requireCurrentSchema($state);
        $camper = $state['camper'] === null ? null : $this->publicCamper($state['camper']);
        $cash = (int) $state['finances']['cashCents'];
        $totalDebt = (int) $state['finances']['loanPrincipalCents']
            + (int) $state['finances']['accruedInterestCents']
            + (int) $state['finances']['payablesCents'];

        $routes = [];
        foreach ($this->content['routes'] as $route) {
            if (!in_array($state['player']['locationId'], [$route['a'], $route['b']], true)) {
                continue;
            }
            $destinationId = $route['a'] === $state['player']['locationId'] ? $route['b'] : $route['a'];
            $towReason = $this->towReason($state, $route);
            $routes[] = [
                'id' => $route['id'],
                'destinationId' => $destinationId,
                'destinationName' => $this->location($destinationId)['name'],
                'distanceKm' => $route['distanceKm'],
                'towCostCents' => $route['towCostCents'],
                'soloCostCents' => $route['soloCostCents'],
                'canTow' => $towReason === null,
                'canSolo' => $cash >= $route['soloCostCents'],
                'towReason' => $towReason,
                'towHours' => $route['towHours'],
                'soloHours' => $route['soloHours'],
            ];
        }

        $listings = [];
        foreach ($state['listings'] as $listing) {
            $template = $this->template($listing['camper']['archetypeId']);
            $known = [];
            foreach ($listing['camper']['defects'] as $defect) {
                if ($defect['known'] && !$defect['resolved']) {
                    $known[] = $defect['severity'] . ' ' . $defect['systemId'] . ' issue';
                }
            }
            $reason = null;
            if ($state['camper'] !== null) {
                $reason = 'Sell the current project first.';
            } elseif ($state['player']['locationId'] !== $listing['locationId']) {
                $reason = 'Travel to ' . $this->location($listing['locationId'])['name'] . ' first.';
            } elseif ($cash < $listing['askCents']) {
                $reason = 'Not enough cash.';
            }
            $listings[] = [
                'id' => $listing['id'],
                'name' => $template['name'],
                'archetypeId' => $template['id'],
                'locationId' => $listing['locationId'],
                'locationName' => $this->location($listing['locationId'])['name'],
                'askCents' => $listing['askCents'],
                'dream' => $template['dream'],
                'knownIssues' => $known,
                'canBuy' => $reason === null,
                'buyReason' => $reason,
            ];
        }

        $offers = [];
        foreach ($state['offers'] as $offer) {
            $offers[] = [
                'id' => $offer['id'],
                'buyerName' => $offer['buyerName'],
                'amountCents' => $offer['amountCents'],
                'projectProfitCents' => $offer['amountCents'] - $this->projectCost($state['camper']),
            ];
        }

        return [
            'buildId' => self::BUILD_ID,
            'contentVersion' => $this->content['contentVersion'],
            'campaignId' => $state['campaignId'],
            'revision' => $state['revision'],
            'status' => $state['status'],
            'day' => $state['day'],
            'hour' => $state['hour'],
            'player' => [
                'locationId' => $state['player']['locationId'],
                'locationName' => $this->location($state['player']['locationId'])['name'],
                'distanceUnit' => $state['player']['distanceUnit'],
                'approachId' => $state['player']['approachId'],
                'approachName' => $state['player']['approachName'],
                'tools' => $state['player']['tools'],
                'perks' => $state['player']['perks'],
            ],
            'inspection' => $this->content['inspection'] + [
                'selfAvailable' => in_array('basic-toolkit', $state['player']['tools'], true),
                'selfReason' => in_array('basic-toolkit', $state['player']['tools'], true)
                    ? null
                    : 'First-time owners need a technician for technical system checks.',
            ],
            'finances' => $state['finances'] + ['totalDebtCents' => $totalDebt],
            'towVehicle' => $state['towVehicle'],
            'objective' => $this->objective($state),
            'camper' => $camper,
            'routes' => $routes,
            'listings' => $listings,
            'offers' => $offers,
            'repairs' => $this->repairViews($state),
            'upgrades' => $this->upgradeViews($state),
            'mapLocations' => array_map(fn(array $location): array => [
                'id' => $location['id'],
                'name' => $location['name'],
                'x' => $location['x'],
                'y' => $location['y'],
                'current' => $location['id'] === $state['player']['locationId'],
                'camper' => $state['camper'] !== null && $location['id'] === $state['camper']['locationId'],
            ], $this->content['locations']),
            'mapRoutes' => array_map(fn(array $route): array => [
                'id' => $route['id'],
                'a' => $route['a'],
                'b' => $route['b'],
                'distanceKm' => $route['distanceKm'],
                'reachable' => in_array($state['player']['locationId'], [$route['a'], $route['b']], true),
            ], $this->content['routes']),
            'recoveryPayableCents' => in_array('roadside-coverage', $state['player']['perks'], true)
                ? $this->content['recovery']['roadsidePayableCents']
                : $this->content['recovery']['payableCents'],
            'recoveryHours' => $this->content['recovery']['hours'],
            'work' => $this->content['work'],
            'market' => $this->content['market'],
            'atGarageWithCamper' => $state['camper'] !== null
                && $state['player']['locationId'] === $this->content['start']['homeLocationId']
                && $state['camper']['locationId'] === $state['player']['locationId'],
            'canAdvertise' => $state['camper'] !== null
                && $state['camper']['locationId'] === $state['player']['locationId']
                && $state['player']['locationId'] === $this->content['start']['homeLocationId'],
            'canWait' => $state['camper'] !== null && $state['ad'] !== null,
            'canRecover' => $state['camper'] !== null
                && $state['camper']['locationId'] !== $this->content['start']['homeLocationId'],
            'canComplete' => $this->canComplete($state),
            'saleCount' => $state['saleCount'],
            'eventLog' => $state['eventLog'],
        ];
    }

    private function travel(array &$state, array $payload): array
    {
        $route = $this->find($this->content['routes'], (string) ($payload['routeId'] ?? ''));
        $mode = $payload['mode'] ?? '';
        if (!in_array($state['player']['locationId'], [$route['a'], $route['b']], true)) {
            throw new GameRuleException('ROUTE_NOT_CONNECTED', 'That route is not connected to the current location.');
        }
        if (!in_array($mode, ['tow', 'solo'], true)) {
            throw new GameRuleException('INVALID_TRAVEL_MODE', 'Travel mode must be tow or solo.');
        }

        $destination = $route['a'] === $state['player']['locationId'] ? $route['b'] : $route['a'];
        $cost = (int) $route[$mode === 'tow' ? 'towCostCents' : 'soloCostCents'];
        $this->requireCash($state, $cost);

        if ($mode === 'tow') {
            $reason = $this->towReason($state, $route);
            if ($reason !== null) {
                throw new GameRuleException('TOWING_BLOCKED', $reason);
            }
            $state['camper']['locationId'] = $destination;
            $state['camper']['towedKm'] += (int) $route['distanceKm'];
            $state['camper']['projectExpenses'][] = ['type' => 'travel', 'amountCents' => $cost];
        }

        $state['finances']['cashCents'] -= $cost;
        $state['player']['locationId'] = $destination;
        $state['towVehicle']['locationId'] = $destination;
        $state['towVehicle']['odometerKm'] += (int) $route['distanceKm'];

        if ($mode === 'tow') {
            $this->applyDistanceTriggers($state['camper']);
        }

        return [
            ucfirst($mode) . ' travel reached ' . $this->location($destination)['name']
                . ' over ' . $route['distanceKm'] . ' km for ' . $this->money($cost) . '.',
            (int) $route[$mode === 'tow' ? 'towHours' : 'soloHours'],
        ];
    }

    private function inspect(array &$state, array $payload): array
    {
        $this->requireCamperTogether($state);
        if (!in_array('basic-toolkit', $state['player']['tools'], true)) {
            throw new GameRuleException('TECHNICIAN_REQUIRED', 'Your experience level requires a technician for technical system checks.');
        }
        $systemId = (string) ($payload['systemId'] ?? '');
        $this->find($this->content['systems'], $systemId);
        $level = $state['camper']['inspectionLevels'][$systemId] ?? null;
        $revealed = 0;
        foreach ($state['camper']['defects'] as &$defect) {
            if ($defect['systemId'] === $systemId && !$defect['known'] && $defect['inspectionTier'] === 'self') {
                $defect['known'] = true;
                $revealed++;
            }
        }
        unset($defect);
        if (in_array($level, ['self', 'technician'], true) && $revealed === 0) {
            throw new GameRuleException('NOTHING_NEW', 'You already inspected that system as thoroughly as you can.');
        }
        if ($level !== 'technician') {
            $state['camper']['inspectionLevels'][$systemId] = 'self';
        }
        return ['Your self-inspection cost no fee and revealed ' . $revealed . ' issue(s) in ' . $systemId . '.', (int) $this->content['inspection']['selfHours']];
    }

    private function technicianInspect(array &$state): array
    {
        $this->requireCamperTogether($state);
        $cost = (int) $this->content['inspection']['technicianCostCents'];
        $this->requireCash($state, $cost);
        $revealed = 0;
        foreach ($state['camper']['defects'] as &$defect) {
            if (!$defect['known']) {
                $defect['known'] = true;
                $revealed++;
            }
        }
        unset($defect);
        $alreadyComplete = true;
        foreach ($this->content['systems'] as $system) {
            if (($state['camper']['inspectionLevels'][$system['id']] ?? null) !== 'technician') {
                $alreadyComplete = false;
            }
            $state['camper']['inspectionLevels'][$system['id']] = 'technician';
        }
        if ($alreadyComplete && $revealed === 0) {
            throw new GameRuleException('NOTHING_NEW', 'The technician has already completed this inspection.');
        }
        $state['finances']['cashCents'] -= $cost;
        $state['camper']['projectExpenses'][] = ['type' => 'inspection', 'amountCents' => $cost];
        return ['The technician inspected the full camper for ' . $this->money($cost) . ' and revealed ' . $revealed . ' issue(s).', (int) $this->content['inspection']['technicianHours']];
    }

    private function repair(array &$state, array $payload): array
    {
        $this->requireCamperTogether($state);
        $repair = $this->find($this->content['repairs'], (string) ($payload['repairId'] ?? ''));
        $method = (string) ($payload['method'] ?? '');
        if (!in_array($method, ['diy', 'technician'], true)) {
            throw new GameRuleException('INVALID_REPAIR_METHOD', 'Repair method must be diy or technician.');
        }
        $location = $this->location($state['player']['locationId']);
        if ($method === 'technician' && !$repair['serviceAnywhere'] && !$location['service']) {
            throw new GameRuleException('SERVICE_UNAVAILABLE', 'That repair requires the garage or a service location.');
        }
        if ($method === 'diy' && !$repair['diyAllowed']) {
            throw new GameRuleException('TECHNICIAN_REQUIRED', 'That repair is technician-only.');
        }
        if ($method === 'diy' && !in_array('basic-toolkit', $state['player']['tools'], true)) {
            throw new GameRuleException('TOOLS_REQUIRED', 'The basic toolkit is required for that DIY repair.');
        }
        $target = null;
        foreach ($state['camper']['defects'] as $index => $defect) {
            if ($defect['systemId'] === $repair['systemId'] && $defect['known'] && !$defect['resolved']) {
                if ($repair['id'] === 'roadside-tire-service' && !$defect['blocking']) {
                    continue;
                }
                $target = $index;
                break;
            }
        }
        if ($target === null) {
            throw new GameRuleException('NO_MATCHING_DEFECT', 'No known unresolved issue matches that repair.');
        }
        $cost = $method === 'technician' ? (int) $repair['technicianCostCents'] : 0;
        $hours = $method === 'technician' ? (int) $repair['technicianHours'] : (int) $repair['diyHours'];
        $this->requireCash($state, $cost);
        $state['finances']['cashCents'] -= $cost;
        $state['camper']['defects'][$target]['resolved'] = true;
        $state['camper']['defects'][$target]['blocking'] = false;
        if ($method === 'technician' || ($state['camper']['inspectionLevels'][$repair['systemId']] ?? null) !== 'technician') {
            $state['camper']['inspectionLevels'][$repair['systemId']] = $method;
        }
        $state['camper']['repairs'][] = $repair['id'];
        if ($cost > 0) {
            $state['camper']['projectExpenses'][] = ['type' => 'repair', 'amountCents' => $cost];
        }
        $price = $method === 'diy' ? 'no direct fee' : $this->money($cost);
        return [$repair['label'] . ' was completed by ' . $method . ' in ' . $hours . ' hour(s) for ' . $price . '.', $hours];
    }

    private function upgrade(array &$state, array $payload): array
    {
        $this->requireGarage($state);
        $upgrade = $this->find($this->content['upgrades'], (string) ($payload['upgradeId'] ?? ''));
        if (in_array($upgrade['id'], $state['camper']['upgrades'], true)) {
            throw new GameRuleException('ALREADY_INSTALLED', 'That upgrade is already installed.');
        }
        if ($this->hudState($state['camper'], $upgrade['requiresSystemId']) !== 'LOOKS_OK') {
            throw new GameRuleException('SYSTEM_NOT_READY', 'Inspect and resolve the required system first.');
        }
        $cost = (int) $upgrade['costCents'];
        $this->requireCash($state, $cost);
        $state['finances']['cashCents'] -= $cost;
        $state['camper']['upgrades'][] = $upgrade['id'];
        $state['camper']['projectExpenses'][] = ['type' => 'upgrade', 'amountCents' => $cost];
        return [$upgrade['label'] . ' installed for ' . $this->money($cost) . '.', (int) $upgrade['hours']];
    }

    private function sell(array &$state, array $payload): array
    {
        $this->requireGarage($state);
        $offer = $this->find($state['offers'], (string) ($payload['offerId'] ?? ''));
        $proceeds = (int) $offer['amountCents'];
        $cost = $this->projectCost($state['camper']);
        $profit = $proceeds - $cost;
        $state['finances']['cashCents'] += $proceeds;
        $state['soldCampers'][] = [
            'instanceId' => $state['camper']['instanceId'],
            'archetypeId' => $state['camper']['archetypeId'],
            'proceedsCents' => $proceeds,
            'projectCostCents' => $cost,
            'profitCents' => $profit,
        ];
        $name = $this->template($state['camper']['archetypeId'])['name'];
        $state['camper'] = null;
        $state['offers'] = [];
        $state['ad'] = null;
        $state['saleCount']++;
        $this->refreshListings($state);

        return [
            $name . ' sold for ' . $this->money($proceeds) . ' gross proceeds. Project '
                . ($profit >= 0 ? 'profit ' : 'loss ') . $this->money(abs($profit))
                . '. Debt was not paid automatically.',
            (int) $this->content['market']['saleHours'],
        ];
    }

    private function buy(array &$state, array $payload): string
    {
        if ($state['camper'] !== null) {
            throw new GameRuleException('PROJECT_LIMIT', 'Simple mode allows one owned camper at a time.');
        }
        $listingId = (string) ($payload['listingId'] ?? '');
        $listingIndex = null;
        foreach ($state['listings'] as $index => $listing) {
            if ($listing['id'] === $listingId) {
                $listingIndex = $index;
                break;
            }
        }
        if ($listingIndex === null) {
            throw new GameRuleException('LISTING_NOT_FOUND', 'That listing is no longer available.');
        }
        $listing = $state['listings'][$listingIndex];
        if ($listing['locationId'] !== $state['player']['locationId']) {
            throw new GameRuleException('WRONG_LOCATION', 'Travel to the listed camper before buying it.');
        }
        $this->requireCash($state, (int) $listing['askCents']);
        $state['finances']['cashCents'] -= (int) $listing['askCents'];
        $state['camper'] = $listing['camper'];
        $state['camper']['projectExpenses'] = [['type' => 'acquisition', 'amountCents' => (int) $listing['askCents']]];
        array_splice($state['listings'], $listingIndex, 1);
        return $this->template($state['camper']['archetypeId'])['name'] . ' purchased for ' . $this->money((int) $listing['askCents']) . '.';
    }

    private function recover(array &$state): array
    {
        if ($state['camper'] === null) {
            throw new GameRuleException('NO_CAMPER', 'There is no camper to recover.');
        }
        $home = $this->content['start']['homeLocationId'];
        if ($state['camper']['locationId'] === $home) {
            throw new GameRuleException('ALREADY_HOME', 'The camper is already at the garage.');
        }
        $distance = $this->shortestDistance($state['camper']['locationId'], $home);
        $payable = in_array('roadside-coverage', $state['player']['perks'], true)
            ? (int) $this->content['recovery']['roadsidePayableCents']
            : (int) $this->content['recovery']['payableCents'];
        $state['finances']['payablesCents'] += $payable;
        $state['player']['locationId'] = $home;
        $state['towVehicle']['locationId'] = $home;
        $state['camper']['locationId'] = $home;
        $state['camper']['transportedKm'] += $distance;
        return ['Commercial recovery moved the project ' . $distance . ' km home and added a ' . $this->money($payable) . ' payable. No defect was repaired.', (int) $this->content['recovery']['hours']];
    }

    private function repay(array &$state, int $requested): string
    {
        if ($requested <= 0) {
            throw new GameRuleException('INVALID_PAYMENT', 'Payment must be greater than zero.');
        }
        $available = min($requested, (int) $state['finances']['cashCents']);
        $total = (int) $state['finances']['accruedInterestCents']
            + (int) $state['finances']['loanPrincipalCents']
            + (int) $state['finances']['payablesCents'];
        $payment = min($available, $total);
        if ($payment <= 0) {
            throw new GameRuleException('NOTHING_TO_PAY', 'There is no affordable debt payment to apply.');
        }
        $remaining = $payment;
        foreach (['accruedInterestCents', 'loanPrincipalCents', 'payablesCents'] as $bucket) {
            $applied = min($remaining, (int) $state['finances'][$bucket]);
            $state['finances'][$bucket] -= $applied;
            $remaining -= $applied;
        }
        $state['finances']['cashCents'] -= $payment;
        return 'Debt payment of ' . $this->money($payment) . ' applied to interest first, then principal, then payables.';
    }

    private function advanceTime(array &$state, int $hours, string $actionType): array
    {
        $notes = [];
        $remaining = $hours;
        while ($remaining > 0) {
            $untilMidnight = 24 - $state['hour'];
            if ($remaining < $untilMidnight) {
                $state['hour'] += $remaining;
                $remaining = 0;
                continue;
            }
            $remaining -= $untilMidnight;
            $state['hour'] = 0;
            $state['day']++;
            $notes[] = $this->settleDay($state);
        }
        $event = $this->maybeEvent($state, $actionType);
        if ($event !== '') {
            $notes[] = $event;
        }
        if ($state['camper'] !== null && $state['ad'] !== null && count($state['offers']) === 0) {
            $this->generateOffer($state);
            $notes[] = 'A persistent buyer offer arrived.';
        }
        return array_values(array_filter($notes));
    }

    private function settleDay(array &$state): string
    {
        $daily = (int) $this->content['daily']['operatingCostCents'];
        if ($state['camper'] !== null && $state['camper']['locationId'] !== $this->content['start']['homeLocationId']) {
            $daily += (int) $this->content['daily']['awayParkingCents'];
        }
        $paid = min($daily, (int) $state['finances']['cashCents']);
        $state['finances']['cashCents'] -= $paid;
        $state['finances']['payablesCents'] += $daily - $paid;
        $interest = intdiv(
            (int) $state['finances']['loanPrincipalCents'] * (int) $this->content['daily']['loanInterestBps'],
            10000
        );
        $state['finances']['accruedInterestCents'] += $interest;
        $notes = ['Midnight settlement: costs ' . $this->money($daily) . '; interest ' . $this->money($interest) . '.'];
        if ($state['camper'] === null) {
            $this->refreshListings($state);
        }
        return implode(' ', $notes);
    }

    private function maybeEvent(array &$state, string $subsystem): string
    {
        $key = $state['rulesetId'] . '|' . $state['seed'] . '|' . $subsystem . '|' . $state['day']
            . '|' . $state['hour'] . '|' . $state['player']['locationId'] . '|' . $state['randomOrdinal'];
        $hash = hash('sha256', $key);
        $state['randomOrdinal']++;
        $roll = hexdec(substr($hash, 0, 8)) % 100;
        if ($roll >= 25) {
            return '';
        }
        $events = $this->content['events'];
        $event = $events[hexdec(substr($hash, 8, 8)) % count($events)];
        $cash = (int) $event['cashDeltaCents'];
        $state['finances']['cashCents'] += $cash;
        return $event['label'] . ($cash > 0 ? ' +' . $this->money($cash) . '.' : '');
    }

    private function generateOffer(array &$state): void
    {
        $camper = $state['camper'];
        $template = $this->template($camper['archetypeId']);
        $major = false;
        $value = (int) $template['soundValueCents'];
        foreach ($camper['defects'] as $defect) {
            if (!$defect['resolved']) {
                $value -= (int) $defect['penaltyCents'];
                $major = $major || $defect['severity'] === 'major';
            }
        }
        foreach ($camper['upgrades'] as $upgradeId) {
            $value += (int) $this->find($this->content['upgrades'], $upgradeId)['valueCents'];
        }
        $buyer = $this->find($this->content['buyers'], $major ? 'project-hunter' : 'weekend-buyer');
        $key = $state['seed'] . '|offer|' . $state['day'] . '|' . $camper['instanceId'];
        $variance = 9500 + (hexdec(substr(hash('sha256', $key), 0, 8)) % 1001);
        $offer = intdiv(max(10000, $value) * (int) $buyer['factorBps'], 10000);
        $offer = intdiv($offer * $variance, 10000);
        $state['offers'][] = [
            'id' => $this->uuid(),
            'buyerName' => $buyer['name'],
            'amountCents' => $offer,
        ];
    }

    private function refreshListings(array &$state): void
    {
        if ($state['camper'] !== null) {
            return;
        }
        $present = array_column(array_column($state['listings'], 'camper'), 'archetypeId');
        foreach ($this->content['camperTemplates'] as $template) {
            if ($template['id'] === $this->content['start']['starterCamperId']
                || $template['unlockAfterSales'] > $state['saleCount']
                || in_array($template['id'], $present, true)) {
                continue;
            }
            $locationId = $this->content['start']['homeLocationId'];
            $state['listings'][] = [
                'id' => $this->uuid(),
                'locationId' => $locationId,
                'askCents' => (int) $template['askCents'],
                'camper' => $this->makeCamper($template, $locationId, (int) $template['askCents']),
            ];
        }
    }

    private function makeCamper(array $template, string $locationId, int $acquisition): array
    {
        $defects = array_map(fn(array $defect): array => $defect + [
            'resolved' => false,
            'blocking' => false,
        ], $template['defects']);
        $actual = [];
        foreach ($this->content['systems'] as $system) {
            $actual[$system['id']] = 92;
        }
        foreach ($defects as $defect) {
            $actual[$defect['systemId']] -= $defect['severity'] === 'major' ? 35 : 18;
        }
        return [
            'instanceId' => $this->uuid(),
            'archetypeId' => $template['id'],
            'locationId' => $locationId,
            'towedKm' => 0,
            'transportedKm' => 0,
            'actualSystemCondition' => $actual,
            'defects' => $defects,
            'inspectionLevels' => [],
            'repairs' => [],
            'upgrades' => [],
            'projectExpenses' => [['type' => 'acquisition', 'amountCents' => $acquisition]],
        ];
    }

    private function publicCamper(array $camper): array
    {
        $template = $this->template($camper['archetypeId']);
        $known = array_values(array_map(
            fn(array $defect): array => [
                'id' => $defect['id'],
                'systemId' => $defect['systemId'],
                'severity' => $defect['severity'],
                'resolved' => $defect['resolved'],
            ],
            array_filter($camper['defects'], fn(array $defect): bool => $defect['known'])
        ));
        return [
            'instanceId' => $camper['instanceId'],
            'archetypeId' => $camper['archetypeId'],
            'name' => $template['name'],
            'locationId' => $camper['locationId'],
            'locationName' => $this->location($camper['locationId'])['name'],
            'dream' => $template['dream'],
            'towedKm' => $camper['towedKm'],
            'transportedKm' => $camper['transportedKm'],
            'hud' => array_map(fn(array $system): array => [
                'id' => $system['id'],
                'label' => $system['label'],
                'state' => $this->hudState($camper, $system['id']),
                'evidence' => $this->hudEvidence($camper, $system['id']),
            ], $this->content['systems']),
            'knownDefects' => $known,
            'upgrades' => $camper['upgrades'],
            'projectCostCents' => $this->projectCost($camper),
        ];
    }

    private function repairViews(array $state): array
    {
        $views = [];
        foreach ($this->content['repairs'] as $repair) {
            $baseReason = 'No matching known issue.';
            if ($state['camper'] !== null && $state['camper']['locationId'] === $state['player']['locationId']) {
                foreach ($state['camper']['defects'] as $defect) {
                    if ($defect['known'] && !$defect['resolved'] && $defect['systemId'] === $repair['systemId']) {
                        if ($repair['id'] !== 'roadside-tire-service' || $defect['blocking']) {
                            $baseReason = null;
                        }
                    }
                }
            }
            $technicianReason = $baseReason;
            $diyReason = $baseReason;
            if ($baseReason === null) {
                $location = $this->location($state['player']['locationId']);
                if (!$repair['serviceAnywhere'] && !$location['service']) {
                    $technicianReason = 'Garage or service location required.';
                }
                if ($technicianReason === null && $state['finances']['cashCents'] < $repair['technicianCostCents']) {
                    $technicianReason = 'Not enough cash.';
                }
                if (!$repair['diyAllowed']) {
                    $diyReason = 'Technician-only repair.';
                } elseif (!in_array('basic-toolkit', $state['player']['tools'], true)) {
                    $diyReason = 'Basic toolkit required.';
                }
            }
            $views[] = [
                'id' => $repair['id'],
                'label' => $repair['label'],
                'systemId' => $repair['systemId'],
                'technicianCostCents' => $repair['technicianCostCents'],
                'technicianHours' => $repair['technicianHours'],
                'technicianAvailable' => $technicianReason === null,
                'technicianReason' => $technicianReason,
                'diyAllowed' => $repair['diyAllowed'],
                'diyHours' => $repair['diyHours'],
                'diyAvailable' => $diyReason === null,
                'diyReason' => $diyReason,
            ];
        }
        return $views;
    }

    private function upgradeViews(array $state): array
    {
        $views = [];
        foreach ($this->content['upgrades'] as $upgrade) {
            $reason = null;
            if ($state['camper'] === null) {
                $reason = 'No current camper.';
            } elseif ($state['player']['locationId'] !== $this->content['start']['homeLocationId']
                || $state['camper']['locationId'] !== $state['player']['locationId']) {
                $reason = 'Camper and player must be at the garage.';
            } elseif (in_array($upgrade['id'], $state['camper']['upgrades'], true)) {
                $reason = 'Already installed.';
            } elseif ($this->hudState($state['camper'], $upgrade['requiresSystemId']) !== 'LOOKS_OK') {
                $reason = 'Required system must look OK.';
            } elseif ($state['finances']['cashCents'] < $upgrade['costCents']) {
                $reason = 'Not enough cash.';
            }
            $views[] = [
                'id' => $upgrade['id'],
                'label' => $upgrade['label'],
                'costCents' => $upgrade['costCents'],
                'hours' => $upgrade['hours'],
                'available' => $reason === null,
                'reason' => $reason,
            ];
        }
        return $views;
    }

    private function hudState(array $camper, string $systemId): string
    {
        $known = array_filter($camper['defects'], fn(array $defect): bool =>
            $defect['systemId'] === $systemId && $defect['known'] && !$defect['resolved']
        );
        foreach ($known as $defect) {
            if ($defect['blocking']) {
                return 'TOWING_BLOCKED';
            }
        }
        foreach ($known as $defect) {
            if ($defect['severity'] === 'major') {
                return 'FAULT';
            }
        }
        if (count($known) > 0) {
            return 'WATCH';
        }
        return isset($camper['inspectionLevels'][$systemId]) ? 'LOOKS_OK' : 'UNKNOWN';
    }

    private function hudEvidence(array $camper, string $systemId): string
    {
        $known = array_values(array_filter($camper['defects'], fn(array $defect): bool =>
            $defect['systemId'] === $systemId && $defect['known'] && !$defect['resolved']
        ));
        if (count($known) > 0) {
            return count($known) . ' known unresolved issue(s).';
        }
        $level = $camper['inspectionLevels'][$systemId] ?? null;
        return match ($level) {
            'technician' => 'Technician inspection found no unresolved issue.',
            'self', 'diy' => 'Your own inspection or repair found no obvious unresolved issue.',
            'brief' => 'Brief pre-purchase roadworthy check found nothing obvious.',
            default => 'Not enough evidence yet.',
        };
    }

    private function applyDistanceTriggers(array &$camper): void
    {
        foreach ($camper['defects'] as &$defect) {
            if (!$defect['resolved']
                && isset($defect['towingBlockerAfterKm'])
                && $camper['towedKm'] >= $defect['towingBlockerAfterKm']) {
                $defect['blocking'] = true;
                $defect['known'] = true;
            }
        }
        unset($defect);
    }

    private function towReason(array $state, array $route): ?string
    {
        if ($state['camper'] === null) {
            return 'There is no camper to tow.';
        }
        if ($state['camper']['locationId'] !== $state['player']['locationId']) {
            return 'The camper is parked somewhere else.';
        }
        foreach ($state['camper']['defects'] as $defect) {
            if ($defect['known'] && !$defect['resolved'] && $defect['blocking']) {
                return 'A known running-gear problem blocks towing. Repair it or use recovery.';
            }
        }
        if ($state['finances']['cashCents'] < $route['towCostCents']) {
            return 'Not enough cash for this tow.';
        }
        return null;
    }

    private function canComplete(array $state): bool
    {
        if ($state['status'] !== 'active' || $state['camper'] === null) {
            return false;
        }
        if ($state['camper']['archetypeId'] !== $this->content['start']['dreamCamperId']
            || $state['camper']['locationId'] !== $this->content['start']['homeLocationId']) {
            return false;
        }
        $debt = array_sum([
            $state['finances']['loanPrincipalCents'],
            $state['finances']['accruedInterestCents'],
            $state['finances']['payablesCents'],
        ]);
        if ($debt !== 0 || $state['finances']['cashCents'] < $this->content['goal']['minimumReserveCents']) {
            return false;
        }
        foreach ($this->content['goal']['requiredSystems'] as $systemId) {
            if ($this->hudState($state['camper'], $systemId) !== 'LOOKS_OK') {
                return false;
            }
        }
        return true;
    }

    private function objective(array $state): string
    {
        if ($state['status'] === 'completed') {
            return 'DREAM CAMPER SECURED';
        }
        if ($state['camper'] === null) {
            return 'BUY THE NEXT PROJECT';
        }
        if ($state['camper']['archetypeId'] === $this->content['start']['starterCamperId']
            && $state['camper']['locationId'] !== $this->content['start']['homeLocationId']) {
            return 'GET THE CAMPER HOME';
        }
        if ($state['camper']['archetypeId'] === $this->content['start']['dreamCamperId']) {
            return $this->canComplete($state) ? 'MAKE THIS ONE HOME' : 'READY THE DREAM CAMPER AND CLEAR DEBT';
        }
        return 'INSPECT, IMPROVE, OR SELL THIS PROJECT';
    }

    private function requireCamperTogether(array $state): void
    {
        if ($state['camper'] === null) {
            throw new GameRuleException('NO_CAMPER', 'There is no current project camper.');
        }
        if ($state['camper']['locationId'] !== $state['player']['locationId']) {
            throw new GameRuleException('WRONG_LOCATION', 'Travel back to the camper first.');
        }
    }

    private function requireGarage(array $state): void
    {
        $this->requireCamperTogether($state);
        if ($state['player']['locationId'] !== $this->content['start']['homeLocationId']) {
            throw new GameRuleException('GARAGE_REQUIRED', 'Bring the camper to the Maple Junction garage first.');
        }
    }

    private function requireCash(array $state, int $cost): void
    {
        if ($cost < 0 || $state['finances']['cashCents'] < $cost) {
            throw new GameRuleException('INSUFFICIENT_CASH', 'There is not enough cash for that action.');
        }
    }

    private function assertInvariants(array $state): void
    {
        foreach (['cashCents', 'loanPrincipalCents', 'accruedInterestCents', 'payablesCents'] as $field) {
            if (!is_int($state['finances'][$field]) || $state['finances'][$field] < 0) {
                throw new RuntimeException('Finance invariant failed for ' . $field . '.');
            }
        }
        foreach (['day', 'hour', 'revision', 'saleCount'] as $field) {
            if (!is_int($state[$field]) || $state[$field] < 0) {
                throw new RuntimeException('Campaign invariant failed for ' . $field . '.');
            }
        }
        if ($state['day'] < 1 || $state['hour'] < 0 || $state['hour'] > 23 || !in_array($state['player']['distanceUnit'], ['km', 'mi'], true)) {
            throw new RuntimeException('Campaign day or unit invariant failed.');
        }
        if ($state['camper'] !== null && ($state['camper']['towedKm'] < 0 || $state['camper']['transportedKm'] < 0)) {
            throw new RuntimeException('Camper distance invariant failed.');
        }
    }

    private function requireCurrentSchema(array $state): void
    {
        if (($state['schemaVersion'] ?? null) !== self::SCHEMA_VERSION) {
            throw new GameRuleException('SAVE_VERSION_UNSUPPORTED', 'This playtest save belongs to an older build and must be reset.');
        }
    }

    private function clock(array $state): string
    {
        return 'Day ' . $state['day'] . ', ' . sprintf('%02d:00', $state['hour']);
    }

    private function shortestDistance(string $from, string $to): int
    {
        $distances = [$from => 0];
        $queue = [[$from, 0]];
        while ($queue !== []) {
            usort($queue, fn(array $left, array $right): int => $left[1] <=> $right[1]);
            [$node, $distance] = array_shift($queue);
            if ($node === $to) {
                return $distance;
            }
            if ($distance !== $distances[$node]) {
                continue;
            }
            foreach ($this->content['routes'] as $route) {
                if (!in_array($node, [$route['a'], $route['b']], true)) {
                    continue;
                }
                $next = $route['a'] === $node ? $route['b'] : $route['a'];
                $candidate = $distance + (int) $route['distanceKm'];
                if (!isset($distances[$next]) || $candidate < $distances[$next]) {
                    $distances[$next] = $candidate;
                    $queue[] = [$next, $candidate];
                }
            }
        }
        throw new RuntimeException('No recovery route to home exists.');
    }

    private function projectCost(?array $camper): int
    {
        if ($camper === null) {
            return 0;
        }
        return array_sum(array_column($camper['projectExpenses'], 'amountCents'));
    }

    private function location(string $id): array
    {
        return $this->find($this->content['locations'], $id);
    }

    private function template(string $id): array
    {
        return $this->find($this->content['camperTemplates'], $id);
    }

    private function find(array $items, string $id): array
    {
        foreach ($items as $item) {
            if (($item['id'] ?? null) === $id) {
                return $item;
            }
        }
        throw new GameRuleException('UNKNOWN_CONTENT_ID', 'Unknown content ID: ' . $id);
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }

    private function money(int $cents): string
    {
        return 'G$' . number_format($cents / 100, 2);
    }
}
