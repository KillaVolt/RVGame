[CmdletBinding()]
param(
    [switch]$Final,
    [switch]$ResetSaves
)

$ErrorActionPreference = 'Stop'
$LocalRoot = 'E:\RVGame\Deploy'
$SourceRoot = 'E:\RVGame'
$RemoteFolder = if ($Final) { 'RVGame' } else { 'RVGame/OT' }
$Channel = if ($Final) { 'main' } else { 'ot' }
$Plink = 'C:\Program Files\PuTTY\plink.exe'
$Pscp = 'C:\Program Files\PuTTY\pscp.exe'
$Php = 'E:\xampp8\php\php.exe'

function Read-Secrets {
    $values = @{}
    Get-Content -LiteralPath 'E:\.secrets.env' | ForEach-Object {
        if ($_ -match '^\s*([A-Z0-9_]+)=(.*)$') {
            $value = $Matches[2].Trim()
            if ($value.Length -ge 2 -and (($value[0] -eq '"' -and $value[-1] -eq '"') -or ($value[0] -eq "'" -and $value[-1] -eq "'"))) {
                $value = $value.Substring(1, $value.Length - 2)
            }
            $values[$Matches[1]] = $value
        }
    }
    $required = @(
        'STARCORE_SSH_HOST', 'STARCORE_SSH_PORT', 'STARCORE_SSH_USERNAME',
        'STARCORE_SSH_ROOT', 'STARCORE_SSH_SESSION', 'STARCORE_SSH_HOSTKEY',
        'STARCORE_ADMIN_USERNAME', 'STARCORE_ADMIN_PASSWORD'
    )
    foreach ($key in $required) {
        if ([string]::IsNullOrWhiteSpace($values[$key])) { throw "Missing $key" }
    }
    return $values
}

function Invoke-Plink([string]$Command) {
    & $script:Plink @script:SshArgs $Command
    if ($LASTEXITCODE -ne 0) { throw 'Remote SSH command failed' }
}

$secrets = Read-Secrets
if (-not (Test-Path -LiteralPath $LocalRoot -PathType Container)) { throw 'Deploy staging folder missing' }
foreach ($path in @($Plink, $Pscp, $Php)) {
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) { throw "Required executable missing: $path" }
}
foreach ($privateName in @('api\.database.php', 'owner\.access.php')) {
    if (Test-Path -LiteralPath (Join-Path $LocalRoot $privateName)) { throw "Private file must not exist in Deploy: $privateName" }
}
if ($secrets.STARCORE_SSH_ROOT -notmatch '^/[A-Za-z0-9._/-]+$') { throw 'Unsafe SSH root path' }
if ($secrets.STARCORE_SSH_PORT -notmatch '^\d+$') { throw 'Invalid SSH port' }

$hostKey = $secrets.STARCORE_SSH_HOSTKEY
if ($hostKey -match '^(ssh-\S+)\s+(\d+)\s+([A-Za-z0-9+/=]+)$') {
    $hostKey = "$($Matches[1]) $($Matches[2]) SHA256:$($Matches[3])"
}
$SshArgs = @(
    '-batch', '-no-antispoof', '-agent', '-share', '-load', $secrets.STARCORE_SSH_SESSION,
    '-hostkey', $hostKey, '-P', $secrets.STARCORE_SSH_PORT,
    '-l', $secrets.STARCORE_SSH_USERNAME
)
$ScpArgs = @(
    '-batch', '-agent', '-share', '-load', $secrets.STARCORE_SSH_SESSION,
    '-hostkey', $hostKey, '-P', $secrets.STARCORE_SSH_PORT
)

$remoteRoot = $secrets.STARCORE_SSH_ROOT.TrimEnd('/')
$target = "$remoteRoot/$RemoteFolder"
$incoming = "$remoteRoot/.rvgame-deploy-$([guid]::NewGuid().ToString('N'))"
$remoteArchivePath = "$remoteRoot/.rvgame-release-$([guid]::NewGuid().ToString('N')).tgz"
$temp = Join-Path $env:TEMP "rvgame-deploy-$([guid]::NewGuid().ToString('N'))"
$package = Join-Path $temp 'package'
$archive = Join-Path $temp 'release.tgz'
$remoteComplete = $false

try {
    [System.IO.Directory]::CreateDirectory((Join-Path $package 'public')) | Out-Null
    [System.IO.Directory]::CreateDirectory((Join-Path $package 'private')) | Out-Null
    Copy-Item -Path (Join-Path $LocalRoot '*') -Destination (Join-Path $package 'public') -Recurse -Force
    Copy-Item -LiteralPath (Join-Path $SourceRoot 'database\schema.sql') -Destination (Join-Path $package 'private\schema.sql')
    Copy-Item -LiteralPath (Join-Path $SourceRoot 'database\SchemaInstaller.php') -Destination (Join-Path $package 'private\SchemaInstaller.php')
    Copy-Item -LiteralPath (Join-Path $SourceRoot 'scripts\ssh-install.php') -Destination (Join-Path $package 'private\ssh-install.php')

    $oldUsername = $env:RVGAME_OWNER_USERNAME
    $oldPassword = $env:RVGAME_OWNER_PASSWORD
    try {
        $env:RVGAME_OWNER_USERNAME = $secrets.STARCORE_ADMIN_USERNAME
        $env:RVGAME_OWNER_PASSWORD = $secrets.STARCORE_ADMIN_PASSWORD
        $ownerAccess = & $Php (Join-Path $SourceRoot 'scripts\generate-owner-access.php')
        if ($LASTEXITCODE -ne 0) { throw 'Cannot generate owner access file' }
        [System.IO.File]::WriteAllText((Join-Path $package 'private\owner.access.php'), $ownerAccess, [System.Text.UTF8Encoding]::new($false))
    } finally {
        $env:RVGAME_OWNER_USERNAME = $oldUsername
        $env:RVGAME_OWNER_PASSWORD = $oldPassword
    }

    $manifest = Get-ChildItem -LiteralPath $package -File -Recurse -Force | Sort-Object FullName | ForEach-Object {
        $relative = $_.FullName.Substring($package.Length + 1).Replace('\', '/')
        '{0}  {1}' -f (Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash.ToLowerInvariant(), $relative
    }
    [System.IO.File]::WriteAllText((Join-Path $package 'manifest.sha256'), ($manifest -join "`n") + "`n", [System.Text.UTF8Encoding]::new($false))
    & tar.exe -czf $archive -C $package .
    if ($LASTEXITCODE -ne 0) { throw 'Cannot create deployment archive' }

    $remoteArchive = "$($secrets.STARCORE_SSH_USERNAME)@$($secrets.STARCORE_SSH_HOST):$remoteArchivePath"
    & $Pscp @ScpArgs $archive $remoteArchive
    if ($LASTEXITCODE -ne 0) { throw 'SCP upload failed' }
    Start-Sleep -Seconds 5

    $reset = if ($ResetSaves) { '1' } else { '0' }
    $remoteCommand = "mkdir -p '$incoming/package' && mv '$remoteArchivePath' '$incoming/release.tgz' && tar -xzf '$incoming/release.tgz' -C '$incoming/package' && cd '$incoming/package' && sha256sum -c manifest.sha256 >/dev/null && php -d display_errors=1 -d log_errors=0 private/ssh-install.php '$remoteRoot' '$target' '$Channel' '$reset' && rm -rf -- '$incoming'"
    Invoke-Plink $remoteCommand
    $remoteComplete = $true
    Write-Output "DEPLOYED $Channel through Pageant SSH to /$RemoteFolder/"
} finally {
    if (-not $remoteComplete) {
        Start-Sleep -Seconds 3
        try { Invoke-Plink "rm -rf -- '$incoming'; rm -f -- '$remoteArchivePath'" } catch { Write-Warning 'Remote temporary release cleanup failed' }
    }
    if (Test-Path -LiteralPath $temp) {
        $resolvedTemp = (Resolve-Path -LiteralPath $temp).Path
        $tempParent = [System.IO.Path]::GetFullPath($env:TEMP).TrimEnd('\')
        if ([System.IO.Path]::GetDirectoryName($resolvedTemp) -ne $tempParent -or [System.IO.Path]::GetFileName($resolvedTemp) -notmatch '^rvgame-deploy-[a-f0-9]{32}$') { throw 'Unsafe temporary cleanup path' }
        Remove-Item -LiteralPath $resolvedTemp -Recurse -Force
    }
}
