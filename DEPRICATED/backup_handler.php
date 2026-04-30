<?php
/**
 * Backup System for CSV Data
 * Creates automatic backups before modifications and allows restore
 */


require_once __DIR__ . '/error_handler.php';
require_once __DIR__ . '/env_loader.php';
load_env();
$crmConfig = require __DIR__ . '/config.local.php';
define('BACKUP_DIR', getenv('BACKUP_DIR') ?: ($crmConfig['backup_dir'] ?? (__DIR__ . '/backups')));
define('BACKUP_RETENTION_DAYS', getenv('BACKUP_RETENTION_DAYS') ?: ($crmConfig['backup_retention_days'] ?? 30));
define('BACKUP_RETENTION_COUNT', getenv('BACKUP_RETENTION_COUNT') ?: ($crmConfig['backup_retention_count'] ?? 50));

/**
 * Initialize backup directory
 */
function initializeBackupSystem() {
    if (!is_dir(BACKUP_DIR)) {
        if (!mkdir(BACKUP_DIR, 0755, true)) {
            logError('Failed to create backup directory', ['path' => BACKUP_DIR]);
            return false;
        }
    }
    return true;
}

/**
 * Create a backup of a CSV file before modification
 * Returns backup filename if successful
 */
function createBackup($filename) {
    initializeBackupSystem();
    
    if (!file_exists($filename)) {
        logWarning('Backup requested for non-existent file', ['filename' => $filename]);
        return null;
    }
    
    try {
        $timestamp = date('YmdHis');
        $basefilename = basename($filename);
        $backupFile = BACKUP_DIR . '/' . pathinfo($basefilename, PATHINFO_FILENAME) 
                    . '_' . $timestamp . '.' . pathinfo($basefilename, PATHINFO_EXTENSION);
        
        if (copy($filename, $backupFile)) {
            logInfo('Backup created successfully', [
                'original' => $filename,
                'backup' => $backupFile,
                'size' => filesize($backupFile),
            ]);
            
            // Clean up old backups
            cleanOldBackups();
            
            return $backupFile;
        } else {
            logError('Failed to create backup file', ['filename' => $filename]);
            return null;
        }
    } catch (Exception $e) {
        logError('Backup creation exception', ['exception' => $e->getMessage()]);
        return null;
    }
}

/**
 * Restore a CSV file from backup
 */
function restoreFromBackup($backupFile, $targetFile) {
    if (!file_exists($backupFile)) {
        throw new Exception("Backup file not found: {$backupFile}");
    }
    
    // Verify backup is in backup directory
    $realBackupPath = realpath($backupFile);
    $realBackupDir = realpath(BACKUP_DIR);
    
    if (strpos($realBackupPath, $realBackupDir) !== 0) {
        throw new Exception("Invalid backup file path (security check failed)");
    }
    
    try {
        // Create backup of current state before restoring
        createBackup($targetFile);
        
        // Restore from backup
        if (copy($backupFile, $targetFile)) {
            logInfo('Data restored from backup', [
                'backup_file' => $backupFile,
                'target_file' => $targetFile,
            ]);
            return true;
        } else {
            throw new Exception("Failed to copy backup to target location");
        }
    } catch (Exception $e) {
        logError('Restore from backup failed', ['exception' => $e->getMessage()]);
        throw $e;
    }
}

/**
 * Get list of available backups for a file
 */
function listBackups($filename = null) {
    initializeBackupSystem();
    
    if (!is_dir(BACKUP_DIR)) {
        return [];
    }
    
    $backups = [];
    $files = scandir(BACKUP_DIR, SCANDIR_SORT_DESCENDING);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        $fullPath = BACKUP_DIR . '/' . $file;
        
        // Filter by filename if specified
        if ($filename !== null) {
            $filenameBase = pathinfo($filename, PATHINFO_FILENAME);
            if (strpos($file, $filenameBase) !== 0) {
                continue;
            }
        }
        
        if (is_file($fullPath)) {
            // Extract timestamp from filename (format: name_YmdHis.ext)
            if (preg_match('/_(\d{14})\./', $file, $matches)) {
                $timestamp = $matches[1];
                $datetime = DateTime::createFromFormat('YmdHis', $timestamp);
                
                $backups[] = [
                    'filename' => $file,
                    'path' => $fullPath,
                    'timestamp' => $timestamp,
                    'datetime' => $datetime ? $datetime->format('Y-m-d H:i:s') : 'Unknown',
                    'size' => filesize($fullPath),
                    'size_formatted' => formatBytes(filesize($fullPath)),
                ];
            }
        }
    }
    
    return $backups;
}

/**
 * Clean up old backup files
 * Removes backups older than BACKUP_RETENTION_DAYS or beyond BACKUP_RETENTION_COUNT
 */
function cleanOldBackups() {
    if (!is_dir(BACKUP_DIR)) {
        return;
    }
    
    $files = scandir(BACKUP_DIR, SCANDIR_SORT_DESCENDING);
    $fileCount = 0;
    $cutoffDate = time() - (BACKUP_RETENTION_DAYS * 86400);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        $fullPath = BACKUP_DIR . '/' . $file;
        
        if (!is_file($fullPath)) {
            continue;
        }
        
        $fileCount++;
        
        // Delete if older than retention days
        if (filemtime($fullPath) < $cutoffDate) {
            if (unlink($fullPath)) {
                logInfo('Old backup deleted (retention period)', ['file' => $file]);
            }
            continue;
        }
        
        // Delete if beyond retention count
        if ($fileCount > BACKUP_RETENTION_COUNT) {
            if (unlink($fullPath)) {
                logInfo('Backup deleted (retention count exceeded)', ['file' => $file]);
            }
        }
    }
}

/**
 * Format bytes as human-readable size
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Get backup statistics
 */
function getBackupStats() {
    if (!is_dir(BACKUP_DIR)) {
        return [
            'total_backups' => 0,
            'total_size' => 0,
            'total_size_formatted' => '0 B',
            'oldest_backup' => null,
            'newest_backup' => null,
        ];
    }
    
    $backups = listBackups();
    $totalSize = 0;
    
    foreach ($backups as $backup) {
        $totalSize += $backup['size'];
    }
    
    return [
        'total_backups' => count($backups),
        'total_size' => $totalSize,
        'total_size_formatted' => formatBytes($totalSize),
        'oldest_backup' => !empty($backups) ? end($backups) : null,
        'newest_backup' => !empty($backups) ? reset($backups) : null,
    ];
}

/**
 * Initialize backup system on require
 */
initializeBackupSystem();

?>
