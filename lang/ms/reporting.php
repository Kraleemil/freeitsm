<?php
/**
 * Bahasa Melayu (ms) — Rentetan modul Pelaporan.
 * Kunci yang hilang kembali kepada lang/en/reporting.php mengikut kunci.
 */
return [
    'title' => 'Pelaporan',

    'nav' => [
        'logs'    => 'Log',
        'tickets' => 'Tiket',
        'intune'  => 'Intune',
        'help'    => 'Bantuan',

        'logs_title'    => 'Log Sistem',
        'tickets_title' => 'Papan Pemuka Tiket',
        'intune_title'  => 'Papan Pemuka Intune',
        'help_title'    => 'Bantuan',
    ],

    'landing' => [
        'heading'  => 'Pelaporan',
        'subtitle' => 'Pilih kawasan pelaporan untuk bermula',

        'logs_title'    => 'Log Sistem',
        'logs_desc'     => 'Lihat percubaan log masuk, import e-mel, dan log aktiviti sistem lain.',
        'tickets_title' => 'Papan Pemuka Tiket',
        'tickets_desc'  => 'Papan pemuka KPI untuk prestasi tiket, masa penyelesaian, dan beban kerja pasukan.',
        'intune_title'  => 'Papan Pemuka Intune',
        'intune_desc'   => 'Pematuhan, penyulitan, taburan OS, trend pendaftaran, dan status sinkronisasi terakhir merentasi setiap peranti yang diurus.',
    ],

    'logs' => [
        'heading'  => 'Log sistem',
        'refresh'  => 'Muat semula',
        'tab_login'        => 'Log masuk pengguna',
        'tab_email_import' => 'Import e-mel',

        'loading'        => 'Memuatkan log...',
        'no_logs'        => 'Tiada log dijumpai',
        'load_error'     => 'Ralat memuatkan log: {error}',

        'col_datetime'    => 'Tarikh/masa',
        'col_username'    => 'Nama pengguna',
        'col_status'      => 'Status',
        'col_ip'          => 'Alamat IP',
        'col_user_agent'  => 'Ejen pengguna',
        'col_from'        => 'Daripada',
        'col_subject'     => 'Subjek',
        'col_type'        => 'Jenis',
        'col_attachments' => 'Lampiran',

        'status_success' => 'Berjaya',
        'status_failed'  => 'Gagal',
        'unknown'        => 'Tidak diketahui',
        'no_subject'     => '(Tiada Subjek)',
        'new_ticket'     => 'Tiket Baharu',
        'reply'          => 'Balas',
        'none'           => 'Tiada',

        'row_title'  => 'Klik untuk lihat butiran JSON',

        'pagination' => 'Halaman {current} daripada {total} ({count} jumlah)',
        'prev'       => 'Sebelumnya',
        'next'       => 'Seterusnya',

        'modal_title' => 'Butiran Log (JSON)',
        'close'       => 'Tutup',
    ],

    'tickets' => [
        'heading' => 'Papan Pemuka Tiket',
        'coming_soon' => 'Papan pemuka KPI dan pelaporan untuk prestasi tiket, masa penyelesaian, dan beban kerja pasukan akan tersedia di sini tidak lama lagi.',
    ],

    'intune' => [
        'heading'      => 'Papan Pemuka Intune',
        'loading_meta' => 'Memuatkan…',
        'refresh'      => 'Muat semula',
        'refresh_title'=> 'Muat semula data',
        'loading_data' => 'Memuatkan data Intune…',

        'last_sync'    => 'Sinkronisasi terakhir: {when}',
        'error'        => 'Ralat: {error}',
        'load_failed'  => 'Gagal memuatkan papan pemuka: {error}',
        'no_devices_title' => 'Tiada peranti Intune dijumpai.',
        'no_devices_body'  => 'Jalankan sinkronisasi Intune daripada modul Aset untuk mengimport peranti, kemudian kembali ke sini.',
        'no_data'      => 'Tiada data',
        'unknown'      => 'Tidak diketahui',

        // KPI strip
        'kpi_total'            => 'Jumlah Peranti',
        'kpi_total_sub'        => 'Semua peranti yang diurus',
        'kpi_compliant'        => 'Mematuhi',
        'kpi_compliant_sub'    => '{count} daripada {total}',
        'kpi_encrypted'        => 'Disulitkan',
        'kpi_encrypted_sub'    => '{count} daripada {total}',
        'kpi_stale'            => 'Lapuk (30+ hari)',
        'kpi_stale_sub'        => 'Tiada sinkronisasi dalam 30 hari terakhir',
        'kpi_enrolled'         => 'Didaftarkan (30 hari)',
        'kpi_enrolled_sub'     => 'Baharu dalam 30 hari terakhir',

        'kpi_compliant_drill'  => 'Peranti mematuhi',
        'kpi_encrypted_drill'  => 'Peranti disulitkan',
        'kpi_stale_drill'      => 'Lapuk (30+ hari)',
        'kpi_enrolled_drill'   => 'Didaftarkan dalam 30 hari terakhir',

        // Widgets
        'w_compliance_title'   => 'Pecahan Pematuhan',
        'w_compliance_desc'    => 'Peranti mengikut status pematuhan',
        'w_os_title'           => 'Sistem Operasi',
        'w_os_desc'            => 'Peranti dikumpulkan mengikut OS',
        'w_owner_title'        => 'Jenis Pemilik',
        'w_owner_desc'         => 'Peranti korporat vs peribadi',
        'w_manufacturers_title'=> 'Pengeluar Teratas',
        'w_manufacturers_desc' => 'Peranti mengikut pengeluar (10 teratas)',
        'w_os_versions_title'  => 'Versi OS Teratas',
        'w_os_versions_desc'   => 'Kombinasi OS + versi yang paling biasa',
        'w_last_sync_title'    => 'Tempoh Sinkronisasi Terakhir',
        'w_last_sync_desc'     => 'Kekerapan peranti mendaftar masuk baru-baru ini',
        'w_enrolment_title'    => 'Pendaftaran (90 hari terakhir)',
        'w_enrolment_desc'     => 'Peranti baharu didaftarkan setiap hari',
        'w_encryption_title'   => 'Penyulitan mengikut OS',
        'w_encryption_desc'    => 'Disulitkan vs tidak disulitkan, mengikut OS',

        // Chart tooltips / labels
        'tooltip_enrolled'     => '{count} didaftarkan (klik untuk perincian)',
        'drill_enrolled_on'    => 'Didaftarkan pada {date}',

        // Drill-down modal
        'drill_devices'        => 'Peranti',
        'drill_loading'        => 'Memuatkan…',
        'drill_count'          => '{count} peranti',
        'drill_count_plural'   => '{count} peranti',
        'drill_no_match'       => 'Tiada peranti sepadan dengan penapis ini.',
        'drill_error'          => 'Ralat: {error}',
        'drill_load_failed'    => 'Gagal memuatkan: {error}',
        'drill_page_info'      => 'Halaman {current} daripada {total}',
        'drill_prev'           => '‹ Sebelumnya',
        'drill_next'           => 'Seterusnya ›',
        'drill_export'         => 'Eksport CSV',
        'drill_close'          => 'Tutup',

        'drill_col_device'     => 'Peranti',
        'drill_col_user'       => 'Pengguna',
        'drill_col_os'         => 'OS',
        'drill_col_compliance' => 'Pematuhan',
        'drill_col_encrypted'  => 'Disulitkan',
        'drill_col_last_sync'  => 'Sinkronisasi Terakhir',

        'never'                => 'Tidak pernah',
        'yes'                  => 'Ya',
        'no'                   => 'Tidak',
    ],

    'help' => [
        'page_title' => 'Panduan Pelaporan',
        'guide'      => 'Panduan',

        'hero_heading' => 'Panduan pelaporan',
        'hero_sub'     => 'Ubah data pusat khidmat anda menjadi pandangan yang boleh ditindaklanjuti dengan log, analitik, dan papan pemuka.',

        'nav_overview'           => 'Gambaran keseluruhan',
        'nav_ticket_reports'     => 'Laporan tiket',
        'nav_system_logs'        => 'Log sistem',
        'nav_understanding_data' => 'Memahami data',
        'nav_settings_filters'   => 'Tetapan & penapis',
        'nav_tips'               => 'Tip pantas',

        // Section 1: Overview
        's1_heading' => 'Gambaran keseluruhan',
        's1_intro'   => 'Modul Pelaporan menghimpunkan segala yang berlaku merentasi pusat khidmat anda dalam satu tempat. Jejaki prestasi tiket, pantau aktiviti sistem, semak percubaan log masuk, dan audit import e-mel — semuanya daripada satu modul yang direka untuk membantu anda mengenal pasti trend dan membuat keputusan berasaskan data.',
        's1_card1_title' => 'Analitik tiket',
        's1_card1_body'  => 'Visualisasikan volum tiket, masa penyelesaian, pematuhan SLA, dan beban kerja pasukan melalui papan pemuka interaktif yang dikemas kini secara masa nyata.',
        's1_card2_title' => 'Log sistem',
        's1_card2_body'  => 'Semak setiap percubaan log masuk, import e-mel, dan peristiwa sistem dalam jadual yang boleh dicari dan ditapis dengan cap masa dan penunjuk status.',
        's1_card3_title' => 'Penjejakan aktiviti',
        's1_card3_body'  => 'Pantau aktiviti penganalisis merentasi platform — siapa yang log masuk, tiket apa yang sedang dikerjakan, dan di mana masa dihabiskan.',
        's1_card4_title' => 'Jejak audit',
        's1_card4_body'  => 'Setiap tindakan direkodkan berserta siapa yang melakukannya, bila, dan apa yang berubah. Penting untuk pematuhan, semakan keselamatan, dan penyelesaian masalah.',

        // Section 2: Ticket reports
        's2_heading' => 'Laporan tiket',
        's2_intro'   => 'Kawasan Tiket dalam pelaporan menyediakan papan pemuka KPI yang memberikan anda gambaran jelas tentang prestasi pusat khidmat anda. Papan pemuka ini menarik data terus daripada rekod tiket anda dan mempersembahkannya melalui carta dan kad ringkasan.',
        's2_card1_title' => 'Volum tiket',
        's2_card1_body'  => 'Lihat berapa banyak tiket dicipta, diselesaikan, dan masih terbuka dalam mana-mana tempoh masa. Kenal pasti hari sibuk dan corak bermusim.',
        's2_card2_title' => 'Pematuhan SLA',
        's2_card2_body'  => 'Jejaki peratusan tiket yang mencapai sasaran respons dan penyelesaian mereka. Perincikan mengikut keutamaan atau kategori untuk mencari kawasan bermasalah.',
        's2_card3_title' => 'Masa penyelesaian',
        's2_card3_body'  => 'Ukur masa purata dan median untuk menyelesaikan tiket. Bandingkan merentasi pasukan, kategori, atau tahap keutamaan untuk mengesan kesesakan.',
        's2_card4_title' => 'Beban kerja pasukan',
        's2_card4_body'  => 'Lihat cara tiket diagihkan merentasi penganalisis. Kenal pasti siapa yang terlalu banyak beban dan siapa yang mempunyai kapasiti untuk mengambil lebih banyak kerja.',
        's2_card5_title' => 'Pecahan kategori',
        's2_card5_body'  => 'Fahami jenis isu yang menjana tiket paling banyak. Gunakan ini untuk menyasarkan latihan, dokumentasi, atau penambahbaikan layan diri.',
        's2_card6_title' => 'Analisis trend',
        's2_card6_body'  => 'Lihat data tiket sepanjang minggu, bulan, atau suku tahun untuk mengesan trend jangka panjang dan mengukur kesan penambahbaikan proses.',
        's2_tip'         => 'Papan pemuka tiket boleh diakses melalui tab Tiket dalam navigasi pengepala. Gunakan penapis julat tarikh untuk membandingkan tempoh berbeza secara bersebelahan.',

        // Section 3: System logs
        's3_heading' => 'Log sistem',
        's3_intro'   => 'Kawasan Log merekodkan segala yang berlaku di sebalik tabir dalam instans FreeITSM anda. Setiap percubaan log masuk, import e-mel, dan peristiwa sistem direkodkan dengan cap masa dan status supaya anda sentiasa mempunyai gambaran lengkap aktiviti platform.',
        's3_badge_login'  => 'LOG MASUK',
        's3_badge_email'  => 'E-MEL',
        's3_badge_system' => 'SISTEM',
        's3_badge_audit'  => 'AUDIT',
        's3_login_title'  => 'Percubaan log masuk',
        's3_login_body'   => 'Setiap log masuk yang berjaya dan gagal direkodkan dengan nama penganalisis, alamat IP, dan cap masa. Percubaan yang gagal ditandakan merah supaya anda dapat mengesan dengan cepat percubaan akses tanpa kebenaran atau pengguna yang disekat.',
        's3_email_title'  => 'Import e-mel',
        's3_email_body'   => 'Apabila sistem memproses e-mel masuk menjadi tiket, setiap import direkodkan dengan alamat penghantar, baris subjek, dan sama ada ia berjaya ditukar. Import yang gagal menunjukkan sebabnya supaya anda boleh menyiasat mesej yang dipulangkan atau tidak sah.',
        's3_system_title' => 'Peristiwa sistem',
        's3_system_body'  => 'Proses latar belakang, tugas berjadual, perubahan konfigurasi, dan aktiviti API semuanya direkodkan di sini. Gunakan log ini untuk mengesahkan tugas automatik berjalan dengan betul dan untuk mendiagnosis isu.',
        's3_audit_title'  => 'Entri audit',
        's3_audit_body'   => 'Penjejakan perubahan pada peringkat medan merentasi platform. Lihat dengan tepat siapa yang mengubah apa, bila, dan apakah nilai sebelumnya. Amat berharga untuk keperluan pematuhan dan penyelesaian pertikaian.',
        's3_step1_title' => 'Buka tab Log',
        's3_step1_body'  => 'klik Log dalam navigasi pengepala untuk mengakses pelihat log sistem.',
        's3_step2_title' => 'Tukar antara jenis log',
        's3_step2_body'  => 'gunakan bar tab di bahagian atas untuk menapis mengikut percubaan log masuk, import e-mel, atau peristiwa sistem.',
        's3_step3_title' => 'Semak butirannya',
        's3_step3_body'  => 'setiap baris menunjukkan cap masa, lencana status (berjaya atau gagal), dan butiran kontekstual seperti alamat IP, subjek e-mel, atau penerangan peristiwa.',
        's3_tip'         => 'Semak log log masuk secara berkala untuk percubaan gagal berulang daripada alamat IP yang tidak dikenali. Ini boleh menandakan serangan brute-force atau bukti kelayakan yang terjejas yang memerlukan perhatian segera.',

        // Section 4: Understanding the data
        's4_heading' => 'Memahami data',
        's4_intro'   => 'Data mentah hanya menjadi berguna apabila anda tahu apa yang perlu dicari. Berikut ialah metrik utama untuk diperhatikan dan cara mentafsirkannya untuk memacu penambahbaikan sebenar dalam operasi pusat khidmat anda.',
        's4_metric1_title' => 'Masa respons pertama',
        's4_metric1_body'  => 'Berapa lama pengguna menunggu sebelum seorang penganalisis mengakui tiket mereka. Trend menaik di sini bermakna pasukan anda mungkin kekurangan kakitangan atau tiket tidak diarahkan dengan berkesan. Sasaran: di bawah ambang SLA anda.',
        's4_metric2_title' => 'Kadar penyelesaian',
        's4_metric2_body'  => 'Peratusan tiket yang diselesaikan dalam tempoh tertentu berbanding yang dicipta. Jika lebih banyak tiket masuk berbanding yang keluar, tunggakan anda semakin bertambah dan anda perlu menyiasat puncanya.',
        's4_metric3_title' => 'Hubungan berulang',
        's4_metric3_body'  => 'Tiket yang dibuka semula atau pengguna yang melaporkan isu yang sama berkali-kali. Kadar hubungan berulang yang tinggi menunjukkan punca utama tidak ditangani, atau penyelesaian tidak disampaikan dengan jelas.',
        's4_metric4_title' => 'Titik panas kategori',
        's4_metric4_body'  => 'Kategori mana yang menjana tiket paling banyak dari semasa ke semasa. Lonjakan dalam kategori tertentu boleh menandakan sistem yang gagal, kemas kini perisian yang bermasalah, atau jurang dalam latihan pengguna yang perlu ditangani.',
        's4_combine'     => 'Gunakan metrik ini bersama-sama, bukan secara berasingan. Sebagai contoh, kadar penyelesaian yang tinggi digabungkan dengan kadar hubungan berulang yang tinggi mungkin menunjukkan tiket ditutup terlalu cepat tanpa menyelesaikan masalah asas.',
        's4_tip'         => 'Jadualkan semakan mingguan metrik utama anda bersama pasukan. Corak yang tidak kelihatan pada asas harian sering menjadi jelas apabila dilihat pada kadar mingguan atau bulanan.',

        // Section 5: Settings & filters
        's5_heading' => 'Tetapan & penapis',
        's5_intro'   => 'Kedua-dua pelihat log dan papan pemuka tiket menyokong pelbagai penapis untuk membantu anda menyempitkan data yang tepat anda perlukan. Penggunaan penapis yang berkesan mengubah timbunan data menjadi maklumat yang disasarkan dan boleh ditindaklanjuti.',
        's5_step1_title' => 'Julat tarikh',
        's5_step1_body'  => 'tapis log dan laporan kepada tempoh masa tertentu. Gunakan julat pratetap (hari ini, minggu ini, bulan ini) atau tetapkan tarikh mula dan tamat tersuai untuk kawalan yang tepat.',
        's5_step2_title' => 'Penapis status',
        's5_step2_body'  => 'dalam pelihat log, tapis mengikut status berjaya atau gagal untuk mengasingkan masalah dengan cepat. Dalam laporan tiket, tapis mengikut status terbuka, diselesaikan, atau ditutup.',
        's5_step3_title' => 'Carian',
        's5_step3_body'  => 'gunakan kotak carian untuk mencari entri tertentu mengikut kata kunci. Dalam log, ini mencari merentasi nama penganalisis, alamat IP, subjek e-mel, dan penerangan peristiwa.',
        's5_step4_title' => 'Pengelompokan masa',
        's5_step4_body'  => 'dalam papan pemuka tiket, kelompokkan data mengikut hari, minggu, atau bulan untuk mengubah kehalusan carta anda. Paparan harian menunjukkan lonjakan jangka pendek; paparan bulanan mendedahkan trend jangka panjang.',
        's5_step5_title' => 'Penapis jabatan',
        's5_step5_body'  => 'sempitkan hasil papan pemuka kepada jabatan tertentu untuk membandingkan prestasi merentasi bahagian organisasi yang berbeza.',
        's5_tip'         => "Gabungkan pelbagai penapis untuk analisis yang disasarkan. Sebagai contoh, tapis mengikut jabatan tertentu dan julat tarikh untuk melihat cara perubahan proses baru-baru ini menjejaskan volum tiket pasukan tersebut.",

        // Section 6: Quick tips
        's6_heading' => 'Tip pantas',
        's6_tip1_title' => 'Semak secara berkala',
        's6_tip1_body'  => 'Laporan paling bernilai apabila disemak secara konsisten. Tetapkan kekerapan — mingguan untuk metrik operasi, bulanan untuk analisis trend — dan patuhinya.',
        's6_tip2_title' => 'Siasat anomali',
        's6_tip2_body'  => 'Lonjakan atau penurunan mendadak dalam mana-mana metrik adalah isyarat yang wajar disiasat. Semak log untuk konteks — adakah berlaku gangguan sistem, pelancaran perisian, atau perubahan kakitangan?',
        's6_tip3_title' => 'Bandingkan tempoh',
        's6_tip3_body'  => 'Gunakan penapis tarikh untuk membandingkan minggu ini dengan minggu lepas, atau bulan ini dengan bulan yang sama tahun lepas. Perbandingan relatif mendedahkan penambahbaikan atau kemerosotan dengan lebih jelas berbanding nombor mentah.',
        's6_tip4_title' => 'Pantau keselamatan',
        's6_tip4_body'  => 'Perhatikan percubaan log masuk yang gagal dalam log sistem. Kegagalan berulang daripada alamat IP yang sama atau terhadap akaun yang sama boleh menandakan kebimbangan keselamatan yang perlu dieskalasikan.',
    ],
];
