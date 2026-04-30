@echo off
REM Backup script for CRM website

REM Set backup folder name with date
set BACKUP_DIR=backup_%DATE:~10,4%_%DATE:~4,2%_%DATE:~7,2%

REM Create backup directory
mkdir %BACKUP_DIR%

REM Copy all files to backup directory
xcopy * %BACKUP_DIR% /E /H /C /I

REM Compress backup directory to ZIP
powershell Compress-Archive -Path %BACKUP_DIR% -DestinationPath %BACKUP_DIR%.zip

REM Remove uncompressed backup directory
rmdir /S /Q %BACKUP_DIR%

REM Database export instructions
REM If you use MySQL, run:
REM mysqldump -u [username] -p [database_name] > %BACKUP_DIR%_db.sql
REM If you use SQLite, copy the .db file to the backup directory.

REM Backup complete
echo Backup complete. ZIP file created: %BACKUP_DIR%.zip
pause
