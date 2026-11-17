#Requires -RunAsAdministrator
# Allow this clone only from the local Wi-Fi subnet, keeping Docker's other blocked ports blocked.
$ErrorActionPreference = 'Stop'
$lanAddress = '192.168.1.154'
$lanSubnet = '192.168.1.0/24'
$lanPort = 8081
$backend = Join-Path $env:LOCALAPPDATA 'Programs\DockerDesktop\resources\com.docker.backend.exe'
$logPath = Join-Path $PSScriptRoot '..\.local-lan\firewall-result.txt'
try {
    $filters = Get-NetFirewallRule -Direction Inbound -Enabled True -Action Block |
        Get-NetFirewallApplicationFilter | Where-Object { $_.Program -eq $backend }
    foreach ($filter in $filters) {
        $rule = Get-NetFirewallRule -AssociatedNetFirewallApplicationFilter $filter
        $portFilter = $rule | Get-NetFirewallPortFilter
        if ($portFilter.Protocol -in @('TCP', '6') -and $portFilter.LocalPort -eq 'Any') {
            $rule | Select-Object Name,DisplayName,Profile | ConvertTo-Json -Compress |
                Add-Content -LiteralPath (Join-Path $PSScriptRoot '..\.local-lan\firewall-original-rules.jsonl')
            $portFilter | Set-NetFirewallPortFilter -LocalPort @('1-8080', '8082-65535')
        }
    }
    if (-not (Get-NetFirewallRule -Name 'ProgramaConsulta-LAN-8081' -ErrorAction SilentlyContinue)) {
        New-NetFirewallRule -Name 'ProgramaConsulta-LAN-8081' -DisplayName 'ProgramaConsulta LAN - WiFi 8081' `
            -Direction Inbound -Action Allow -Protocol TCP -LocalPort $lanPort `
            -LocalAddress $lanAddress -RemoteAddress $lanSubnet -InterfaceAlias 'WiFi' `
            -Profile Any -Program $backend | Out-Null
    }
    'OK: Wi-Fi subnet access enabled for 192.168.1.154:8081.' | Set-Content -LiteralPath $logPath
} catch {
    $_.Exception.Message | Set-Content -LiteralPath $logPath
    exit 1
}
