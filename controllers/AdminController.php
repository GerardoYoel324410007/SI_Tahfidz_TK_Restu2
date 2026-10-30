<?php
/**
 * Controller: Admin
 * Semua aksi yang berkaitan dengan role admin
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../models/GuruModel.php';
require_once __DIR__ . '/../models/SiswaModel.php';
require_once __DIR__ . '/../models/KelasModel.php';
require_once __DIR__ . '/../models/SetoranModel.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../models/PengumumanModel.php';
require_once __DIR__ . '/../models/NotifikasiModel.php';
require_once __DIR__ . '/../models/SuratModel.php';

class AdminController {

    private $guruModel;
    private $siswaModel;
    private $kelasModel;
    private $setoranModel;
    private $userModel;
    private $pengumumanModel;
    private $notifikasiModel;
    private $suratModel;

    private $db;

    public function __construct() {
        Auth::requireRole('admin');
        $this->guruModel        = new GuruModel();
        $this->siswaModel       = new SiswaModel();
        $this->kelasModel       = new KelasModel();
        $this->setoranModel     = new SetoranModel();
        $this->userModel        = new UserModel();
        $this->pengumumanModel  = new PengumumanModel();
        $this->notifikasiModel  = new NotifikasiModel();
        $this->suratModel       = new SuratModel();
        $this->db               = getDB();
    }

    private function beginTransaction(): void { $this->db->beginTransaction(); }
    private function commit(): void { $this->db->commit(); }
    private function rollback(): void { $this->db->rollBack(); }

    /**
     * GET /api/admin/dashboard-stats.php
     * Statistik semua kelas untuk dashboard admin
     */
    public function dashboardStats(): array {
        $stats = $this->setoranModel->getDashboardStatsAll();

        $totalSiswa = 0;
        $totalSudahKumpul = 0;

        foreach ($stats as &$kelas) {
            $totalSiswa += (int) $kelas['total_siswa'];
            $totalSudahKumpul += (int) $kelas['sudah_kumpul'];
            $kelas['persentase'] = (int) $kelas['total_siswa'] > 0
                ? round(($kelas['sudah_kumpul'] / $kelas['total_siswa']) * 100)
                : 0;
        }

        // Total kelas aktif (exclude TPA)
        $totalKelas = (int) $this->db->query(
            "SELECT COUNT(*) FROM kelas WHERE is_active = 1 AND jenjang != 'TPA'"
        )->fetchColumn();

        // Total guru (semua record di tabel guru, kolom is_active tidak ada di tabel ini)
        $totalGuru = (int) $this->db->query(
            "SELECT COUNT(*) FROM guru"
        )->fetchColumn();

        // Guru yang mengampu (sudah ditugaskan ke kelas aktif)
        $guruMengampu = (int) $this->db->query(
            "SELECT COUNT(DISTINCT guru_id) FROM kelas WHERE is_active = 1 AND guru_id IS NOT NULL AND jenjang != 'TPA'"
        )->fetchColumn();

        // Total target hafalan yang sudah dipublikasikan (sepanjang masa)
        $totalTargetHafalan = (int) $this->db->query(
            "SELECT COUNT(*) FROM tugas_hafalan WHERE status_publikasi = 'published'"
        )->fetchColumn();

        return [
            'total_siswa'          => $totalSiswa,
            'total_kelas'          => $totalKelas,
            'guru_mengampu'        => $guruMengampu,
            'total_guru'           => $totalGuru,
            'total_target_hafalan' => $totalTargetHafalan,
            // Data lama dipertahankan agar tidak merusak fitur lain
            'persentase_setoran'   => $totalSiswa > 0
                ? round(($totalSudahKumpul / $totalSiswa) * 100) : 0,
            'guru_hadir'           => $guruMengampu,
            'keterlambatan'        => (int) $this->setoranModel->countKeterlambatanKemarin(),
            'kelas'                => $stats
        ];
    }

    /**
     * GET /api/admin/monitor-kelas.php?kelas_id=X&tanggal=Y
     * Data setoran per kelas untuk monitor
     */
    public function monitorKelas(): array {
        $kelasId = (int) ($_GET['kelas_id'] ?? 0);
        $tanggal = $_GET['tanggal'] ?? null;

        if (!$kelasId) {
            throw new \Exception('Parameter kelas_id wajib diisi.');
        }

        $kelas = $this->kelasModel->getById($kelasId);
        if (!$kelas) {
            throw new \Exception('Kelas tidak ditemukan.');
        }

        $data = $this->setoranModel->getMonitorKelas($kelasId, $tanggal);
        $tugas = $this->setoranModel->findOne("
            SELECT * FROM tugas_hafalan
            WHERE kelas_id = :kelas_id AND tanggal_tugas = :tanggal AND status_publikasi = 'published'
            LIMIT 1
        ", [':kelas_id' => $kelasId, ':tanggal' => $tanggal ?: date('Y-m-d')]);

        return [
            'kelas' => $kelas,
            'tugas' => $tugas,
            'siswa' => $data
        ];
    }

    /**
     * GET /api/admin/daftar-guru.php?search=X&page=1&per_page=20
     */
    public function daftarGuru(): array {
        $search = $_GET['search'] ?? null;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(1000, max(1, (int) ($_GET['per_page'] ?? 20)));
        return $this->guruModel->getAll($search, $page, $perPage);
    }

    /**
     * GET /api/admin/daftar-siswa.php?kelas_id=X&search=Y&page=1&per_page=20
     */
    public function daftarSiswa(): array {
        $kelasId = !empty($_GET['kelas_id']) ? (int) $_GET['kelas_id'] : null;
        $search = $_GET['search'] ?? null;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(10000, max(1, (int) ($_GET['per_page'] ?? 20)));
        return $this->siswaModel->getAll($kelasId, $search, $page, $perPage);
    }

    /**
     * POST /api/admin/tambah-guru.php
     */
    public function tambahGuru(array $input): array {
        $nama     = trim($input['nama_lengkap'] ?? '');
        $nuptk    = trim($input['nuptk'] ?? '');
        $nik      = trim($input['nik'] ?? '');
        $inobetik = trim($input['inobetik'] ?? '');
        $jabatan  = trim($input['jabatan'] ?? '');
        $kelasId  = $input['kelas_id'] ?? null;
        $noTelp   = trim($input['no_telepon'] ?? '');
        $password = $input['password'] ?? '12345678';

        if (empty($nama) || empty($jabatan)) {
            throw new \Exception('Nama lengkap dan jabatan wajib diisi.');
        }

        // Prioritas identitas login: Inobetik > NUPTK > NIK > auto-generate
        $nomorIdentitas = $inobetik ?: ($nuptk ?: ($nik ?: ('GUR' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT))));

        $this->beginTransaction();

        try {
            $userId = $this->userModel->create([
                'nomor_identitas' => $nomorIdentitas,
                'password_hash'   => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                'role'            => 'guru',
                'is_active'       => 1
            ]);

            $guruId = $this->guruModel->create([
                'user_id'      => $userId,
                'nuptk'        => $nuptk ?: null,
                'nik'          => $nik ?: null,
                'inobetik'     => $inobetik ?: null,
                'nama_lengkap' => $nama,
                'jabatan'      => $jabatan,
                'status'       => $jabatan,
                'no_telepon'   => $noTelp ?: null
            ]);

            if ($kelasId) {
                $this->kelasModel->updateKelas((int) $kelasId, ['guru_id' => $guruId]);
            }

            $this->commit();

            return [
                'id'              => $guruId,
                'user_id'         => $userId,
                'nomor_identitas' => $nomorIdentitas,
                'nama_lengkap'    => $nama
            ];
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * POST /api/admin/tambah-siswa.php
     */
    public function tambahSiswa(array $input): array {
        $nis = trim($input['nis'] ?? '');
        $nama = trim($input['nama_lengkap'] ?? '');
        $namaPanggilan = trim($input['nama_panggilan'] ?? '');
        $jenisKelamin = $input['jenis_kelamin'] ?? '';
        $kelasId = (int) ($input['kelas_id'] ?? 0);
        $namaWali = trim($input['nama_wali'] ?? '');
        $password = $input['password'] ?: $nis;

        if (empty($nis) || empty($nama) || !$kelasId || empty($jenisKelamin)) {
            throw new \Exception('NIS, nama, jenis kelamin, dan kelas wajib diisi.');
        }

        $this->beginTransaction();

        try {
            $userId = $this->userModel->create([
                'nomor_identitas' => $nis,
                'password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                'role' => 'wali_murid',
                'is_active' => 1
            ]);

            $siswaId = $this->siswaModel->create([
                'user_id' => $userId,
                'nis' => $nis,
                'nama_lengkap' => $nama,
                'nama_panggilan' => $namaPanggilan ?: null,
                'jenis_kelamin' => $jenisKelamin,
                'kelas_id' => $kelasId,
                'nama_wali' => $namaWali ?: null,
                'is_active' => 1
            ]);

            $this->commit();

            return [
                'id' => $siswaId,
                'user_id' => $userId,
                'nis' => $nis,
                'nama_lengkap' => $nama
            ];
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * PUT /api/admin/edit-guru.php?id=X
     */
    public function editGuru(array $input): array {
        $id = (int) ($_GET['id'] ?? $input['id'] ?? 0);
        if (!$id) throw new \Exception('ID guru wajib diisi.');

        $guru = $this->guruModel->getById($id);
        if (!$guru) {
            throw new \Exception('Guru tidak ditemukan.');
        }

        $userId = (int) $guru['user_id'];

        $this->beginTransaction();

        try {
            // 1. Update profil guru
            $guruData = [];
            if (isset($input['nama_lengkap'])) $guruData['nama_lengkap'] = trim($input['nama_lengkap']);
            if (isset($input['jabatan'])) {
                $guruData['jabatan'] = trim($input['jabatan']);
                $guruData['status']  = trim($input['jabatan']);
            }
            if (isset($input['no_telepon'])) $guruData['no_telepon'] = trim($input['no_telepon']) ?: null;
            if (isset($input['alamat']))     $guruData['alamat']     = trim($input['alamat']) ?: null;
            if (isset($input['pendidikan'])) $guruData['pendidikan'] = trim($input['pendidikan']) ?: null;
            if (isset($input['nuptk']))      $guruData['nuptk']      = trim($input['nuptk']) ?: null;
            if (isset($input['nik']))        $guruData['nik']        = trim($input['nik']) ?: null;
            if (isset($input['inobetik']))   $guruData['inobetik']   = trim($input['inobetik']) ?: null;

            if (!empty($guruData)) {
                $this->guruModel->updateGuru($id, $guruData);
            }

            // 2. Update data user (akun login)
            $userData = [];
            if (isset($input['nuptk']) && trim($input['nuptk']) !== '') {
                $userData['nomor_identitas'] = trim($input['nuptk']);
            }
            if (isset($input['is_active'])) {
                $userData['is_active'] = (int) $input['is_active'];
            }
            if (!empty($input['password'])) {
                $userData['password_hash'] = password_hash($input['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            }

            if (!empty($userData)) {
                $this->userModel->updateUserInfo($userId, $userData);
            }

            // 3. Mutasi kelas
            if (isset($input['kelas_id'])) {
                $newKelasId = (int) $input['kelas_id'];
                
                // Cari kelas lama guru ini
                $currentKelas = $this->guruModel->getKelasByGuruId($id);
                $currentKelasId = $currentKelas ? (int) $currentKelas['id'] : 0;

                if ($currentKelasId !== $newKelasId) {
                    // Kosongkan kelas lama
                    if ($currentKelasId > 0) {
                        $this->kelasModel->updateKelas($currentKelasId, ['guru_id' => null]);
                    }
                    // Isi kelas baru jika newKelasId > 0
                    if ($newKelasId > 0) {
                        // Kosongkan guru lain di kelas baru tersebut agar tidak bentrok
                        $stmt = $this->db->prepare("UPDATE kelas SET guru_id = NULL WHERE id = :id");
                        $stmt->execute([':id' => $newKelasId]);
                        // Set guru baru
                        $this->kelasModel->updateKelas($newKelasId, ['guru_id' => $id]);
                    }
                }
            }

            $this->commit();
            return ['id' => $id, 'updated' => true];
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * DELETE /api/admin/hapus-pengguna.php?id=X&type=guru|siswa
     */
    public function hapusPengguna(): array {
        $id = (int) ($_GET['id'] ?? 0);
        $type = $_GET['type'] ?? '';

        if (!$id || !in_array($type, ['guru', 'siswa'])) {
            throw new \Exception('Parameter id dan type wajib diisi.');
        }

        if ($type === 'guru') {
            $guru = $this->guruModel->getById($id);
            if ($guru) $this->userModel->setActive((int) $guru['user_id'], 0);
        } else {
            $siswa = $this->siswaModel->getById($id);
            if ($siswa) {
                $this->userModel->setActive((int) $siswa['user_id'], 0);
                $this->siswaModel->updateSiswa($id, ['is_active' => 0]);
            }
        }

        return ['id' => $id, 'type' => $type, 'deleted' => true];
    }

    /**
     * POST /api/admin/kirim-pengumuman.php
     */
    public function kirimPengumuman(array $input): array {
        $judul = trim($input['judul'] ?? '');
        $isi = trim($input['isi'] ?? '');
        $targetKelas = $input['target_kelas'] ?? [];

        if (empty($judul) || empty($isi)) {
            throw new \Exception('Judul dan isi pengumuman wajib diisi.');
        }

        if (empty($targetKelas)) {
            throw new \Exception('Pilih minimal satu kelas target.');
        }

        $user = Auth::getCurrentUser();

        $pengumumanId = $this->pengumumanModel->create([
            'judul' => $judul,
            'isi' => $isi,
            'pengirim_id' => $user['user_id'],
            'target_kelas' => json_encode($targetKelas)
        ]);

        // Kirim notifikasi ke wali murid di kelas target
        $waliMuridIds = $this->notifikasiModel->getWaliMuridByKelas($targetKelas);
        $notifCount = 0;
        if (!empty($waliMuridIds)) {
            $notifCount = $this->notifikasiModel->bulkCreate(
                $waliMuridIds,
                "Pengumuman: {$judul}",
                $isi
            );
        }

        return [
            'pengumuman_id' => $pengumumanId,
            'notif_terkirim' => $notifCount
        ];
    }

    /**
     * PUT /api/admin/edit-siswa.php?id=X
     */
    public function editSiswa(array $input): array {
        $id = (int) ($_GET['id'] ?? $input['id'] ?? 0);
        if (!$id) throw new \Exception('ID siswa wajib diisi.');

        $siswa = $this->siswaModel->getById($id);
        if (!$siswa) {
            throw new \Exception('Siswa tidak ditemukan.');
        }

        $userId = (int) $siswa['user_id'];

        $this->beginTransaction();

        try {
            // 1. Update profil siswa
            $siswaData = [];
            if (isset($input['nama_lengkap'])) $siswaData['nama_lengkap'] = trim($input['nama_lengkap']);
            if (isset($input['nama_panggilan'])) $siswaData['nama_panggilan'] = trim($input['nama_panggilan']) ?: null;
            if (isset($input['jenis_kelamin'])) $siswaData['jenis_kelamin'] = $input['jenis_kelamin'];
            if (isset($input['nama_wali'])) $siswaData['nama_wali'] = trim($input['nama_wali']) ?: null;
            if (isset($input['kelas_id'])) $siswaData['kelas_id'] = (int) $input['kelas_id'];

            if (!empty($siswaData)) {
                $this->siswaModel->updateSiswa($id, $siswaData);
            }

            // 2. Update data user
            $userData = [];
            if (isset($input['nis']) && trim($input['nis']) !== '') {
                $userData['nomor_identitas'] = trim($input['nis']);
                $this->siswaModel->updateSiswa($id, ['nis' => trim($input['nis'])]);
            }
            if (isset($input['is_active'])) {
                $userData['is_active'] = (int) $input['is_active'];
                $this->siswaModel->updateSiswa($id, ['is_active' => (int) $input['is_active']]);
            }
            if (!empty($input['password'])) {
                $userData['password_hash'] = password_hash($input['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            }

            if (!empty($userData)) {
                $this->userModel->updateUserInfo($userId, $userData);
            }

            $this->commit();
            return ['id' => $id, 'updated' => true];
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * GET /api/admin/riwayat-pengumuman.php
     */
    public function riwayatPengumuman(): array {
        return $this->pengumumanModel->getAll(50);
    }

    // ================================================================
    // CRUD KELAS
    // ================================================================

    /**
     * GET /api/admin/daftar-kelas.php
     */
    public function daftarKelas(): array {
        $includeTpa = isset($_GET['include_tpa']) && $_GET['include_tpa'] === '1';
        return $this->kelasModel->getAll(!$includeTpa);
    }

    /**
     * POST /api/admin/tambah-kelas.php
     */
    public function tambahKelas(array $input): array {
        $kodeKelas  = strtoupper(trim($input['kode_kelas'] ?? ''));
        $namaKelas  = trim($input['nama_kelas'] ?? '');
        $jenjang    = $input['jenjang'] ?? '';
        $jenisKelas = $input['jenis_kelas'] ?? 'reguler';
        $tahunAjaran= trim($input['tahun_ajaran'] ?? '2025/2026');
        $urutan     = (int) ($input['urutan'] ?? 5);

        if (empty($kodeKelas) || empty($namaKelas) || !in_array($jenjang, ['KB','TKA','TKB','TPA'])) {
            throw new \Exception('Kode kelas, nama kelas, dan jenjang (KB/TKA/TKB) wajib diisi.');
        }

        if ($this->kelasModel->isKodeExists($kodeKelas)) {
            throw new \Exception("Kode kelas '{$kodeKelas}' sudah digunakan.");
        }

        $id = $this->kelasModel->createKelas([
            'kode_kelas'   => $kodeKelas,
            'nama_kelas'   => $namaKelas,
            'jenjang'      => $jenjang,
            'jenis_kelas'  => $jenisKelas,
            'tahun_ajaran' => $tahunAjaran,
            'urutan'       => $urutan,
            'is_active'    => 1
        ]);

        return ['id' => $id, 'kode_kelas' => $kodeKelas, 'nama_kelas' => $namaKelas];
    }

    /**
     * PUT /api/admin/edit-kelas.php?id=X
     */
    public function editKelas(array $input): array {
        $id = (int) ($_GET['id'] ?? $input['id'] ?? 0);
        if (!$id) throw new \Exception('ID kelas wajib diisi.');

        $kelas = $this->kelasModel->getById($id);
        if (!$kelas) throw new \Exception('Kelas tidak ditemukan.');

        $data = [];
        if (isset($input['nama_kelas']))   $data['nama_kelas']   = trim($input['nama_kelas']);
        if (isset($input['jenis_kelas']))  $data['jenis_kelas']  = $input['jenis_kelas'];
        if (isset($input['tahun_ajaran'])) $data['tahun_ajaran'] = trim($input['tahun_ajaran']);
        if (isset($input['urutan']))       $data['urutan']       = (int) $input['urutan'];
        if (isset($input['guru_id']))      $data['guru_id']      = $input['guru_id'] ? (int) $input['guru_id'] : null;
        if (isset($input['is_active']))    $data['is_active']    = (int) $input['is_active'];

        if (!empty($data)) {
            $this->kelasModel->updateKelas($id, $data);
        }

        return ['id' => $id, 'updated' => true];
    }

    /**
     * DELETE /api/admin/hapus-kelas.php?id=X
     */
    public function hapusKelas(): array {
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) throw new \Exception('ID kelas wajib diisi.');

        $kelas = $this->kelasModel->getById($id);
        if (!$kelas) throw new \Exception('Kelas tidak ditemukan.');

        $jumlahSiswa = $this->kelasModel->getSiswaCount($id);
        if ($jumlahSiswa > 0) {
            throw new \Exception("Kelas tidak bisa dihapus karena masih memiliki {$jumlahSiswa} siswa aktif. Pindahkan siswa terlebih dahulu.");
        }

        $this->kelasModel->deactivateKelas($id);
        return ['id' => $id, 'deleted' => true];
    }

    // ================================================================
    // CRUD SURAT AL-QURAN
    // ================================================================

    /**
     * GET /api/admin/daftar-surat.php
     */
    public function daftarSurat(): array {
        $activeOnly = !isset($_GET['all']) || $_GET['all'] !== '1';
        return $this->suratModel->getAll($activeOnly);
    }

    /**
     * POST /api/admin/tambah-surat.php
     */
    public function tambahSurat(array $input): array {
        $nomorSurat = (int) ($input['nomor_surat'] ?? 0);
        $namaSurat  = trim($input['nama_surat'] ?? '');
        $namaLatin  = trim($input['nama_latin'] ?? '');
        $jumlahAyat = (int) ($input['jumlah_ayat'] ?? 1);

        if (!$nomorSurat || empty($namaSurat)) {
            throw new \Exception('Nomor surat dan nama surat wajib diisi.');
        }

        if ($this->suratModel->isNomorExists($nomorSurat)) {
            throw new \Exception("Surat nomor {$nomorSurat} sudah ada.");
        }

        $id = $this->suratModel->create([
            'nomor_surat' => $nomorSurat,
            'nama_surat'  => $namaSurat,
            'nama_latin'  => $namaLatin ?: null,
            'jumlah_ayat' => $jumlahAyat,
            'is_active'   => 1
        ]);

        return ['id' => $id, 'nomor_surat' => $nomorSurat, 'nama_surat' => $namaSurat];
    }

    /**
     * PUT /api/admin/edit-surat.php?id=X
     */
    public function editSurat(array $input): array {
        $id = (int) ($_GET['id'] ?? $input['id'] ?? 0);
        if (!$id) throw new \Exception('ID surat wajib diisi.');

        $surat = $this->suratModel->getById($id);
        if (!$surat) throw new \Exception('Surat tidak ditemukan.');

        $data = [];
        if (isset($input['nama_surat']))  $data['nama_surat']  = trim($input['nama_surat']);
        if (isset($input['nama_latin']))  $data['nama_latin']  = trim($input['nama_latin']) ?: null;
        if (isset($input['jumlah_ayat'])) $data['jumlah_ayat'] = (int) $input['jumlah_ayat'];
        if (isset($input['is_active']))   $data['is_active']   = (int) $input['is_active'];

        if (!empty($data)) {
            $this->suratModel->updateSurat($id, $data);
        }

        return ['id' => $id, 'updated' => true];
    }

    /**
     * DELETE /api/admin/hapus-surat.php?id=X
     */
    public function hapusSurat(): array {
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) throw new \Exception('ID surat wajib diisi.');

        $this->suratModel->deactivate($id);
        return ['id' => $id, 'deleted' => true];
    }
}
