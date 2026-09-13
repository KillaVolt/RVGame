<?php
declare(strict_types=1);

final class Analytics
{
    private const VISIT_MINUTES = 30;

    public static function recordPageView(PDO $pdo, int $sessionId, string $channel): void
    {
        if (Database::analyticsExcluded($pdo, $sessionId)) return;
        $token = self::cookieToken($channel);
        $visitId = $token === null ? null : self::activeVisitId($pdo, $sessionId, $channel, $token);

        if ($visitId !== null) {
            $update = $pdo->prepare(
                'UPDATE rvgame_analytics_visits
                 SET page_views = page_views + 1, last_activity_at = CURRENT_TIMESTAMP
                 WHERE id = ?'
            );
            $update->execute([$visitId]);
            self::setVisitCookie($channel, $token);
            return;
        }

        $token = bin2hex(random_bytes(32));
        self::setVisitCookie($channel, $token);
        [$source, $medium, $campaign, $referrerHost] = self::acquisition();
        [$device, $browser, $operatingSystem, $isBot] = self::agentSummary();
        $language = self::language();

        $insert = $pdo->prepare(
            'INSERT INTO rvgame_analytics_visits
                (session_id, channel, visit_token_hash, source, medium, campaign, referrer_host,
                 landing_path, device, browser, operating_system, language, is_bot)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $sessionId,
            $channel,
            hash('sha256', $token, true),
            $source,
            $medium,
            $campaign,
            $referrerHost,
            self::clean((string) ($_SERVER['REQUEST_URI'] ?? ''), 255),
            $device,
            $browser,
            $operatingSystem,
            $language,
            $isBot ? 1 : 0,
        ]);
    }

    public static function touch(PDO $pdo, int $sessionId, string $channel): void
    {
        $visitId = self::currentVisitId($pdo, $sessionId, $channel);
        if ($visitId === null) {
            return;
        }
        $update = $pdo->prepare('UPDATE rvgame_analytics_visits SET last_activity_at = CURRENT_TIMESTAMP WHERE id = ?');
        $update->execute([$visitId]);
    }

    public static function recordEvent(
        PDO $pdo,
        int $sessionId,
        string $channel,
        string $eventName,
        ?string $campaignId = null,
        ?int $value = null
    ): void {
        if (!preg_match('/^[a-z0-9_]{2,60}$/', $eventName)) {
            throw new InvalidArgumentException('Analytics event name is invalid.');
        }

        $visitId = self::currentVisitId($pdo, $sessionId, $channel);
        if ($visitId === null) {
            return;
        }

        $insert = $pdo->prepare(
            'INSERT INTO rvgame_analytics_events
                (visit_id, session_id, channel, campaign_id, event_name, event_value)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([$visitId, $sessionId, $channel, $campaignId, $eventName, $value]);

        $update = $pdo->prepare(
            'UPDATE rvgame_analytics_visits
             SET event_count = event_count + 1, last_activity_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $update->execute([$visitId]);
    }

    private static function currentVisitId(PDO $pdo, int $sessionId, string $channel): ?int
    {
        if (Database::analyticsExcluded($pdo, $sessionId)) return null;
        $token = self::cookieToken($channel);
        if ($token === null) {
            return null;
        }
        self::setVisitCookie($channel, $token);
        return self::activeVisitId($pdo, $sessionId, $channel, $token);
    }

    private static function activeVisitId(PDO $pdo, int $sessionId, string $channel, string $token): ?int
    {
        $select = $pdo->prepare(
            'SELECT id
             FROM rvgame_analytics_visits
             WHERE session_id = ? AND channel = ? AND visit_token_hash = ?
               AND last_activity_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL ' . self::VISIT_MINUTES . ' MINUTE)
             LIMIT 1'
        );
        $select->execute([$sessionId, $channel, hash('sha256', $token, true)]);
        $id = $select->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    private static function cookieToken(string $channel): ?string
    {
        $name = $channel === 'ot' ? 'rvgame_ot_visit' : 'rvgame_visit';
        $token = $_COOKIE[$name] ?? null;
        return is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token) ? $token : null;
    }

    private static function setVisitCookie(string $channel, string $token): void
    {
        setcookie($channel === 'ot' ? 'rvgame_ot_visit' : 'rvgame_visit', $token, [
            'expires' => time() + (self::VISIT_MINUTES * 60),
            'path' => $channel === 'ot' ? '/RVGame/OT' : '/RVGame',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    private static function acquisition(): array
    {
        $source = self::clean(strtolower((string) ($_GET['utm_source'] ?? '')), 80);
        $medium = self::clean(strtolower((string) ($_GET['utm_medium'] ?? '')), 40);
        $campaign = self::clean((string) ($_GET['utm_campaign'] ?? ''), 80);
        $referrerHost = strtolower((string) parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST));
        $referrerHost = self::clean($referrerHost, 190);

        if ($source !== '') {
            return [$source, $medium === '' ? 'tagged' : $medium, $campaign, $referrerHost];
        }
        if (isset($_GET['fbclid']) || preg_match('/(^|\.)facebook\.com$|(^|\.)fb\.com$/', $referrerHost)) {
            return ['facebook', 'social', $campaign, $referrerHost];
        }
        if (preg_match('/(^|\.)(instagram\.com|twitter\.com|x\.com|bsky\.app|reddit\.com)$/', $referrerHost)) {
            return [str_replace('www.', '', $referrerHost), 'social', $campaign, $referrerHost];
        }
        if (preg_match('/(^|\.)(google\.[a-z.]+|bing\.com|duckduckgo\.com)$/', $referrerHost)) {
            return [str_replace('www.', '', $referrerHost), 'organic', $campaign, $referrerHost];
        }
        if ($referrerHost === '' || $referrerHost === 'starlightrv.ca' || str_ends_with($referrerHost, '.starlightrv.ca')) {
            return [$referrerHost === '' ? 'direct' : 'internal', 'none', $campaign, $referrerHost];
        }
        return [$referrerHost, 'referral', $campaign, $referrerHost];
    }

    private static function agentSummary(): array
    {
        $agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $isBot = preg_match('/bot|crawler|spider|facebookexternalhit|facebot|preview|slurp/i', $agent) === 1;
        $device = $isBot ? 'Bot' : (preg_match('/ipad|tablet/i', $agent) ? 'Tablet' : (preg_match('/mobile|android|iphone/i', $agent) ? 'Mobile' : 'Desktop'));
        $browser = match (true) {
            preg_match('/Edg\//', $agent) === 1 => 'Edge',
            preg_match('/OPR\//', $agent) === 1 => 'Opera',
            preg_match('/Brave/i', $agent) === 1 => 'Brave',
            preg_match('/Chrome\//', $agent) === 1 => 'Chrome/Chromium',
            preg_match('/Firefox\//', $agent) === 1 => 'Firefox',
            preg_match('/Safari\//', $agent) === 1 => 'Safari',
            default => $isBot ? 'Crawler' : 'Other',
        };
        $operatingSystem = match (true) {
            preg_match('/Windows/i', $agent) === 1 => 'Windows',
            preg_match('/Android/i', $agent) === 1 => 'Android',
            preg_match('/iPhone|iPad/i', $agent) === 1 => 'iOS/iPadOS',
            preg_match('/Mac OS/i', $agent) === 1 => 'macOS',
            preg_match('/Linux/i', $agent) === 1 => 'Linux',
            default => $isBot ? 'Crawler' : 'Other',
        };
        return [$device, $browser, $operatingSystem, $isBot];
    }

    private static function language(): string
    {
        $raw = (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        return preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z]{2,4})?/', $raw, $match) ? strtolower($match[0]) : 'unknown';
    }

    private static function clean(string $value, int $maximum): string
    {
        $value = trim(preg_replace('/[^\pL\pN._:\/\- ]/u', '', $value) ?? '');
        return substr($value, 0, $maximum);
    }
}
