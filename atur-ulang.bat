@echo off
REM ================================================================
REM  Mengembalikan data contoh ke keadaan semula.
REM  Semua perubahan yang dibuat saat mencoba akan hilang.
REM ================================================================
setlocal enabledelayedexpansion
cd /d "%~dp0"
title Atur ulang data contoh

set "PHP="
for /f "delims=" %%D in ('dir /b /ad "C:\laragon\bin\php\php-8*" 2^>nul') do (
  if exist "C:\laragon\bin\php\%%D\php.exe" set "PHP=C:\laragon\bin\php\%%D\php.exe"
)
if not defined PHP if exist "C:\xampp\php\php.exe" set "PHP=C:\xampp\php\php.exe"
if not defined PHP for /f "delims=" %%P in ('where php 2^>nul') do set "PHP=%%P"

if not defined PHP (
  echo.
  echo   PHP tidak ditemukan di komputer ini.
  echo.
  pause
  exit /b 1
)

echo.
echo   Seluruh isi basis data akan dihapus dan diisi ulang dengan data contoh.
echo.
choice /c YT /n /m "  Lanjutkan? [Y]a / [T]idak: "
if errorlevel 2 exit /b 0

echo.
"%PHP%" artisan migrate:fresh --seed --force
echo.
echo   Selesai. Jalankan lagi jalankan.bat.
pause
