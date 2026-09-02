# Build the Mark As Sold package from the local dev install and deploy it to the live forum over SSH.
#
# Usage (PowerShell, from anywhere):
#   pwsh dev/tools/deploy.ps1                   # build, rehearse the upgrade locally, deploy
#   pwsh dev/tools/deploy.ps1 -BuildOnly        # only build the .tar (for manual upload via AdminCP)
#   pwsh dev/tools/deploy.ps1 -NoPhpFpmRestart  # skip the php-fpm restart at the end
#
# You will be asked for your SSH password (unless you have an SSH key set up)
# and then for your sudo password on the server.
#
# What it does:
#   1. Runs the unit tests (dev/tests) and checks that the local IPS dev install and MySQL are up.
#   2. Syncs this repository into the local IN_DEV install (applications/markassold).
#   3. Builds the package there, exactly like Developer Center > Build, into a .tar, and checks
#      its contents (required files present, nothing from dev/ or .git, no custom upgrade routines).
#   4. Runs the upgrade routine against the LOCAL dev install first, as a rehearsal.
#   5. Uploads the .tar and dev/tools/remote-upgrade.php to the server.
#   6. On the server: backs up the live app, removes files that are no longer in the package,
#      extracts the package, normalises ownership and permissions (644/755), runs the AdminCP
#      upgrade routine as the web server user (tables, settings rows, language strings, templates,
#      recorded version, cache clear) and restarts php-fpm so opcache picks up the new files.
#
# Why not just copy files (like other plugins): a plain copy never runs the install routine,
# so new tables, settings rows and language strings would be missing. See README "Deploying".
#
# Rollback: the script prints the backup path. To restore:
#   sudo tar -xzf ~/markassold-backup-<stamp>.tgz -C /home/www/www/ipb.saltvattensguiden.se/docs/applications
#   then run the previous package's upgrade routine, or re-upload the previous .tar in the AdminCP.
#   Database tables added by a newer version are left in place; that is harmless.

[CmdletBinding()]
param(
	[string] $SshUser    = "mr_alex",
	[string] $SshHost    = "saltvattensguiden.se",
	[int]    $SshPort    = 22,
	[string] $RemoteRoot = "/home/www/www/ipb.saltvattensguiden.se/docs",   # folder containing init.php
	[string] $Owner      = "www-data:www-data",
	[string] $LocalIps   = "C:\laragon\www\ips5",                            # local IN_DEV install
	[string] $PhpExe     = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe",
	[string] $PhpIni     = "",                                               # php.ini to use; auto-detected next to php.exe if empty
	[string[]] $PhpExtensions = @( "mysqli", "pdo_mysql", "mbstring", "curl", "gd", "openssl", "fileinfo", "exif", "intl", "zip", "sodium" ),
	[switch] $BuildOnly,
	[switch] $SkipTests,
	[switch] $AllowDirty,
	[switch] $NoPhpFpmRestart    # by default php-fpm is restarted so opcache serves the new files
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
if ( -not (Test-Path $PhpExe) )                          { Fail "PHP not found: $PhpExe" }
if ( -not (Test-Path (Join-Path $LocalIps "init.php")) ) { Fail "Local IPS install not found: $LocalIps (no init.php)" }
if ( -not (Test-Path $localApp) )                        { Fail "App is not installed in the local dev install: $localApp" }
if ( -not (Select-String -Path (Join-Path $LocalIps "constants.php") -Pattern "IN_DEV'\s*,\s*TRUE" -Quiet) ) { Fail "$LocalIps is not in developer mode (IN_DEV must be TRUE in constants.php)" }
if ( -not (Get-Process mysqld -ErrorAction SilentlyContinue) ) { Fail "MySQL is not running. Start it in Laragon first; the build reads and writes the local IPS database." }

$phpArgs = Get-PhpArgs
& $PhpExe @phpArgs -r "exit( ( extension_loaded('mysqli') && extension_loaded('mbstring') ) ? 0 : 1 );"
if ( $LASTEXITCODE -ne 0 ) { Fail "PHP CLI cannot load mysqli/mbstring. Pass -PhpIni <path to php.ini> or adjust -PhpExtensions." }

$dirty = git -C $repo status --porcelain
$head  = git -C $repo rev-parse --short HEAD
if ( $dirty -and -not $AllowDirty ) { Fail "Working tree has uncommitted changes. Commit first, or pass -AllowDirty.`n$($dirty -join "`n")" }
Write-Host "Repo: $repo @ $head$( if ($dirty) { ' (dirty: deploying the working tree, not the commit)' } )"

if ( -not $SkipTests ) {
	Step "Unit tests"
	& $PhpExe @phpArgs (Join-Path $repo "dev\tests\run.php")
	if ( $LASTEXITCODE -ne 0 ) { Fail "Tests failed" }
}

# ---------------------------------------------------------------- 2. Sync repo -> local dev install
Step "Sync repository into $localApp"
# The dev install's generated data/*.xml are kept; the build regenerates them. Everything else mirrors the repo.
robocopy $repo $localApp /MIR /XD .git /XF *.xml /NFL /NDL /NJH /NJS | Out-Null
if ( $LASTEXITCODE -ge 8 ) { Fail "robocopy failed with exit code $LASTEXITCODE" }
Write-Host "Synced"

# ---------------------------------------------------------------- 3. Build package
Step "Build package"
New-Item -ItemType Directory -Force $outDir | Out-Null
if ( Test-Path $tarPath ) { Remove-Item $tarPath -Force }
& $PhpExe @phpArgs (Join-Path $repo "dev\tools\build.php") $LocalIps $AppDir $tarPath
if ( $LASTEXITCODE -ne 0 -or -not (Test-Path $tarPath) ) { Fail "Build failed" }

$entries = @( & tar -tf $tarPath ) | ForEach-Object { $_.TrimEnd('/') }
if ( $LASTEXITCODE -ne 0 ) { Fail "Cannot read $tarPath" }
foreach ( $required in @( "Application.php", "data/lang.xml", "data/application.json", "data/schema.json", "sources/TagLogic/TagLogic.php" ) ) {
	if ( $entries -notcontains $required ) { Fail "Package is missing $required" }
}
$leaked = $entries | Where-Object { $_ -match '^(dev/|\.git|docs/|superpowers/)' }
if ( $leaked ) { Fail "Package contains files that must not ship:`n$($leaked -join "`n")" }
$customRoutines = $entries | Where-Object { $_ -match '^setup/upg_\d+/upgrade\.php$' }
if ( $customRoutines ) { Fail "Package contains custom upgrade routines ($($customRoutines -join ', ')). This script cannot run those; upload the .tar through the AdminCP instead." }
$dbSteps = $entries | Where-Object { $_ -match '^setup/upg_\d+/queries\.json$' }
Write-Host "Package OK: $($entries.Count) entries, $([math]::Round((Get-Item $tarPath).Length / 1KB)) KB -> $tarPath"
if ( $dbSteps ) { Write-Host "Database steps in package: $($dbSteps -join ', ')" }

if ( $BuildOnly ) {
	Write-Host "`nBuild only. Upload $tarPath via AdminCP > System > Site Features > Applications > Upload." -ForegroundColor Green
	exit 0
}

# ---------------------------------------------------------------- 4. Rehearse the upgrade routine locally
Step "Rehearse upgrade routine on the local dev install"
& $PhpExe @phpArgs (Join-Path $repo "dev\tools\remote-upgrade.php") $LocalIps $AppDir
if ( $LASTEXITCODE -ne 0 ) { Fail "The upgrade routine failed on the local dev install; not deploying." }

# ---------------------------------------------------------------- 5. Upload
Step "Upload to $SshUser@$SshHost"
$remoteTmp = "~/markassold-deploy"
ssh -p $SshPort "$SshUser@$SshHost" "rm -rf $remoteTmp && mkdir -p $remoteTmp"
if ( $LASTEXITCODE -ne 0 ) { Fail "ssh failed" }
scp -q -P $SshPort $tarPath (Join-Path $repo "dev\tools\remote-upgrade.php") "$SshUser@${SshHost}:$remoteTmp/"
if ( $LASTEXITCODE -ne 0 ) { Fail "scp failed" }
Write-Host "Uploaded"

# ---------------------------------------------------------------- 6. Install on the server
Step "Install on server"
$remoteApp = "$RemoteRoot/applications/$AppDir"
$webUser   = $Owner.Split(":")[0]
$fpmStep   = if ( $NoPhpFpmRestart ) {
	"echo 'php-fpm not restarted (-NoPhpFpmRestart). If the change does not show, opcache may still serve old files.'"
} else {
@"
FPM="`$(systemctl list-units --type=service --state=active --plain --no-legend 'php*fpm*' 2>/dev/null | awk '{print `$1}' | head -n 1)"
if [ -n "`$FPM" ]; then
  if sudo systemctl restart "`$FPM"; then echo "Restarted `$FPM"; else echo "WARNING: could not restart `$FPM; restart PHP manually if the change does not show"; fi
else
  echo 'No active php-fpm unit found; if the change does not show, restart PHP manually'
fi
"@
}
# Notes on the remote script:
#  - The package is the complete application, and IPS never writes into applications/<app>/ at runtime,
#    so files that are not in the package are stale (old builds shipped docs/, superpowers/ and an
#    extensions/core/ContentMenu/ controller) and are removed before extracting.
#  - The .tar is built on Windows and carries 0666 modes; root's tar would apply them, so permissions
#    are normalised to 644/755 before any PHP runs.
#  - remote-upgrade.php runs from a world-readable temp dir because www-data cannot read ~mr_alex.
$remoteScript = @"
set -e
PHP="`$(command -v php || true)"
if [ -z "`$PHP" ]; then echo 'php CLI not found on the server; upload the .tar via AdminCP instead'; exit 3; fi
test -f '$RemoteRoot/init.php' || { echo 'init.php not found under $RemoteRoot'; exit 3; }
test -d '$remoteApp' || { echo 'App is not installed on the server: $remoteApp (install once via AdminCP)'; exit 3; }
WORK="`$(mktemp -d /tmp/markassold-deploy.XXXXXX)"
cp $remoteTmp/$AppDir.tar $remoteTmp/remote-upgrade.php "`$WORK/"
chmod 755 "`$WORK"; chmod 644 "`$WORK"/*
sudo tar -czf ~/markassold-backup-$stamp.tgz -C '$RemoteRoot/applications' $AppDir
echo "Backup: ~/markassold-backup-$stamp.tgz"
tar -tf "`$WORK/$AppDir.tar" | sed 's#/`$##' | sort -u > "`$WORK/manifest"
( cd '$remoteApp' && sudo find . -type f -printf '%P\n' ) | sort -u | comm -23 - "`$WORK/manifest" > "`$WORK/stale" || true
if [ -s "`$WORK/stale" ]; then
  echo 'Removing files no longer in the package:'; sed 's/^/  /' "`$WORK/stale"
  ( cd '$remoteApp' && sudo xargs -d '\n' rm -f -- ) < "`$WORK/stale"
  sudo find '$remoteApp' -mindepth 1 -depth -type d -empty -delete
fi
sudo tar --no-same-permissions --no-same-owner -xf "`$WORK/$AppDir.tar" -C '$remoteApp'
sudo chown -R $Owner '$remoteApp'
sudo chmod -R u=rwX,go=rX '$remoteApp'
sudo -u $webUser "`$PHP" "`$WORK/remote-upgrade.php" '$RemoteRoot' $AppDir
$fpmStep
rm -rf "`$WORK" $remoteTmp
echo "Deployed. Backup on server: ~/markassold-backup-$stamp.tgz"
"@
# A checkout with core.autocrlf=true gives this file CRLF line endings; bash must not see the CRs.
$remoteScript = $remoteScript -replace "`r", ""
# -t: sudo on the server needs a tty for its password prompt
ssh -t -p $SshPort "$SshUser@$SshHost" $remoteScript
if ( $LASTEXITCODE -ne 0 ) { Fail "Remote install failed (the backup is still on the server as ~/markassold-backup-$stamp.tgz)" }

Remove-Item $outDir -Recurse -Force -ErrorAction SilentlyContinue
Write-Host "`nDone ($head). Now test on the live forum: mark, unmark, moderator, both tag slots." -ForegroundColor Green
