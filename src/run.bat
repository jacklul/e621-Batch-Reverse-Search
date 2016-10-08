@echo off
TITLE e621 Batch Reverse Search

SET SPATH=%~dp0
SET PATH=%SPATH%\runtime\;%PATH%

php "%SPATH%/e621BRS.phar" %*

@pause
