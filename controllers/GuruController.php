<?php
/**
 * Controller: Guru (Wali Kelas)
 * Semua aksi yang berkaitan dengan role guru
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../models/GuruModel.php';
require_once __DIR__ . '/../models/SiswaModel.php';
require_once __DIR__ . '/../models/KelasModel.php';
require_once __DIR__ . '/../models/TugasModel.php';
require_once __DIR__ . '/../models/SetoranModel.php';
require_once __DIR__ . '/../models/PerpanjanganModel.php';
require_once __DIR__ . '/../models/NotifikasiModel.php';
require_once __DIR__ . '/../models/KoreksiDetailModel.php';
require_once __DIR__ . '/../models/SuratModel.php';

class GuruController {

    private $guruModel;
    private $siswaModel;
    private $kelasModel;
    private $tugasModel;
    private $setoranModel;
    private $perpanjanganModel;
    private $notifikasiModel;
    private $koreksiDetailModel;
    private $suratModel;
    private $user;
    private $guru;
    private $kelas;
    private $db;

    public function __construct() {
        Auth::requireRole('guru');
        $this->user = Auth::getCurrentUser();

        $this->guruModel = new GuruModel();
        $this->siswaModel = new SiswaModel();
        $this->kelasModel = new KelasModel();
        $this->tugasModel = new TugasModel();
        $this->setoranModel = new SetoranModel();
        $this->perpanjanganModel = new PerpanjanganModel();
        $this->notifikasiModel = new NotifikasiModel();
        $this->koreksiDetailModel = new KoreksiDetailModel();
        $this->suratModel = new SuratModel();
        $this->db = getDB();

        $this->guru = $this->guruModel->getByUserId($this->user['user_id']);
        if (!$this->guru) {
            throw new \Exception('Data guru tidak ditemukan.');
        }

        $this->kelas = $this->kelasModel->getAll();
    }

    private function beginTransaction(): void { $this->db->beginTransaction(); }
    private function commit(): void { $this->db->commit(); }
    private function rollback(): void { $this->db->rollBack(); }

    /**
     * GET /api/guru/beranda-stats.php
     * Widget statistik beranda guru
     */
    public function berandaStats(): array {
        $kelasId = $this->guru['kelas_id'] ?? null;
        if (!$kelasId) {
            return ['total_siswa' => 0, 'sudah_kumpul' => 0, 'menunggu' => 0, 'belum_kumpul' => 0, 'deadline' => null];
        }

        $stats = $this->setoranModel->getStatistikHariIni($kelasId);
        $tugas = $this->tugasModel->getByKelasHariIni($kelasId);

        return [
            'guru' => [
                'nama' => $this->guru['nama_lengkap'],
                'kelas' => $this->guru['nama_kelas'] ?? '-',
                'kelas_id' => $kelasId
            ],
            'statistik' => $stats,
            'tugas' => $tugas,
            'deadline' => $tugas['deadline'] ?? null
        ];
    }

    /**
     * POST /api/guru/simpan-target.php
     * Buat tugas hafalan baru + upload audio panduan
     */
    public function simpanTarget(array $input, array $files = []): array {
        $kelasId = (int) ($input['kelas_id'] ?? 0);
        $namaSurat = trim($input['nama_surat'] ?? '');
        $bab = trim($input['bab'] ?? '') ?: null;
        $ayatDari = (int) ($input['ayat_dari'] ?? 0);
        $ayatSampai = (int) ($input['ayat_sampai'] ?? 0);
        $tanggalTugas = $input['tanggal_tugas'] ?? date('Y-m-d');
        $statusPublikasi = $input['status_publikasi'] ?? 'published';

        if (!$kelasId || empty($namaSurat) || !$ayatDari || !$ayatSampai) {
            throw new \Exception('Kelas, surat, dan ayat wajib diisi.');
        }

        if ($ayatSampai < $ayatDari) {
            throw new \Exception('Ayat sampai harus >= ayat dari.');
        }

        $audioPath = null;
        if (!empty($files['audio_panduan']) && $files['audio_panduan']['error'] === UPLOAD_ERR_OK) {
            $audioPath = $this->uploadAudioPanduan($files['audio_panduan'], $kelasId, $tanggalTugas);
        }

        if (!empty($input['deadline'])) {
            $deadline = date('Y-m-d H:i:s', strtotime($input['deadline']));
        } else {
            $deadline = $tanggalTugas . ' 23:59:00';
        }

        $tugasId = $this->tugasModel->create([
            'kelas_id' => $kelasId,
            'guru_id' => (int) $this->guru['id'],
            'nama_surat' => $namaSurat,
            'bab' => $bab,
            'ayat_dari' => $ayatDari,
            'ayat_sampai' => $ayatSampai,
            'audio_panduan' => $audioPath,
            'tanggal_tugas' => $tanggalTugas,
            'deadline' => $deadline,
            'status_publikasi' => $statusPublikasi
        ]);

        // Notifikasi ke wali murid di kelas ini
        if ($statusPublikasi === 'published') {
            $waliMuridIds = $this->notifikasiModel->getWaliMuridByKelas([$kelasId]);
            if (!empty($waliMuridIds)) {
                $babText = $bab ? " ({$bab})" : "";
                $this->notifikasiModel->bulkCreate(
                    $waliMuridIds,
                    "Tugas Hafalan Baru",
                    "Tugas: QS. {$namaSurat}{$babText} Ayat {$ayatDari}-{$ayatSampai}. Deadline: " . date('H:i', strtotime($deadline))
                );
            }
        }

        return [
            'tugas_id' => $tugasId,
            'nama_surat' => $namaSurat,
            'bab' => $bab,
            'ayat' => "{$ayatDari}-{$ayatSampai}",
            'audio_panduan' => $audioPath,
            'deadline' => $deadline
        ];
    }

    /**
     * Upload file audio panduan guru
     */
    private function uploadAudioPanduan(array $file, int $kelasId, string $tanggal): string {
        $originalExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['mp3', 'wav', 'm4a', 'ogg', 'aac', 'webm'];
        $maxSize = 20 * 1024 * 1024; // 20MB

        if ($file['size'] > $maxSize) {
            throw new \Exception('Ukuran file audio maksimal 20MB.');
        }

        // Get MIME type safely
        $mimeType = null;
        if (class_exists('finfo')) {
            try {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($file['tmp_name']);
            } catch (\Exception $e) {
                // Fallback jika instansiasi gagal
            }
        }
        if (!$mimeType && function_exists('mime_content_type')) {
            $mimeType = @mime_content_type($file['tmp_name']);
        }
        if (!$mimeType) {
            $mimeType = $file['type'] ?? '';
        }

        $allowedMimeTypes = [
            'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-wav', 
            'audio/mp4', 'audio/x-m4a', 'audio/ogg', 'audio/aac', 
            'audio/webm', 'video/webm', 'video/x-matroska', 
            'audio/x-matroska', 'application/octet-stream'
        ];

        $isValidMime = in_array($mimeType, $allowedMimeTypes);
        $isValidExt = in_array($originalExt, $allowedExtensions);

        if (!$isValidMime && !$isValidExt) {
            throw new \Exception('Format audio tidak didukung. Gunakan MP3, WAV, M4A, OGG, atau WebM.');
        }

        $ext = match ($mimeType) {
            'audio/mpeg', 'audio/mp3' => 'mp3',
            'audio/wav', 'audio/x-wav' => 'wav',
            'audio/mp4', 'audio/x-m4a' => 'm4a',
            'audio/ogg' => 'ogg',
            'audio/aac' => 'aac',
            'audio/webm', 'video/webm', 'video/x-matroska', 'audio/x-matroska' => 'webm',
            default => $isValidExt ? $originalExt : 'mp3'
        };

        $dir = __DIR__ . '/../uploads/audio/panduan/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $filename = "{$kelasId}_{$tanggal}_" . uniqid() . ".{$ext}";
        $filepath = $dir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new \Exception('Gagal menyimpan file audio.');
        }

        return "uploads/audio/panduan/{$filename}";
    }

    /**
     * GET /api/guru/antrian-setoran.php?kelas_id=X
     * Daftar setoran menunggu koreksi hari ini
     */
    public function antrianSetoran(): array {
        $kelasId = (int) ($_GET['kelas_id'] ?? $this->guru['kelas_id'] ?? 0);
        if (!$kelasId) {
            return [];
        }

        return $this->setoranModel->getAntrian($kelasId);
    }

    /**
     * POST /api/guru/koreksi.php
     * Kirim keputusan koreksi (diterima/perbaiki) + catatan
     */
    public function koreksi(array $input): array {
        $setoranId   = (int) ($input['setoran_id'] ?? 0);
        $keputusan   = $input['keputusan'] ?? '';
        $catatan     = trim($input['catatan'] ?? '');
        $nilaiPoin   = isset($input['nilai_poin']) ? (int) $input['nilai_poin'] : null;
        $nilaiLabel  = trim($input['nilai_label'] ?? '') ?: null;

        if (!$setoranId || !in_array($keputusan, ['diterima', 'perbaiki'])) {
            throw new \Exception('Setoran ID dan keputusan (diterima/perbaiki) wajib diisi.');
        }

        if ($keputusan === 'perbaiki' && empty($catatan)) {
            throw new \Exception('Catatan wajib diisi jika memilih Perbaiki.');
        }

        // Validasi nilai BB/MB/BSH/BSB jika dikirim
        if ($nilaiPoin !== null && !in_array($nilaiPoin, [1, 2, 3, 4])) {
            throw new \Exception('Nilai poin tidak valid. Gunakan 1=BB, 2=MB, 3=BSH, 4=BSB.');
        }

        $setoran = $this->setoranModel->getById($setoranId);
        if (!$setoran) throw new \Exception('Setoran tidak ditemukan.');

        $this->beginTransaction();

        try {
            $this->setoranModel->updateKoreksi($setoranId, $keputusan, $catatan ?: null, $nilaiPoin, $nilaiLabel);

            // Notifikasi ke wali murid
            $statusLabel = $keputusan === 'diterima' ? 'DITERIMA' : 'PERBAIKI';
            $pesan = "Setoran QS. {$setoran['nama_surat']} Ayat {$setoran['ayat_dari']}-{$setoran['ayat_sampai']} telah {$statusLabel}.";
            if ($catatan) $pesan .= " Catatan: {$catatan}";

            $this->notifikasiModel->create(
                (int) $setoran['siswa_user_id'],
                "Koreksi Setoran: {$statusLabel}",
                $pesan
            );

            $this->commit();

            return [
                'setoran_id' => $setoranId,
                'status' => $keputusan,
                'catatan' => $catatan
            ];
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * GET /api/guru/antrian-berikutnya.php?kelas_id=X&setoran_id=Y
     * Setoran berikutnya setelah yang dikoreksi
     */
    public function antrianBerikutnya(): ?array {
        $kelasId = (int) ($_GET['kelas_id'] ?? $this->guru['kelas_id'] ?? 0);
        $setoranId = (int) ($_GET['setoran_id'] ?? 0);

        if (!$kelasId || !$setoranId) {
            throw new \Exception('Parameter kelas_id dan setoran_id wajib diisi.');
        }

        return $this->setoranModel->getBerikutnya($kelasId, $setoranId);
    }

    /**
     * POST /api/guru/perpanjang-waktu.php
     * Perpanjang deadline siswa
     */
    public function perpanjangWaktu(array $input): array {
        $tugasId = (int) ($input['tugas_id'] ?? 0);
        $siswaId = (int) ($input['siswa_id'] ?? 0);
        $deadlineBaru = $input['deadline_baru'] ?? '';
        $alasan = trim($input['alasan'] ?? '');

        if (!$tugasId || !$siswaId || empty($deadlineBaru)) {
            throw new \Exception('Tugas ID, siswa ID, dan deadline baru wajib diisi.');
        }

        $tugas = $this->tugasModel->getById($tugasId);
        if (!$tugas) throw new \Exception('Tugas tidak ditemukan.');

        $siswa = $this->siswaModel->getById($siswaId);
        if (!$siswa) throw new \Exception('Siswa tidak ditemukan.');

        $perpanjanganId = $this->perpanjanganModel->create([
            'tugas_id' => $tugasId,
            'siswa_id' => $siswaId,
            'guru_id' => (int) $this->guru['id'],
            'deadline_baru' => $deadlineBaru,
            'alasan' => $alasan ?: null
        ]);

        // Notifikasi ke wali murid siswa
        $this->notifikasiModel->create(
            (int) $siswa['user_id'],
            "Perpanjangan Deadline",
            "Batas waktu setoran QS. {$tugas['nama_surat']} diperpanjang hingga " . date('d M Y H:i', strtotime($deadlineBaru)) . "."
        );

        return [
            'perpanjangan_id' => $perpanjanganId,
            'siswa' => $siswa['nama_lengkap'],
            'deadline_baru' => $deadlineBaru
        ];
    }

    /**
     * GET /api/guru/notifikasi.php?unread=1
     * Notifikasi belum dibaca untuk guru
     */
    public function notifikasi(): array {
        $unreadOnly = isset($_GET['unread']) && $_GET['unread'] === '1';

        if ($unreadOnly) {
            return $this->notifikasiModel->getUnread((int) $this->user['user_id']);
        }

        return $this->notifikasiModel->findAll("
            SELECT * FROM notifikasi
            WHERE user_id = :user_id
            ORDER BY created_at DESC
            LIMIT 50
        ", [':user_id' => $this->user['user_id']]);
    }

    /**
     * GET /api/guru/riwayat-tugas.php?kelas_id=X&hari=7
     * Riwayat tugas 7 hari terakhir
     */
    public function riwayatTugas(): array {
        $kelasId = (int) ($_GET['kelas_id'] ?? $this->guru['kelas_id'] ?? 0);
        $hari = (int) ($_GET['hari'] ?? 7);

        if (!$kelasId) {
            return [];
        }

        return $this->tugasModel->getRiwayat($kelasId, $hari);
    }

    /**
     * GET /api/guru/progres-kelas.php?bulan=X&tahun=Y
     * Progres hafalan bulanan untuk seluruh siswa di kelas guru
     */
    public function progresKelas(): array {
        $kelasId = $this->guru['kelas_id'] ?? null;
        if (!$kelasId) {
            throw new \Exception('Guru tidak memiliki kelas penugasan.');
        }

        $bulan = (int) ($_GET['bulan'] ?? date('m'));
        $tahun = (int) ($_GET['tahun'] ?? date('Y'));

        $query = "
            SELECT 
                s.id AS siswa_id, 
                s.nis, 
                s.nama_lengkap, 
                s.nama_panggilan,
                COALESCE(stats.total_setoran, 0) AS total_setoran,
                COALESCE(stats.diterima, 0) AS diterima,
                COALESCE(stats.menunggu, 0) AS menunggu,
                COALESCE(stats.perbaiki, 0) AS perbaiki,
                COALESCE(stats.persentase_berhasil, 0) AS persentase_berhasil
            FROM siswa s
            LEFT JOIN (
                SELECT 
                    st.siswa_id,
                    COUNT(st.id) AS total_setoran,
                    SUM(CASE WHEN st.status = 'diterima' THEN 1 ELSE 0 END) AS diterima,
                    SUM(CASE WHEN st.status = 'menunggu' THEN 1 ELSE 0 END) AS menunggu,
                    SUM(CASE WHEN st.status = 'perbaiki' THEN 1 ELSE 0 END) AS perbaiki,
                    ROUND((SUM(CASE WHEN st.status = 'diterima' THEN 1 ELSE 0 END) / NULLIF(COUNT(st.id), 0)) * 100, 1) AS persentase_berhasil
                FROM setoran st
                JOIN tugas_hafalan t ON t.id = st.tugas_id
                WHERE MONTH(t.tanggal_tugas) = :bulan AND YEAR(t.tanggal_tugas) = :tahun
                GROUP BY st.siswa_id
            ) stats ON stats.siswa_id = s.id
            WHERE s.kelas_id = :kelas_id AND s.is_active = 1
            ORDER BY s.nama_lengkap
        ";

        return $this->siswaModel->findAll($query, [
            ':kelas_id' => $kelasId,
            ':bulan' => $bulan,
            ':tahun' => $tahun
        ]);
    }

    /**
     * GET /api/guru/pengumuman.php
     * Pengumuman terbaru untuk kelas guru
     */
    public function pengumuman(): array {
        $kelasId = $this->guru['kelas_id'] ?? null;
        if (!$kelasId) {
            return [];
        }
        return $this->guruModel->findAll("
            SELECT p.*
            FROM pengumuman p
            WHERE JSON_CONTAINS(p.target_kelas, :kelas_id)
               OR p.target_kelas = '\"all\"'
            ORDER BY p.created_at DESC
            LIMIT 10
        ", [':kelas_id' => (string) $kelasId]);
    }

    /**
     * GET /api/guru/daftar-surat.php
     * Daftar surat Al-Quran aktif
     */
    public function daftarSurat(): array {
        return $this->suratModel->getAll(true);
    }
}
