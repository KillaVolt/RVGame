param([string]$BaseUrl = 'https://starlightrv.ca/RVGame/OT')
$ErrorActionPreference = 'Stop'
if ([string]::IsNullOrWhiteSpace($env:RVGAME_OWNER_USERNAME) -or [string]::IsNullOrWhiteSpace($env:RVGAME_OWNER_PASSWORD)) { throw 'Owner test credentials are required in the environment.' }
$session = [Microsoft.PowerShell.Commands.WebRequestSession]::new()
$ownerUrl = $BaseUrl.TrimEnd('/') + '/owner/'
$login = Invoke-WebRequest $ownerUrl -WebSession $session
if ($login.Content -notmatch '<title>RVGame Owner Login</title>') { throw 'Unauthenticated owner login gate missing.' }
$dashboard = Invoke-WebRequest $ownerUrl -Method Post -WebSession $session -Body @{ action = 'login'; username = $env:RVGAME_OWNER_USERNAME; password = $env:RVGAME_OWNER_PASSWORD }
if ($dashboard.Content -notmatch '<title>RVGame Owner Dashboard</title>') { throw 'Owner login failed; do not retry without checking credentials.' }
if (-not $dashboard.Content.Contains('Owner testing in this browser is excluded from all metrics')) { throw 'Owner exclusion notice missing.' }
if ($session.Cookies.GetCookies([Uri]$ownerUrl)['rvgame_owner_testing'].Value -ne '1') { throw 'Owner browser exclusion cookie missing.' }
$channel = if ($BaseUrl.TrimEnd('/').EndsWith('/OT')) { 'ot' } else { 'main' }
$panels = @('Daily traffic and campaign starts','Campaign funnel','Traffic sources','Devices','Browsers','Operating systems','Languages','Visit start hours','Game actions','Ratings','Feedback','Latest feedback')
$metrics = @('Active now','Visitors','Visits','Page views','Avg engagement','Bounce estimate','Campaign starts','Rating')
foreach ($days in @(7,30,90,365)) {
    $page = Invoke-WebRequest ($ownerUrl + '?days=' + $days) -WebSession $session
    if ($page.Content -notmatch ('<span class="channel">' + $channel + '</span>')) { throw 'Owner channel mismatch.' }
    foreach ($label in ($panels + $metrics)) {
        if (-not $page.Content.Contains('>' + $label + '<')) { throw "Dashboard section missing: $label" }
    }
    if (-not $page.Content.Contains('class="active" href="?days=' + $days + '"')) { throw 'Requested date range not active.' }
    Write-Output "PASS owner $channel $days days: 8 metrics, 12 panels, authenticated rendering."
}
$csrf = [regex]::Match($page.Content, 'name="csrf" value="([a-f0-9]{48})"').Groups[1].Value
if ($csrf.Length -ne 48) { throw 'Logout token missing.' }
$signedOut = Invoke-WebRequest $ownerUrl -Method Post -WebSession $session -Body @{ action = 'logout'; csrf = $csrf }
if ($signedOut.Content -notmatch '<title>RVGame Owner Login</title>') { throw 'Owner logout failed.' }
Write-Output "PASS owner $channel sign-out; no credentials or feedback contents printed."
