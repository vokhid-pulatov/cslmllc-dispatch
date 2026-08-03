$ErrorActionPreference = 'Stop'
$source = Join-Path $PSScriptRoot 'cslm-dispatch'
$destination = Join-Path (Split-Path (Split-Path $PSScriptRoot -Parent) -Parent) 'cslm-dispatch.zip'
if (Test-Path -LiteralPath $destination) {
    Remove-Item -LiteralPath $destination
}
tar -a -c -f $destination -C $PSScriptRoot 'cslm-dispatch'
if ($LASTEXITCODE -ne 0) {
    throw 'Could not build CSLM Dispatch package.'
}
Write-Output $destination
