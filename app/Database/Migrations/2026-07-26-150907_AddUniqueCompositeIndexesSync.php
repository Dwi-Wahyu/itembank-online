<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUniqueCompositeIndexesSync extends Migration
{
    public function up()
    {
        $this->db->query("ALTER TABLE ujian_attempt ADD UNIQUE INDEX sync_uk_ujian_attempt (kode, id_mahasiswa, id_paket)");
        $this->db->query("ALTER TABLE jawaban_osce ADD UNIQUE INDEX sync_uk_jawaban_osce (osce_id, soal_id, mahasiswa_id)");
    }

    public function down()
    {
        $this->db->query("ALTER TABLE ujian_attempt DROP INDEX sync_uk_ujian_attempt");
        $this->db->query("ALTER TABLE jawaban_osce DROP INDEX sync_uk_jawaban_osce");
    }
}
