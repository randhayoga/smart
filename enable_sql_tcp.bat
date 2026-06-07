@echo off
:: Check for admin privileges
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo [ERROR] Please right-click this file and select "Run as administrator".
    pause
    exit /b
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0enable_sql_tcp.ps1"
