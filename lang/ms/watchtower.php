<?php
/**
 * Bahasa Melayu (ms) — Rentetan modul Watchtower.
 *
 * Kunci yang tiada dalam fail ini kembali kepada lang/en/watchtower.php mengikut
 * kunci masing-masing (lihat includes/i18n.php).
 *
 * Watchtower ialah papan pemuka perhatian merentas modul. Meliputi pengepala,
 * rangka papan pemuka, label/metrik/baris perhatian kad setiap modul yang
 * dipaparkan oleh JS sebaris, dan panduan bantuan yang lengkap.
 *
 * TIDAK diliputi di sini (data ditarik secara langsung daripada modul lain):
 * subjek tiket, tajuk peristiwa, tajuk artikel, nama perkhidmatan, dsb.
 */
return [
    'title' => 'Watchtower',

    'nav' => [
        'dashboard' => 'Papan Pemuka',
        'help'      => 'Bantuan',
    ],

    'dashboard' => [
        'heading'      => 'Gambaran Keseluruhan Perhatian',
        'refresh'      => 'Muat Semula',
        'updated'      => 'Dikemas kini {time}',
    ],

    // Nama kad setiap modul yang dipaparkan pada pengepala kad (pautan ke setiap modul).
    'cards' => [
        'morning_checks' => 'Semakan Pagi',
        'tickets'        => 'Tiket',
        'changes'        => 'Perubahan',
        'calendar'       => 'Kalendar',
        'service_status' => 'Status Perkhidmatan',
        'contracts'      => 'Kontrak',
        'knowledge'      => 'Pengetahuan',
        'assets'         => 'Aset',
        'tasks'          => 'Tugasan',
        'workflows'      => 'Aliran Kerja',
    ],

    // Kad Aliran Kerja. Enjin ini sengaja menelan ralatnya sendiri (supaya
    // aliran kerja yang rosak tidak merosakkan simpanan tiket yang mencetuskannya)
    // — bermakna aliran kerja yang gagal berlaku secara senyap. Kad ini memecahkan
    // kesenyapan itu.
    'workflows' => [
        'all_clear'     => 'Tiada kegagalan aliran kerja',
        'failed'        => '<span class="wt-attention-bold">{count}</span> jalanan aliran kerja gagal dalam 24 jam lepas',
        'aborted'       => '<span class="wt-attention-bold">{count}</span> jalanan digugurkan oleh perlindungan gelung dalam 24 jam lepas',
        'dead_webhooks' => '<span class="wt-attention-bold">{count}</span> webhook berhenti mencuba semula — mesej tidak pernah sampai',
        'failures'      => '{count} kegagalan',
    ],

    // Kad Semakan Pagi.
    'mc' => [
        'metric_done' => 'Selesai',
        'metric_ok'   => 'OK',
        'metric_warn' => 'Amaran',
        'metric_fail' => 'Gagal',
        'not_started'      => 'Semakan belum dimulakan hari ini',
        'pending'          => '{count} semakan masih tertangguh',
        'failed'           => '{count} semakan gagal',
        'warnings'         => '{count} semakan dengan amaran',
        'all_passing'      => 'Semua semakan selesai dan lulus',
    ],

    // Kad Tiket.
    'tickets' => [
        'metric_open'   => 'Terbuka',
        'metric_new'    => 'Baharu',
        'metric_active' => 'Aktif',
        'metric_hold'   => 'Ditahan',
        'urgent_high'   => '<span class="wt-attention-bold">{count}</span> tiket keutamaan segera/tinggi',
        'unassigned'    => '<span class="wt-attention-bold">{count}</span> tiket belum ditugaskan',
        'paused_one'    => '<span class="wt-attention-bold">{count}</span> tiket dijeda lebih {hours}j (jam SLA berhenti)',
        'paused_many'   => '<span class="wt-attention-bold">{count}</span> tiket dijeda lebih {hours}j (jam SLA berhenti)',
        'all_clear'     => 'Tiada item segera',
    ],

    // Kad Perubahan.
    'changes' => [
        'metric_next_7d' => '7h Akan Datang',
        'metric_active'  => 'Aktif',
        'metric_pending' => 'Tertangguh',
        'awaiting'       => '<span class="wt-attention-bold">{count}</span> perubahan menunggu kelulusan',
        'in_progress'    => '{count} perubahan sedang berjalan sekarang',
        'scheduled'      => '{count} perubahan dijadualkan minggu ini',
        'all_clear'      => 'Tiada perubahan akan datang',
    ],

    // Kad Kalendar.
    'calendar' => [
        'metric_today' => 'Hari Ini',
        'metric_week'  => 'Minggu Ini',
        'all_day'      => 'Sepanjang hari',
        'no_events'    => 'Tiada peristiwa hari ini',
    ],

    // Kad Status Perkhidmatan.
    'service' => [
        'all_operational' => 'Semua sistem beroperasi',
        'active_incidents' => '<span class="wt-attention-bold">{count}</span> insiden aktif',
    ],

    // Kad Kontrak.
    'contracts' => [
        'metric_30d'     => '30 hari',
        'metric_90d'     => '90 hari',
        'metric_notices' => 'Notis',
        'expiring'       => '<span class="wt-attention-bold">{count}</span> kontrak tamat tempoh dalam masa 30 hari',
        'notices'        => '<span class="wt-attention-bold">{count}</span> tempoh notis semakin hampir',
        'all_clear'      => 'Tiada kontrak yang memerlukan perhatian',
    ],

    // Kad Pengetahuan.
    'knowledge' => [
        'overdue'         => '<span class="wt-attention-bold">{count}</span> artikel tertunggak untuk disemak',
        'published_week'  => 'Diterbitkan minggu ini',
        'up_to_date'      => 'Pangkalan pengetahuan terkini',
    ],

    // Kad Aset.
    'assets' => [
        'metric_total'    => 'Jumlah',
        'metric_offline'  => 'Luar Talian',
        'metric_warranty' => 'Waranti',
        'warranty'        => '<span class="wt-attention-bold">{count}</span> aset dengan waranti tamat tempoh atau tamat dalam masa {days} hari',
        'offline'         => '<span class="wt-attention-bold">{count}</span> aset tidak dikesan selama 7+ hari',
        'all_active'      => 'Semua aset aktif baru-baru ini',
    ],

    // Kad Tugasan.
    'tasks' => [
        'metric_todo'   => 'Perlu Buat',
        'metric_active' => 'Aktif',
        'overdue'       => '<span class="wt-attention-bold">{count}</span> tugasan tertunggak',
        'due_today'     => '<span class="wt-attention-bold">{count}</span> perlu siap hari ini',
        'all_clear'     => 'Tiada tugasan tertunggak',
    ],

    // Panduan bantuan.
    'help' => [
        'page_title'   => 'Panduan Watchtower',
        'sidebar_label' => 'Panduan',
        'hero_title'   => 'Panduan Watchtower',
        'hero_subtitle' => 'Papan pemuka perhatian bersepadu yang memaparkan item boleh tindak daripada setiap modul dalam satu pandangan.',

        'nav_overview'  => 'Gambaran Keseluruhan',
        'nav_layout'    => 'Susun atur papan pemuka',
        'nav_dots'      => 'Memahami titik status',
        'nav_cards'     => 'Kad modul dijelaskan',
        'nav_refresh'   => 'Muat semula automatik',
        'nav_tips'      => 'Tip pantas',

        // Seksyen 1 — Gambaran keseluruhan
        's1_title' => 'Gambaran Keseluruhan',
        's1_intro' => 'Watchtower ialah panel tunggal anda untuk operasi IT. Daripada membuka setiap modul secara berasingan untuk menyemak item segera, Watchtower menarik maklumat paling penting daripada setiap modul ke dalam satu papan pemuka. Dalam sekali pandang anda boleh melihat apa yang memerlukan perhatian, apa yang berjalan lancar, dan di mana untuk menumpukan masa anda.',
        's1_feat1_title' => 'Papan perhatian',
        's1_feat1_desc'  => 'Lihat apa yang memerlukan fokus anda merentas semua modul di satu tempat. Semakan pagi, tiket, perubahan, peristiwa kalendar, status perkhidmatan, kontrak, artikel pengetahuan dan aset semuanya diringkaskan pada satu skrin.',
        's1_feat2_title' => 'Status berkod warna',
        's1_feat2_desc'  => 'Setiap kad modul memaparkan titik status hijau, kuning atau merah untuk triaj segera. Anda boleh mengetahui dalam sekelip mata kawasan mana yang sihat, mana yang memerlukan perhatian, dan mana yang memerlukan tindakan segera.',
        's1_feat3_title' => 'Muat semula automatik',
        's1_feat3_desc'  => 'Papan pemuka dimuat semula secara automatik setiap 5 minit, jadi maklumat kekal terkini tanpa sebarang tindakan manual. Biarkan Watchtower terbuka dan ia terus mengemas kini dirinya di latar belakang.',
        's1_feat4_title' => 'Klik terus',
        's1_feat4_desc'  => 'Lompat terus ke mana-mana modul daripada kadnya. Setiap nama modul adalah pautan boleh klik yang membawa anda terus ke kawasan berkaitan, supaya anda boleh bertindak ke atas isu tanpa mencari halaman yang betul.',

        // Seksyen 2 — Susun atur papan pemuka
        's2_title' => 'Susun atur papan pemuka',
        's2_p1' => 'Papan pemuka Watchtower menggunakan grid responsif 3 lajur bagi kad modul. Pada skrin yang lebih kecil, grid menyesuaikan diri kepada 2 lajur atau satu lajur tunggal, jadi ia berfungsi pada mana-mana peranti. Di atas grid ialah bar tajuk dengan butang muat semula dan cap masa "Dikemas kini" yang menunjukkan bila data terakhir diambil.',
        's2_p2' => 'Setiap kad dalam grid mengikut struktur yang konsisten supaya anda boleh mengimbasnya dengan pantas:',
        's2_diagram_name'   => 'Nama Modul',
        's2_diagram_open'   => 'TERBUKA',
        's2_diagram_active' => 'AKTIF',
        's2_diagram_hold'   => 'DITAHAN',
        's2_diagram_clear'  => 'Semua jelas — tiada item segera',
        's2_field_icon'    => '<strong>Ikon berwarna</strong> &mdash; ikon segi empat kecil dalam warna tema modul (warna teal untuk Semakan Pagi, biru untuk Tiket, dll.) supaya anda dapat mengenal pasti setiap kad dengan segera.',
        's2_field_name'    => '<strong>Nama modul</strong> &mdash; pautan boleh klik yang menavigasi terus ke modul tersebut. Klik untuk lompat terus dan bertindak.',
        's2_field_dot'     => '<strong>Titik status</strong> &mdash; titik hijau, kuning atau merah di penjuru kanan atas yang menunjukkan tahap keperluan tindakan segera keseluruhan bagi modul tersebut.',
        's2_field_metrics' => '<strong>Metrik utama</strong> &mdash; nombor besar yang meringkaskan kiraan paling penting (cth. tiket terbuka, semakan selesai, kontrak yang tamat tempoh).',
        's2_field_attention' => '<strong>Item perhatian</strong> &mdash; baris mesej berkod warna yang menonjolkan apa secara khusus yang memerlukan perhatian anda dalam modul tersebut.',
        's2_tip' => 'Susun atur kad direka untuk mengimbas, bukan analisis mendalam. Gunakan Watchtower untuk mengenal pasti modul mana yang memerlukan perhatian anda, kemudian klik untuk masuk ke modul itu sendiri bagi maklumat penuh.',

        // Seksyen 3 — Titik status
        's3_title' => 'Memahami titik status',
        's3_intro' => 'Setiap kad modul memaparkan titik status pada pengepalanya. Titik ini memberikan penunjuk visual segera sama ada kawasan operasi IT anda itu memerlukan perhatian. Warna ditentukan secara automatik berdasarkan data yang dikembalikan daripada setiap modul.',
        's3_green_label' => 'Hijau',
        's3_green_desc'  => 'Semuanya baik. Tiada tindakan diperlukan. Modul berada dalam keadaan sihat tanpa isu tertunggak atau item yang memerlukan perhatian.',
        's3_green_examples' => '<strong>Contoh:</strong> Semua semakan pagi lulus, tiada tiket segera, semua sistem beroperasi, tiada kontrak tamat tempoh tidak lama lagi.',
        's3_amber_label' => 'Kuning',
        's3_amber_desc'  => 'Sesuatu memerlukan perhatian tetapi tidak kritikal. Terdapat item yang perlu anda semak apabila sempat, tetapi tiada apa-apa yang terbakar.',
        's3_amber_examples' => '<strong>Contoh:</strong> Semakan dengan amaran, tiket belum ditugaskan, perubahan menunggu kelulusan, kontrak tamat tempoh dalam masa 90 hari.',
        's3_red_label' => 'Merah',
        's3_red_desc'  => 'Item segera memerlukan tindakan serta-merta. Sesuatu telah gagal, tertunggak, atau terjejas secara kritikal dan perlu ditangani segera.',
        's3_red_examples' => '<strong>Contoh:</strong> Semakan pagi belum dimulakan atau gagal, tiket keutamaan segera/tinggi, gangguan perkhidmatan besar, kontrak tamat tempoh dalam masa 30 hari.',
        's3_tip' => 'Anggap titik ini seperti lampu isyarat. Hijau bermaksud teruskan hari anda, kuning bermaksud semak apabila boleh, dan merah bermaksud berhenti daripada apa yang anda lakukan dan siasat. Matlamatnya ialah memastikan semua titik kekal hijau.',

        // Seksyen 4 — Kad modul dijelaskan
        's4_title' => 'Kad modul dijelaskan',
        's4_intro' => 'Watchtower memantau lapan modul. Setiap kad disesuaikan untuk memaparkan maklumat paling relevan bagi kawasan tersebut. Berikut ialah apa yang dipaparkan oleh setiap kad dan apa yang mencetuskan warna titik statusnya.',
        's4_mc_title'    => 'Semakan Pagi',
        's4_mc_desc'     => 'Menunjukkan kemajuan penyempurnaan (cth. 8/10 selesai) berserta kiraan keputusan OK, Amaran dan Gagal. Item perhatian menandakan apabila semakan belum dimulakan atau apabila mana-mana gagal.',
        's4_mc_triggers' => '<strong>Merah:</strong> Semakan belum dimulakan hari ini, atau mana-mana semakan gagal. <strong>Kuning:</strong> Semakan belum lengkap atau terdapat amaran. <strong>Hijau:</strong> Semua semakan selesai dan lulus.',
        's4_tk_title'    => 'Tiket',
        's4_tk_desc'     => 'Memaparkan jumlah kiraan terbuka yang dipecahkan kepada Baharu, Aktif dan Ditahan. Item perhatian menonjolkan tiket keutamaan segera/tinggi dan mana-mana yang belum ditugaskan.',
        's4_tk_triggers' => '<strong>Merah:</strong> Terdapat tiket keutamaan segera atau tinggi. <strong>Kuning:</strong> Terdapat tiket belum ditugaskan. <strong>Hijau:</strong> Tiada item segera atau tiket belum ditugaskan.',
        's4_ch_title'    => 'Perubahan',
        's4_ch_desc'     => 'Menunjukkan bilangan perubahan yang dijadualkan dalam 7 hari akan datang, berapa banyak yang sedang berjalan, dan berapa banyak yang menunggu kelulusan. Item perhatian menonjolkan perubahan yang belum diluluskan dan aktif.',
        's4_ch_triggers' => '<strong>Kuning:</strong> Perubahan menunggu kelulusan. <strong>Hijau:</strong> Tiada perubahan yang belum diluluskan.',
        's4_cal_title'    => 'Kalendar',
        's4_cal_desc'     => 'Memaparkan bilangan peristiwa hari ini dan minggu ini. Jika terdapat peristiwa hari ini, ia disenaraikan berserta masanya (atau "Sepanjang hari" untuk peristiwa sepanjang hari).',
        's4_cal_triggers' => '<strong>Kuning:</strong> Peristiwa dijadualkan untuk hari ini. <strong>Hijau:</strong> Tiada peristiwa hari ini.',
        's4_ss_title'    => 'Status Perkhidmatan',
        's4_ss_desc'     => 'Menunjukkan kiraan insiden aktif dan menyenaraikan perkhidmatan yang terjejas berserta lencana tahap kesannya (Gangguan Besar, Gangguan Separa, Merosot, Penyelenggaraan). Apabila semuanya sihat, sepanduk hijau "Semua sistem beroperasi" dipaparkan.',
        's4_ss_triggers' => '<strong>Merah:</strong> Gangguan besar atau separa pada mana-mana perkhidmatan. <strong>Kuning:</strong> Status merosot atau penyelenggaraan. <strong>Hijau:</strong> Semua sistem beroperasi.',
        's4_ct_title'    => 'Kontrak',
        's4_ct_desc'     => 'Memaparkan kontrak yang tamat tempoh dalam masa 30 hari, dalam masa 90 hari, dan tempoh notis yang semakin hampir. Item perhatian memberi amaran tentang tamat tempoh yang hampir dan tarikh akhir notis yang akan datang.',
        's4_ct_triggers' => '<strong>Merah:</strong> Kontrak tamat tempoh dalam masa 30 hari. <strong>Kuning:</strong> Kontrak tamat tempoh dalam masa 90 hari atau tempoh notis semakin hampir. <strong>Hijau:</strong> Tiada kontrak yang memerlukan perhatian.',
        's4_kb_title'    => 'Pengetahuan',
        's4_kb_desc'     => 'Menunjukkan bilangan artikel yang tertunggak untuk disemak dan menyenaraikan artikel yang baru diterbitkan minggu ini. Apabila tiada semakan tertunggak dan pangkalan pengetahuan terkini, kad menunjukkan mesej semua jelas.',
        's4_kb_triggers' => '<strong>Kuning:</strong> Artikel tertunggak untuk disemak. <strong>Hijau:</strong> Pangkalan pengetahuan terkini.',
        's4_as_title'    => 'Aset',
        's4_as_desc'     => 'Memaparkan jumlah bilangan aset yang dijejaki dan berapa banyak yang tidak dikesan selama 7 hari atau lebih. Ini membantu mengenal pasti peranti yang mungkin luar talian, dinyahtauliah, atau hilang.',
        's4_as_triggers' => '<strong>Kuning:</strong> Aset tidak dikesan selama 7+ hari. <strong>Hijau:</strong> Semua aset aktif baru-baru ini.',

        // Seksyen 5 — Muat semula automatik
        's5_title' => 'Muat semula automatik dan manual',
        's5_intro' => 'Watchtower direka sebagai alat pemantauan pasif yang boleh anda biarkan terbuka dalam tab pelayar sepanjang hari. Papan pemuka mengemas kini dirinya melalui kitaran muat semula automatik.',
        's5_step1' => '<strong>Muat semula automatik</strong> &mdash; papan pemuka mengambil data terkini daripada semua modul setiap 5 minit. Anda tidak perlu memuat semula halaman atau mengklik apa-apa; kad dan titik status dikemas kini secara senyap di latar belakang.',
        's5_step2' => '<strong>Muat semula manual</strong> &mdash; klik butang <strong>Muat Semula</strong> di penjuru kanan atas untuk mengambil data terkini dengan segera. Ikon butang berputar semasa permintaan sedang diproses, mengesahkan bahawa data baharu sedang dimuatkan.',
        's5_step3' => '<strong>Cap masa dikemas kini</strong> &mdash; di sebelah butang muat semula, cap masa menunjukkan masa terakhir data diambil (cth. "Dikemas kini 09:15"). Ini memberitahu anda dengan tepat betapa terkininya maklumat yang dipaparkan.',
        's5_tip' => 'Biarkan Watchtower terbuka dalam tab pelayar khusus untuk pemantauan pasif. Kitaran muat semula 5 minit bermaksud anda sentiasa mendapat paparan hampir masa nyata operasi IT anda tanpa perlu menyemak setiap modul secara manual.',

        // Seksyen 6 — Tip pantas
        's6_title' => 'Tip pantas',
        's6_tip1_title' => 'Mulakan hari anda di sini',
        's6_tip1_desc'  => 'Buka Watchtower sebaik sahaja bangun setiap pagi untuk gambaran operasi yang pantas. Dalam beberapa saat anda boleh melihat sama ada semakan pagi telah selesai, sama ada mana-mana tiket segera, dan sama ada semua perkhidmatan sihat.',
        's6_tip2_title' => 'Titik merah dahulu',
        's6_tip2_desc'  => 'Tangani titik status merah sebelum apa-apa lagi. Ini menunjukkan item segera yang memerlukan perhatian serta-merta &mdash; semakan yang gagal, tiket keutamaan tinggi, atau gangguan perkhidmatan yang sedang menjejaskan pengguna secara aktif.',
        's6_tip3_title' => 'Klik untuk masuk terus',
        's6_tip3_desc'  => 'Klik mana-mana nama modul pada kad untuk menavigasi terus ke modul tersebut. Tidak perlu menggunakan menu utama atau navigasi waffle &mdash; Watchtower bertindak sebagai jalan pintas terus ke mana-mana perhatian diperlukan.',
        's6_tip4_title' => 'Tekan Muat Semula untuk yang terkini',
        's6_tip4_desc'  => 'Walaupun papan pemuka dimuat semula secara automatik setiap 5 minit, anda boleh klik butang Muat Semula pada bila-bila masa anda mahukan data yang paling terkini. Berguna selepas menyelesaikan isu untuk mengesahkan titik status telah berubah.',
        's6_tip5_title' => 'Gunakan dalam mesyuarat pasukan',
        's6_tip5_desc'  => 'Paparkan Watchtower pada skrin semasa mesyuarat harian atau mesyuarat semakan operasi. Titik berkod warna memudahkan perbincangan mengenai kawasan mana yang memerlukan perhatian dan menetapkan pemilikan item kuning atau merah.',
        's6_tip6_title' => 'Hijau bermaksud semua jelas',
        's6_tip6_desc'  => 'Apabila setiap titik pada papan pemuka berwarna hijau, operasi IT anda berada dalam keadaan baik. Tiada tiket segera, tiada semakan gagal, tiada kontrak tamat tempoh, dan semua perkhidmatan beroperasi. Itulah matlamatnya.',
    ],
];
