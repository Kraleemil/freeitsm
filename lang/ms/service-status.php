<?php
/**
 * Bahasa Melayu (ms) — Rentetan modul Status Perkhidmatan.
 * Kunci yang tiada akan kembali kepada lang/en/service-status.php bagi setiap kunci.
 */
return [
    'title' => 'Status Perkhidmatan',

    'nav' => [
        'status'   => 'Status',
        'settings' => 'Tetapan',
        'help'     => 'Bantuan',
    ],

    'board' => [
        'services'        => 'Perkhidmatan',
        'service_count'   => '{count} perkhidmatan',
        'loading'         => 'Memuatkan...',
        'no_services'     => 'Tiada perkhidmatan dikonfigurasi. Pergi ke Tetapan untuk menambah perkhidmatan.',
        'incidents'       => 'Insiden',
        'new'             => 'Baharu',
        'col_title'       => 'Tajuk',
        'col_status'      => 'Status',
        'col_affected'    => 'Perkhidmatan Terjejas',
        'col_updated'     => 'Dikemas kini',
        'no_incidents'    => 'Tiada insiden untuk dipaparkan.',
        'none'            => 'Tiada',
    ],

    'modal' => [
        'new_incident'        => 'Insiden Baharu',
        'edit_incident'       => 'Edit Insiden',
        'title'               => 'Tajuk',
        'title_placeholder'   => 'Penerangan ringkas tentang insiden',
        'status'              => 'Status',
        'comment'             => 'Komen',
        'comment_placeholder' => 'Butiran tentang insiden...',
        'affected_services'   => 'Perkhidmatan Terjejas',
        'add_service'         => '+ Tambah Perkhidmatan',
        'delete'              => 'Padam',
        'cancel'              => 'Batal',
        'save'                => 'Simpan',
    ],

    'toast' => [
        'incident_saved'   => 'Insiden disimpan',
        'incident_deleted' => 'Insiden dipadam',
        'save_failed'      => 'Gagal menyimpan',
        'delete_failed'    => 'Gagal memadam',
        'save_incident_failed'   => 'Gagal menyimpan insiden',
        'delete_incident_failed' => 'Gagal memadam insiden',
        'saved'            => 'Disimpan',
        'deleted'          => 'Dipadam',
        'save_service_failed'    => 'Gagal menyimpan perkhidmatan',
        'delete_service_failed'  => 'Gagal memadam perkhidmatan',
    ],

    'confirm' => [
        'delete_incident_title'   => 'Padam insiden',
        'delete_incident_message' => 'Padam insiden ini?',
        'delete_title'            => 'Padam',
        'delete_message'          => 'Padam "{name}"?',
        'delete_label'            => 'Padam',
    ],

    'settings' => [
        'tab_services'     => 'Perkhidmatan',
        'tab_statuses'     => 'Status',
        'tab_impacts'      => 'Tahap Impak',

        'services_heading' => 'Perkhidmatan',
        'statuses_heading' => 'Status Insiden',
        'impacts_heading'  => 'Tahap Impak',
        'add'              => 'Tambah',
        'loading'          => 'Memuatkan...',
        'no_services'      => 'Belum ada perkhidmatan. Klik Tambah untuk mencipta satu.',
        'no_items'         => 'Tiada item dijumpai',
        'load_failed'      => 'Gagal memuatkan data',
        'error_prefix'     => 'Ralat: {message}',

        'statuses_intro_html' => 'Keadaan aliran kerja untuk insiden perkhidmatan. Status yang ditanda sebagai <em>diselesaikan</em> menutup insiden — menstempel <code>resolved_datetime</code> secara automatik dan mengeluarkan insiden daripada papan pemuka aktif. Tepat satu status menjadi lalai untuk insiden baharu.',
        'impacts_intro_html'  => 'Jalur keterukan yang dipaparkan sebagai lencana pada setiap kad perkhidmatan. <strong>Susunan keterukan</strong> menentukan susunan "impak semasa paling teruk" pada papan pemuka — lebih rendah = lebih teruk (1 = gangguan besar, 5 = beroperasi). Dua baris boleh berkongsi susunan yang sama.',

        'col_name'        => 'Nama',
        'col_description' => 'Penerangan',
        'col_order'       => 'Susunan',
        'col_status'      => 'Status',
        'col_actions'     => 'Tindakan',
        'col_colour'      => 'Warna',
        'col_resolved'    => 'Diselesaikan',
        'col_default'     => 'Lalai',
        'col_severity'    => 'Keterukan',

        'active'          => 'Aktif',
        'inactive'        => 'Tidak Aktif',
        'yes'             => 'Ya',
        'no'              => 'Tidak',
        'edit'            => 'Edit',
        'delete'          => 'Padam',

        'kind_status'     => 'status',
        'kind_impact'     => 'tahap impak',

        // Service modal
        'add_service'     => 'Tambah perkhidmatan',
        'edit_service'    => 'Edit perkhidmatan',
        'field_name'      => 'Nama',
        'field_description' => 'Penerangan',
        'field_order'     => 'Susunan paparan',
        'field_active'    => 'Aktif',

        // Lookup modal (statuses + impact levels)
        'add_item'        => 'Tambah item',
        'add_kind'        => 'Tambah {kind}',
        'edit_kind'       => 'Edit {kind}',
        'field_colour'    => 'Warna',
        'field_resolved'  => 'Dikira sebagai diselesaikan',
        'resolved_help_html' => 'Insiden dalam status ini menstempel <code>resolved_datetime</code> secara automatik dan digugurkan daripada papan pemuka aktif.',
        'field_severity'  => 'Susunan keterukan',
        'severity_help'   => '1 = paling teruk (Gangguan Besar). Lebih tinggi = kurang teruk.',
        'field_default'   => 'Lalai',

        'cancel'          => 'Batal',
        'save'            => 'Simpan',
    ],

    'help' => [
        'page_title' => 'Panduan Status Perkhidmatan',
        'guide'      => 'Panduan',

        'nav_overview'  => 'Gambaran Keseluruhan',
        'nav_dashboard' => 'Papan pemuka status',
        'nav_services'  => 'Menguruskan perkhidmatan',
        'nav_history'   => 'Sejarah insiden',
        'nav_settings'  => 'Tetapan',
        'nav_tips'      => 'Petua Ringkas',

        'hero_title' => 'Panduan status perkhidmatan',
        'hero_sub'   => 'Pantau perkhidmatan IT anda, komunikasikan insiden, dan pastikan pihak berkepentingan dimaklumkan secara masa nyata.',

        // Section 1: Overview
        'overview_heading' => 'Gambaran Keseluruhan',
        'overview_intro'   => 'Modul Status Perkhidmatan memberikan anda paparan berpusat tentang kesihatan setiap perkhidmatan IT yang organisasi anda bergantung kepadanya. Apabila sesuatu yang tidak kena berlaku, anda boleh merekodkan insiden, mengemas kini perkhidmatan yang terjejas, dan memastikan pengguna dimaklumkan sepanjang proses penyelesaian.',
        'feature_dashboard_title' => 'Papan pemuka status',
        'feature_dashboard_desc'  => 'Lihat kesihatan semasa setiap perkhidmatan sepintas lalu. Lencana berkod warna menunjukkan sama ada setiap perkhidmatan beroperasi, merosot, dalam penyelenggaraan, atau mengalami gangguan.',
        'feature_incident_title'  => 'Penjejakan insiden',
        'feature_incident_desc'   => 'Rekodkan insiden dengan tajuk, kemas kini status, dan komen. Pautkan perkhidmatan terjejas kepada setiap insiden supaya semua orang tahu dengan tepat apa yang terjejas dan sebabnya.',
        'feature_management_title' => 'Pengurusan perkhidmatan',
        'feature_management_desc'  => 'Konfigurasikan katalog perkhidmatan anda dalam tetapan. Tambah perkhidmatan dengan nama, penerangan, dan susunan paparan. Aktifkan atau nyahaktifkan perkhidmatan mengikut evolusi infrastruktur anda.',
        'feature_comms_title' => 'Komunikasi',
        'feature_comms_desc'  => 'Pastikan pihak berkepentingan dimaklumkan dengan kemas kini status masa nyata. Setiap insiden membawa status dan jejak komen supaya pengguna boleh mengikuti kemajuan penyelesaian tanpa perlu mengejar meja bantuan.',

        // Section 2: Dashboard
        'dashboard_heading' => 'Papan pemuka status',
        'dashboard_p1'      => 'Papan pemuka ialah perkara pertama yang anda lihat apabila membuka modul Status Perkhidmatan. Ia memaparkan grid kad perkhidmatan, setiap satu menunjukkan nama perkhidmatan, penerangan ringkas, dan lencana impak berkod warna yang mencerminkan status paling teruk semasa. Di bawah grid terletak jadual insiden yang menyenaraikan semua insiden terkini dan aktif.',
        'dashboard_p2_html' => 'Setiap kad perkhidmatan secara automatik mencerminkan tahap impak paling teruk yang ditetapkan kepadanya daripada mana-mana insiden aktif (belum diselesaikan). Apabila semua insiden yang menjejaskan perkhidmatan diselesaikan, ia kembali kepada <strong>Beroperasi</strong>.',
        'status_levels'     => 'Tahap status',
        'level_operational_name' => 'Beroperasi',
        'level_operational_desc' => 'Perkhidmatan berjalan secara normal tanpa sebarang isu yang diketahui. Ini ialah keadaan lalai untuk semua perkhidmatan yang sihat.',
        'level_degraded_name'    => 'Prestasi Merosot',
        'level_degraded_desc'    => 'Perkhidmatan tersedia tetapi berjalan lebih perlahan daripada dijangka atau dengan fungsi yang berkurangan. Pengguna mungkin perasan kelewatan.',
        'level_maintenance_name' => 'Dalam Penyelenggaraan',
        'level_maintenance_desc' => 'Masa henti terancang atau tempoh penyelenggaraan. Perkhidmatan mungkin tidak tersedia buat sementara waktu semasa kerja dijalankan.',
        'level_outage_name'      => 'Gangguan Besar',
        'level_outage_desc'      => 'Perkhidmatan langsung tidak tersedia. Ini ialah status paling teruk dan harus mencetuskan siasatan segera.',
        'dashboard_tip'     => 'Tahap impak adalah berhierarki. Jika perkhidmatan dipautkan kepada beberapa insiden aktif, papan pemuka menunjukkan impak paling teruk. Sebagai contoh, satu insiden menandakan perkhidmatan sebagai Merosot dan satu lagi menandakannya sebagai Gangguan Besar akan menghasilkan paparan Gangguan Besar.',

        // Section 3: Managing services
        'services_heading_html' => 'Menguruskan perkhidmatan &amp; merekod insiden',
        'services_intro'        => 'Perkhidmatan ialah blok binaan bagi halaman status anda. Setiap satu mewakili perkhidmatan IT, sistem, atau komponen infrastruktur yang bergantung kepadanya oleh pengguna anda. Apabila sesuatu yang tidak kena berlaku, anda mencipta insiden dan memautkannya kepada perkhidmatan yang terjejas.',
        'add_incident_heading'  => 'Menambah insiden baharu',
        'add_incident_step1_html' => '<strong>Klik "Baharu"</strong> pada papan pemuka untuk membuka borang insiden.',
        'add_incident_step2_html' => '<strong>Masukkan tajuk</strong> &mdash; penerangan ringkas dan jelas tentang isu tersebut. Contohnya: "Kelewatan penghantaran e-mel" atau "Get laluan VPN tidak dapat diakses".',
        'add_incident_step3_html' => '<strong>Tetapkan status</strong> &mdash; pilih Menyiasat, Dikenal Pasti, Pihak Ke-3, Memantau, atau Diselesaikan. Mula dengan Menyiasat dan kemas kini apabila anda mengetahui lebih lanjut.',
        'add_incident_step4_html' => '<strong>Tambah komen</strong> &mdash; terangkan apa yang diketahui setakat ini, tindakan yang sedang diambil, dan sebarang penyelesaian sementara yang tersedia untuk pengguna.',
        'add_incident_step5_html' => '<strong>Pautkan perkhidmatan terjejas</strong> &mdash; tambah satu atau lebih perkhidmatan dan pilih tahap impak untuk setiap satu (Gangguan Besar, Gangguan Sebahagian, Merosot, Penyelenggaraan, Beroperasi, atau Tiada Gangguan).',
        'add_incident_step6_html' => '<strong>Simpan</strong> &mdash; insiden muncul dalam jadual dan kad perkhidmatan terjejas dikemas kini serta-merta pada papan pemuka.',
        'workflow_heading'  => 'Aliran kerja status insiden',
        'workflow_investigating' => 'Menyiasat',
        'workflow_identified'    => 'Dikenal Pasti',
        'workflow_monitoring'    => 'Memantau',
        'workflow_resolved'      => 'Diselesaikan',
        'workflow_note_html'     => 'Gunakan <strong>Pihak Ke-3</strong> apabila punca akar terletak pada vendor atau pembekal luaran.',
        'services_tip'      => 'Anda boleh mengedit mana-mana insiden dengan mengklik tajuknya dalam jadual. Kemas kini status, tambah komen baharu, atau ubah perkhidmatan terjejas apabila keadaan berkembang. Memastikan insiden sentiasa dikemas kini adalah kunci kepada komunikasi yang telus.',

        // Section 4: Incident history
        'history_heading' => 'Sejarah insiden',
        'history_p1'      => 'Jadual insiden pada papan pemuka menunjukkan insiden aktif dan yang telah diselesaikan, memberikan anda garis masa lengkap kesihatan perkhidmatan. Setiap baris memaparkan tajuk insiden, status semasa, perkhidmatan terjejas berserta tahap impaknya, dan cap masa kemas kini terakhir.',
        'history_field_title_html'    => '<strong>Tajuk</strong> &mdash; pautan boleh klik yang membuka insiden untuk penyuntingan. Gunakan tajuk yang jelas dan deskriptif supaya sejarah mudah diimbas.',
        'history_field_status_html'   => '<strong>Status</strong> &mdash; lencana berkod warna yang menunjukkan fasa siasatan semasa (Menyiasat, Dikenal Pasti, Pihak Ke-3, Memantau, atau Diselesaikan).',
        'history_field_affected_html' => '<strong>Perkhidmatan terjejas</strong> &mdash; lencana bertag yang menunjukkan setiap perkhidmatan yang dipautkan berserta warna tahap impaknya. Sepintas lalu anda boleh melihat apa yang terjejas dan setakat mana keterukannya.',
        'history_field_updated_html'  => '<strong>Dikemas kini</strong> &mdash; cap masa perubahan terkini. Insiden yang diselesaikan digayakan dengan teks pudar supaya insiden aktif menonjol secara visual.',
        'history_p2'      => 'Insiden yang diselesaikan kekal kelihatan dalam jadual sebagai rekod sejarah. Ini memudahkan pengesanan isu berulang, semakan cara insiden lepas dikendalikan, dan pengenalpastian corak yang mungkin menunjukkan masalah asas.',
        'history_tip'     => 'Menyemak sejarah insiden anda secara berkala membantu anda mengenal pasti perkhidmatan yang kerap terganggu. Jika perkhidmatan yang sama muncul dalam beberapa insiden, mungkin sudah tiba masanya untuk menyiasat punca akar dengan lebih mendalam atau merancang naik taraf infrastruktur.',

        // Section 5: Settings
        'settings_heading' => 'Tetapan',
        'settings_p1'      => 'Halaman Tetapan ialah tempat anda membina dan menyelenggara katalog perkhidmatan anda. Setiap perkhidmatan yang muncul pada papan pemuka status mesti dikonfigurasikan di sini terlebih dahulu.',
        'settings_step1_html' => '<strong>Tambah perkhidmatan</strong> &mdash; klik "Tambah" dan berikan nama (cth. "E-mel", "VPN", "Sistem ERP") dan penerangan pilihan yang menerangkan fungsi perkhidmatan tersebut.',
        'settings_step2_html' => '<strong>Tetapkan susunan paparan</strong> &mdash; nombor susunan mengawal di mana perkhidmatan muncul pada grid papan pemuka. Nombor lebih rendah muncul dahulu, jadi letakkan perkhidmatan paling kritikal anda di bahagian atas.',
        'settings_step3_html' => '<strong>Togol aktif/tidak aktif</strong> &mdash; menyahaktifkan perkhidmatan mengeluarkannya daripada papan pemuka tanpa memadamkannya. Ini berguna untuk perkhidmatan yang telah dinyahtauliahkan atau sistem bermusim.',
        'settings_step4_html' => '<strong>Edit atau padam</strong> &mdash; gunakan butang tindakan pada setiap baris untuk mengemas kini butiran perkhidmatan atau mengeluarkan perkhidmatan sepenuhnya. Penyuntingan sentiasa lebih digalakkan berbanding pemadaman supaya pautan insiden sejarah kekal utuh.',
        'settings_tip'     => 'Anggap katalog perkhidmatan anda sebagai asas halaman status anda. Luangkan masa untuk memastikan nama dan penerangan tepat &mdash; inilah yang akan dilihat oleh pengguna dan pihak berkepentingan anda apabila menyemak kesihatan persekitaran IT anda.',

        // Section 6: Quick tips
        'tips_heading' => 'Petua Ringkas',
        'tip_communicate_title' => 'Berkomunikasi awal',
        'tip_communicate_desc'  => "Siarkan insiden sebaik sahaja anda tahu sesuatu tidak kena, walaupun anda belum mempunyai semua butiran lagi. Mengakui isu dengan pantas membina kepercayaan dengan pengguna anda.",
        'tip_update_title' => 'Kemas kini kerap',
        'tip_update_desc'  => 'Kemas kini status yang kerap &mdash; walaupun tiada apa yang berubah &mdash; menunjukkan kepada pengguna bahawa isu sedang ditangani secara aktif. Kesenyapan mencetuskan kekecewaan dan tiket sokongan.',
        'tip_review_title' => 'Semak corak',
        'tip_review_desc'  => 'Semak sejarah insiden anda secara berkala. Jika perkhidmatan yang sama terus muncul, ia mungkin menunjukkan isu infrastruktur yang lebih mendalam yang wajar ditangani secara proaktif.',
        'tip_maintenance_title' => 'Rancang penyelenggaraan',
        'tip_maintenance_desc'  => 'Gunakan tahap impak Penyelenggaraan untuk kerja yang dirancang. Mencipta insiden lebih awal membolehkan pengguna mengetahui tentang masa henti berjadual sebelum ia berlaku.',
    ],
];
