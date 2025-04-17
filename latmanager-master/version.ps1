Write-Host "=== Mise à jour de version ===" -ForegroundColor Cyan
Write-Host "Composant à mettre à jour :"
Write-Host "1) manager_version (Application complète)"
Write-Host "2) Praxedo_articles"
Write-Host "3) Praxedo_activities"
Write-Host "4) Wavesoft_currency"
Write-Host "5) Api_trimble"

$component = Read-Host "Votre choix (1-5)"
switch ($component) {
    "1" { $component = "manager_version" }
    "2" { $component = "Praxedo_articles" }
    "3" { $component = "Praxedo_activities" }
    "4" { $component = "Wavesoft_currency" }
    "5" { $component = "Api_trimble" }
    default {
        Write-Host "Choix invalide" -ForegroundColor Red
        exit 1
    }
}

Write-Host "`nType de mise à jour :"
Write-Host "1) patch (correction de bug)"
Write-Host "2) minor (nouvelle fonctionnalité)"
Write-Host "3) major (changement majeur)"

$type = Read-Host "Votre choix (1-3)"
switch ($type) {
    "1" { $type = "patch" }
    "2" { $type = "minor" }
    "3" { $type = "major" }
    default {
        Write-Host "Choix invalide" -ForegroundColor Red
        exit 1
    }
}

$commit = Read-Host "Voulez-vous faire un commit ? (O/N)"
if ($commit -eq "O" -or $commit -eq "o") {
    php bin/console app:version:update --type=$type --component=$component
} else {
    php bin/console app:version:update --type=$type --component=$component --no-git
} 