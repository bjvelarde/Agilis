@echo off
IF [%1] NEQ [] (
  php %~dp0\agilis.php %1
) ELSE (
  echo Usage: agilis ^<appname^>
)  