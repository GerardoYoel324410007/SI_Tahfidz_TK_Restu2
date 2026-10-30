/**
 * Shared API Helper
 * assets/js/api.js
 *
 * Usage: <script src="../../assets/js/api.js"></script>
 *        const data = await api.get('/api/admin/dashboard-stats.php');
 *        const result = await api.post('/api/admin/tambah-guru.php', { nama_lengkap: '...' });
 */

/**
 * Deteksi base path project secara tepat.
 * Contoh: window.location di /Sistem_Tahfidz_Restu2/views/admin/dashboard.html
 *  -> basePath = /Sistem_Tahfidz_Restu2
 */
function _getProjectBasePath() {
    const pathname = window.location.pathname;
    const segments = pathname.split('/');
    // Jika segment pertama adalah folder reserved internal sistem,
    // berarti project sedang dijalankan pada root domain (Virtual Host / VHost)
    const reserved = ['views', 'api', 'assets', 'uploads', 'database', 'config', 'controllers', 'models'];
    if (segments.length >= 2 && segments[1] && !reserved.includes(segments[1])) {
        return '/' + segments[1];
    }
    return '';
}

const _basePath = _getProjectBasePath();

const api = {
    /**
     * Konversi path absolut API ke URL lengkap dengan base project
     * Contoh: '/api/admin/dashboard-stats.php'
     *      -> 'http://localhost/Sistem_Tahfidz_Restu2/api/admin/dashboard-stats.php'
     */
    toAbsoluteUrl(url) {
        if (url.startsWith('http') || url.startsWith('//')) {
            return url;
        }
        const cleanUrl = url.startsWith('/') ? url : '/' + url;
        return window.location.origin + _basePath + cleanUrl;
    },

    /**
     * GET request
     */
    async get(url) {
        try {
            const response = await fetch(this.toAbsoluteUrl(url), {
                method: 'GET',
                credentials: 'same-origin'
            });
            return await this._handleResponse(response);
        } catch (error) {
            this._handleError(error);
            throw error;
        }
    },

    /**
     * POST request (JSON)
     */
    async post(url, data = {}) {
        try {
            const response = await fetch(this.toAbsoluteUrl(url), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(data)
            });
            return await this._handleResponse(response);
        } catch (error) {
            this._handleError(error);
            throw error;
        }
    },

    /**
     * PUT request (JSON)
     */
    async put(url, data = {}) {
        try {
            const response = await fetch(this.toAbsoluteUrl(url), {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(data)
            });
            return await this._handleResponse(response);
        } catch (error) {
            this._handleError(error);
            throw error;
        }
    },

    /**
     * DELETE request
     */
    async delete(url) {
        try {
            const response = await fetch(this.toAbsoluteUrl(url), {
                method: 'DELETE',
                credentials: 'same-origin'
            });
            return await this._handleResponse(response);
        } catch (error) {
            this._handleError(error);
            throw error;
        }
    },

    /**
     * POST multipart/form-data (untuk upload file)
     */
    async upload(url, formData) {
        try {
            const response = await fetch(this.toAbsoluteUrl(url), {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });
            return await this._handleResponse(response);
        } catch (error) {
            this._handleError(error);
            throw error;
        }
    },

    /**
     * Handle response JSON
     */
    async _handleResponse(response) {
        const responseText = await response.text();

        let data;
        try {
            data = JSON.parse(responseText);
        } catch (e) {
            console.error('Response bukan JSON:', responseText);
            throw new Error('Response tidak valid dari server.');
        }

        if (!response.ok || data.status === 'error') {
            if (response.status === 401 || response.status === 403) {
                window.location.href = _basePath + '/views/shared/login.html';
                return;
            }
            throw new Error(data.message || 'Terjadi kesalahan.');
        }

        return data;
    },

    /**
     * Handle network/parsing errors
     */
    _handleError(error) {
        console.error('API Error:', error);
    },

    /**
     * Toast notification sederhana
     */
    toast(message, type = 'success', duration = 3000) {
        const existing = document.getElementById('api-toast');
        if (existing) existing.remove();

        const colors = {
            success: 'background:#2e7d32;color:#fff',
            error: 'background:#c62828;color:#fff',
            warning: 'background:#f9a825;color:#000',
            info: 'background:#1565c0;color:#fff'
        };

        const toast = document.createElement('div');
        toast.id = 'api-toast';
        toast.style.cssText = `position:fixed;top:1rem;right:1rem;z-index:9999;${colors[type]||colors.info};padding:12px 24px;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,0.2);font-size:14px;font-weight:600;transition:all 0.3s ease`;
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },

    /**
     * Format tanggal Indonesia
     */
    formatDate(dateStr) {
        if (!dateStr) return '-';
        const cleanStr = dateStr.includes(' ') && !dateStr.includes('Z') && !dateStr.includes('+')
            ? dateStr.replace(' ', 'T') + 'Z'
            : dateStr;
        const d = new Date(cleanStr);
        return new Intl.DateTimeFormat('id-ID', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
            timeZone: 'Asia/Jakarta'
        }).format(d);
    },

    /**
     * Format waktu (HH:MM)
     */
    formatTime(dateStr) {
        if (!dateStr) return '-';
        const cleanStr = dateStr.includes(' ') && !dateStr.includes('Z') && !dateStr.includes('+')
            ? dateStr.replace(' ', 'T') + 'Z'
            : dateStr;
        const d = new Date(cleanStr);
        return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta' }).replace('.', ':') + ' WIB';
    },

    /**
     * Format datetime
     */
    formatDateTime(dateStr) {
        if (!dateStr) return '-';
        return this.formatDate(dateStr) + ' ' + this.formatTime(dateStr);
    },

    /**
     * Render status badge HTML
     */
    statusBadge(status) {
        const map = {
            'diterima': { label: 'Diterima', style: 'background:#e8f5e9;color:#2e7d32' },
            'menunggu': { label: 'Menunggu', style: 'background:#fff9c4;color:#f57f17' },
            'perbaiki': { label: 'Perbaiki', style: 'background:#ffebee;color:#c62828' },
            'belum_kumpul': { label: 'Belum Kumpul', style: 'background:#f5f5f5;color:#616161' }
        };
        const s = map[status] || map['belum_kumpul'];
        return `<span style="${s.style};display:inline-flex;align-items:center;padding:2px 10px;border-radius:9999px;font-size:12px;font-weight:600">${s.label}</span>`;
    },

    /**
     * Layout Sidebar Manager (Premium Responsive Transitions)
     */
    layout: {
        isMarginLayout: false,
        init() {
            // Render sidebar dynamically
            this.renderSidebar();

            const sidebar = document.getElementById('sidebar');
            const header = document.querySelector('header');
            const main = document.querySelector('main');

            // Cek apakah layout halaman saat ini menggunakan margin offset (MD:ML-72)
            if (header && (header.classList.contains('md:ml-72') || header.classList.contains('ml-72'))) {
                this.isMarginLayout = true;
            } else if (main && (main.classList.contains('md:ml-72') || main.classList.contains('ml-72'))) {
                this.isMarginLayout = true;
            }

            const desktopToggleBtn = document.getElementById('desktop-collapse-btn');
            if (desktopToggleBtn) {
                desktopToggleBtn.addEventListener('click', () => this.toggleCollapse());
            }

            if (localStorage.getItem('sidebar_collapsed') === 'true') {
                this.applyCollapse(true);
            }

            // Mobile menu toggle
            const mobileMenuBtn = document.getElementById('mobile-menu-btn');
            if (mobileMenuBtn && sidebar) {
                mobileMenuBtn.addEventListener('click', (e) => {
                    sidebar.classList.toggle('-translate-x-full');
                    sidebar.classList.toggle('hidden');
                    sidebar.classList.toggle('flex');
                });
            }

            // Mobile queue toggle (khusus untuk koreksi.html)
            const mobileQueueBtn = document.getElementById('mobile-queue-btn');
            const antreanPanel = document.getElementById('antrean-panel');
            if (mobileQueueBtn && antreanPanel) {
                mobileQueueBtn.addEventListener('click', (e) => {
                    antreanPanel.classList.toggle('-translate-x-full');
                    antreanPanel.classList.toggle('hidden');
                    antreanPanel.classList.toggle('flex');
                });
            }

            // Close sidebar / panel when clicking outside
            document.addEventListener('click', (e) => {
                const isKoreksiPage = window.location.pathname.includes('koreksi.html');
                if (sidebar && !sidebar.contains(e.target)) {
                    const shouldClose = isKoreksiPage || window.innerWidth < 768;
                    const isMobileMenu = mobileMenuBtn && (e.target === mobileMenuBtn || mobileMenuBtn.contains(e.target));
                    if (shouldClose && !isMobileMenu && !sidebar.classList.contains('-translate-x-full')) {
                        sidebar.classList.add('-translate-x-full', 'hidden');
                        sidebar.classList.remove('flex');
                    }
                }
                if (antreanPanel && window.innerWidth < 1024 && !antreanPanel.contains(e.target)) {
                    const isMobileQueue = mobileQueueBtn && (e.target === mobileQueueBtn || mobileQueueBtn.contains(e.target));
                    if (!isMobileQueue && !antreanPanel.classList.contains('-translate-x-full')) {
                        antreanPanel.classList.add('-translate-x-full', 'hidden');
                        antreanPanel.classList.remove('flex');
                    }
                }
            });
        },
        renderSidebar() {
            const path = window.location.pathname;
            let role = '';
            if (path.includes('/views/admin/')) {
                role = 'admin';
            } else if (path.includes('/views/guru/')) {
                role = 'guru';
            }

            if (!role) return;

            let sidebar = document.getElementById('sidebar');
            if (!sidebar) {
                sidebar = document.createElement('aside');
                sidebar.id = 'sidebar';
                const main = document.querySelector('main') || document.querySelector('div.flex-1');
                if (main) {
                    main.parentNode.insertBefore(sidebar, main);
                } else {
                    document.body.prepend(sidebar);
                }
            }

            // Enforce layout class for sidebar (different for koreksi page)
            const isKoreksiPage = path.includes('koreksi.html');
            if (isKoreksiPage) {
                sidebar.className = "fixed left-0 top-0 h-full w-72 bg-primary shadow-xl flex-col py-8 z-[60] transform transition-transform duration-300 -translate-x-full hidden overflow-y-auto";
            } else {
                sidebar.className = "fixed left-0 top-0 h-full w-72 bg-primary shadow-[40px_0_60px_-15px_rgba(0,67,35,0.15)] flex-col py-8 z-50 transform transition-transform duration-300 -translate-x-full md:translate-x-0 hidden md:flex overflow-y-auto";
            }

            const activePage = path.split('/').pop() || 'dashboard.html';

            if (role === 'admin') {
                const adminMenu = [
                    { page: 'dashboard.html', icon: 'dashboard', text: 'Dashboard' },
                    { page: 'manajemen-pengguna.html', icon: 'person_4', text: 'Manajemen Pengguna' },
                    { page: 'manajemen-kelas.html', icon: 'meeting_room', text: 'Manajemen Kelas' },
                    { page: 'manajemen-surat.html', icon: 'menu_book', text: 'Target Hafalan' },
                    { page: 'monitor-kelas.html', icon: 'school', text: 'Monitor Kelas' },
                    { page: 'komando.html', icon: 'campaign', text: 'Pengumuman Global' },
                    { page: 'laporan.html', icon: 'analytics', text: 'Laporan & Rekap' },
                    { page: 'log-login.html', icon: 'manage_history', text: 'Log Aktivitas Login' },
                ];

                let html = `
                <div class="px-8 mb-10 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-white rounded-full overflow-hidden flex items-center justify-center p-1 border border-white/20">
                            <img alt="Logo Restu Z" class="w-full h-full object-contain" src="../../LOGO Restu 2 Excellent.png">
                        </div>
                        <div>
                            <h1 class="text-headline-md font-headline-md font-bold text-white leading-tight">Tahfidz BA</h1>
                            <p class="text-label-md font-label-md text-white/70">Admin Panel</p>
                        </div>
                    </div>
                    <button onclick="api.layout.closeMobileSidebar()" class="md:hidden text-white/80 hover:text-white p-1 rounded-full hover:bg-white/10 transition-colors">
                        <span class="material-symbols-outlined text-[24px]">close</span>
                    </button>
                </div>
                <nav class="flex-1 flex flex-col space-y-2">
                `;

                adminMenu.forEach(item => {
                    const isActive = activePage.startsWith(item.page);
                    const activeClass = isActive 
                        ? 'bg-white text-primary rounded-l-full ml-4 py-3 pl-6 font-bold shadow-sm flex items-center gap-4 scale-95 transition-transform' 
                        : 'text-white/80 hover:text-white py-3 pl-10 flex items-center gap-4 transition-all duration-200 hover:bg-primary-fixed-dim/10 rounded-l-full ml-4';
                    const fillStyle = isActive ? "style=\"font-variation-settings: 'FILL' 1;\"" : "";
                    const hrefVal = (isActive && !window.location.search) ? 'javascript:void(0)' : item.page;
                    html += `
                    <a class="${activeClass}" href="${hrefVal}">
                        <span class="material-symbols-outlined" ${fillStyle}>${item.icon}</span>
                        <span class="font-label-md text-label-md">${item.text}</span>
                    </a>
                    `;
                });

                html += `
                    <a class="text-white/80 hover:text-white py-3 pl-10 flex items-center gap-4 transition-all duration-200 hover:bg-primary-fixed-dim/10 rounded-l-full ml-4 mt-auto" href="#" onclick="authGuard.logout()">
                        <span class="material-symbols-outlined">logout</span>
                        <span class="font-label-md text-label-md">Logout</span>
                    </a>
                </nav>
                `;
                sidebar.innerHTML = html;
            } else if (role === 'guru') {
                const guruMenu = [
                    { page: 'beranda.html', icon: 'dashboard', text: 'Dashboard' },
                    { page: 'presensi.html', icon: 'fact_check', text: 'Presensi Kelas' },
                    { page: 'koreksi.html', icon: 'menu_book', text: 'Setoran Hari Ini' },
                    { page: 'target.html', icon: 'assignment_turned_in', text: 'Manajemen Tugas' },
                    { page: 'progres-hafalan.html', icon: 'monitoring', text: 'Progres Hafalan' },
                    { page: 'daftar-siswa.html', icon: 'group', text: 'Daftar Siswa' },
                    { page: 'pengumuman-kelas.html', icon: 'campaign', text: 'Pengumuman Kelas' }
                ];

                let html = `
                <div class="px-8 mb-10 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-white rounded-full overflow-hidden flex items-center justify-center p-1 shadow-sm border border-white/20">
                            <img alt="Logo Restu Z" class="w-full h-full object-contain" src="../../LOGO Restu 2 Excellent.png">
                        </div>
                        <div>
                            <h1 class="text-headline-md font-headline-md font-bold text-white leading-tight">Tahfidz BA</h1>
                            <p class="text-label-md font-label-md text-white/70">Wali Kelas Portal</p>
                        </div>
                    </div>
                    <button onclick="api.layout.closeMobileSidebar()" class="${isKoreksiPage ? '' : 'md:hidden'} text-white/80 hover:text-white p-1 rounded-full hover:bg-white/10 transition-colors">
                        <span class="material-symbols-outlined text-[24px]">close</span>
                    </button>
                </div>
                <nav class="flex-1 space-y-2">
                `;

                guruMenu.forEach(item => {
                    const isActive = activePage.startsWith(item.page);
                    const activeClass = isActive 
                        ? 'bg-white text-primary rounded-l-full ml-4 py-3 pl-6 font-bold shadow-sm flex items-center gap-4 scale-95 transition-transform' 
                        : 'text-white/80 hover:text-white py-3 pl-10 flex items-center gap-4 transition-all duration-200 hover:bg-primary-fixed-dim/10 rounded-l-full ml-4';
                    const fillStyle = isActive ? "style=\"font-variation-settings: 'FILL' 1;\"" : "";
                    const hrefVal = (isActive && !window.location.search) ? 'javascript:void(0)' : item.page;
                    html += `
                    <a class="${activeClass}" href="${hrefVal}">
                        <span class="material-symbols-outlined" ${fillStyle}>${item.icon}</span>
                        <span class="font-label-md text-label-md">${item.text}</span>
                    </a>
                    `;
                });

                html += `
                </nav>
                <div class="px-6 mt-auto">
                    <div class="flex items-center gap-3 p-4 bg-white/10 rounded-xl border border-white/10 font-normal">
                        <div class="w-10 h-10 rounded-full bg-primary-fixed flex items-center justify-center text-primary font-bold flex-shrink-0" id="sidebar-initials">-</div>
                        <div class="overflow-hidden">
                            <p class="font-label-md text-label-md text-white truncate" id="sidebar-nama">-</p>
                            <p class="text-[10px] text-white/60" id="sidebar-kelas">-</p>
                        </div>
                    </div>
                </div>
                `;
                sidebar.innerHTML = html;

                // Auto-fetch profile data for teacher sidebar
                fetch(this.toAbsoluteUrl('/api/guru/beranda-stats.php'), { credentials: 'same-origin' })
                    .then(res => {
                        if (!res.ok) throw new Error();
                        return res.json();
                    })
                    .then(resData => {
                        if (resData.status === 'success' && resData.data && resData.data.guru) {
                            const g = resData.data.guru;
                            const initials = g.nama.split(' ').map(w => w[0]).join('').substring(0,2).toUpperCase();
                            const sidebarNama = document.getElementById('sidebar-nama');
                            const sidebarKelas = document.getElementById('sidebar-kelas');
                            const sidebarInitials = document.getElementById('sidebar-initials');
                            if (sidebarNama) sidebarNama.textContent = g.nama;
                            if (sidebarKelas) sidebarKelas.textContent = `Wali Kelas ${g.kelas}`;
                            if (sidebarInitials) sidebarInitials.textContent = initials;
                        }
                    })
                    .catch(err => {
                        console.warn('Gagal memuat info profil di sidebar:', err);
                    });
            }
        },
        toggleCollapse() {
            const collapsed = localStorage.getItem('sidebar_collapsed') === 'true';
            this.applyCollapse(!collapsed);
            localStorage.setItem('sidebar_collapsed', !collapsed);
        },
        applyCollapse(shouldCollapse) {
            const sidebar = document.getElementById('sidebar');
            const header = document.querySelector('header');
            const main = document.querySelector('main');
            const isHeaderInsideMain = main && header && main.contains(header);
            const toggleIcon = document.querySelector('#desktop-collapse-btn span');

            if (!sidebar) return;

            if (shouldCollapse) {
                sidebar.classList.remove('md:flex');
                sidebar.classList.add('md:hidden');
                
                if (this.isMarginLayout) {
                    if (header && !isHeaderInsideMain) {
                        header.classList.remove('md:ml-72', 'md:w-[calc(100%-18rem)]');
                        header.classList.add('md:ml-0', 'md:w-full');
                    }
                    
                    if (main) {
                        main.classList.remove('md:ml-72', 'md:w-[calc(100%-18rem)]');
                        main.classList.add('md:ml-0', 'md:w-full');
                    }
                }

                if (toggleIcon) {
                    toggleIcon.textContent = 'menu';
                }
            } else {
                sidebar.classList.remove('md:hidden');
                sidebar.classList.add('md:flex');
                
                if (this.isMarginLayout) {
                    if (header && !isHeaderInsideMain) {
                        header.classList.remove('md:ml-0', 'md:w-full');
                        header.classList.add('md:ml-72', 'md:w-[calc(100%-18rem)]');
                    }
                    
                    if (main) {
                        main.classList.remove('md:ml-0', 'md:w-full');
                        main.classList.add('md:ml-72', 'md:w-[calc(100%-18rem)]');
                    }
                }

                if (toggleIcon) {
                    toggleIcon.textContent = 'menu_open';
                }
            }
        },
        closeMobileSidebar() {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) {
                sidebar.classList.add('-translate-x-full', 'hidden');
                sidebar.classList.remove('flex');
            }
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    api.layout.init();
});
