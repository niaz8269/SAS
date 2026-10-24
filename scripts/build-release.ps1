<#
  Builds the zip you upload to the server (Windows / PowerShell). Nothing is deployed and no server is touched.

    .\scripts\build-release.ps1                       # uses `php` and `composer` from the PATH
    .\scripts\build-release.ps1 -Php C:\xampp\php\php.exe -Composer C:\tools\composer.phar

  What it does:
    1. Exports the committed files only (git archive), so .env, the local database, uploads, logs and anything untracked can never end up in the zip.
    2. Installs PHP packages without the development ones (composer install --no-dev --optimize-autoloader).
    3. Builds the CSS/JS (npm ci, npm run build).
    4. Removes tests, docs for developers and tooling files, then zips the result to dist\sas-release-<date>.zip.

  The content bundle (data\launch\sas-content.jsonl.gz) is included; load it on the server with `php artisan sas:import-content --replace`.
  Commit first: the zip is built from HEAD, and the script stops if there are uncommitted changes to tracked files.
#>
param(
    [string]$Php = 'php',
    [string]$Composer = 'composer',
    [string]$Out = ''
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

function Run($label, [scriptblock]$block) {
    Write-Host "==> $label"
    & $block
    if ($LASTEXITCODE -ne 0) { throw "$label failed (exit $LASTEXITCODE)" }
}

if (git status --porcelain --untracked-files=no) { throw 'There are uncommitted changes to tracked files. Commit them first, so the release matches a commit.' }
if (-not (Test-Path 'data\launch\sas-content.jsonl.gz')) { Write-Warning 'data\launch\sas-content.jsonl.gz is missing (php artisan sas:export-content). The release will have no content.' }

$commit = (git rev-parse --short HEAD).Trim()
$stamp = Get-Date -Format 'yyyy-MM-dd'
$work = Join-Path ([IO.Path]::GetTempPath()) "sas-release-$commit"
if (Test-Path $work) { Remove-Item $work -Recurse -Force }
New-Item -ItemType Directory $work | Out-Null

$tar = Join-Path $work 'src.tar'
Run 'Export committed files' { git archive --format=tar --output=$tar HEAD }
$site = Join-Path $work 'site'
New-Item -ItemType Directory $site | Out-Null
Run 'Unpack' { tar -xf $tar -C $site }
Remove-Item $tar

# The bundle may be newer than the last commit; take the working copy.
if (Test-Path 'data\launch\sas-content.jsonl.gz') {
    New-Item -ItemType Directory (Join-Path $site 'data\launch') -Force | Out-Null
    Copy-Item 'data\launch\sas-content.jsonl.gz' (Join-Path $site 'data\launch\') -Force
}

Push-Location $site
try {
    if ($Composer -like '*.phar') { Run 'composer install (no dev)' { & $Php $Composer install --no-dev --optimize-autoloader --no-interaction --no-progress } }
    else { Run 'composer install (no dev)' { & $Composer install --no-dev --optimize-autoloader --no-interaction --no-progress } }
    Run 'npm ci' { npm ci --no-audit --no-fund }
    Run 'npm run build' { npm run build }

    foreach ($p in 'node_modules', 'tests', '.github', 'scripts', 'phpunit.xml', 'pint.json', 'package.json', 'package-lock.json', 'vite.config.js', 'AGENTS.md', 'CLAUDE.md', '.editorconfig', '.gitattributes', '.claude', 'phpstan.neon', '.npmrc') {
        if (Test-Path $p) { Remove-Item $p -Recurse -Force }
    }
    # Folders the application writes to must exist and be writable on the server.
    foreach ($d in 'storage\app\public', 'storage\app\backups', 'storage\framework\cache\data', 'storage\framework\sessions', 'storage\framework\views', 'storage\logs', 'bootstrap\cache') {
        New-Item -ItemType Directory $d -Force | Out-Null
    }
}
finally { Pop-Location }

if (-not $Out) { $Out = Join-Path $root "dist\sas-release-$stamp-$commit.zip" }
New-Item -ItemType Directory (Split-Path $Out) -Force | Out-Null
if (Test-Path $Out) { Remove-Item $Out }
Write-Host '==> Zip'
Compress-Archive -Path (Join-Path $site '*') -DestinationPath $Out -CompressionLevel Optimal
Remove-Item $work -Recurse -Force

$mb = [math]::Round((Get-Item $Out).Length / 1MB, 1)
Write-Host "Done: $Out ($mb MB). Upload it, unzip it, copy .env.production.example to .env and follow docs\LAUNCH.md."
