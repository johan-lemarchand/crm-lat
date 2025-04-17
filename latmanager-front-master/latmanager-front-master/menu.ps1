while ($true) {
    Write-Host "+------------------------------------+" -ForegroundColor Blue
    Write-Host "|          Console Frontend          |" -ForegroundColor Blue
    Write-Host "+------------------------------------+" -ForegroundColor Blue
    Write-Host "| Version actuelle:" -NoNewline -ForegroundColor Blue
    $version = Get-Content .version -ErrorAction SilentlyContinue
    if (-not $version) { $version = "0.0.0" }
    Write-Host " $version" -ForegroundColor Green
    Write-Host "+------------------------------------+" -ForegroundColor Blue
    Write-Host "| 1. Patch (0.0.X) - Corrections    |" -ForegroundColor Blue
    Write-Host "| 2. Minor (0.X.0) - Nouvelles fonc.|" -ForegroundColor Blue
    Write-Host "| 3. Major (X.0.0) - Chang. majeurs |" -ForegroundColor Blue
    Write-Host "| 4. Commit sans chang. de version  |" -ForegroundColor Blue
    Write-Host "| 5. Quitter                        |" -ForegroundColor Blue
    Write-Host "+------------------------------------+" -ForegroundColor Blue

    $choice = Read-Host "Choix"
    
    switch ($choice) {
        "1" {
            make bump-patch
            $message = Read-Host "Message de commit"
            make commit message="`"$message`""
            Write-Host "`n✨ Changements commités et poussés avec succès ! ✨" -ForegroundColor Green
            Write-Host "Version mise à jour et changelog modifié" -ForegroundColor Green
            Start-Sleep -Seconds 2
            exit
        }
        "2" {
            make bump-minor
            $message = Read-Host "Message de commit"
            make commit message="`"$message`""
            Write-Host "`n✨ Changements commités et poussés avec succès ! ✨" -ForegroundColor Green
            Write-Host "Version mise à jour et changelog modifié" -ForegroundColor Green
            Start-Sleep -Seconds 2
            exit
        }
        "3" {
            make bump-major
            $message = Read-Host "Message de commit"
            make commit message="`"$message`""
            Write-Host "`n✨ Changements commités et poussés avec succès ! ✨" -ForegroundColor Green
            Write-Host "Version mise à jour et changelog modifié" -ForegroundColor Green
            Start-Sleep -Seconds 2
            exit
        }
        "4" {
            $message = Read-Host "Message de commit"
            make commit message="`"$message`""
            Write-Host "`n✨ Changements commités et poussés avec succès ! ✨" -ForegroundColor Green
            Write-Host "Changelog modifié" -ForegroundColor Green
            Start-Sleep -Seconds 2
            exit
        }
        "5" {
            Write-Host "`nAu revoir !" -ForegroundColor Blue
            exit
        }
        default {
            Write-Host "Choix invalide" -ForegroundColor Red
            Start-Sleep -Seconds 1
            Clear-Host
        }
    }
} 