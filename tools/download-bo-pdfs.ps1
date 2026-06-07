param(
    [int] $Limit = 0,
    [string] $OutputDir = "storage/app/imports/bo",
    [string] $Manifest = "storage/app/imports/bo/manifest.json",
    [int] $MinYear = 2024
)

$ErrorActionPreference = "Stop"
$base = "https://www.sgg.gov.ma"
$endpoint = "$base/DesktopModules/MVC/TableListBO/BO/AjaxMethod"
$headers = @{
    ModuleId = "2873"
    TabId = "775"
    RequestVerificationToken = ""
}

New-Item -ItemType Directory -Force -Path $OutputDir | Out-Null
$response = Invoke-RestMethod -Uri $endpoint -Headers $headers
$items = @($response)
$rows = New-Object System.Collections.Generic.List[object]
$seen = @{}

foreach ($item in $items) {
    $rawUrl = [string] $item.BoUrl

    if ([string]::IsNullOrWhiteSpace($rawUrl) -or $rawUrl -notmatch "\.pdf(\?|$)") {
        continue
    }

    $url = if ($rawUrl.StartsWith("/")) { "$base$rawUrl" } else { $rawUrl }
    $cleanUrl = ($url -split "\?")[0]
    $path = ([Uri] $cleanUrl).AbsolutePath

    if ($path -match "/(20\d{2})/") {
        $year = [int] $Matches[1]
    } else {
        $year = 0
    }

    if ($MinYear -gt 0 -and $year -gt 0 -and $year -lt $MinYear) {
        continue
    }

    $key = $cleanUrl.ToLowerInvariant()

    if ($seen.ContainsKey($key)) {
        continue
    }

    $seen[$key] = $true
    $fileName = [IO.Path]::GetFileName($path)
    $safeName = ($fileName -replace "[^\w.\-]", "_")
    $localPath = Join-Path $OutputDir $safeName

    if (-not (Test-Path $localPath)) {
        Invoke-WebRequest -Uri $url -OutFile $localPath -UseBasicParsing
    }

    $boNum = [string] $item.BoNum

    $rows.Add([ordered]@{
        sourceUrl = $cleanUrl
        localPath = $localPath
        documentTitle = "Bulletin officiel n $boNum - Textes generaux"
        lawReference = "BO n $boNum"
        category = "official-bulletin"
        sourceName = "Secretariat General du Gouvernement - Bulletin officiel"
        language = "fr"
        tags = @("official-bulletin", "public-law", "fr", "$year")
    }) | Out-Null

    if ($Limit -gt 0 -and $rows.Count -ge $Limit) {
        break
    }
}

$manifestDir = Split-Path -Parent $Manifest
New-Item -ItemType Directory -Force -Path $manifestDir | Out-Null
$rows | ConvertTo-Json -Depth 5 | Set-Content -Path $Manifest -Encoding UTF8
Write-Output "Downloaded/verified $($rows.Count) official BO PDFs."
Write-Output "Manifest: $Manifest"
