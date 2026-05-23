<?php
require 'config.php';

$tables = ['sesi_ujian', 'hasil_ujian', 'log_aktivitas'];

foreach ($tables as $table) {
    echo "=== TABLE: $table ===\n";
    $desc = $conn->query("DESCRIBE $table");
    while ($col = $desc->fetch_assoc()) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    echo "\n";
}
?>
