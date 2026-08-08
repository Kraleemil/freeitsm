<?php
/**
 * Bahasa Melayu (ms) — Rentetan Pengesahan Persediaan (pemasang larian pertama).
 *
 * Meliputi halaman tunggal setup/index.php: tajuk halaman, lencana ringkasan,
 * nama + butiran semakan individu, bahagian Pengesahan Pangkalan Data, blok
 * log masuk lalai, amaran nota kaki, dan rentetan JS yang digunakan oleh
 * runDbVerify().
 *
 * Bahagian dinamik (laluan, nama pemacu, nama sambungan, mesej ralat mentah)
 * dihantar melalui parameter {placeholder} dan bukan diterjemahkan.
 */
return [
    'title'   => 'Persediaan FreeITSM',
    'heading' => 'Pengesahan Persediaan',

    'summary' => [
        'passed'   => '{n} lulus',
        'warning'  => '{n} amaran',
        'warnings' => '{n} amaran',
        'failed'   => '{n} gagal',
    ],

    'checks' => [
        'config'         => 'config.php',
        'db_config'      => 'db_config.php',
        'db_connection'  => 'Sambungan pangkalan data',
        'encryption_key' => 'Kunci penyulitan',
        'ssl_verify'     => 'Pengesahan sijil HTTPS',
        'ca_bundle_ini'  => 'Bundel CA dalam php.ini',
        'display_errors' => 'Paparan ralat',
        'php_version'    => 'Versi PHP',
        'php_extension'  => 'Sambungan PHP: {ext}',
        'php_extension_optional' => 'Sambungan PHP: {ext} (pilihan)',
    ],

    'detail' => [
        'found'                    => 'Dijumpai',
        'config_not_found'         => 'Tidak dijumpai — salin config.php ke root aplikasi',
        'db_config_not_found'      => 'Tidak dijumpai di: {path}',
        'db_config_path_unset'     => 'Pemboleh ubah $db_config_path tidak ditetapkan dalam config.php',
        'db_connected'             => 'Bersambung (pemacu: {driver})',
        'db_constants_undefined'   => 'Pemalar pangkalan data tidak ditakrifkan — semak db_config.php',
        'encryption_key_missing'   => 'Tidak dijumpai di: {path} — diperlukan untuk menyulitkan tetapan sensitif',
        'encryption_key_undefined' => 'ENCRYPTION_KEY_PATH tidak ditakrifkan dalam includes/encryption.php',
        'ssl_enabled'              => 'Diaktifkan',
        'ssl_verified'             => 'Aktif dan berfungsi — permintaan HTTPS langsung telah disahkan sijilnya (bundel CA: {bundle})',
        'ssl_broken'               => 'Aktif, tetapi pelayan tidak dapat mengesahkan sijil — HTTPS keluar (e-mel, AI, webhook, log masuk) akan gagal. Penyelesaian paling mudah: letakkan fail cacert.pem dalam folder includes/ aplikasi (muat turun daripada https://curl.se/ca/cacert.pem) — tiada perubahan php.ini diperlukan. Ralat: {error}',
        'ssl_untested'             => 'Aktif, tetapi permintaan ujian langsung tidak dapat diselesaikan (tiada rangkaian keluar?), jadi pengesahan tidak dapat disahkan. Ralat: {error}',
        'ssl_bundle_system'        => 'stor sistem',
        'help_link'                => 'Cara membetulkannya — panduan sijil HTTPS →',
        'ca_ini_status'            => 'curl.cainfo: {curl} · openssl.cafile: {ossl}',
        'ca_ini_none'              => 'tidak ditetapkan',
        'ca_ini_missing'           => '{path} (fail tiada!)',
        'ca_ini_note_fix'          => ' — betulkan laluan atau komenkan tetapan tersebut dalam php.ini.',
        'ca_ini_note_fallback'     => ' — pilihan: FreeITSM kembali menggunakan senarai CA terbundelnya (Windows) atau stor amanah OS (Linux). Nota: ini mencerminkan PHP pelayan web; pekerja latar belakang menggunakan php.ini CLI yang berasingan.',
        'ssl_disabled'             => 'Dinyahaktifkan — aktifkan untuk produksi (tetapkan SSL_VERIFY_PEER kepada true dalam config.php)',
        'ssl_undefined'            => 'SSL_VERIFY_PEER tidak ditakrifkan dalam config.php',
        'display_errors_enabled'   => 'Diaktifkan — nyahaktifkan untuk produksi (tetapkan display_errors kepada 0 dalam config.php)',
        'display_errors_disabled'  => 'Dinyahaktifkan',
        'php_version_ok'           => '{version}',
        'php_version_too_low'      => '{version} — PHP 7.4 atau lebih tinggi diperlukan',
        'php_version_eol'          => '{version} — masih disokong, tetapi keluaran ini tidak lagi menerima kemas kini keselamatan sejak mencapai tamat hayat. PHP 8.3 atau 8.4 disyorkan.',
        'extension_loaded'         => 'Dimuatkan',
        'extension_not_loaded'     => 'Tidak dimuatkan — aktifkan dalam php.ini',
        'pdo_mysql_not_loaded'     => 'Tidak dimuatkan — aktifkan pdo_mysql dalam php.ini',
        'imap_not_loaded'          => 'Tidak dimuatkan — hanya diperlukan untuk peti mel IMAP/SMTP asas. PHP 8.4 tidak lagi menyertakan sambungan ini; pasangkannya melalui PECL jika anda menggunakannya.',
    ],

    'db_verify' => [
        'heading' => 'Pengesahan Pangkalan Data',
        'intro'   => 'Semak dan cipta secara automatik mana-mana jadual atau lajur yang hilang dalam pangkalan data.',
        'run'     => 'Jalankan',
    ],

    'login' => [
        'heading'  => 'Log Masuk Lalai',
        'intro'    => 'Akaun admin lalai dicipta apabila anda menjalankan Pengesahan Pangkalan Data.',
        'username' => 'Nama pengguna:',
        'password' => 'Kata laluan:',
    ],

    'footer' => [
        'warning'   => 'Setelah sistem anda berada dalam produksi, padamkan folder {folder} demi keselamatan.',
        'signature' => 'Pengesahan Persediaan FreeITSM',
    ],

    'js' => [
        'running'        => 'Sedang berjalan...',
        'run'            => 'Jalankan',
        'tables_checked' => '{n} jadual disemak:',
        'ok'             => '{n} OK',
        'created'        => '{n} dicipta',
        'updated'        => '{n} dikemas kini',
        'errors'         => '{n} ralat',
        'unknown_error'  => 'Ralat tidak diketahui',
        'verify_failed'  => 'Gagal menjalankan pengesahan pangkalan data: {error}',
    ],
];
