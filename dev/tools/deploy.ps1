# Build the Mark As Sold package from the local dev install and deploy it to the live forum over SSH.
#
# Usage (PowerShell, from anywhere):
#   pwsh dev/tools/deploy.ps1                   # build and deploy
#   pwsh dev/tools/deploy.ps1 -BuildOnly        # only build the .tar (for manual upload via AdminCP)
#   pwsh dev/tools/deploy.ps1 -NoPhpFpmRestart  # skip the php-fpm restart at the end
#   pwsh dev/tools/deploy.ps1 -Full             # build with the local IN_DEV install + MySQL instead
#
# You will be asked for your SSH password (unless you have an SSH key set up)
# and then for your sudo password on the server.
#
# What it does:
#   1. Runs the unit tests (dev/tests).
#   2. Builds the package. Default ("lite", no database needed): stages the repository without
#      dev/ and .git, generates data/lang.xml, theme.xml, javascript.xml and emails.xml with
#      dev/tools/make-package-data.php, and tars it. With -Full: syncs the repository into the local
#      IN_DEV install, runs Developer Center > Build there (dev/tools/build.php) and rehearses the
#      upgrade routine against the local database first.
#   3. Checks the package: required files present, nothing from dev/ or .git, no custom upgrade routines.
#   4. Uploads the .tar and dev/tools/remote-upgrade.php to the server.
#   5. On the server: backs up the live app, removes files that are no longer in the package,
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
	[switch] $NoPhpFpmRestart,   # by default php-fpm is restarted so opcache serves the new files
	[switch] $Full               # build through the local IN_DEV install + MySQL (Developer Center build + local rehearsal)
)

$ErrorActionPreference = "Stop"
$AppDir   = "markassold"
$repo     = (Resolve-Path (Join-Path $PSScriptRoot "..\..")).Path
$localApp = Join-Path $LocalIps "applications\$AppDir"
$stamp    = Get-Date -Format "yyyyMMdd-HHmmss"
$outDir   = Join-Path $env:TEMP "markassold-deploy"
$stageDir = Join-Path $outDir "stage"
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
Step "Preflight ($( if ($Full) { 'full build via local IPS install' } else { 'lite build, no database needed' } ))"
if ( -not (Test-Path $PhpExe) ) { Fail "PHP not found: $PhpExe" }
if ( $Full ) {
	if ( -not (Test-Path (Join-Path $LocalIps "init.php")) ) { Fail "Local IPS install not found: $LocalIps (no init.php)" }
	if ( -not (Test-Path $localApp) )                        { Fail "App is not installed in the local dev install: $localApp" }
	if ( -not (Select-String -Path (Join-Path $LocalIps "constants.php") -Pattern "IN_DEV'\s*,\s*TRUE" -Quiet) ) { Fail "$LocalIps is not in developer mode (IN_DEV must be TRUE in constants.php)" }
	if ( -not (Get-Process mysqld -ErrorAction SilentlyContinue) ) { Fail "MySQL is not running. Start it in Laragon first, or drop -Full." }
}

$phpArgs = Get-PhpArgs
$needed  = if ( $Full ) { "extension_loaded('mysqli') && extension_loaded('mbstring')" } else { "extension_loaded('mbstring') && extension_loaded('xmlwriter')" }
& $PhpExe @phpArgs -r "exit( ( $needed ) ? 0 : 1 );"
if ( $LASTEXITCODE -ne 0 ) { Fail "PHP CLI cannot load the required extensions. Pass -PhpIni <path to php.ini> or adjust -PhpExtensions." }

$dirty = git -C $repo status --porcelain
$head  = git -C $repo rev-parse --short HEAD
if ( $dirty -and -not $AllowDirty ) { Fail "Working tree has uncommitted changes. Commit first, or pass -AllowDirty.`n$($dirty -join "`n")" }
Write-Host "Repo: $repo @ $head$( if ($dirty) { ' (dirty: deploying the working tree, not the commit)' } )"

if ( -not $SkipTests ) {
	Step "Unit tests"
	& $PhpExe @phpArgs (Join-Path $repo "dev\tests\run.php")
	if ( $LASTEXITCODE -ne 0 ) { Fail "Tests failed" }
}

# ---------------------------------------------------------------- 2. Build package
New-Item -ItemType Directory -Force $outDir | Out-Null
if ( Test-Path $tarPath ) { Remove-Item $tarPath -Force }
$previousLong = $null

if ( $Full ) {
	Step "Sync repository into $localApp"
	# The dev install's generated data/*.xml are kept; the build regenerates them. Everything else mirrors the repo.
	robocopy $repo $localApp /MIR /XD .git /XF *.xml /NFL /NDL /NJH /NJS | Out-Null
	if ( $LASTEXITCODE -ge 8 ) { Fail "robocopy failed with exit code $LASTEXITCODE" }
	Write-Host "Synced"

	Step "Build package (Developer Center build)"
	$buildOutput = & $PhpExe @phpArgs (Join-Path $repo "dev\tools\build.php") $LocalIps $AppDir $tarPath 2>&1
	$buildOutput | ForEach-Object { Write-Host $_ }
	if ( $LASTEXITCODE -ne 0 -or -not (Test-Path $tarPath) ) { Fail "Build failed" }
	# build() has just recorded the new version in the dev database; remember what was installed before it
	$previousLong = ( $buildOutput | Select-String -Pattern 'Previously installed: .*\((\d+)\)' | Select-Object -First 1 ).Matches[0].Groups[1].Value
	if ( -not $previousLong ) { Fail "Could not read the previously installed version from the build output" }
}
else {
	Step "Build package (lite: stage repository + generate data files)"
	if ( Test-Path $stageDir ) { Remove-Item $stageDir -Recurse -Force }
	New-Item -ItemType Directory -Force $stageDir | Out-Null
	# Same exclusions as IPS's BuilderFilter (.git, dev) plus repo-only files
	robocopy $repo $stageDir /E /XD .git dev /XF .gitattributes /NFL /NDL /NJH /NJS | Out-Null
	if ( $LASTEXITCODE -ge 8 ) { Fail "robocopy failed with exit code $LASTEXITCODE" }
	& $PhpExe @phpArgs (Join-Path $repo "dev\tools\make-package-data.php") $repo $stageDir
	if ( $LASTEXITCODE -ne 0 ) { Fail "Generating data files failed" }
	# Explicit top-level names so entries have no ./ prefix (matches the layout of a Developer Center build)
	$top = Get-ChildItem $stageDir -Force | Select-Object -ExpandProperty Name
	& tar -cf $tarPath --format=ustar -C $stageDir @top
	if ( $LASTEXITCODE -ne 0 -or -not (Test-Path $tarPath) ) { Fail "Creating the tar failed" }
	Remove-Item $stageDir -Recurse -Force
}

$entries = @( & tar -tf $tarPath ) | ForEach-Object { $_.TrimEnd('/') }
if ( $LASTEXITCODE -ne 0 ) { Fail "Cannot read $tarPath" }
foreach ( $required in @( "Application.php", "data/lang.xml", "data/application.json", "data/schema.json", "sources/TagLogic/TagLogic.php" ) ) {
	if ( $entries -notcontains $required ) { Fail "Package is missing $required" }
}
$leaked = $entries | Where-Object { $_ -match '^(dev/|\.git(/|$)|docs/|superpowers/)' }
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

# ---------------------------------------------------------------- 3. Rehearse the upgrade routine locally (full mode only)
if ( $Full ) {
	Step "Rehearse upgrade routine on the local dev install (from version $previousLong)"
	& $PhpExe @phpArgs (Join-Path $repo "dev\tools\remote-upgrade.php") $LocalIps $AppDir "--from=$previousLong"
	if ( $LASTEXITCODE -ne 0 ) { Fail "The upgrade routine failed on the local dev install; not deploying." }
}

# ---------------------------------------------------------------- 4. Upload
Step "Upload to $SshUser@$SshHost"
$remoteTmp = "~/markassold-deploy"
ssh -p $SshPort "$SshUser@$SshHost" "rm -rf $remoteTmp && mkdir -p $remoteTmp"
if ( $LASTEXITCODE -ne 0 ) { Fail "ssh failed" }
scp -q -P $SshPort $tarPath (Join-Path $repo "dev\tools\remote-upgrade.php") "$SshUser@${SshHost}:$remoteTmp/"
if ( $LASTEXITCODE -ne 0 ) { Fail "scp failed" }
Write-Host "Uploaded"

# ---------------------------------------------------------------- 5. Install on the server
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
set -o pipefail
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
grep -qx 'Application.php' "`$WORK/manifest" || { echo 'Package manifest looks wrong (no Application.php); aborting before touching the app'; exit 3; }
( cd '$remoteApp' && sudo find . -type f -printf '%P\n' ) | sort -u | comm -23 - "`$WORK/manifest" > "`$WORK/stale"
STALE="`$(wc -l < "`$WORK/stale")"
if [ "`$STALE" -gt 200 ]; then echo "Refusing to remove `$STALE files; that does not look like a stale-file cleanup. Aborting before touching the app."; exit 3; fi
if [ "`$STALE" -gt 0 ]; then
  echo "Removing `$STALE files no longer in the package:"; sed 's/^/  /' "`$WORK/stale"
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
if ( $LASTEXITCODE -ne 0 ) { Fail "Remote install failed. If the output above shows 'Backup:', the previous app is in ~/markassold-backup-$stamp.tgz on the server; see the rollback note at the top of this script." }

Remove-Item $outDir -Recurse -Force -ErrorAction SilentlyContinue
Write-Host "`nDone ($head). Now test on the live forum: mark, unmark, moderator, both tag slots." -ForegroundColor Green
