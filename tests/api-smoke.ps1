param([string]$BaseUrl = 'http://localhost/RVGame')
$ErrorActionPreference = 'Stop'
# Fresh anonymous test session; never resets an existing campaign.
$session = [Microsoft.PowerShell.Commands.WebRequestSession]::new()
$testUri = [uri]$BaseUrl
$session.Cookies.Add([Net.Cookie]::new('rvgame_owner_testing', '1', '/RVGame', $testUri.Host))
function Request-Game($body) {
    $args = @{ Uri = "$BaseUrl/api/"; WebSession = $session; SkipHttpErrorCheck = $true }
    if ($null -ne $body) {
        $args.Method = 'POST'
        $args.ContentType = 'application/json'
        $args.Headers = @{ 'X-RVGame' = '1' }
        $args.Body = ConvertTo-Json -InputObject $body -Depth 12 -Compress
    }
    $reply = Invoke-WebRequest @args
    return @{ Status = [int]$reply.StatusCode; Data = $reply.Content | ConvertFrom-Json }
}
function Check($condition, $message) { if (-not $condition) { throw $message } }
$start = Request-Game @{ action = 'new'; loadoutId = 'hands-on' }
Check ($start.Status -eq 200 -and $start.Data.state.revision -eq 0) 'Fresh campaign failed'
$request = @{ action = 'command'; commandId = [guid]::NewGuid().ToString(); expectedRevision = 0; type = 'repay'; payload = @{ amountCents = 1 } }
$paid = Request-Game $request
Check ($paid.Status -eq 200 -and $paid.Data.state.finances.cashCents -eq 149999 -and $paid.Data.state.revision -eq 1) 'Exact payment failed'
$duplicate = Request-Game $request
Check ($duplicate.Status -eq 200 -and $duplicate.Data.duplicate -and $duplicate.Data.state.revision -eq 1 -and $duplicate.Data.state.finances.cashCents -eq 149999) 'Duplicate command charged twice'
$request.payload.amountCents = 2
$conflict = Request-Game $request
Check ($conflict.Status -eq 409 -and $conflict.Data.error.code -eq 'COMMAND_ID_CONFLICT') 'Changed duplicate must reject'
$request.commandId = [guid]::NewGuid().ToString()
$stale = Request-Game $request
Check ($stale.Status -eq 409 -and $stale.Data.error.code -eq 'STALE_REVISION') 'Stale revision must reject'
$request.expectedRevision = 1
$request.payload.amountCents = 99999999
$invalid = Request-Game $request
Check ($invalid.Status -eq 422) 'Oversized payment must reject'
$saved = Request-Game $null
Check ($saved.Status -eq 200 -and $saved.Data.state.revision -eq 1 -and $saved.Data.state.finances.cashCents -eq 149999) 'Rejected commands changed the save'
Check ($saved.Data.community.ownerExcluded -eq $true) 'Owner test session is not excluded'
foreach ($submission in @(@{ action = 'rate'; rating = 1 }, @{ action = 'feedback'; category = 'bug'; message = 'Owner test must not be recorded' })) {
    $blocked = Request-Game $submission
    Check ($blocked.Status -eq 422 -and $blocked.Data.error.code -eq 'OWNER_EXCLUDED') 'Owner submission was not rejected'
}
Write-Output "PASS API: exact payment, duplicate receipt, conflicting ID, stale revision, rejected-payment rollback, reload ($BaseUrl)"
Write-Output 'PASS owner rating and feedback rejected without recording.'
