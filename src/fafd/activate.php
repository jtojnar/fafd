<?php

function check_action($result) {
    if (!$result) {
        $error = error_get_last();
        throw new Exception($error !== null ? $error['message'] : 'Unknown reason');
    }
}

// We are still affected by timeouts of HTTP server & co.
set_time_limit(0);

header('Content-type: text/plain');

if (!isset($_POST['key']) || $_POST['key'] !== '@key@') {
    http_response_code(401);
    echo 'Unauthorized' . PHP_EOL;
    exit(1);
}

$filesToCarryOver = @carryover_files@;

try {
    $phase = 'open archive';
    $originalDir = __DIR__;
    $parentDir = dirname($originalDir);
    $archive = $originalDir . '/@archive@';
    $zip = new ZipArchive;
    $zipOpenResult = $zip->open($archive);
    if ($zipOpenResult !== TRUE) {
        throw new Exception($zip->getStatusString());
    }

    $phase = 'create a temporary directory for extraction';
    $tempDir = $parentDir . '/' . basename($originalDir) . '.tmp.' . date('Ymd.His');
    check_action(mkdir($tempDir));

    $phase = 'extract archive to temporary directory';
    $extractResult = $zip->extractTo($tempDir);
    if ($extractResult !== TRUE) {
        throw new Exception($zip->getStatusString());
    }
    $zip->close();

    $phase = 'check whether archive contains a single top-level directory';
    $extratedFiles = new FilesystemIterator($tempDir, FilesystemIterator::SKIP_DOTS);
    $archiveDir = null;
    foreach ($extratedFiles as $file) {
        if ($archiveDir === null && $file->isDir()) {
            $archiveDir = $file->getPathname();
        } else {
            $archiveDir = null;
            break;
        }
    }
    $extractedDir = $archiveDir !== null ? $archiveDir : $tempDir;

    $phase = 'copy selected files from the old directory to make room for the new one';
    foreach ($filesToCarryOver as $file) {
        $srcFile = $originalDir . '/' . $file;
        $dstFile = $extractedDir . '/' . $file;
        if (!is_dir(dirname($dstFile))) {
            check_action(mkdir(dirname($dstFile), 0777, true));
        }
        check_action(copy($srcFile, $dstFile));
    }

    $phase = 'remove the uploaded archive';
    check_action(unlink($archive));

    $phase = 'remove this script';
    check_action(unlink(__FILE__));

    $phase = 'rename the old directory to make room for the new one';
    $backupDir = $parentDir . '/' . basename($originalDir) . '.bak.' . date('Ymd.His');
    check_action(rename($originalDir, $backupDir));

    $phase = 'deploy the uploaded directory';
    check_action(rename($extractedDir, $originalDir));

    $phase = 'clean up temporary directory';
    if ($archiveDir !== null) {
        rmdir($tempDir);
    }

    echo 'success' . PHP_EOL;
} catch (Exception $e) {
    http_response_code(500);
    echo 'Failed to ' . $phase . ': ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
