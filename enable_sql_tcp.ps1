Write-Host "Enabling TCP/IP for SQL Server..." -ForegroundColor Cyan

# Find all MSSQL instance registry paths
$instances = Get-ChildItem -Path "HKLM:\SOFTWARE\Microsoft\Microsoft SQL Server" | Where-Object { $_.PSChildName -like "MSSQL*" }

if ($instances.Count -eq 0) {
    Write-Error "No SQL Server instances found in registry."
    Read-Host "Press Enter to exit"
    exit
}

foreach ($inst in $instances) {
    $tcpPath = "HKLM:\SOFTWARE\Microsoft\Microsoft SQL Server\$($inst.PSChildName)\MSSQLServer\SuperSocketNetLib\Tcp"
    if (Test-Path $tcpPath) {
        Write-Host "Enabling TCP/IP for instance: $($inst.PSChildName)..."
        Set-ItemProperty -Path $tcpPath -Name Enabled -Value 1
        
        # Ensure port is 1433
        $ipAllPath = "$tcpPath\IPAll"
        if (Test-Path $ipAllPath) {
            Set-ItemProperty -Path $ipAllPath -Name TcpPort -Value "1433"
        }
    }
}

# Restart SQL Server services
$services = Get-Service | Where-Object { $_.Name -like "MSSQL*" -and $_.Status -eq "Running" }
foreach ($svc in $services) {
    Write-Host "Restarting service: $($svc.DisplayName)..."
    Restart-Service -Name $svc.Name -Force
}

Write-Host "Success! TCP/IP enabled on port 1433 and SQL Server restarted." -ForegroundColor Green
Read-Host "Press Enter to exit"
