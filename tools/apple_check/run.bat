@echo off
chcp 65001 >nul
title Apple Serial Checker
echo ================================
echo    APPLE SERIAL CHECKER
echo ================================
echo.
python --version >nul 2>&1
if errorlevel 1 (
  echo [LOI] May chua cai Python!
  echo Tai Python tai: https://www.python.org/downloads/
  echo Khi cai NHO TICK o "Add Python to PATH"
  echo.
  pause
  exit /b
)
echo Dang cai thu vien lan dau ^(cho ti...^)
python -m pip install --upgrade pip >nul 2>&1
python -m pip install selenium webdriver-manager openpyxl ddddocr >nul 2>&1
echo Xong! Dang mo tool...
echo.
python check.py
pause
