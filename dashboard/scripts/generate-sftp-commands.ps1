# Generates SFTP mkdir and put commands for DASH-13 production upload.
# Usage: powershell -File scripts/generate-sftp-commands.ps1 > ../docs/dashboard/DASH-13-SFTP-COMMANDS.txt

$root = Split-Path $PSScriptRoot -Parent
$repo = Split-Path $root -Parent
$dash = $root
$excludeDirs = @('node_modules', '.next', 'test-results', 'playwright-report', 'tests')
$excludeFiles = @(
    '.gitignore', 'eslint.config.mjs', 'playwright.config.ts', 'playwright.reuse.config.ts',
    'scripts/sync-dashboard-export.mjs', 'tsconfig.tsbuildinfo', 'styles/tokens.md',
    'scripts/generate-sftp-commands.ps1'
)

$laravelFiles = @(
    'app/Http/Controllers/BackOffice/BackOfficeDashboardController.php',
    'config/dashboard.php',
    'routes/admin.php',
    'routes/staff.php',
    'routes/web.php'
)

Write-Output "# DASH-13 SFTP mkdir commands"
Write-Output "mkdir /home/pkjetp/jetpk_app/app/Http/Controllers/BackOffice"
Write-Output "mkdir /home/pkjetp/jetpk_app/config"
Write-Output "mkdir /home/pkjetp/jetpk_app/dashboard"

$dirs = Get-ChildItem -Path $dash -Recurse -Directory | Where-Object {
    $p = $_.FullName
    -not ($excludeDirs | Where-Object { $p -match [regex]::Escape((Join-Path $dash $_)) })
} | ForEach-Object {
    $_.FullName.Replace("$dash\", '').Replace('\', '/')
} | Sort-Object -Unique

foreach ($d in $dirs) {
    if ($d) { Write-Output "mkdir /home/pkjetp/jetpk_app/dashboard/$d" }
}

Write-Output ""
Write-Output "# Laravel put commands"
foreach ($f in $laravelFiles) {
    $local = Join-Path $repo ($f -replace '/', '\')
    Write-Output "put `"$local`" `"/home/pkjetp/jetpk_app/$f`""
}

Write-Output ""
Write-Output "# Dashboard put commands"
Get-ChildItem -Path $dash -Recurse -File | Where-Object {
    $p = $_.FullName
    -not ($excludeDirs | Where-Object { $p -match [regex]::Escape((Join-Path $dash $_)) })
} | ForEach-Object {
    $rel = $_.FullName.Replace("$dash\", '').Replace('\', '/')
    if ($excludeFiles -notcontains $rel) {
        Write-Output "put `"$($_.FullName)`" `"/home/pkjetp/jetpk_app/dashboard/$rel`""
    }
} | Sort-Object
