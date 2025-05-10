<?php
// Replace with the actual path to your upload directory
$uploadDir = '/../../Uploads/lost_found';

if (!is_dir($uploadDir)) {
    echo "Directory does not exist: $uploadDir\n";
} elseif (!is_writable($uploadDir)) {
    echo "Directory is not writable: $uploadDir\n";
} else {
    $testFile = $uploadDir . '/test.txt';
    if (file_put_contents($testFile, 'Test content')) {
        echo "Successfully wrote to $testFile\n";
        unlink($testFile); // Clean up
    } else {
        echo "Failed to write to $testFile\n";
    }
}
?>
