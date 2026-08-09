<?php
/**
 * Bahasa Melayu (ms) — Rentetan UI kongsi bersama.
 *
 * Digunakan di merata-rata tempat. Kekalkan ia kecil — rentetan khusus modul
 * patut berada dalam lang/en/<module>.php. Lokaliti lain mencerminkan struktur
 * fail ini di bawah lang/<locale>/common.php.
 */
return [
    // Keutamaan keterlihatan panel kiri — label kongsi yang digunakan semula
    // oleh setiap modul yang mempunyai panel kiri (halaman tetapan + Sistem →
    // Keutamaan). Hanya rentetan yang sama persis berada di sini; salinan
    // pengenalan/penerangan khusus modul kekal dalam fail modul masing-masing.
    'left_panel' => [
        'tab'        => 'Panel kiri',
        'visibility' => 'Keterlihatan',
        'always'     => 'Sentiasa dipaparkan',
        'hover'      => 'Papar apabila tuding',
    ],

    // Panel pembekal/model/kunci AI kongsi bersama (includes/ai_settings_panel.php),
    // digunakan semula oleh tab tetapan AI setiap modul.
    'ai' => [
        'provider'            => 'Pembekal',
        'provider_anthropic'  => 'Anthropic (Claude)',
        'provider_openai'     => 'OpenAI (GPT)',
        'provider_openrouter' => 'OpenRouter (satu kunci, banyak model)',
        'openrouter_note'     => 'Dengan OpenRouter, satu kunci sahaja boleh mengakses beratus-ratus model. Ambil perhatian bahawa gesaan (prompt) dihalakan melalui perkhidmatan OpenRouter.',
        'model'               => 'Model',
        'model_placeholder'   => 'Taip atau pilih model…',
        'model_set'           => 'Model',
        'loading_models'      => 'Memuatkan senarai model…',
        'no_models'           => 'Tiada model sepadan — anda boleh menaip mana-mana ID model',
        'openrouter_pricing'  => 'Harga dipaparkan bagi setiap 1 juta token (masuk / keluar).',
        'models_stale'        => 'dalam cache',
        'api_key'             => 'Kunci API',
        'api_key_help'        => 'Disimpan dalam bentuk disulitkan. Biarkan kosong untuk mengekalkan kunci yang telah disimpan.',
        'api_key_set'         => 'Satu kunci telah disimpan. Biarkan kosong untuk mengekalkannya.',
        'verify_ssl'          => 'Sahkan sijil SSL',
        'verify_ssl_help'     => 'Biarkan ia diaktifkan dalam persekitaran produksi. Matikan hanya jika server anda tidak dapat mengesahkan sijil pembekal.',
        'save'                => 'Simpan',
        'test'                => 'Uji',
        'testing'             => 'Menguji…',
        'test_ok'             => 'Sambungan OK',
        'test_failed'         => 'Ujian gagal',
        'saved'               => 'Disimpan',
        'save_failed'         => 'Gagal menyimpan',
    ],

    // Butang
    'save'         => 'Simpan',
    'cancel'       => 'Batal',
    'delete'       => 'Padam',
    'add'          => 'Tambah',
    'edit'         => 'Sunting',
    'close'        => 'Tutup',
    'copy'         => 'Salin',
    'copied'       => 'Disalin',
    'retry'        => 'Cuba lagi',
    'export'       => 'Eksport',
    'back'         => 'Kembali',
    'open'         =>  'Buka',
    'apply'        => 'Guna',

    // Sahkan / status
    'yes'          => 'Ya',
    'no'           => 'Tidak',
    'ok'           => 'OK',
    'loading'      => 'Memuatkan...',
    'saving'       => 'Menyimpan...',
    'saved'        => 'Disimpan',
    'unsaved'      => 'Belum disimpan',
    'unsaved_changes' => 'Perubahan belum disimpan',
    'failed'       => 'Gagal',

    // Masa / unit (selalunya disisipkan dalam teks)
    'just_now'     => 'sebentar tadi',
    'today'        => 'Hari ini',
    'yesterday'    => 'Semalam',

    // Pembantu borang
    'required'     => 'Diperlukan',
    'optional'     => 'Pilihan',
    'select_one'   => 'Pilih…',
    'search'       => 'Cari',

    // Ralat
    'error_generic'        => 'Sesuatu telah berlaku ralat.',
    'error_network'        => 'Ralat rangkaian',
    'error_not_logged_in'  => 'Anda perlu log masuk.',

    // Halaman utama / laman pendaratan (index.php)
    'home' => [
        'header_title'     => 'Meja Perkhidmatan',
        'browser_title'    => 'Meja Perkhidmatan - ITSM',
        'welcome_heading'  => 'Apakah yang anda ingin lakukan?',
        'welcome_subtitle' => 'Pilih modul untuk bermula',
        'footer'           => 'Meja Perkhidmatan ITSM',
    ],

    // Panel penukar modul Waffle (pengepala kongsi)
    'waffle' => [
        'title' => 'Modul ITSM',
    ],

    // Nama paparan setiap modul + penerangan satu baris.
    // Digunakan oleh kad halaman utama (nama + petua alat penerangan) dan panel waffle (nama sahaja).
    'modules' => [
        'watchtower'     => ['name' => 'Menara Pengawal', 'description' => 'Papan pemuka perhatian bersepadu merentasi semua modul'],
        'tickets'        => ['name' => 'Tiket',       'description' => 'Urus permintaan sokongan, e-mel dan isu pengguna'],
        'assets'         => ['name' => 'Aset',        'description' => 'Jejak aset IT dan penugasan pengguna'],
        'knowledge'      => ['name' => 'Pengetahuan', 'description' => 'Cipta dan semak imbas artikel pangkalan pengetahuan'],
        'changes'        => ['name' => 'Perubahan',   'description' => 'Rancang, jejak dan urus perubahan IT'],
        'problems'       => ['name' => 'Pengurusan Masalah', 'name_short' => 'Masalah', 'description' => 'Jejak punca akar di sebalik insiden berulang'],
        'calendar'       => ['name' => 'Kalendar',    'description' => 'Jejak acara, tarikh akhir dan jadual'],
        'morning-checks' => ['name' => 'Pemeriksaan', 'description' => 'Rekod pemeriksaan infrastruktur harian'],
        'reporting'      => ['name' => 'Pelaporan',   'description' => 'Lihat log sistem dan analitik'],
        'software'       => ['name' => 'Perisian',    'description' => 'Semak imbas inventori perisian dan pelesenan'],
        'forms'          => ['name' => 'Borang',      'description' => 'Reka borang tersuai dan lihat penyerahan'],
        'contracts'      => ['name' => 'Kontrak',     'description' => 'Urus pembekal, kenalan dan kontrak'],
        'service-status' => ['name' => 'Status',      'description' => 'Pantau kesihatan perkhidmatan dan jejak insiden'],
        'wiki'           => ['name' => 'Wiki',        'description' => 'Semak imbas dokumentasi kod sumber yang dijana secara automatik'],
        'lms'            => ['name' => 'LMS',         'description' => 'Sistem Pengurusan Pembelajaran dengan pemain kursus SCORM'],
        'process-mapper' => ['name' => 'Proses',      'description' => 'Alat pemetaan proses dan carta alir visual'],
        'tasks'          => ['name' => 'Tugasan',     'description' => 'Papan Kanban dan paparan senarai untuk menjejak tugasan'],
        'cmdb'           => ['name' => 'CMDB',        'description' => 'Pangkalan Data Pengurusan Konfigurasi'],
        'network-mapper' => ['name' => 'Rangkaian',   'description' => 'Reka dan dokumenkan gambar rajah rangkaian'],
        'workflow'       => ['name' => 'Aliran Kerja', 'description' => 'Automasi merentasi modul — pencetus, syarat, tindakan'],
        'system'         => ['name' => 'Sistem',      'description' => 'Pentadbiran dan konfigurasi sistem'],
    ],

    // Menu akaun / pengguna dalam pengepala kongsi
    'account' => [
        'mail_check'      => 'Semak e-mel baharu',
        'preferences'     => 'Keutamaan',
        'appearance'      => 'Rupa',
        'change_password' => 'Tukar Kata Laluan',
        'mfa'             => 'Pengesahan Berbilang Faktor',
        'trusted_device'  => 'Peranti Dipercayai',
        'logout'          => 'Log Keluar',
        'logout_confirm'  => 'Adakah anda pasti mahu log keluar?',
        'badge_off'       => 'Mati',
        'badge_on'        => 'Hidup',
    ],

    // Modal tukar kata laluan (label statik — toast JS dinamik kekal dalam Bahasa Inggeris buat masa ini)
    'password_modal' => [
        'title'            => 'Tukar Kata Laluan',
        'current_password' => 'Kata Laluan Semasa',
        'new_password'     => 'Kata Laluan Baharu',
        'confirm_password' => 'Sahkan Kata Laluan Baharu',
        'submit'           => 'Tukar Kata Laluan',
    ],

    // Modal MFA (hanya tajuk statik — kandungan dinamik dijana oleh JS)
    'mfa_modal' => [
        'title' => 'Pengesahan Berbilang Faktor',
    ],

    // Elemen asas kalendar — bulan, hari dalam minggu, navigasi. Dikongsi oleh
    // mana-mana modul yang memaparkan kalendar (tickets/calendar.php pada masa
    // ini; calendar/ peringkat atas seterusnya).
    'calendar' => [
        'previous'   => 'Sebelumnya',
        'next'       => 'Seterusnya',
        'today'      => 'Hari ini',
        'view_month' => 'Bulan',
        'view_week'  => 'Minggu',
        'view_day'   => 'Hari',

        'months' => [
            'january'   => 'Januari',
            'february'  => 'Februari',
            'march'     => 'Mac',
            'april'     => 'April',
            'may'       => 'Mei',
            'june'      => 'Jun',
            'july'      => 'Julai',
            'august'    => 'Ogos',
            'september' => 'September',
            'october'   => 'Oktober',
            'november'  => 'November',
            'december'  => 'Disember',
        ],

        'weekdays' => [
            'monday'    => 'Isnin',
            'tuesday'   => 'Selasa',
            'wednesday' => 'Rabu',
            'thursday'  => 'Khamis',
            'friday'    => 'Jumaat',
            'saturday'  => 'Sabtu',
            'sunday'    => 'Ahad',
        ],
    ],
];
