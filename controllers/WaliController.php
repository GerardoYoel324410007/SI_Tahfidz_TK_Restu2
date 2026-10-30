<?php
/**
 * Controller: Wali Murid (Orang Tua)
 * Semua aksi yang berkaitan dengan role wali_murid
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../models/SiswaModel.php';
require_once __DIR__ . '/../models/TugasModel.php';
require_once __DIR__ . '/../models/SetoranModel.php';
require_once __DIR__ . '/../models/PerpanjanganModel.php';
require_once __DIR__ . '/../models/PengumumanModel.php';
require_once __DIR__ . '/../models/NotifikasiModel.php';
require_once __DIR__ . '/../models/KoreksiDetailModel.php';

class WaliController {

    private $siswaModel;
    private $tugasModel;
    private $setoranModel;
    private $perpanjanganModel;
    private $pengumumanModel;
    private $notifikasiModel;
    private $koreksiDetailModel;
    private $user;
    private $siswa;

    public function __construct() {
        Auth::requireRole('wali_murid');
        $this->user = Auth::getCurrentUser();

        $this->siswaModel = new SiswaModel();
        $this->tugasModel = new TugasModel();
        $this->setoranModel = new SetoranModel();
        $this->perpanjanganModel = new PerpanjanganModel();
        $this->pengumumanModel = new PengumumanModel();
        $this->notifikasiModel = new NotifikasiModel();
        $this->koreksiDetailModel = new KoreksiDetailModel();

        $this->siswa = $this->siswaModel->getByUserId($this->user['user_id']);
        if (!$this->siswa) {
            throw new \Exception('Data siswa tidak ditemukan.');
        }
    }

    /**
     * GET /api/wali/beranda.php
     * Status setoran hari ini + pengumuman
     */
    public function beranda(): array {
        $siswaId = (int) $this->siswa['id'];
        $kelasId = (int) $this->siswa['kelas_id'];

        $setoran = $this->setoranModel->getBySiswaHariIni($siswaId);
        $tugas = $this->tugasModel->getByKelasHariIni($kelasId);
        $pengumuman = $this->pengumumanModel->getByKelas($kelasId, 3);

        // Cek lock status
        $isLocked = false;
        if ($tugas && !$setoran) {
            $now = date('Y-m-d H:i:s');
            $deadline = $tugas['deadline'];
            $perpanjangan = $this->perpanjanganModel->getAktif((int) $tugas['id'], $siswaId);

            if ($now > $deadline && !$perpanjangan) {
                $isLocked = true;
            }
        }

        // Status setoran
        $statusSetoran = 'belum_kumpul';
        if ($setoran) {
            $statusSetoran = $setoran['status']; // menunggu, diterima, perbaiki
        }

        // Hitung total poin (jumlah nilai di detail koreksi dari setoran diterima)
        $poinQuery = "
            SELECT COALESCE(SUM(kd.nilai), 0)
            FROM setoran st
            JOIN koreksi_detail kd ON kd.setoran_id = st.id
            WHERE st.siswa_id = :siswa_id AND st.status = 'diterima'
        ";
        $totalPoin = (int) $this->setoranModel->findScalar($poinQuery, [':siswa_id' => $siswaId]);

        // Hitung presensi (jumlah setoran terkumpul vs total tugas terbit)
        $totalTugas = (int) $this->setoranModel->findScalar("
            SELECT COUNT(*) FROM tugas_hafalan
            WHERE kelas_id = :kelas_id AND status_publikasi = 'published'
        ", [':kelas_id' => $kelasId]);

        $submittedSetoran = (int) $this->setoranModel->findScalar("
            SELECT COUNT(DISTINCT st.tugas_id)
            FROM setoran st
            JOIN tugas_hafalan t ON t.id = st.tugas_id
            WHERE st.siswa_id = :siswa_id AND t.status_publikasi = 'published'
        ", [':siswa_id' => $siswaId]);

        $presensiVal = $totalTugas > 0 ? round(($submittedSetoran / $totalTugas) * 100) : 100;

        return [
            'siswa' => [
                'id' => $this->siswa['id'],
                'nis' => $this->siswa['nis'],
                'nama' => $this->siswa['nama_lengkap'],
                'kelas' => $this->siswa['nama_kelas'],
                'foto' => $this->siswa['foto_siswa'],
                'poin' => $totalPoin,
                'presensi' => $presensiVal . '%'
            ],
            'setoran_hari_ini' => [
                'status' => $statusSetoran,
                'setoran' => $setoran,
                'is_locked' => $isLocked
            ],
            'tugas' => $tugas,
            'pengumuman' => $pengumuman
        ];
    }

    /**
     * GET /api/wali/tugas-hari-ini.php
     * Tugas aktif hari ini + audio panduan + lock check
     */
    public function tugasHariIni(): array {
        $siswaId = (int) $this->siswa['id'];
        $kelasId = (int) $this->siswa['kelas_id'];

        $tugas = $this->tugasModel->getByKelasHariIni($kelasId);
        if (!$tugas) {
            return ['tugas' => null, 'is_locked' => false, 'setoran' => null];
        }

        $setoran = $this->setoranModel->getByTugasSiswa((int) $tugas['id'], $siswaId);
        $perpanjangan = $this->perpanjanganModel->getAktif((int) $tugas['id'], $siswaId);

        $now = date('Y-m-d H:i:s');
        $deadline = $tugas['deadline'];

        // Lock check: terkunci jika lewat deadline DAN tidak ada perpanjangan aktif
        $isLocked = false;
        if (!$setoran && $now > $deadline && !$perpanjangan) {
            $isLocked = true;
        }

        // Jika ada perpanjangan, gunakan deadline baru
        $effectiveDeadline = $deadline;
        if ($perpanjangan) {
            $effectiveDeadline = $perpanjangan['deadline_baru'];
        }

        return [
            'tugas' => $tugas,
            'setoran' => $setoran,
            'is_locked' => $isLocked,
            'deadline_efektif' => $effectiveDeadline,
            'perpanjangan' => $perpanjangan
        ];
    }

    /**
     * POST /api/wali/kirim-setoran.php
     * Upload audio hafalan siswa
     */
    public function kirimSetoran(array $files): array {
        $siswaId = (int) $this->siswa['id'];
        $kelasId = (int) $this->siswa['kelas_id'];

        $tugas = $this->tugasModel->getByKelasHariIni($kelasId);
        if (!$tugas) {
            throw new \Exception('Tidak ada tugas hafalan hari ini.');
        }

        $tugasId = (int) $tugas['id'];

        // Cek lock
        $now = date('Y-m-d H:i:s');
        $perpanjangan = $this->perpanjanganModel->getAktif($tugasId, $siswaId);
        $deadline = $perpanjangan ? $perpanjangan['deadline_baru'] : $tugas['deadline'];

        if ($now > $deadline) {
            throw new \Exception('Batas waktu pengumpulan sudah habis. Hubungi wali kelas.');
        }

        // Cek duplikat
        $existing = $this->setoranModel->getByTugasSiswa($tugasId, $siswaId);
        if ($existing && $existing['status'] === 'diterima') {
            throw new \Exception('Setoran sudah diterima. Tidak perlu mengirim ulang.');
        }

        // Validasi file
        if (empty($files['file_audio']) || $files['file_audio']['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception('File audio wajib diunggah.');
        }

        $audioPath = $this->uploadSetoranAudio($files['file_audio'], $kelasId);

        // Tentukan keterlambatan
        $isTerlambat = ($now > $tugas['deadline']) ? 1 : 0;

        // Jika sudah ada setoran menunggu/perbaiki, update. Jika buat baru.
        if ($existing) {
            $this->setoranModel->updateSetoran((int) $existing['id'], [
                'file_audio' => $audioPath,
                'waktu_kirim' => $now,
                'status' => 'menunggu',
                'catatan_guru' => null,
                'waktu_koreksi' => null,
                'is_terlambat' => $isTerlambat
            ]);
            $setoranId = (int) $existing['id'];
        } else {
            $setoranId = $this->setoranModel->create([
                'tugas_id' => $tugasId,
                'siswa_id' => $siswaId,
                'file_audio' => $audioPath,
                'waktu_kirim' => $now,
                'status' => 'menunggu',
                'is_terlambat' => $isTerlambat
            ]);
        }

        // Notifikasi ke guru
        $guruUserId = $this->setoranModel->findScalar("
            SELECT g.user_id FROM tugas_hafalan t
            JOIN guru g ON g.id = t.guru_id
            WHERE t.id = :tugas_id
        ", [':tugas_id' => $tugasId]);

        if ($guruUserId) {
            $this->notifikasiModel->create(
                (int) $guruUserId,
                "Setoran Baru",
                "{$this->siswa['nama_lengkap']} mengirim setoran QS. {$tugas['nama_surat']} Ayat {$tugas['ayat_dari']}-{$tugas['ayat_sampai']}."
            );
        }

        return [
            'setoran_id' => $setoranId,
            'status' => 'menunggu',
            'file_audio' => $audioPath,
            'is_terlambat' => (bool) $isTerlambat
        ];
    }

    /**
     * Upload file audio setoran siswa
     */
    private function uploadSetoranAudio(array $file, int $kelasId): string {
        $originalExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['mp3', 'wav', 'm4a', 'ogg', 'aac', 'webm'];
        $maxSize = 10 * 1024 * 1024; // 10MB

        if ($file['size'] > $maxSize) {
            throw new \Exception('Ukuran file audio maksimal 10MB.');
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

        $tanggal = date('Y-m-d');
        $dir = __DIR__ . "/../uploads/audio/setoran/{$kelasId}/{$tanggal}/";
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $filename = "{$this->siswa['nis']}_" . uniqid() . ".{$ext}";
        $filepath = $dir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new \Exception('Gagal menyimpan file audio.');
        }

        return "uploads/audio/setoran/{$kelasId}/{$tanggal}/{$filename}";
    }

    /**
     * GET /api/wali/detail-koreksi.php?setoran_id=X
     * Detail koreksi + catatan guru
     */
    public function detailKoreksi(): array {
        $setoranId = (int) ($_GET['setoran_id'] ?? 0);
        if (!$setoranId) throw new \Exception('Parameter setoran_id wajib diisi.');

        $setoran = $this->setoranModel->getById($setoranId);
        if (!$setoran) throw new \Exception('Setoran tidak ditemukan.');

        // Pastikan milik siswa ini
        if ((int) $setoran['siswa_id'] !== (int) $this->siswa['id']) {
            throw new \Exception('Akses ditolak.');
        }

        $detailKoreksi = $this->koreksiDetailModel->getBySetoran($setoranId);

        return [
            'setoran' => $setoran,
            'detail_koreksi' => $detailKoreksi
        ];
    }

    /**
     * POST /api/wali/balas-koreksi.php
     * Balasan wali ke guru
     */
    public function balasKoreksi(array $input): array {
        $setoranId = (int) ($input['setoran_id'] ?? 0);
        $teksBalasan = trim($input['teks_balasan'] ?? '');

        if (!$setoranId || empty($teksBalasan)) {
            throw new \Exception('Setoran ID dan teks balasan wajib diisi.');
        }

        $setoran = $this->setoranModel->getById($setoranId);
        if (!$setoran) throw new \Exception('Setoran tidak ditemukan.');

        if ((int) $setoran['siswa_id'] !== (int) $this->siswa['id']) {
            throw new \Exception('Akses ditolak.');
        }

        $this->setoranModel->updateBalasan($setoranId, $teksBalasan);

        // Notifikasi ke guru
        $guruUserId = $this->setoranModel->findScalar("
            SELECT g.user_id FROM tugas_hafalan t
            JOIN guru g ON g.id = t.guru_id
            WHERE t.id = :tugas_id
        ", [':tugas_id' => $setoran['tugas_id']]);

        if ($guruUserId) {
            $this->notifikasiModel->create(
                (int) $guruUserId,
                "Balasan dari Wali Murid",
                "{$this->siswa['nama_lengkap']} membalas catatan koreksi Anda."
            );
        }

        return [
            'setoran_id' => $setoranId,
            'balasan' => $teksBalasan
        ];
    }

    /**
     * GET /api/wali/raport.php?bulan=X&tahun=Y
     * Data raport bulanan
     */
    public function raport(): array {
        $bulan = (int) ($_GET['bulan'] ?? date('m'));
        $tahun = (int) ($_GET['tahun'] ?? date('Y'));

        $data = $this->setoranModel->getRaportBulanan((int) $this->siswa['id'], $bulan, $tahun);

        $riwayat = $data['riwayat'];
        foreach ($riwayat as &$r) {
            $r['detail_koreksi'] = $this->koreksiDetailModel->getBySetoran((int) $r['setoran_id']);
        }

        return [
            'siswa' => [
                'nama' => $this->siswa['nama_lengkap'],
                'kelas' => $this->siswa['nama_kelas']
            ],
            'bulan' => $bulan,
            'tahun' => $tahun,
            'ringkasan' => [
                'total_setoran' => $data['total_setoran'],
                'diterima' => $data['diterima'],
                'menunggu' => $data['menunggu'],
                'perbaiki' => $data['perbaiki'],
                'persentase_berhasil' => $data['persentase_berhasil']
            ],
            'riwayat' => $riwayat
        ];
    }

    /**
     * GET /api/wali/pengumuman.php
     * Pengumuman terbaru untuk kelas siswa
     */
    public function pengumuman(): array {
        return $this->pengumumanModel->getByKelas((int) $this->siswa['kelas_id'], 5);
    }

    /**
     * GET /api/wali/histori.php
     * Riwayat audio lengkap siswa
     */
    public function histori(): array {
        $siswaId = (int) $this->siswa['id'];
        $history = $this->setoranModel->getAllAudioHistory($siswaId);
        foreach ($history as &$h) {
            $h['detail_koreksi'] = $this->koreksiDetailModel->getBySetoran((int) $h['setoran_id']);
        }
        return $history;
    }
}
