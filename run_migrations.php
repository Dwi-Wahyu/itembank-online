<?php
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR);
chdir(__DIR__);
require 'app/Config/Paths.php';
$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '\\/ ') . DIRECTORY_SEPARATOR . 'bootstrap.php';

$db = \Config\Database::connect();

try {
    $db->query("ALTER TABLE ujian_attempt ADD UNIQUE INDEX sync_uk_ujian_attempt (kode, id_mahasiswa, id_paket)");
    echo "Online: uk_ujian_attempt added\n";
} catch (Exception $e) { echo $e->getMessage() . "\n"; }

try {
    $db->query("ALTER TABLE jawaban_osce ADD UNIQUE INDEX sync_uk_jawaban_osce (osce_id, soal_id, mahasiswa_id)");
    echo "Online: uk_jawaban_osce added\n";
} catch (Exception $e) { echo $e->getMessage() . "\n"; }
