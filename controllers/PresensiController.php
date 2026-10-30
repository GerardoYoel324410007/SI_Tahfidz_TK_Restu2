<?php
/**
 * Controller: Presensi Tahfidz
 * Input & rekap presensi kelas tahfidz (Senin-Kamis)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../models/PresensiModel.php';
require_once __DIR__ . '/../models/GuruModel.php';
require_once __DIR__ . '/../models/SiswaModel.php';
require_once __DIR__ . '/../models/KelasModel.php';

class PresensiController {

    private $presensiModel;
    private $guruModel;
    private $siswaModel;
    private $kelasModel;
    private $user;
    private $guru;

    public function __construct() {
        $this->user         = Auth::getCurrentUser();
        $this->presensiModel = new PresensiModel();
        $this->guruModel    = new GuruModel();
        $this->siswaModel   = new SiswaModel();
        $this->kelasModel   = new KelasModel();

        if ($this->user['role'] === 'guru') {
            $this->guru = $this->guruModel->getByUserId($this->user['user_id']);
            if (!$this->guru) {
                throw new \Exception('Data guru tidak ditemukan.');
            }
        }
    }

    /**
     * POST /api/guru/presensi-input.php
     * Bulk input presensi satu kelas untuk satu tanggal
     * 
     * Body: {
     *   tanggal: "2026-08-04",
     *   kelas_id: 14,
     *   data: [
     *     { siswa_id: 1, status: "hadir", catatan: "" },
     *     { siswa_id: 2, status: "izin",  catatan: "sakit demam" },
     *     ...
     *   ]
     * }
     */
    public function inputPresensi(array $input): array {
        Auth::requireRole('guru');

        $tanggal = $input['tanggal'] ?? date('Y-m-d');
        $kelasId = (int) ($input['kelas_id'] ?? $this->guru['kelas_id'] ?? 0);
        $data    = $input['data'] ?? [];

        // Validasi: hanya Senin-Kamis
        $namaHari = PresensiModel::getNamaHari($tanggal);
        if (!$namaHari) {
            throw new \Exception('Presensi tahfidz hanya dicatat pada hari Senin–Kamis.');
        }

        if (!$kelasId) {
            throw new \Exception('Parameter kelas_id wajib diisi.');
        }

        if (empty($data) || !is_array($data)) {
            throw new \Exception('Data presensi tidak boleh kosong.');
        }

        $statusValid = ['hadir', 'izin', 'sakit', 'alpha'];
        $saved = 0;

        foreach ($data as $item) {
            $siswaId = (int) ($item['siswa_id'] ?? 0);
            $status  = $item['status'] ?? 'hadir';
            $catatan = trim($item['catatan'] ?? '');

            if (!$siswaId || !in_array($status, $statusValid)) continue;

            $this->presensiModel->upsert([
                'siswa_id'     => $siswaId,
                'kelas_id'     => $kelasId,
                'tanggal'      => $tanggal,
                'hari'         => $namaHari,
                'status'       => $status,
                'catatan'      => $catatan ?: null,
                'dicatat_oleh' => (int) $this->guru['id']
            ]);
            $saved++;
        }

        return [
            'tanggal'  => $tanggal,
            'hari'     => $namaHari,
            'kelas_id' => $kelasId,
            'tersimpan'=> $saved
        ];
    }

    /**
     * GET /api/guru/presensi-rekap.php?kelas_id=X&dari=Y&sampai=Z
     * Rekap presensi per kelas dalam rentang tanggal
     */
    public function rekapKelas(): array {
        Auth::requireAnyRole(['guru', 'admin']);

        $kelasId     = (int) ($_GET['kelas_id'] ?? $this->guru['kelas_id'] ?? 0);
        $tanggalDari = $_GET['dari']   ?? date('Y-m-01'); // default awal bulan
        $tanggalAkhir= $_GET['sampai'] ?? date('Y-m-d');  // default hari ini

        if (!$kelasId) {
            throw new \Exception('Parameter kelas_id wajib diisi.');
        }

        $kelas = $this->kelasModel->getById($kelasId);
        if (!$kelas) {
            throw new \Exception('Kelas tidak ditemukan.');
        }

        $rekap = $this->presensiModel->getRekapKelas($kelasId, $tanggalDari, $tanggalAkhir);

        return [
            'kelas'        => $kelas,
            'periode'      => ['dari' => $tanggalDari, 'sampai' => $tanggalAkhir],
            'rekap_siswa'  => $rekap
        ];
    }

    /**
     * GET /api/guru/presensi-harian.php?kelas_id=X&tanggal=Y
     * Data presensi satu hari (untuk cek/edit hari ini)
     */
    public function presensiHarian(): array {
        Auth::requireAnyRole(['guru', 'admin']);

        $kelasId = (int) ($_GET['kelas_id'] ?? $this->guru['kelas_id'] ?? 0);
        $tanggal = $_GET['tanggal'] ?? date('Y-m-d');

        if (!$kelasId) {
            throw new \Exception('Parameter kelas_id wajib diisi.');
        }

        // Ambil daftar siswa kelas ini
        $siswaDiKelas = $this->siswaModel->getAll($kelasId, null, 1, 200);
        $presensiAda  = $this->presensiModel->getByKelasAndTanggal($kelasId, $tanggal);

        // Index presensi by siswa_id
        $presensiMap = [];
        foreach ($presensiAda as $p) {
            $presensiMap[$p['siswa_id']] = $p;
        }

        // Gabungkan dengan daftar siswa
        $hasil = array_map(function($siswa) use ($presensiMap) {
            $p = $presensiMap[$siswa['id']] ?? null;
            return [
                'siswa_id'      => $siswa['id'],
                'nis'           => $siswa['nis'],
                'nama_lengkap'  => $siswa['nama_lengkap'],
                'nama_panggilan'=> $siswa['nama_panggilan'],
                'status'        => $p['status'] ?? null,   // null = belum diinput
                'catatan'       => $p['catatan'] ?? '',
            ];
        }, $siswaDiKelas['data'] ?? []);

        $namaHari = PresensiModel::getNamaHari($tanggal);

        return [
            'tanggal'     => $tanggal,
            'hari'        => $namaHari,
            'hari_valid'  => $namaHari !== null,
            'sudah_input' => count($presensiAda) > 0,
            'kelas_id'    => $kelasId,
            'siswa'       => $hasil
        ];
    }

    /**
     * GET /api/wali/presensi.php?siswa_id=X&dari=Y&sampai=Z
     * Presensi siswa untuk wali murid
     */
    public function presensiSiswa(): array {
        Auth::requireAnyRole(['wali_murid', 'admin', 'guru']);

        $siswaId      = (int) ($_GET['siswa_id'] ?? 0);
        $tanggalDari  = $_GET['dari']   ?? date('Y-m-01');
        $tanggalAkhir = $_GET['sampai'] ?? date('Y-m-d');

        if (!$siswaId) {
            throw new \Exception('Parameter siswa_id wajib diisi.');
        }

        // Wali murid hanya boleh lihat data anaknya sendiri
        if ($this->user['role'] === 'wali_murid') {
            $siswa = $this->siswaModel->getByUserId($this->user['user_id']);
            if (!$siswa || (int)$siswa['id'] !== $siswaId) {
                throw new \Exception('Akses tidak diizinkan.');
            }
        }

        $data = $this->presensiModel->getRekapSiswa($siswaId, $tanggalDari, $tanggalAkhir);
        $siswaInfo = $this->siswaModel->getById($siswaId);

        return [
            'siswa'   => $siswaInfo,
            'periode' => ['dari' => $tanggalDari, 'sampai' => $tanggalAkhir],
            'rekap'   => $data['rekap'],
            'riwayat' => $data['riwayat']
        ];
    }
}
