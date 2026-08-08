<?php
/**
 * Bahasa Melayu (ms) — rentetan modul System Wiki.
 *
 * System Wiki mengindeks kod sumber (fail, fungsi, jadual pangkalan data) dan
 * memaparkan pelayarnya. Perhatikan bahawa nama fail, nama fungsi, nama jadual
 * dan label operasi SQL (SELECT/INSERT/...) adalah DATA yang diambil daripada
 * imbasan, bukan antara muka UI — ia tidak diterjemahkan. Kunci-kunci di sini
 * merangkumi rangka halaman, tajuk, label lajur, butang, mesej status dan
 * literal yang dijana oleh JS.
 *
 * Sekiranya kunci tiada di sini, ia kembali kepada nilai dalam
 * lang/en/system-wiki.php (lihat includes/i18n.php).
 */
return [
    'title' => 'Wiki Sistem',

    'nav' => [
        'browse' => 'Layari',
        'search' => 'Cari',
        'tables' => 'Jadual',
        'scan'   => 'Imbas',
    ],

    'nav_title' => [
        'browse' => 'Layari Fail',
        'search' => 'Cari',
        'tables' => 'Jadual Pangkalan Data',
        'scan'   => 'Pengurusan Imbasan',
    ],

    'index' => [
        'page_title'         => 'Meja Perkhidmatan - Wiki Sistem',
        'loading_stats'      => 'Memuatkan statistik...',
        'folders'            => 'Folder',
        'loading'            => 'Memuatkan...',
        'all_files'          => 'Semua Fail',
        'root_files'         => 'Fail Root',
        'col_file'           => 'Fail',
        'col_type'           => 'Jenis',
        'col_lines'          => 'Baris',
        'col_functions'      => 'Fungsi',
        'col_description'    => 'Keterangan',
        'no_scan_data'       => 'Tiada data imbasan. Jalankan pengimbas dahulu.',
        'stat_files'         => 'fail',
        'stat_php'           => 'PHP',
        'stat_js'            => 'JS',
        'stat_functions'     => 'fungsi',
        'stat_tables'        => 'jadual',
        'stat_folders'       => 'folder',
        'last_scan'          => 'Imbasan terakhir: {date} {time} ({duration}s)',
        'search_placeholder' => 'Cari fail, fungsi...',
        'search_btn'         => 'Cari',
        'no_files_title'     => 'Tiada fail ditemui',
        'no_files_body'      => 'Jalankan pengimbas untuk mengisi wiki.',
        'files_count'        => '{n} fail',
    ],

    'search' => [
        'page_title'           => 'Meja Perkhidmatan - Carian Wiki',
        'search_placeholder'   => 'Cari fail, fungsi, jadual pangkalan data...',
        'search_btn'           => 'Cari',
        'tab_files'            => 'Fail',
        'tab_functions'       => 'Fungsi',
        'tab_tables'          => 'Jadual',
        'no_files'            => 'Tiada fail sepadan dengan carian anda.',
        'no_functions'       => 'Tiada fungsi sepadan dengan carian anda.',
        'no_tables'          => 'Tiada jadual pangkalan data sepadan dengan carian anda.',
        'lines'              => 'baris',
        'root'               => 'root',
        'in'                 => 'dalam',
        'line'               => 'baris',
        'refs_across_files'  => '{refs} rujukan merentasi {files} fail',
    ],

    'scan' => [
        'page_title'      => 'Meja Perkhidmatan - Pengurusan Imbasan',
        'heading'         => 'Pengurusan Imbasan',
        'subtitle'        => 'Jalankan pengimbas PowerShell untuk mengkatalogkan kod sumber',
        'run_now'         => 'Jalankan Imbasan Sekarang',
        'info_html'       => 'Mengimbas kod sumber dan membina semula pangkalan data wiki.<br>Atau jalankan secara manual:',
        'history'         => 'Sejarah Imbasan',
        'col_date'        => 'Tarikh',
        'col_status'      => 'Status',
        'col_duration'    => 'Tempoh',
        'col_files'       => 'Fail',
        'col_functions'   => 'Fungsi',
        'col_classes'     => 'Kelas',
        'col_scanned_by'  => 'Diimbas Oleh',
        'loading'         => 'Memuatkan...',
        'no_scans'        => 'Belum ada imbasan. Klik "Jalankan Imbasan Sekarang" atau jalankan skrip PowerShell.',
        'starting'        => 'Memulakan imbasan...',
        'triggered'       => 'Imbasan dicetuskan!',
        'error_prefix'    => 'Ralat: ',
    ],

    'file' => [
        'page_title'         => 'Meja Perkhidmatan - Butiran Fail',
        'loading'            => 'Memuatkan butiran fail...',
        'no_id'              => 'Tiada ID fail dinyatakan.',
        'error_prefix'       => 'Ralat: ',
        'wiki'               => 'Wiki',
        'lines'              => 'baris',
        'kb'                 => 'KB',
        'modified'           => 'Diubah suai:',
        'unknown'            => 'Tidak diketahui',
        'sec_functions'      => 'Fungsi',
        'sec_classes'        => 'Kelas',
        'sec_dependencies'   => 'Kebergantungan (fail ini menggunakan)',
        'sec_dependents'     => 'Pergantung (fail yang menggunakan ini)',
        'sec_db_tables'      => 'Jadual Pangkalan Data',
        'sec_session_vars'   => 'Pemboleh Ubah Sesi',
        'none_found'         => 'Tiada dijumpai',
        'col_name'           => 'Nama',
        'col_line'           => 'Baris',
        'col_parameters'     => 'Parameter',
        'col_scope'          => 'Skop',
        'col_description'    => 'Keterangan',
        'col_extends'        => 'Melanjutkan',
        'col_type'           => 'Jenis',
        'col_target'         => 'Sasaran',
        'col_source_file'    => 'Fail Sumber',
        'col_operation'      => 'Operasi',
        'col_table'          => 'Jadual',
        'col_access'         => 'Akses',
        'col_variable'       => 'Pemboleh Ubah',
        'extends'            => 'melanjutkan',
        'static'             => 'statik',
    ],

    'function' => [
        'page_title'    => 'Meja Perkhidmatan - Butiran Fungsi',
        'loading'       => 'Memuatkan butiran fungsi...',
        'no_id'         => 'Tiada ID fungsi dinyatakan.',
        'error_prefix'  => 'Ralat: ',
        'wiki'          => 'Wiki',
        'static'        => 'statik',
        'defined_in'    => 'Ditakrifkan dalam:',
        'line'          => 'Baris:',
        'parameters'    => 'Parameter:',
        'called_by'     => 'Dipanggil Oleh',
        'no_callers'    => 'Tiada pemanggil ditemui (atau hanya dipanggil dalam fail yang sama)',
        'col_file'      => 'Fail',
        'col_line'      => 'Baris',
    ],

    'table' => [
        'page_title'        => 'Meja Perkhidmatan - Rujukan Jadual',
        'loading'           => 'Memuatkan rujukan jadual...',
        'no_name'           => 'Tiada nama jadual dinyatakan.',
        'error_prefix'      => 'Ralat: ',
        'wiki'              => 'Wiki',
        'tables'            => 'Jadual',
        'refs_across_files' => '{refs} rujukan merentasi {files} fail',
        'references'        => 'rujukan',
        'no_references'     => 'Tiada rujukan ditemui untuk jadual ini.',
    ],

    'tables' => [
        'page_title'    => 'Meja Perkhidmatan - Jadual Pangkalan Data',
        'heading'       => 'Jadual Pangkalan Data',
        'loading'       => 'Memuatkan...',
        'col_name'      => 'Nama Jadual',
        'col_files'     => 'Fail',
        'col_total'     => 'Jumlah Rujukan',
        'col_select'    => 'SELECT',
        'col_insert'    => 'INSERT',
        'col_update'    => 'UPDATE',
        'col_delete'    => 'DELETE',
        'col_join'      => 'JOIN',
        'no_refs'       => 'Tiada rujukan jadual ditemui. Jalankan pengimbas dahulu.',
        'discovered'    => '{n} jadual pangkalan data ditemui merentasi kod sumber',
    ],
];
