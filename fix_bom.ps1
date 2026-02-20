# Remove invisible characters and BOM from app.js.php
# Set UTF-8 encoding first
chcp 65001 | Out-Null

Write-Host "Removing invisible characters from app.js.php..." -ForegroundColor Green

try {
    # Read the file with UTF-8 encoding without BOM
    $content = Get-Content -Path "app.js.php" -Raw -Encoding UTF8
    
    if (-not $content) {
        Write-Host "Error: Could not read app.js.php file" -ForegroundColor Red
        exit 1
    }
    
    Write-Host "Original content starts with: $($content.Substring(0, [Math]::Min(50, $content.Length)).Replace("`r", '\r').Replace("`n", '\n'))" -ForegroundColor Yellow
    
    # Remove BOM (Byte Order Mark) if present
    $content = $content -replace "^\uFEFF", ""
    
    # Remove invisible characters at the beginning
    $content = $content -replace "^[\s\u200B-\u200D\uFEFF]+", ""
    
    # Remove other invisible characters throughout the file
    $content = $content -replace "[\u200B-\u200D\uFEFF]", ""
    
    # Ensure the file starts with <?php
    $content = $content -replace "^\s*<\?php", "<?php"
    
    # Write back to file with UTF-8 encoding without BOM
    $utf8WithBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText("app.js.php", $content, $utf8WithBom)
    
    Write-Host "Fixed successfully!" -ForegroundColor Green
    Write-Host "Content now starts with: $($content.Substring(0, [Math]::Min(50, $content.Length)).Replace("`r", '\r').Replace("`n", '\n'))" -ForegroundColor Yellow
    
} catch {
    Write-Host "Error occurred: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

Write-Host "Script completed." -ForegroundColor Green
