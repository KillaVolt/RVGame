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
    private const SCHEMA_VERSION = 3;
    private array $content;

    public function __construct(string $contentPath)
    {
        $raw = file_get_contents($contentPath);
        if ($raw === false) {
            throw new RuntimeException('Canonical game content could not be read.');
        }
        $content = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        foreach (['releaseId', 'contentVersion', 'rulesetId', 'start', 'inspection', 'daily', 'recovery', 'work', 'market', 'weight', 'marketTags', 'marketPhases', 'locations', 'routes', 'systems', 'repairs', 'upgrades', 'camperTemplates', 'buyers', 'events'] as $required) {
            if (!array_key_exists($required, $content)) {
                throw new RuntimeException('Canonical content is missing ' . $required . '.');
            }
        }
        if (!is_int($content['market']['offerLifetimeHours'] ?? null) || $content['market']['offerLifetimeHours'] < 1
            || !is_int($content['market']['listingLifetimeHours'] ?? null) || $content['market']['listingLifetimeHours'] < 24
            || !is_int($content['market']['historySnapshots'] ?? null) || $content['market']['historySnapshots'] !== 4
            || !is_int($content['market']['eventChancePercent'] ?? null) || $content['market']['eventChancePercent'] < 0 || $content['market']['eventChancePercent'] > 100
            || !is_int($content['work']['startHour'] ?? null) || !is_int($content['work']['lastStartHour'] ?? null)
            || $content['work']['startHour'] < 0 || $content['work']['lastStartHour'] > 23
            || $content['work']['lastStartHour'] < $content['work']['startHour']
            || $content['work']['lastStartHour'] - $content['work']['startHour'] >= $content['work']['hours']) {
            throw new RuntimeException('Invalid market timing, event chance, or job hours.');
        }
        $locationIds = [];
        foreach ($content['locations'] as $location) {
            if (!is_string($location['id'] ?? null) || isset($locationIds[$location['id']])
                || !is_string($location['job']['employer'] ?? null) || !is_string($location['job']['task'] ?? null)
                || !is_bool($location['technician']['available'] ?? null) || !is_int($location['technician']['priceBps'] ?? null)
                || !is_bool($location['water']['freshFill'] ?? null) || !is_bool($location['water']['wasteDump'] ?? null)
                || !is_int($location['market']['askBps'] ?? null) || !is_int($location['market']['intelCostCents'] ?? null)) {
                throw new RuntimeException('Each location must define unique identity, work, technician, water, and market data.');
            }
            foreach (array_keys($content['marketTags']) as $tag) {
                if (!is_int($location['market']['demandBps'][$tag] ?? null)) {
                    throw new RuntimeException('Each location must define demand for every market tag.');
                }
            }
            $locationIds[$location['id']] = true;
        }
        foreach ($content['routes'] as $route) {
            if (!isset($locationIds[$route['a'] ?? ''], $locationIds[$route['b'] ?? ''])
                || !is_int($route['distanceKm'] ?? null) || $route['distanceKm'] < 1) {
                throw new RuntimeException('Every route must connect known locations with a positive integer distance.');
            }
        }
        $systemIds = array_fill_keys(array_column($content['systems'], 'id'), true);
        foreach ($content['camperTemplates'] as $template) {
            if (!is_int($template['dryWeightKg'] ?? null) || !is_int($template['grossVehicleWeightRatingKg'] ?? null)
                || $template['dryWeightKg'] < 1 || $template['grossVehicleWeightRatingKg'] <= $template['dryWeightKg']
                || !is_array($template['tags'] ?? null) || $template['tags'] === []) {
                throw new RuntimeException('Every camper template must define tags and a valid dry/GVWR weight envelope.');
            }
            foreach (['fresh', 'grey', 'black'] as $tank) {
                $capacity = $template['tankCapacitiesLitres'][$tank] ?? null;
                $current = $template['startingTankLitres'][$tank] ?? null;
                if (!is_int($capacity) || !is_int($current) || $capacity < 0 || $current < 0 || $current > $capacity) {
                    throw new RuntimeException('Every camper template must define valid tank capacities and starting litres.');
                }
            }
            foreach ($template['defects'] as $defect) {
                if (!isset($systemIds[$defect['systemId'] ?? ''])) {
                    throw new RuntimeException('Camper defect references an unknown system.');
                }
            }
        }
        foreach ($content['events'] as $event) {
            if (!is_array($event['triggerActions'] ?? null) || $event['triggerActions'] === [] || count($event['choices'] ?? []) < 2) {
                throw new RuntimeException('Every event must define trigger actions and at least two choices.');
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

        $state = [
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
                'maxTrailerWeightKg' => $start['towVehicleMaxTrailerWeightKg'],
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
            'pendingEvent' => null,
            'seenEventIds' => [],
            'marketHistory' => [],
            'intelReports' => [],
            'technicianCreditCents' => 0,
            'offerBonusBps' => 0,
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
        $this->refreshListings($state);
        $state['marketHistory'][] = $this->marketSnapshot($state);
        $this->assertInvariants($state);
        return $state;
    }

    public function apply(array $state, string $type, array $payload): array
    {
        $this->requireCurrentSchema($state);
        if ($state['status'] !== 'active' && $type !== 'set-unit') {
            throw new GameRuleException('CAMPAIGN_COMPLETE', 'This campaign is already complete.');
        }
        if ($state['pendingEvent'] !== null && !in_array($type, ['resolve-event', 'set-unit'], true)) {
            throw new GameRuleException('EVENT_DECISION_REQUIRED', 'Resolve the current event before taking another action.');
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
            case 'set-tank':
                [$summary, $hours] = $this->setTank($state, $payload);
                break;
            case 'set-loose-load':
                $summary = $this->setLooseLoad($state, $payload);
                break;
            case 'buy-intel':
                $summary = $this->buyIntel($state);
                break;
            case 'resolve-event':
                [$summary, $hours] = $this->resolveEvent($state, $payload);
                break;
            case 'advertise':
            case 'relist':
                $this->requireGarage($state);
                if ($type === 'advertise' && $state['ad'] !== null) {
                    throw new GameRuleException('ALREADY_ADVERTISED', 'Already listed. Relist to request fresh offers after improvements.');
                }
                $state['offers'] = [];
                $state['ad'] = ['strategy' => 'market', 'day' => $state['day'], 'hour' => $state['hour'], 'locationId' => $state['player']['locationId'], 'askCents' => $this->guidePrice($state['camper'])];
                $summary = 'Listed at ' . $this->money($state['ad']['askCents']) . '. Buyers make their own offers as time advances.' . ($type === 'relist' ? ' Earlier offers were withdrawn.' : '');
                break;
            case 'decline-offer':
                $offer = $this->find($state['offers'], (string) ($payload['offerId'] ?? ''));
                $state['offers'] = array_values(array_filter($state['offers'], fn(array $item): bool => $item['id'] !== $offer['id']));
                $summary = 'Offer declined. Your listing stays active; advance time to hear from another buyer.';
                break;
            case 'wait':
                if ($state['camper'] === null || $state['ad'] === null) {
                    throw new GameRuleException('NO_ACTIVE_AD', 'Advertise a camper before waiting for offers.');
                }
                if ($this->activeOffers($state) !== []) {
                    throw new GameRuleException('OFFER_WAITING', 'A buyer is waiting. Accept, decline or relist before waiting for another offer.');
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
                if (!$this->workAvailable($state)) {
                    throw new GameRuleException('JOB_CLOSED', 'Local jobs accept a start between ' . $this->content['work']['startHour'] . ':00 and ' . $this->content['work']['lastStartHour'] . ':00. Rest until the next opening.');
                }
                $gross = (int) $this->content['work']['grossCents'];
                $state['finances']['cashCents'] += $gross;
                $job = $this->location($state['player']['locationId'])['job'];
                $summary = $job['employer'] . ' paid ' . $this->money($gross) . ' for completing: ' . $job['task'] . '.';
                $hours = (int) $this->content['work']['hours'];
                break;
            case 'rest':
                if ($this->workAvailable($state)) {
                    throw new GameRuleException('JOB_OPEN', 'A local job is available now. Accept it or choose a project action.');
                }
                $hours = $this->restHours($state);
                $summary = 'You rested until local jobs opened. Daily bills still apply.';
                break;
            case 'recover':
                [$summary, $hours] = $this->recover($state);
                break;
            case 'repay':
                if (!is_int($payload['amountCents'] ?? null)) {
                    throw new GameRuleException('INVALID_PAYMENT', 'Enter a payment with no more than two decimal places.');
                }
                $summary = $this->repay($state, $payload['amountCents']);
                break;
            case 'set-unit':
                $unit = $payload['unit'] ?? '';
                if (!in_array($unit, ['km', 'mi'], true)) {
                    throw new GameRuleException('INVALID_UNIT', 'Distance unit must be km or mi.');
                }
                $state['player']['distanceUnit'] = $unit;
                $summary = 'Distances now shown in ' . ($unit === 'km' ? 'kilometers.' : 'miles.');
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
            $extras = $this->advanceTime($state, (int) $hours, $type, $type !== 'resolve-event');
            $summary = 'Time advanced: ' . $startClock . ' to ' . $this->clock($state) . '. ' . $summary;
            if ($extras !== []) {
                $summary .= ' ' . implode(' ', $extras);
            }
        }

        $state['revision']++;
        $state['eventLog'][] = ['day' => $state['day'], 'hour' => $state['hour'], 'text' => $summary, 'tone' => $type];
        $this->assertInvariants($state);

        return [$state, ['summary' => $summary]];
    }

    public function publicView(array $state): array
    {
        $this->requireCurrentSchema($state);
        $camper = $state['camper'] === null ? null : $this->publicCamper($state['camper']);
        if ($camper !== null) {
            foreach ($camper['hud'] as &$system) {
                $system['selfReason'] = $this->inspectionReason($state, $system['id']);
            }
            unset($system);
        }
        $cash = (int) $state['finances']['cashCents'];
        $location = $this->location($state['player']['locationId']);
        $marketContext = $this->marketContext($state['day'], $state['player']['locationId']);
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
                'currentWeightKg' => $camper['weight']['currentKg'] ?? null,
                'towLimitKg' => $state['towVehicle']['maxTrailerWeightKg'],
            ];
        }

        $listings = [];
        foreach ($state['listings'] as $listing) {
            $template = $this->template($listing['camper']['archetypeId']);
            $known = [];
            foreach ($listing['camper']['defects'] as $defect) {
                if ($defect['known'] && !$defect['resolved']) {
                    $known[] = $defect['severity'] . ' issue: ' . $this->find($this->content['systems'], $defect['systemId'])['label'];
                }
            }
            $reason = null;
            if ($listing['expiresAtHour'] <= $this->absoluteHour($state)) {
                $reason = 'This listing expired; the next midnight refresh will replace it.';
            } elseif ($state['camper'] !== null) {
                $reason = 'Sell the current project first.';
            } elseif ($state['player']['locationId'] !== $listing['locationId']) {
                $reason = 'Travel to ' . $this->location($listing['locationId'])['name'] . ' first.';
            } elseif ($cash < $listing['askCents']) {
                $reason = 'Not enough cash.';
            }
            $listings[] = [
                'id' => $listing['id'],
                'instanceId' => $listing['camper']['instanceId'],
                'name' => $template['name'],
                'archetypeId' => $template['id'],
                'locationId' => $listing['locationId'],
                'locationName' => $this->location($listing['locationId'])['name'],
                'askCents' => $listing['askCents'],
                'sellerClaim' => $listing['sellerClaim'],
                'expiresAtHour' => $listing['expiresAtHour'],
                'dryWeightKg' => $template['dryWeightKg'],
                'grossVehicleWeightRatingKg' => $template['grossVehicleWeightRatingKg'],
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
                'expiresAtHour' => $offer['expiresAtHour'] ?? null,
                'expired' => isset($offer['expiresAtHour']) && $offer['expiresAtHour'] <= $this->absoluteHour($state),
                'rationale' => $offer['rationale'] ?? ['This earlier offer did not record a rationale. Relist for current buyer reasoning.'],
                'handoverLocationName' => $offer['handoverLocationName'] ?? $this->location($this->content['start']['homeLocationId'])['name'],
            ];
        }

        return [
            'buildId' => $this->content['releaseId'],
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
            'inspection' => array_merge($this->content['inspection'], [
                'technicianReason' => $this->inspectionReason($state, null),
                'selfAvailable' => in_array('basic-toolkit', $state['player']['tools'], true),
                'selfReason' => in_array('basic-toolkit', $state['player']['tools'], true)
                    ? null
                    : 'First-time owners need a technician for technical system checks.',
                'technicianName' => $location['technician']['name'],
                'technicianCostCents' => $this->technicianPrice((int) $this->content['inspection']['technicianCostCents'], $state),
            ]),
            'finances' => $state['finances'] + ['totalDebtCents' => $totalDebt],
            'daily' => $this->content['daily'] + [
                'nextCostCents' => $this->dailyCost($state),
                'nextInterestCents' => intdiv($state['finances']['loanPrincipalCents'] * $this->content['daily']['loanInterestBps'], 10000),
            ],
            'goal' => $this->content['goal'] + ['requirements' => $this->completionRequirements($state), 'unlockAfterSales' => $this->template($this->content['start']['dreamCamperId'])['unlockAfterSales']],
            'ad' => $state['ad'],
            'guidePriceCents' => $state['camper'] === null ? null : $this->guidePrice($state['camper']),
            'soldCampers' => array_map(fn(array $sale): array => $sale + ['name' => $this->template($sale['archetypeId'])['name']], $state['soldCampers']),
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
            'work' => $this->content['work'] + $this->location($state['player']['locationId'])['job'] + ['available' => $this->workAvailable($state), 'restHours' => $this->restHours($state)],
            'market' => $this->content['market'] + [
                'local' => [
                    'phase' => $marketContext['phase'],
                    'demand' => array_map(fn(string $tag, int $bps): array => [
                        'tag' => $tag,
                        'label' => $this->content['marketTags'][$tag],
                        'bps' => $bps,
                        'level' => $this->demandLevel($bps),
                    ], array_keys($marketContext['demandBps']), array_values($marketContext['demandBps'])),
                    'technician' => $location['technician'] + ['currentPriceBps' => $marketContext['technicianPriceBps']],
                    'water' => $location['water'],
                    'intel' => [
                        'contact' => $location['market']['intelContact'],
                        'costCents' => $location['market']['intelCostCents'],
                        'available' => !$this->hasIntelFor($state, $state['player']['locationId'], $state['day'] + 1)
                            && $cash >= $location['market']['intelCostCents'],
                        'reason' => $this->hasIntelFor($state, $state['player']['locationId'], $state['day'] + 1)
                            ? 'Tomorrow\'s local forecast is already in your notes.'
                            : ($cash < $location['market']['intelCostCents'] ? 'Not enough cash.' : null),
                    ],
                ],
                'history' => $this->publicMarketHistory($state, $state['player']['locationId']),
                'intelReports' => array_slice($state['intelReports'], -5),
            ],
            'weightRules' => $this->content['weight'],
            'pendingEvent' => $this->publicPendingEvent($state),
            'technicianCreditCents' => $state['technicianCreditCents'],
            'atGarageWithCamper' => $state['camper'] !== null
                && $state['player']['locationId'] === $this->content['start']['homeLocationId']
                && $state['camper']['locationId'] === $state['player']['locationId'],
            'canAdvertise' => $state['ad'] === null && $state['camper'] !== null
                && $state['camper']['locationId'] === $state['player']['locationId']
                && $state['player']['locationId'] === $this->content['start']['homeLocationId'],
            'canWait' => $state['camper'] !== null && $state['ad'] !== null && $this->activeOffers($state) === [],
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

        $breakdown = $mode === 'tow' && $this->applyDistanceTriggers($state['camper']);

        return [
            ucfirst($mode) . ' travel reached ' . $this->location($destination)['name']
                . ' over ' . $route['distanceKm'] . ' km for ' . $this->money($cost) . '.'
                . ($mode === 'solo' && $state['camper'] !== null ? ' Your camper stayed at ' . $this->location($state['camper']['locationId'])['name'] . '.' : '')
                . ($breakdown ? ' Tire failure: towing is now blocked. Arrange tire service here or recovery to Maple Junction; recovery does not repair the tire.' : ''),
            (int) $route[$mode === 'tow' ? 'towHours' : 'soloHours'],
        ];
    }

    private function inspect(array &$state, array $payload): array
    {
        $reason = $this->inspectionReason($state, (string) ($payload['systemId'] ?? ''));
        if ($reason !== null) {
            throw new GameRuleException('INSPECTION_UNAVAILABLE', $reason);
        }
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
        return ['Self-check of ' . $this->find($this->content['systems'], $systemId)['label'] . ': ' . $revealed . ' new ' . ($revealed === 1 ? 'issue' : 'issues') . '. A self-check can miss hidden faults; a technician can inspect more deeply.', (int) $this->content['inspection']['selfHours']];
    }

    private function technicianInspect(array &$state): array
    {
        $reason = $this->inspectionReason($state, null);
        if ($reason !== null) {
            throw new GameRuleException('INSPECTION_UNAVAILABLE', $reason);
        }
        $this->requireCamperTogether($state);
        $gross = $this->technicianGrossPrice((int) $this->content['inspection']['technicianCostCents'], $state);
        $credit = min((int) $state['technicianCreditCents'], $gross);
        $cost = $gross - $credit;
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
        $state['technicianCreditCents'] -= $credit;
        $state['camper']['projectExpenses'][] = ['type' => 'inspection', 'amountCents' => $cost];
        return [$this->location($state['player']['locationId'])['technician']['name'] . ' inspected the full camper for ' . $this->money($cost)
            . ($credit > 0 ? ' after a ' . $this->money($credit) . ' credit' : '') . ' and found ' . $revealed . ' new '
            . ($revealed === 1 ? 'issue.' : 'issues.'), (int) $this->content['inspection']['technicianHours']];
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
        if ($method === 'technician' && !$repair['serviceAnywhere'] && !$location['technician']['available']) {
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
        $gross = $method === 'technician' ? $this->technicianGrossPrice((int) $repair['technicianCostCents'], $state) : 0;
        $credit = $method === 'technician' ? min((int) $state['technicianCreditCents'], $gross) : 0;
        $cost = $gross - $credit;
        $hours = $method === 'technician' ? (int) $repair['technicianHours'] : (int) $repair['diyHours'];
        $this->requireCash($state, $cost);
        $state['finances']['cashCents'] -= $cost;
        $state['technicianCreditCents'] -= $credit;
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
        $provider = $repair['serviceAnywhere'] && !$location['technician']['available'] ? 'regional roadside service' : $location['technician']['name'];
        return [$repair['label'] . ' completed ' . ($method === 'diy' ? 'by you' : 'by ' . $provider) . ' in ' . $hours . ' '
            . ($hours === 1 ? 'hour' : 'hours') . ' for ' . $price . ($credit > 0 ? ' after a ' . $this->money($credit) . ' credit' : '') . '.', $hours];
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
        return [$upgrade['label'] . ' installed for ' . $this->money($cost) . ' and added ' . $upgrade['weightKg'] . ' kg.', (int) $upgrade['hours']];
    }

    private function setTank(array &$state, array $payload): array
    {
        $this->requireCamperTogether($state);
        $tank = (string) ($payload['tankId'] ?? '');
        $target = $payload['targetLitres'] ?? null;
        if (!in_array($tank, ['fresh', 'grey', 'black'], true) || !is_int($target)) {
            throw new GameRuleException('INVALID_TANK_CHANGE', 'Choose a valid tank and whole-litre target.');
        }
        $capacity = (int) $state['camper']['tankCapacitiesLitres'][$tank];
        $current = (int) $state['camper']['tankLitres'][$tank];
        if ($target < 0 || $target > $capacity || $target === $current) {
            throw new GameRuleException('INVALID_TANK_CHANGE', 'Tank target must differ and remain within its capacity.');
        }
        $water = $this->location($state['player']['locationId'])['water'];
        $cost = 0;
        if ($tank === 'fresh' && $target > $current) {
            if (!$water['freshFill']) {
                throw new GameRuleException('WATER_UNAVAILABLE', 'Fresh-water filling is unavailable at this location.');
            }
            $cost = ($target - $current) * (int) $this->content['weight']['freshWaterCentsPerLitre'];
        } elseif ($tank !== 'fresh' && $target > $current) {
            throw new GameRuleException('INVALID_TANK_CHANGE', 'Waste tanks increase only through authored events, not a manual command.');
        } elseif ($tank !== 'fresh') {
            if (!$water['wasteDump']) {
                throw new GameRuleException('DUMP_UNAVAILABLE', 'A waste-dump station is required here.');
            }
            $cost = (int) $this->content['weight']['wasteDumpCostCents'];
        }
        $this->requireCash($state, $cost);
        $state['finances']['cashCents'] -= $cost;
        $state['camper']['tankLitres'][$tank] = $target;
        if ($cost > 0) {
            $state['camper']['projectExpenses'][] = ['type' => 'tank service', 'amountCents' => $cost];
        }
        $change = $target - $current;
        return [ucfirst($tank) . ' tank changed from ' . $current . ' L to ' . $target . ' L ('
            . ($change > 0 ? '+' : '') . $change . ' kg) for ' . $this->money($cost) . '.', (int) $this->content['weight']['tankServiceHours']];
    }

    private function setLooseLoad(array &$state, array $payload): string
    {
        $this->requireCamperTogether($state);
        $target = $payload['targetKg'] ?? null;
        $maximum = (int) $this->content['weight']['maximumLooseLoadKg'];
        if (!is_int($target) || $target < 0 || $target > $maximum || $target === $state['camper']['looseLoadKg']) {
            throw new GameRuleException('INVALID_LOAD_CHANGE', 'Loose load must be a different whole-kilogram value within the displayed limit.');
        }
        $before = (int) $state['camper']['looseLoadKg'];
        $state['camper']['looseLoadKg'] = $target;
        return 'Loose camping load changed from ' . $before . ' kg to ' . $target . ' kg. No time or money passed.';
    }

    private function buyIntel(array &$state): string
    {
        $locationId = $state['player']['locationId'];
        $location = $this->location($locationId);
        $forecastDay = $state['day'] + 1;
        if ($this->hasIntelFor($state, $locationId, $forecastDay)) {
            throw new GameRuleException('INTEL_ALREADY_OWNED', 'Tomorrow\'s local forecast is already in your notes.');
        }
        $cost = (int) $location['market']['intelCostCents'];
        $this->requireCash($state, $cost);
        $state['finances']['cashCents'] -= $cost;
        $report = $this->createIntelReport($state, $locationId, $forecastDay);
        return $location['market']['intelContact'] . ' sold you a dated forecast for ' . $this->money($cost) . ': ' . $report['text'];
    }

    private function resolveEvent(array &$state, array $payload): array
    {
        $pending = $state['pendingEvent'];
        if ($pending === null || ($payload['eventInstanceId'] ?? '') !== $pending['instanceId']) {
            throw new GameRuleException('EVENT_NOT_FOUND', 'That event decision is no longer pending.');
        }
        $event = $this->find($this->content['events'], $pending['eventId']);
        $choice = $this->find($event['choices'], (string) ($payload['choiceId'] ?? ''));
        $effects = $choice['effects'];
        $cost = (int) ($choice['cashCostCents'] ?? 0);
        if (($effects['forecast'] ?? false) === true) {
            $locationId = $state['player']['locationId'];
            if ($this->hasIntelFor($state, $locationId, $state['day'] + 1)) {
                throw new GameRuleException('INTEL_ALREADY_OWNED', 'Tomorrow\'s local forecast is already in your notes. Choose the no-cost response.');
            }
            $cost += (int) $this->location($locationId)['market']['intelCostCents'];
        }
        $this->requireCash($state, $cost);
        $state['finances']['cashCents'] -= $cost;

        $notes = [];
        if (isset($effects['cashGainCents'])) {
            $state['finances']['cashCents'] += (int) $effects['cashGainCents'];
        }
        if (isset($effects['technicianCreditCents'])) {
            $state['technicianCreditCents'] += (int) $effects['technicianCreditCents'];
        }
        if (isset($effects['revealSystemId']) && $state['camper'] !== null) {
            foreach ($state['camper']['defects'] as &$defect) {
                if ($defect['systemId'] === $effects['revealSystemId'] && !$defect['resolved']) {
                    $defect['known'] = true;
                }
            }
            unset($defect);
        }
        if (($effects['recoverHome'] ?? false) === true) {
            if ($state['camper'] === null || $state['camper']['locationId'] === $this->content['start']['homeLocationId']) {
                throw new GameRuleException('RECOVERY_UNAVAILABLE', 'The camper is already at Maple Junction. Choose another response.');
            }
            [$distance, $payable] = $this->moveRecovery($state);
            $notes[] = $distance . ' km recovered; ' . $this->money($payable) . ' added to debt; no repair performed.';
        }
        if (isset($effects['offerBonusBps'])) {
            $bonus = (int) $effects['offerBonusBps'];
            if ($state['offers'] !== []) {
                $state['offers'][0]['amountCents'] = intdiv($state['offers'][0]['amountCents'] * (10000 + $bonus), 10000);
                $state['offers'][0]['rationale'][] = 'A direct local lead improved this recorded offer by ' . ($bonus / 100) . '%.';
            } else {
                $state['offerBonusBps'] += $bonus;
            }
        }
        if (($effects['forecast'] ?? false) === true) {
            $report = $this->createIntelReport($state, $state['player']['locationId'], $state['day'] + 1);
            $notes[] = $report['text'];
        }
        $state['pendingEvent'] = null;
        return [$choice['outcome'] . ($notes === [] ? '' : ' ' . implode(' ', $notes)), (int) $choice['hours']];
    }

    private function sell(array &$state, array $payload): array
    {
        $this->requireGarage($state);
        $offer = $this->find($state['offers'], (string) ($payload['offerId'] ?? ''));
        if (isset($offer['expiresAtHour']) && $offer['expiresAtHour'] <= $this->absoluteHour($state)) {
            throw new GameRuleException('OFFER_EXPIRED', 'This offer expired. Advance time or relist to request a new buyer.');
        }
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
            'expenses' => $state['camper']['projectExpenses'],
        ];
        $name = $this->template($state['camper']['archetypeId'])['name'];
        $state['camper'] = null;
        $state['offers'] = [];
        $state['ad'] = null;
        $state['saleCount']++;

        return [
            $name . ' sold for ' . $this->money($proceeds) . ' gross proceeds. Project '
                . ($profit >= 0 ? 'profit ' : 'loss ') . $this->money(abs($profit))
                . ' before living costs, interest and recovery bills. Debt was not paid automatically.'
                . ($state['saleCount'] === $this->template($this->content['start']['dreamCamperId'])['unlockAfterSales'] ? ' The Sunbeam dream camper is now available to buy!' : ''),
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
        if ($listing['expiresAtHour'] <= $this->absoluteHour($state)) {
            throw new GameRuleException('LISTING_EXPIRED', 'That listing expired. The next midnight settlement will refresh the market.');
        }
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
        [$distance, $payable] = $this->moveRecovery($state);
        return ['Commercial recovery moved the project ' . $distance . ' km home and added a ' . $this->money($payable) . ' bill to debt. This is campaign overhead, excluded from project profit. No defect was repaired.', (int) $this->content['recovery']['hours']];
    }

    private function moveRecovery(array &$state): array
    {
        $home = $this->content['start']['homeLocationId'];
        $distance = $this->shortestDistance($state['camper']['locationId'], $home);
        $payable = in_array('roadside-coverage', $state['player']['perks'], true)
            ? (int) $this->content['recovery']['roadsidePayableCents']
            : (int) $this->content['recovery']['payableCents'];
        $state['finances']['payablesCents'] += $payable;
        $state['player']['locationId'] = $home;
        $state['towVehicle']['locationId'] = $home;
        $state['camper']['locationId'] = $home;
        $state['camper']['transportedKm'] += $distance;
        return [$distance, $payable];
    }

    private function repay(array &$state, int $requested): string
    {
        if ($requested <= 0) {
            throw new GameRuleException('INVALID_PAYMENT', 'Payment must be greater than zero.');
        }
        $total = (int) $state['finances']['accruedInterestCents']
            + (int) $state['finances']['loanPrincipalCents']
            + (int) $state['finances']['payablesCents'];
        if ($requested > $state['finances']['cashCents'] || $requested > $total) {
            throw new GameRuleException('INVALID_PAYMENT', 'Payment exceeds your cash or remaining debt. Enter a smaller amount or choose Pay maximum.');
        }
        $payment = $requested;
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

    private function advanceTime(array &$state, int $hours, string $actionType, bool $allowEvent = true): array
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
        $event = $allowEvent ? $this->maybeEvent($state, $actionType) : '';
        if ($event !== '') {
            $notes[] = $event;
        }
        if (count($this->activeOffers($state)) !== count($state['offers'])) {
            $state['offers'] = $this->activeOffers($state);
            $notes[] = 'The earlier buyer offer expired.';
        }
        if ($state['camper'] !== null && $state['ad'] !== null && count($state['offers']) === 0) {
            $this->generateOffer($state);
            $notes[] = 'A buyer offered ' . $this->money($state['offers'][0]['amountCents']) . '. The offer is held for ' . $this->content['market']['offerLifetimeHours'] . ' hours; handover is at Maple Junction.';
        }
        return array_values(array_filter($notes));
    }

    private function settleDay(array &$state): string
    {
        $daily = $this->dailyCost($state);
        $paid = min($daily, (int) $state['finances']['cashCents']);
        $state['finances']['cashCents'] -= $paid;
        $state['finances']['payablesCents'] += $daily - $paid;
        $interest = intdiv(
            (int) $state['finances']['loanPrincipalCents'] * (int) $this->content['daily']['loanInterestBps'],
            10000
        );
        $state['finances']['accruedInterestCents'] += $interest;
        $notes = ['Midnight settlement: costs ' . $this->money($daily) . '; interest ' . $this->money($interest) . '.'];
        if ($paid < $daily) {
            $notes[] = 'Cash covered ' . $this->money($paid) . '; the unpaid ' . $this->money($daily - $paid) . ' became a bill in your debt balance.';
        }
        $this->refreshListings($state);
        $state['marketHistory'][] = $this->marketSnapshot($state);
        $state['marketHistory'] = array_slice($state['marketHistory'], -$this->content['market']['historySnapshots']);
        return implode(' ', $notes);
    }

    private function maybeEvent(array &$state, string $subsystem): string
    {
        $key = $state['rulesetId'] . '|' . $state['seed'] . '|' . $subsystem . '|' . $state['day']
            . '|' . $state['hour'] . '|' . $state['player']['locationId'] . '|' . $state['randomOrdinal'];
        $hash = hash('sha256', $key);
        $state['randomOrdinal']++;
        $roll = hexdec(substr($hash, 0, 8)) % 100;
        if ($roll >= $this->content['market']['eventChancePercent']) {
            return '';
        }
        $events = array_values(array_filter($this->content['events'], function (array $event) use ($state, $subsystem): bool {
            if (!in_array($subsystem, $event['triggerActions'], true)) return false;
            if (($event['requiresCamper'] ?? false) && $state['camper'] === null) return false;
            if (($event['requiresAd'] ?? false) && $state['ad'] === null) return false;
            if (($event['requiresTechnician'] ?? false) && !$this->location($state['player']['locationId'])['technician']['available']) return false;
            if (isset($event['locationIds']) && !in_array($state['player']['locationId'], $event['locationIds'], true)) return false;
            if (isset($event['requiresUnresolvedSystemId'])) {
                if ($state['camper'] === null) return false;
                $matching = array_filter($state['camper']['defects'], fn(array $defect): bool =>
                    !$defect['resolved'] && $defect['systemId'] === $event['requiresUnresolvedSystemId']
                );
                if ($matching === []) return false;
            }
            return true;
        }));
        if ($events === []) return '';
        $unseen = array_values(array_filter($events, fn(array $event): bool => !in_array($event['id'], $state['seenEventIds'], true)));
        if ($unseen !== []) $events = $unseen;
        $event = $events[hexdec(substr($hash, 8, 8)) % count($events)];
        $state['pendingEvent'] = ['instanceId' => $this->uuid(), 'eventId' => $event['id'], 'day' => $state['day'], 'hour' => $state['hour']];
        $state['seenEventIds'][] = $event['id'];
        return 'Decision waiting: ' . $event['title'] . '. Choose a response before continuing.';
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
        $eligibleBuyers = array_values(array_filter($this->content['buyers'], fn(array $buyer): bool => !$major || $buyer['acceptsMajorDefects']));
        $key = $state['seed'] . '|offer|' . $state['day'] . '|' . $state['hour'] . '|' . $state['randomOrdinal'] . '|' . $camper['instanceId'];
        $buyer = $eligibleBuyers[hexdec(substr(hash('sha256', $key), 8, 8)) % count($eligibleBuyers)];
        $preferred = array_values(array_intersect($buyer['preferredTags'], $template['tags']));
        $demandTag = $preferred[0] ?? $template['tags'][0];
        $adLocation = $state['ad']['locationId'] ?? $this->content['start']['homeLocationId'];
        $demandBps = $this->marketContext($state['day'], $adLocation)['demandBps'][$demandTag];
        $variance = 9500 + (hexdec(substr(hash('sha256', $key), 0, 8)) % 1001);
        $offer = intdiv(max(10000, $value) * (int) $buyer['factorBps'], 10000);
        $offer = intdiv($offer * $demandBps, 10000);
        $offer = intdiv($offer * $variance, 10000);
        if ($state['offerBonusBps'] > 0) {
            $offer = intdiv($offer * (10000 + $state['offerBonusBps']), 10000);
        }
        $rationale = [
            $buyer['fitReason'],
            ucfirst($this->demandLevel($demandBps)) . ' local demand for ' . $this->content['marketTags'][$demandTag] . ' adjusted the offer.',
            $major ? 'Unresolved major work is included in the as-is price.' : 'No unresolved major fault reduced this buyer\'s valuation.',
        ];
        if ($state['offerBonusBps'] > 0) {
            $rationale[] = 'A direct buyer lead added ' . ($state['offerBonusBps'] / 100) . '%.';
        }
        $state['offers'][] = [
            'id' => $this->uuid(),
            'buyerId' => $buyer['id'],
            'buyerName' => $buyer['name'],
            'amountCents' => $offer,
            'expiresAtHour' => $this->absoluteHour($state) + $this->content['market']['offerLifetimeHours'],
            'rationale' => $rationale,
            'handoverLocationName' => $this->location($this->content['start']['homeLocationId'])['name'],
        ];
        $state['offerBonusBps'] = 0;
    }

    private function refreshListings(array &$state): void
    {
        $now = $this->absoluteHour($state);
        $state['listings'] = array_values(array_filter($state['listings'], fn(array $listing): bool => $listing['expiresAtHour'] > $now));
        $eligible = array_values(array_filter($this->content['camperTemplates'], fn(array $template): bool =>
            $template['id'] !== $this->content['start']['starterCamperId'] && $template['unlockAfterSales'] <= $state['saleCount']
        ));
        if ($eligible === []) return;
        $waveOffset = hexdec(substr(hash('sha256', $state['seed'] . '|listing-wave|' . $state['day']), 0, 4));
        foreach ($this->content['locations'] as $locationIndex => $location) {
            $localCount = count(array_filter($state['listings'], fn(array $listing): bool => $listing['locationId'] === $location['id']));
            while ($localCount < $this->content['market']['listingsPerLocation']) {
                $key = $state['seed'] . '|listing|' . $state['day'] . '|' . $location['id'] . '|' . $localCount . '|' . $state['randomOrdinal'];
                $hash = hash('sha256', $key);
                $state['randomOrdinal']++;
                $choices = $eligible;
                $dreamPresent = array_filter($state['listings'], fn(array $listing): bool => $listing['camper']['archetypeId'] === $this->content['start']['dreamCamperId']);
                if ($dreamPresent !== []) {
                    $choices = array_values(array_filter($choices, fn(array $template): bool => !$template['dream']));
                }
                if ($choices === []) break;
                $template = $choices[($waveOffset + $locationIndex) % count($choices)];
                $variance = 9400 + (hexdec(substr($hash, 8, 8)) % 1201);
                $ask = intdiv((int) $template['askCents'] * (int) $location['market']['askBps'], 10000);
                $ask = intdiv($ask * $variance, 10000);
                $state['listings'][] = [
                    'id' => $this->uuid(),
                    'locationId' => $location['id'],
                    'askCents' => $ask,
                    'sellerClaim' => $template['sellerClaims'][hexdec(substr($hash, 16, 8)) % count($template['sellerClaims'])],
                    'expiresAtHour' => $now + $this->content['market']['listingLifetimeHours'],
                    'camper' => $this->makeCamper($template, $location['id'], $ask),
                ];
                $localCount++;
            }
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
            'dryWeightKg' => $template['dryWeightKg'],
            'grossVehicleWeightRatingKg' => $template['grossVehicleWeightRatingKg'],
            'tankCapacitiesLitres' => $template['tankCapacitiesLitres'],
            'tankLitres' => $template['startingTankLitres'],
            'looseLoadKg' => $template['startingLooseLoadKg'],
            'actualSystemCondition' => $actual,
            'defects' => $defects,
            'inspectionLevels' => [],
            'repairs' => [],
            'upgrades' => [],
            'projectExpenses' => [['type' => 'acquisition', 'amountCents' => $acquisition]],
        ];
    }

    private function weightState(array $camper): array
    {
        $tankKg = array_sum($camper['tankLitres']);
        $fullTankKg = array_sum($camper['tankCapacitiesLitres']);
        $upgradeKg = 0;
        foreach ($camper['upgrades'] as $upgradeId) {
            $upgradeKg += (int) $this->find($this->content['upgrades'], $upgradeId)['weightKg'];
        }
        $current = (int) $camper['dryWeightKg'] + $tankKg + $upgradeKg + (int) $camper['looseLoadKg'];
        return [
            'dryKg' => (int) $camper['dryWeightKg'],
            'tankKg' => $tankKg,
            'fullTankKg' => $fullTankKg,
            'upgradeKg' => $upgradeKg,
            'looseLoadKg' => (int) $camper['looseLoadKg'],
            'currentKg' => $current,
            'allTanksFullKg' => (int) $camper['dryWeightKg'] + $fullTankKg + $upgradeKg + (int) $camper['looseLoadKg'],
            'grossVehicleWeightRatingKg' => (int) $camper['grossVehicleWeightRatingKg'],
            'remainingPayloadKg' => (int) $camper['grossVehicleWeightRatingKg'] - $current,
        ];
    }

    private function marketContext(int $day, string $locationId): array
    {
        $location = $this->location($locationId);
        $phase = $this->content['marketPhases'][($day - 1) % count($this->content['marketPhases'])];
        $demand = [];
        foreach ($this->content['marketTags'] as $tag => $_label) {
            $demand[$tag] = max(7000, min(13000,
                (int) $location['market']['demandBps'][$tag] + (int) $phase['demandDeltaBps'][$tag]
            ));
        }
        return [
            'phase' => $phase['label'],
            'demandBps' => $demand,
            'technicianPriceBps' => $location['technician']['available']
                ? max(7500, (int) $location['technician']['priceBps'] + (int) $phase['serviceDeltaBps'])
                : 0,
        ];
    }

    private function marketSnapshot(array $state): array
    {
        $locations = [];
        foreach ($this->content['locations'] as $location) {
            $context = $this->marketContext($state['day'], $location['id']);
            $locations[$location['id']] = $context + [
                'listingCount' => count(array_filter($state['listings'], fn(array $listing): bool => $listing['locationId'] === $location['id'])),
            ];
        }
        return ['day' => $state['day'], 'locations' => $locations];
    }

    private function publicMarketHistory(array $state, string $locationId): array
    {
        $history = [];
        foreach ($state['marketHistory'] as $snapshot) {
            if (!isset($snapshot['locations'][$locationId])) continue;
            $local = $snapshot['locations'][$locationId];
            $demand = [];
            foreach ($local['demandBps'] as $tag => $bps) {
                $demand[] = ['tag' => $tag, 'label' => $this->content['marketTags'][$tag], 'bps' => $bps, 'level' => $this->demandLevel($bps)];
            }
            $history[] = [
                'day' => $snapshot['day'],
                'phase' => $local['phase'],
                'demand' => $demand,
                'technicianPriceBps' => $local['technicianPriceBps'],
                'listingCount' => $local['listingCount'],
            ];
        }
        return $history;
    }

    private function hasIntelFor(array $state, string $locationId, int $forecastDay): bool
    {
        foreach ($state['intelReports'] as $report) {
            if ($report['locationId'] === $locationId && $report['forecastDay'] === $forecastDay) return true;
        }
        return false;
    }

    private function createIntelReport(array &$state, string $locationId, int $forecastDay): array
    {
        $context = $this->marketContext($forecastDay, $locationId);
        $demand = $context['demandBps'];
        arsort($demand);
        $tag = (string) array_key_first($demand);
        $location = $this->location($locationId);
        $service = $context['technicianPriceBps'] === 0
            ? 'no local technician is scheduled'
            : 'technician prices are scheduled at ' . number_format($context['technicianPriceBps'] / 100, 0) . '% of base';
        $report = [
            'id' => $this->uuid(),
            'purchasedDay' => $state['day'],
            'locationId' => $locationId,
            'locationName' => $location['name'],
            'forecastDay' => $forecastDay,
            'tag' => $tag,
            'tagLabel' => $this->content['marketTags'][$tag],
            'demandBps' => $demand[$tag],
            'text' => 'Day ' . $forecastDay . ' at ' . $location['name'] . ': ' . $this->demandLevel($demand[$tag])
                . ' demand for ' . $this->content['marketTags'][$tag] . '; ' . $service . '.',
        ];
        $state['intelReports'][] = $report;
        return $report;
    }

    private function publicPendingEvent(array $state): ?array
    {
        if ($state['pendingEvent'] === null) return null;
        $event = $this->find($this->content['events'], $state['pendingEvent']['eventId']);
        $choices = [];
        foreach ($event['choices'] as $choice) {
            $cost = (int) ($choice['cashCostCents'] ?? 0);
            $reason = null;
            if (($choice['effects']['forecast'] ?? false) === true) {
                if ($this->hasIntelFor($state, $state['player']['locationId'], $state['day'] + 1)) {
                    $reason = 'Tomorrow\'s forecast is already owned.';
                }
                $cost += (int) $this->location($state['player']['locationId'])['market']['intelCostCents'];
            }
            if (($choice['effects']['recoverHome'] ?? false) === true
                && ($state['camper'] === null || $state['camper']['locationId'] === $this->content['start']['homeLocationId'])) {
                $reason = 'The camper is already at Maple Junction.';
            }
            if ($reason === null && $state['finances']['cashCents'] < $cost) $reason = 'Not enough cash.';
            $choices[] = [
                'id' => $choice['id'],
                'label' => $choice['label'],
                'preview' => $choice['preview'],
                'hours' => $choice['hours'],
                'cashCostCents' => $cost,
                'recoveryPayableCents' => ($choice['effects']['recoverHome'] ?? false)
                    ? (in_array('roadside-coverage', $state['player']['perks'], true) ? $this->content['recovery']['roadsidePayableCents'] : $this->content['recovery']['payableCents'])
                    : 0,
                'available' => $reason === null,
                'reason' => $reason,
            ];
        }
        return $state['pendingEvent'] + ['title' => $event['title'], 'text' => $event['text'], 'choices' => $choices];
    }

    private function demandLevel(int $bps): string
    {
        return $bps >= 11000 ? 'strong' : ($bps <= 9400 ? 'soft' : 'steady');
    }

    private function technicianGrossPrice(int $baseCents, array $state): int
    {
        $bps = $this->marketContext($state['day'], $state['player']['locationId'])['technicianPriceBps'];
        if ($bps === 0) $bps = 12500;
        return intdiv($baseCents * $bps + 9999, 10000);
    }

    private function technicianPrice(int $baseCents, array $state): int
    {
        return max(0, $this->technicianGrossPrice($baseCents, $state) - (int) $state['technicianCreditCents']);
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
            'tags' => array_map(fn(string $tag): string => $this->content['marketTags'][$tag], $template['tags']),
            'towedKm' => $camper['towedKm'],
            'transportedKm' => $camper['transportedKm'],
            'weight' => $this->weightState($camper),
            'tankLitres' => $camper['tankLitres'],
            'tankCapacitiesLitres' => $camper['tankCapacitiesLitres'],
            'looseLoadKg' => $camper['looseLoadKg'],
            'hud' => array_map(fn(array $system): array => [
                'id' => $system['id'],
                'label' => $system['label'],
                'state' => $this->hudState($camper, $system['id']),
                'evidence' => $this->hudEvidence($camper, $system['id']),
            ], $this->content['systems']),
            'knownDefects' => $known,
            'upgrades' => $camper['upgrades'],
            'projectCostCents' => $this->projectCost($camper),
            'projectExpenses' => $camper['projectExpenses'],
        ];
    }

    private function repairViews(array $state): array
    {
        $views = [];
        $location = $this->location($state['player']['locationId']);
        foreach ($this->content['repairs'] as $repair) {
            $baseReason = 'No matching known issue.';
            if ($state['camper'] !== null) {
                foreach ($state['camper']['defects'] as $defect) {
                    if ($defect['known'] && !$defect['resolved'] && $defect['systemId'] === $repair['systemId']) {
                        if ($repair['id'] !== 'roadside-tire-service' || $defect['blocking']) {
                            $baseReason = $state['camper']['locationId'] === $state['player']['locationId'] ? null : 'Return to the camper at ' . $this->location($state['camper']['locationId'])['name'] . '.';
                        }
                    }
                }
            }
            $technicianReason = $baseReason;
            $diyReason = $baseReason;
            if ($baseReason === null) {
                if (!$repair['serviceAnywhere'] && !$location['technician']['available']) {
                    $technicianReason = 'Garage or service location required.';
                }
                $technicianCost = $this->technicianPrice((int) $repair['technicianCostCents'], $state);
                if ($technicianReason === null && $state['finances']['cashCents'] < $technicianCost) {
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
                'systemName' => $this->find($this->content['systems'], $repair['systemId'])['label'],
                'serviceNote' => $repair['serviceAnywhere'] ? 'Call-out service, including roadsides.' : $location['technician']['name'] . ' local price.',
                'technicianCostCents' => $this->technicianPrice((int) $repair['technicianCostCents'], $state),
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
                $reason = 'Inspect and repair ' . $this->find($this->content['systems'], $upgrade['requiresSystemId'])['label'] . ' first.';
            } elseif ($state['finances']['cashCents'] < $upgrade['costCents']) {
                $reason = 'Not enough cash.';
            }
            $views[] = [
                'id' => $upgrade['id'],
                'label' => $upgrade['label'],
                'costCents' => $upgrade['costCents'],
                'hours' => $upgrade['hours'],
                'description' => $upgrade['description'],
                'valueCents' => $upgrade['valueCents'],
                'weightKg' => $upgrade['weightKg'],
                'appealTags' => array_map(fn(string $tag): string => $this->content['marketTags'][$tag], $upgrade['appealTags']),
                'resultingWeightKg' => $state['camper'] === null ? null : $this->weightState($state['camper'])['currentKg'] + $upgrade['weightKg'],
                'installed' => $state['camper'] !== null && in_array($upgrade['id'], $state['camper']['upgrades'], true),
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
            return count($known) . ' known unresolved ' . (count($known) === 1 ? 'issue.' : 'issues.');
        }
        foreach ($camper['defects'] as $defect) {
            if ($defect['systemId'] === $systemId && $defect['resolved']) {
                return 'Known issue repaired. Current evidence finds no unresolved issue in this system.';
            }
        }
        $level = $camper['inspectionLevels'][$systemId] ?? null;
        return match ($level) {
            'technician' => 'Technician inspection found no unresolved issue.',
            'self', 'diy' => 'Your own inspection or repair found no obvious unresolved issue.',
            'brief' => 'Brief pre-purchase roadworthy check found nothing obvious.',
            default => 'Not enough evidence yet.',
        };
    }

    private function applyDistanceTriggers(array &$camper): bool
    {
        $newBlocker = false;
        foreach ($camper['defects'] as &$defect) {
            if (!$defect['resolved']
                && isset($defect['towingBlockerAfterKm'])
                && $camper['towedKm'] >= $defect['towingBlockerAfterKm']) {
                $newBlocker = $newBlocker || !$defect['blocking'];
                $defect['blocking'] = true;
                $defect['known'] = true;
            }
        }
        unset($defect);
        return $newBlocker;
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
        $weight = $this->weightState($state['camper']);
        if ($weight['currentKg'] > $weight['grossVehicleWeightRatingKg']) {
            return 'Camper weight exceeds its ' . $weight['grossVehicleWeightRatingKg'] . ' kg rating. Reduce tanks or loose load.';
        }
        if ($weight['currentKg'] > $state['towVehicle']['maxTrailerWeightKg']) {
            return 'Camper weight exceeds Bluebird\'s ' . $state['towVehicle']['maxTrailerWeightKg'] . ' kg trailer limit. Reduce tanks or loose load.';
        }
        if ($state['finances']['cashCents'] < $route['towCostCents']) {
            return 'Not enough cash for this tow.';
        }
        return null;
    }

    private function absoluteHour(array $state): int
    {
        return ($state['day'] - 1) * 24 + $state['hour'];
    }

    private function activeOffers(array $state): array
    {
        return array_values(array_filter($state['offers'], fn(array $offer): bool =>
            !isset($offer['expiresAtHour']) || $offer['expiresAtHour'] > $this->absoluteHour($state)
        ));
    }

    private function guidePrice(array $camper): int
    {
        $price = $this->template($camper['archetypeId'])['soundValueCents'];
        foreach ($camper['defects'] as $defect) {
            if ($defect['known'] && !$defect['resolved']) $price -= $defect['penaltyCents'];
        }
        foreach ($camper['upgrades'] as $id) $price += $this->find($this->content['upgrades'], $id)['valueCents'];
        return max(10000, $price);
    }

    private function workAvailable(array $state): bool
    {
        return $state['status'] === 'active' && $state['pendingEvent'] === null && $state['hour'] >= $this->content['work']['startHour']
            && $state['hour'] <= $this->content['work']['lastStartHour'];
    }

    private function restHours(array $state): int
    {
        $hours = ($this->content['work']['startHour'] - $state['hour'] + 24) % 24;
        return $hours === 0 ? 24 : $hours;
    }

    private function dailyCost(array $state): int
    {
        return $this->content['daily']['operatingCostCents'] +
            ($state['camper'] !== null && $state['camper']['locationId'] !== $this->content['start']['homeLocationId']
                ? $this->content['daily']['awayParkingCents'] : 0);
    }

    private function inspectionReason(array $state, ?string $systemId): ?string
    {
        if ($state['status'] !== 'active') return 'Campaign complete.';
        if ($state['camper'] === null) return 'No current camper.';
        if ($state['camper']['locationId'] !== $state['player']['locationId']) {
            return 'Return to the camper at ' . $this->location($state['camper']['locationId'])['name'] . '.';
        }
        $levels = $state['camper']['inspectionLevels'];
        if ($systemId !== null) {
            $this->find($this->content['systems'], $systemId);
            if (!in_array('basic-toolkit', $state['player']['tools'], true)) return 'Your preparation requires a technician.';
            if (in_array($levels[$systemId] ?? null, ['self', 'diy', 'technician'], true)) return 'Check complete. No further self-check is needed.';
        } else {
            $done = true;
            foreach ($this->content['systems'] as $system) $done = $done && ($levels[$system['id']] ?? null) === 'technician';
            if ($done) return 'Full inspection complete.';
            $technician = $this->location($state['player']['locationId'])['technician'];
            if (!$technician['available']) return 'No technician is available at this location.';
            if ($state['finances']['cashCents'] < $this->technicianPrice((int) $this->content['inspection']['technicianCostCents'], $state)) return 'Not enough cash for a full inspection.';
        }
        return null;
    }

    private function completionRequirements(array $state): array
    {
        $camper = $state['camper'];
        $home = $this->content['start']['homeLocationId'];
        $checks = [
            ['label' => 'Own the Sunbeam dream camper', 'met' => $camper !== null && $camper['archetypeId'] === $this->content['start']['dreamCamperId']],
            ['label' => 'Bring yourself and the camper to Maple Junction', 'met' => $camper !== null && $camper['locationId'] === $home && $state['player']['locationId'] === $home],
            ['label' => 'Clear all loan principal, interest and bills', 'met' => $state['finances']['loanPrincipalCents'] + $state['finances']['accruedInterestCents'] + $state['finances']['payablesCents'] === 0],
            ['label' => 'Keep ' . $this->money($this->content['goal']['minimumReserveCents']) . ' cash in reserve', 'met' => $state['finances']['cashCents'] >= $this->content['goal']['minimumReserveCents']],
            ['label' => 'Fit the camper and Bluebird weight limits', 'met' => $camper !== null
                && $this->weightState($camper)['currentKg'] <= $camper['grossVehicleWeightRatingKg']
                && $this->weightState($camper)['currentKg'] <= $state['towVehicle']['maxTrailerWeightKg']],
        ];
        foreach ($this->content['goal']['requiredSystems'] as $id) {
            $checks[] = ['label' => $this->find($this->content['systems'], $id)['label'] . ': checked and no unresolved issue', 'met' => $camper !== null && $this->hudState($camper, $id) === 'LOOKS_OK'];
        }
        return $checks;
    }

    private function canComplete(array $state): bool
    {
        return $state['status'] === 'active' && !in_array(false, array_column($this->completionRequirements($state), 'met'), true);
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
        if ($state['camper'] !== null) {
            foreach (['fresh', 'grey', 'black'] as $tank) {
                if (!is_int($state['camper']['tankLitres'][$tank]) || $state['camper']['tankLitres'][$tank] < 0
                    || $state['camper']['tankLitres'][$tank] > $state['camper']['tankCapacitiesLitres'][$tank]) {
                    throw new RuntimeException('Camper tank invariant failed.');
                }
            }
            if (!is_int($state['camper']['looseLoadKg']) || $state['camper']['looseLoadKg'] < 0
                || $state['camper']['looseLoadKg'] > $this->content['weight']['maximumLooseLoadKg']) {
                throw new RuntimeException('Camper loose-load invariant failed.');
            }
        }
        if (count($state['marketHistory']) > $this->content['market']['historySnapshots'] || $state['technicianCreditCents'] < 0 || $state['offerBonusBps'] < 0) {
            throw new RuntimeException('Market history or credit invariant failed.');
        }
    }

    private function requireCurrentSchema(array $state): void
    {
        if (($state['schemaVersion'] ?? null) !== self::SCHEMA_VERSION || ($state['contentVersion'] ?? null) !== $this->content['contentVersion']) {
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
