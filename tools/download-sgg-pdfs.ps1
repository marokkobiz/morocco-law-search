param(
    [int] $Limit = 0,
    [string] $OutputDir = "storage/app/imports/sgg",
    [string] $Manifest = "storage/app/imports/sgg/manifest.json",
    [switch] $IncludeConsolidated,
    [switch] $IncludeArabic
)

$ErrorActionPreference = "Stop"
$base = "https://www.sgg.gov.ma"
$pages = New-Object System.Collections.Generic.List[object]
$pages.Add(@{ Url = "$base/textesimportants.aspx"; Language = "fr"; Category = "official-sgg-important"; Tags = @("official-sgg", "textes-importants", "fr") }) | Out-Null

if ($IncludeConsolidated) {
    $pages.Add(@{ Url = "$base/textesconsolides.aspx"; Language = "fr"; Category = "official-sgg-consolidated"; Tags = @("official-sgg", "textes-consolides", "fr") }) | Out-Null
}

if ($IncludeArabic) {
    $pages.Add(@{ Url = "$base/arabe/textesimportants.aspx"; Language = "ar"; Category = "official-sgg-important"; Tags = @("official-sgg", "textes-importants", "ar") }) | Out-Null
    $pages.Add(@{ Url = "$base/arabe/textesconsolides.aspx"; Language = "ar"; Category = "official-sgg-consolidated"; Tags = @("official-sgg", "textes-consolides", "ar") }) | Out-Null
}

New-Item -ItemType Directory -Force -Path $OutputDir | Out-Null
$seen = @{}
$rows = New-Object System.Collections.Generic.List[object]

foreach ($page in $pages) {
    $response = Invoke-WebRequest -Uri $page.Url -UseBasicParsing

    foreach ($link in $response.Links) {
        $href = [string] $link.href

        if ([string]::IsNullOrWhiteSpace($href) -or $href -notmatch "\.pdf(\?|$)") {
            continue
        }

        if ($href.StartsWith("/")) {
            $url = "$base$href"
        } elseif ($href.StartsWith("http")) {
            $url = $href
        } else {
            continue
        }

        $cleanUrl = ($url -split "\?")[0]
        $hostName = ([Uri] $cleanUrl).Host.ToLowerInvariant()

        if ($hostName -ne "www.sgg.gov.ma" -and $hostName -ne "sgg.gov.ma") {
            continue
        }

        $key = $cleanUrl.ToLowerInvariant()

        if ($seen.ContainsKey($key)) {
            continue
        }

        $seen[$key] = $true
        $fileName = [IO.Path]::GetFileName(([Uri] $cleanUrl).AbsolutePath)
        $safeName = ($fileName -replace "[^\w.\-]", "_")
        $localPath = Join-Path $OutputDir $safeName

        if (-not (Test-Path $localPath)) {
            Invoke-WebRequest -Uri $url -OutFile $localPath -UseBasicParsing
        }

        $title = [string] $link.innerText

        if ([string]::IsNullOrWhiteSpace($title)) {
            $title = [IO.Path]::GetFileNameWithoutExtension($fileName) -replace "[_\-]+", " "
        }

        $rows.Add([ordered]@{
            sourceUrl = $cleanUrl
            localPath = $localPath
            documentTitle = ($title -replace "\s+", " ").Trim()
            lawReference = $null
            category = $page.Category
            sourceName = "Secretariat General du Gouvernement - Textes officiels"
            language = $page.Language
            tags = $page.Tags
        }) | Out-Null

        if ($Limit -gt 0 -and $rows.Count -ge $Limit) {
            break
        }
    }

    if ($Limit -gt 0 -and $rows.Count -ge $Limit) {
        break
    }
}

$manifestDir = Split-Path -Parent $Manifest
New-Item -ItemType Directory -Force -Path $manifestDir | Out-Null
$rows | ConvertTo-Json -Depth 5 | Set-Content -Path $Manifest -Encoding UTF8
Write-Output "Downloaded/verified $($rows.Count) official SGG PDFs."
Write-Output "Manifest: $Manifest"
