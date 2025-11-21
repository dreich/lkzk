@echo off
setlocal
chcp 65001>nul
git %*
exit /b %errorlevel%
