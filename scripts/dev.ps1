<#
  Local development runner (Windows / PowerShell).
  Starts the web server, the queue worker and the scheduler in separate windows.

    .\scripts\dev.ps1            # start everything
    .\scripts\dev.ps1 -Fresh     # first reset the database and load the fictional demo records
    .\scripts\dev.ps1 -Build     # rebuild the CSS/JS first

  Stop by closing the three windows. Nothing here is for production.
#>
param([switch]$Fresh, [switch]$Build)

$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

# Find PHP (XAMPP default) and Node without requiring them on PATH.
$phpDir = @('C:\xampp\php', "$env:ProgramFiles\PHP", 'C:\php') | Where-Object { Test-Path "$_\php.exe" } | Select-Object -First 1
if (-not $phpDir) { Write-Error 'PHP 8.4 not found. Install it or edit $phpDir in scripts/dev.ps1.'; exit 1 }
$env:Path = "$phpDir;$env:ProgramFiles\nodejs;" + $env:Path

if (-not (Test-Path .env)) { Copy-Item .env.example .env; php artisan key:generate | Out-Null }
if (-not (Select-String -Path .env -Pattern '^SAS_SCANNER=fake' -Quiet)) {
    Add-Content .env "`nSAS_SCANNER=fake"   # local only: no ClamAV here, so uploads would stay 'Pending scan' forever
}
if (-not (Test-Path database\database.sqlite)) { New-Item database\database.sqlite -ItemType File | Out-Null }
if (-not (Test-Path vendor)) { composer install }
if ($Build -or -not (Test-Path public\build\manifest.json)) { npm install; npm run build }

if ($Fresh) { php artisan migrate:fresh --force --seed --seeder=DemoSeeder } else { php artisan migrate --force }

$shell = (Get-Command powershell.exe).Source
foreach ($cmd in @(
    'php artisan serve --host=127.0.0.1 --port=8000',
    'php artisan queue:work --queue=default,mail,reminders,scanning --tries=3 --sleep=2',
    'php artisan schedule:work'
)) {
    Start-Process $shell -ArgumentList '-NoExit', '-Command', "`$env:Path='$env:Path'; Set-Location '$root'; $cmd"
}

Write-Host ''
Write-Host 'SAS is starting at http://127.0.0.1:8000' -ForegroundColor Green
Write-Host 'Staff admin:  http://127.0.0.1:8000/admin   (demo-reviewer@example.test / demo-password-123, set up an authenticator on first login)'
Write-Host 'Student demo: demo-student@example.test / demo-password-123'
Write-Host 'Outgoing mail is written to storage\logs\laravel.log (MAIL_MAILER=log).'
