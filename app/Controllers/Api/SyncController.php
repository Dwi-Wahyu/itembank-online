<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;

class SyncController extends ResourceController
{
    protected $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    private function verifyToken()
    {
        $headerToken = $this->request->getHeaderLine('X-API-KEY');
        if ($headerToken !== env('SYNC_API_KEY')) {
            return false;
        }
        return true;
    }

    // Endpoint: GET /api/sync/export-teori/{kode_ujian}
    public function exportTeori($kode_ujian)
    {
        if (!$this->verifyToken()) {
            return $this->failUnauthorized('Invalid API Key');
        }

        // 1. Fetch Exam Meta (buat_teori)
        $exam = $this->db->table('buat_teori')->where('kode', $kode_ujian)->get()->getRowArray();
        if (!$exam) {
            return $this->failNotFound('Exam session not found');
        }

        // 2. Fetch Enrolled Participants (admin_cbt)
        $participants = $this->db->table('admin_cbt')
            ->where('kode', $kode_ujian)
            ->get()->getResultArray();

        // 3. Fetch Questions (massAssignSoal links via id_paket = buat_teori.id):
        $soal_list = [];
        if (!empty($exam)) {
            $paket_id = $exam['id'];
            $soal_list = $this->db->table('ujian_teori')
                ->where('id_paket', $paket_id)
                ->where('status', 2)
                ->get()->getResultArray();
        }

        return $this->respond([
            'status' => 'success',
            'data' => [
                'exam' => $exam,
                'participants' => $participants,
                'questions' => $soal_list
            ]
        ]);
    }

    // GET /api/sync/list-sessions?from=YYYY-MM-DD
    public function listSessions()
    {
        if (!$this->verifyToken()) return $this->failUnauthorized('Invalid API Key');

        $from = $this->request->getGet('from') ?? date('Y-m-d');

        $sessions = $this->db->table('buat_teori')
            ->select('id, kode, nama, tanggal, status')
            ->where('tanggal >=', $from)
            ->orderBy('tanggal', 'ASC')
            ->get()->getResultArray();

        return $this->respond(['status' => 'success', 'data' => $sessions]);
    }

    // GET /api/sync/export-osce/{kode}
    public function exportOsce($kode)
    {
        if (!$this->verifyToken()) return $this->failUnauthorized('Invalid API Key');

        $session = $this->db->table('osce')->where('kode', $kode)->get()->getRowArray();
        if (!$session) return $this->failNotFound('OSCE session not found');

        $participants = $this->db->table('admin_cbt')
            ->where('kode', $kode)
            ->get()->getResultArray();

        // Stations assigned to this OSCE session
        $stations = $this->db->table('osce_soal')
            ->where('osce_id', $session['id'])
            ->get()->getResultArray();

        return $this->respond([
            'status' => 'success',
            'data'   => [
                'session'      => $session,
                'participants' => $participants,
                'stations'     => $stations,
            ]
        ]);
    }

    // POST /api/sync/import-osce-results
    public function importOsceResults()
    {
        if (!$this->verifyToken()) return $this->failUnauthorized('Invalid API Key');

        $payload  = $this->request->getJSON(true);
        $results  = $payload['results'] ?? [];

        if (empty($results)) return $this->fail('No results provided');

        $this->db->transStart();
        // jawaban_osce stores per-station scoring per student
        $this->db->table('jawaban_osce')->upsertBatch($results);
        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return $this->failServerError('Failed to save OSCE results');
        }

        return $this->respondCreated(['status' => 'success', 'message' => 'OSCE results synced']);
    }

    // GET /api/sync/list-osce-sessions
    public function listOsceSessions()
    {
        if (!$this->verifyToken()) return $this->failUnauthorized('Invalid API Key');
        $from = $this->request->getGet('from') ?? date('Y-m-d');
        $sessions = $this->db->table('osce')
            ->select('id, kode, nama_ujian, tanggal')
            ->where('tanggal >=', $from)
            ->orderBy('tanggal', 'ASC')
            ->get()->getResultArray();
        return $this->respond(['status' => 'success', 'data' => $sessions]);
    }

    // Endpoint: POST /api/sync/import-results
    public function importResults()
    {
        if (!$this->verifyToken()) {
            return $this->failUnauthorized('Invalid API Key');
        }

        $payload = $this->request->getJSON(true);
        $attempts = $payload['attempts'] ?? [];

        if (empty($attempts)) {
            return $this->fail('No attempts provided');
        }

        // Insert or update results back into the master database
        $this->db->transStart();
        $this->db->table('ujian_attempt')->upsertBatch($attempts);
        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return $this->failServerError('Failed to save results to master DB');
        }

        return $this->respondCreated(['status' => 'success', 'message' => 'Results synced successfully']);
    }
}