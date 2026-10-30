/**
 * Auth Guard - Proteksi halaman view
 * Include SEBELUM script lain: <script src="../../assets/js/auth-guard.js"></script>
 */

// Ambil base path project (sama seperti di api.js)
function _getAuthBasePath() {
    const segments = window.location.pathname.split('/');
    const reserved = ['views', 'api', 'assets', 'uploads', 'database', 'config', 'controllers', 'models'];
    if (segments.length >= 2 && segments[1] && !reserved.includes(segments[1])) {
        return '/' + segments[1];
    }
    return '';
}
const _authBasePath = _getAuthBasePath();

const authGuard = {
    /**
     * Cek apakah user sudah login
     * @returns {object|null} User data atau null
     */
    async checkSession() {
        try {
            const response = await fetch(
                window.location.origin + _authBasePath + '/api/auth/cek-sesi.php',
                { method: 'GET', credentials: 'same-origin' }
            );
            const data = await response.json();
            return data.logged_in ? data : null;
        } catch (err) {
            console.error('Auth check failed:', err);
            return null;
        }
    },

    /**
     * Proteksi halaman: redirect jika belum login
     * @param {string|null} requiredRole Role yang dibutuhkan (opsional)
     */
    async protect(requiredRole = null) {
        const user = await this.checkSession();

        if (!user) {
            window.location.href = _authBasePath + '/views/shared/login.html';
            return null;
        }

        if (requiredRole && user.role !== requiredRole) {
            const redirectMap = {
                'admin': _authBasePath + '/views/admin/dashboard.html',
                'guru': _authBasePath + '/views/guru/beranda.html',
                'wali_murid': _authBasePath + '/views/wali_murid/beranda.html'
            };
            window.location.href = redirectMap[user.role] || _authBasePath + '/views/shared/login.html';
            return null;
        }

        return user;
    },

    /**
     * Inisialisasi user data di halaman (nama, inisial, dll)
     * @param {object} user Data user dari checkSession
     */
    initUserData(user) {
        if (!user) return;

        const namaEl = document.getElementById('header-nama');
        const initialsEl = document.getElementById('header-initials');
        const sidebarNamaEl = document.getElementById('sidebar-nama');
        const sidebarInitialsEl = document.getElementById('sidebar-initials');
        const welcomeEl = document.getElementById('welcome-msg');

        if (user.nama) {
            const initials = user.nama.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();

            if (namaEl) namaEl.textContent = user.nama;
            if (initialsEl) initialsEl.textContent = initials;
            if (sidebarNamaEl) sidebarNamaEl.textContent = user.nama;
            if (sidebarInitialsEl) sidebarInitialsEl.textContent = initials;
            if (welcomeEl) welcomeEl.textContent = `Selamat Datang Kembali, ${user.nama}`;
        }
    },

    /**
     * Logout user dengan konfirmasi
     */
    async logout() {
        // Tampilkan konfirmasi sebelum logout
        if (!window.confirm('Yakin ingin keluar dari sistem?\n\nSemua sesi aktif akan diakhiri.')) {
            return; // Batal jika user memilih "Batal"
        }
        try {
            await fetch(
                window.location.origin + _authBasePath + '/api/auth/logout.php',
                { method: 'POST', credentials: 'same-origin' }
            );
        } catch(e) {}
        window.location.href = _authBasePath + '/views/shared/login.html';
    }
};
