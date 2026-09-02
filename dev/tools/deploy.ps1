# Build the Mark As Sold package from the local dev install and deploy it to the live forum over SSH.
#
# Usage (PowerShell, from anywhere):
#   pwsh dev/tools/deploy.ps1                 # build + deploy
#   pwsh dev/tools/deploy.ps1 -BuildOnly      # only build the .tar (for manual upload via AdminCP)
#
# You will be asked for your SSH password (unless you have an SSH key set up)
# and then for your sudo password on the server.
#
# What it does:
#   1. Runs the unit tests (dev/tests) and checks that the local IPS dev install and MySQL are up.
#   2. Syncs this repository into the local IN_DEV install (applications/markassold).
#   3. Builds the package there, exactly like Developer Center > Build, into a .tar.
#   4. Uploads the .tar and dev/tools/remote-upgrade.php to the server.
#   5. On the server: backs up the live app, extracts the package over it, removes stale
#      folders from older builds, fixes ownership, and runs the AdminCP upgrade routine
#      (settings rows, language strings, templates, recorded version, cache clear).
#
# Why not just copy files (like other plugins): a plain copy never runs the install routine,
# so new settings rows and language strings would be missing. See README "Deploy checklist".

[CmdletBinding()]
param(
	[string] $SshUser    = "mr_alex",
	[string] $SshHost    = "saltvattensguiden.se",
	[string] $RemoteRoot = "/home/www/www/ipb.saltvattensguiden.se/docs",   # folder containing init.php
	[string] $Owner      = "www-data:www-data",
	[string] $LocalIps   = "C:\laragon\www\ips5",                            # local IN_DEV install
	[string] $PhpExe     = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe",
	[string] $PhpIni     = "",                                               # php.ini to use; auto-detected next to php.exe if empty
	[string[]] $PhpExtensions = @( "mysqli", "pdo_mysql", "mbstring", "curl", "gd", "openssl", "fileinfo", "exif", "intl", "zip", "sodium" ),
	[switch] $BuildOnly,
	[switch] $SkipTests,
	[switch] $AllowDirty
)

$ErrorActionPreference = "Stop"
$AppDir   = "markassold"
$repo     = (Resolve-Path (Join-Path $PSScriptRoot "..\..")).Path
$localApp = Join-Path $LocalIps "applications\$AppDir"
$stamp    = Get-Date -Format "yyyyMMdd-HHmmss"
$outDir   = Join-Path $env:TEMP "markassold-deploy"
$tarPath  = Join-Path $outDir "$AppDir.tar"

function Fail( [string] $msg ) { Write-Host "ERROR: $msg" -ForegroundColor Red; exit 1 }
function Step( [string] $msg ) { Write-Host "`n== $msg" -ForegroundColor Cyan }

# PHP invocation: use a php.ini when one exists, otherwise load the needed extensions explicitly
# (Laragon's bundled CLI PHP ships without a php.ini).
function Get-PhpArgs {
	$phpDir = Split-Path $PhpExe
	$ini = $PhpIni
	if ( -not $ini -and (Test-Path (Join-Path $phpDir "php.ini")) ) { $ini = Join-Path $phpDir "php.ini" }
	if ( $ini ) {
		if ( -not (Test-Path $ini) ) { Fail "php.ini not found: $ini" }
		return @( "-c", $ini )
	}
	$list = @( "-d", "extension_dir=$phpDir\ext", "-d", "memory_limit=512M" )
	foreach ( $e in $PhpExtensions ) { $list += @( "-d", "extension=$e" ) }
	return $list
}

# ---------------------------------------------------------------- 1. Preflight
Step "Preflight"
if ( -not (Test-Path $PhpExe) )                       { Fail "PHP not found: $PhpExe" }
if ( -not (Test-Path (Join-Path $LocalIps "init.php")) ) { Fail "Local IPS install not found: $LocalIps (no init.php)" }
if ( -not (Test-Path $localApp) )                     { Fail "App is not installed in the local dev install: $localApp" }
if ( -not (Select-String -Path (Join-Path $LocalIps "constants.php") -Pattern "IN_DEV'\s*,\s*TRUE" -Quiet) ) { Fail "$LocalIps is not in developer mode (IN_DEV must be TRUE in constants.php)" }
if ( -not (Get-Process mysqld -ErrorAction SilentlyContinue) ) { Fail "MySQL is not running. Start it in Laragon first; the build reads and writes the local IPS database." }

$phpArgs = Get-PhpArgs
& $PhpExe @phpArgs -r "exit( ( extension_loaded('mysqli') && extension_loaded('mbstring') ) ? 0 : 1 );"
if ( $LASTEXITCODE -ne 0 ) { Fail "PHP CLI cannot load mysqli/mbstring. Pass -PhpIni <path to php.ini> or adjust -PhpExtensions." }

$dirty = git -C $repo status --porcelain
$head  = git -C $repo rev-parse --short HEAD
if ( $dirty -and -not $AllowDirty ) { Fail "Working tree has uncommitted changes. Commit first, or pass -AllowDirty.`n$($dirty -join "`n")" }
Write-Host "Repo: $repo @ $head$( if ($dirty) { ' (dirty)' } )"

if ( -not $SkipTests ) {
	Step "Unit tests"
	& $PhpExe @phpArgs (Join-Path $repo "dev\tests\run.php")
	if ( $LASTEXITCODE -ne 0 ) { Fail "Tests failed" }
}

# ---------------------------------------------------------------- 2. Sync repo -> local dev install
Step "Sync repository into $localApp"
# Keep the dev install's generated files (data/*.xml, setup/upg_*) - the build regenerates them.
robocopy $repo $localApp /MIR /XD .git setup /XF *.xml /NFL /NDL /NJH /NJS | Out-Null
if ( $LASTEXITCODE -ge 8 ) { Fail "robocopy failed with exit code $LASTEXITCODE" }
Write-Host "Synced"

# ---------------------------------------------------------------- 3. Build package
Step "Build package"
New-Item -ItemType Directory -Force $outDir | Out-Null
if ( Test-Path $tarPath ) { Remove-Item $tarPath -Force }
& $PhpExe @phpArgs (Join-Path $repo "dev\tools\build.php") $LocalIps $AppDir $tarPath
if ( $LASTEXITCODE -ne 0 -or -not (Test-Path $tarPath) ) { Fail "Build failed" }

$entries = & tar -tf $tarPath
if ( $LASTEXITCODE -ne 0 ) { Fail "Cannot read $tarPath" }
foreach ( $required in @( "Application.php", "data/lang.xml", "data/application.json", "sources/TagLogic/TagLogic.php" ) ) {
	if ( $entries -notcontains $required ) { Fail "Package is missing $required" }
}
$leaked = $entries | Where-Object { $_ -match '^(dev/|\.git|docs/|superpowers/)' }
if ( $leaked ) { Fail "Package contains files that must not ship:`n$($leaked -join "`n")" }
Write-Host "Package OK: $($entries.Count) entries, $([math]::Round((Get-Item $tarPath).Length / 1KB)) KB -> $tarPath"

if ( $BuildOnly ) {
	Write-Host "`nBuild only. Upload $tarPath via AdminCP > System > Site Features > Applications > Upload." -ForegroundColor Green
	exit 0
}

# ---------------------------------------------------------------- 4. Upload
Step "Upload to $SshUser@$SshHost"
$remoteTmp = "~/markassold-deploy"
ssh "$SshUser@$SshHost" "rm -rf $remoteTmp && mkdir -p $remoteTmp"
if ( $LASTEXITCODE -ne 0 ) { Fail "ssh failed" }
scp -q $tarPath (Join-Path $repo "dev\tools\remote-upgrade.php") "$SshUser@${SshHost}:$remoteTmp/"
if ( $LASTEXITCODE -ne 0 ) { Fail "scp failed" }
Write-Host "Uploaded"

# ---------------------------------------------------------------- 5. Install on the server
Step "Install on server"
$remoteApp  = "$RemoteRoot/applications/$AppDir"
$webUser    = $Owner.Split(":")[0]
$remoteScript = @"
set -e
PHP="`$(command -v php || true)"
if [ -z "`$PHP" ]; then echo 'php CLI not found on the server; upload the .tar via AdminCP instead'; exit 3; fi
test -f '$RemoteRoot/init.php' || { echo 'init.php not found under $RemoteRoot'; exit 3; }
test -d '$remoteApp' || { echo 'App is not installed on the server: $remoteApp (install once via AdminCP)'; exit 3; }
sudo tar -czf ~/markassold-backup-$stamp.tgz -C '$RemoteRoot/applications' $AppDir
sudo tar -xf $remoteTmp/$AppDir.tar -C '$remoteApp'
for stale in docs superpowers dev extensions/core/ContentMenu; do sudo rm -rf "$remoteApp/`$stale"; done
sudo chown -R $Owner '$remoteApp'
sudo -u $webUser "`$PHP" $remoteTmp/remote-upgrade.php '$RemoteRoot' $AppDir
rm -rf $remoteTmp
echo "Deployed. Backup on server: ~/markassold-backup-$stamp.tgz"
"@
ssh -t "$SshUser@$SshHost" $remoteScript
if ( $LASTEXITCODE -ne 0 ) { Fail "Remote install failed (the backup is still on the server as ~/markassold-backup-$stamp.tgz)" }

Remove-Item $outDir -Recurse -Force -ErrorAction SilentlyContinue
Write-Host "`nDone ($head). Now test on the live forum: mark, unmark, moderator, both tag slots." -ForegroundColor Green
Write-Host "Auto-lock for topic authors requires the group setting 'Can lock and unlock own content?'."
