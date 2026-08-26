[CmdletBinding()]
param(
    [switch]$Install,
    [string]$ApiUrl
)

$ErrorActionPreference = 'Stop'
$AgentVersion = '1.0.0'
$TaskName = 'NexusControlMonitoringAgent'
$InstallDirectory = Join-Path $env:ProgramData 'NexusControl\Agent'
$InstalledScript = Join-Path $InstallDirectory 'nexus-windows-agent.ps1'

function Assert-Administrator {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($identity)
    if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        throw 'Run PowerShell as Administrator to install the Nexus Control agent.'
    }
}

function Install-NexusAgent {
    Assert-Administrator

    if ([string]::IsNullOrWhiteSpace($ApiUrl)) {
        $ApiUrl = Read-Host 'Nexus API URL (example: http://172.16.12.210:8000/api)'
    }
    $ApiUrl = $ApiUrl.TrimEnd('/')

    $secureToken = Read-Host 'Paste the new sma_ agent token' -AsSecureString
    $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secureToken)
    try {
        $plainToken = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    }
    finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
    }

    if ([string]::IsNullOrWhiteSpace($plainToken) -or -not $plainToken.StartsWith('sma_')) {
        throw 'The token is empty or invalid. Issue a new agent credential in Nexus Control.'
    }

    New-Item -ItemType Directory -Path $InstallDirectory -Force | Out-Null
    Copy-Item -LiteralPath $PSCommandPath -Destination $InstalledScript -Force

    [Environment]::SetEnvironmentVariable('NEXUS_AGENT_TOKEN', $plainToken, 'Machine')
    [Environment]::SetEnvironmentVariable('NEXUS_AGENT_API_URL', $ApiUrl, 'Machine')
    $plainToken = $null

    $action = New-ScheduledTaskAction `
        -Execute 'powershell.exe' `
        -Argument ('-NoLogo -NoProfile -NonInteractive -ExecutionPolicy Bypass -File "{0}"' -f $InstalledScript)
    $trigger = New-ScheduledTaskTrigger `
        -Once `
        -At (Get-Date).AddSeconds(15) `
        -RepetitionInterval (New-TimeSpan -Minutes 1)
    $settings = New-ScheduledTaskSettingsSet `
        -StartWhenAvailable `
        -MultipleInstances IgnoreNew `
        -ExecutionTimeLimit (New-TimeSpan -Minutes 2)

    Register-ScheduledTask `
        -TaskName $TaskName `
        -Action $action `
        -Trigger $trigger `
        -Settings $settings `
        -User 'SYSTEM' `
        -RunLevel Highest `
        -Force | Out-Null

    Write-Host 'Nexus Control agent installed. Sending the first sample now...' -ForegroundColor Green
    & $InstalledScript
    Write-Host "Scheduled task '$TaskName' will send metrics every minute." -ForegroundColor Green
}

function Get-NexusMetrics {
    $timestamp = (Get-Date).ToUniversalTime().ToString('o')

    $processors = @(Get-CimInstance -ClassName Win32_Processor)
    $cpu = if ($processors.Count -gt 0) {
        ($processors | Measure-Object -Property LoadPercentage -Average).Average
    } else { 0 }

    $operatingSystem = Get-CimInstance -ClassName Win32_OperatingSystem
    $totalMemory = [double]$operatingSystem.TotalVisibleMemorySize
    $freeMemory = [double]$operatingSystem.FreePhysicalMemory
    $memoryPercent = if ($totalMemory -gt 0) {
        (($totalMemory - $freeMemory) / $totalMemory) * 100
    } else { 0 }

    $uptime = [math]::Max(0, [int64]((Get-Date) - $operatingSystem.LastBootUpTime).TotalSeconds)
    $metrics = @(
        @{ type = 'cpu_percent'; value = [math]::Round([double]$cpu, 2); recorded_at = $timestamp },
        @{ type = 'memory_percent'; value = [math]::Round($memoryPercent, 2); recorded_at = $timestamp },
        @{ type = 'uptime_seconds'; value = $uptime; recorded_at = $timestamp },
        @{ type = 'process_count'; value = @(Get-Process).Count; recorded_at = $timestamp }
    )

    foreach ($disk in @(Get-CimInstance -ClassName Win32_LogicalDisk -Filter 'DriveType = 3')) {
        if ([double]$disk.Size -le 0) { continue }
        $usedPercent = (([double]$disk.Size - [double]$disk.FreeSpace) / [double]$disk.Size) * 100
        $metrics += @{
            type = 'disk_percent'
            key = [string]$disk.DeviceID
            value = [math]::Round($usedPercent, 2)
            recorded_at = $timestamp
        }
    }

    return @{ Metrics = $metrics; Uptime = $uptime; OperatingSystem = $operatingSystem }
}

if ($Install) {
    Install-NexusAgent
    exit 0
}

$token = [Environment]::GetEnvironmentVariable('NEXUS_AGENT_TOKEN', 'Machine')
$configuredApiUrl = [Environment]::GetEnvironmentVariable('NEXUS_AGENT_API_URL', 'Machine')
if ([string]::IsNullOrWhiteSpace($token) -or [string]::IsNullOrWhiteSpace($configuredApiUrl)) {
    throw 'Agent configuration is missing. Run this script with -Install first.'
}

$sample = Get-NexusMetrics
$body = @{
    agent_version = $AgentVersion
    uptime_seconds = $sample.Uptime
    payload = @{
        hostname = $env:COMPUTERNAME
        windows_version = [string]$sample.OperatingSystem.Caption
        windows_build = [string]$sample.OperatingSystem.BuildNumber
    }
    metrics = $sample.Metrics
} | ConvertTo-Json -Depth 6 -Compress

$response = Invoke-RestMethod `
    -Method Post `
    -Uri ($configuredApiUrl.TrimEnd('/') + '/agent/heartbeat') `
    -Headers @{ Authorization = "Bearer $token" } `
    -ContentType 'application/json' `
    -Body $body `
    -TimeoutSec 30

Write-Output ('Nexus heartbeat sent at {0}; metrics accepted: {1}' -f (Get-Date), $response.data.metrics_accepted)
