<?php
/**
 * German (de) — Setup-Prüfung (Installationsassistent beim ersten Start).
 *
 * Deckt die einzelne Seite setup/index.php ab: Seitentitel, Zusammenfassungs-
 * Badges, die einzelnen Prüfungen und ihre Details, den Abschnitt zur
 * Datenbankprüfung, die Standard-Anmeldedaten, den Fußzeilenhinweis und die
 * JS-Texte aus runDbVerify().
 *
 * Dynamische Werte (Pfade, Treibernamen, Erweiterungsnamen, rohe Fehlermeldungen)
 * werden über {placeholder}-Parameter eingesetzt und nicht übersetzt.
 *
 * Konventionen (aus den bestehenden de-Dateien übernommen, nicht neu erfunden):
 * Sie-Form, "Ticket"/"Asset"/"Analyst" bleiben englisch, "KI" statt "AI".
 * Produkt- und Dateinamen (config.php, php.ini, curl.cainfo) bleiben unverändert,
 * weil der Anwender genau danach im Dateisystem sucht.
 */
return [
    'title'   => 'FreeITSM-Einrichtung',
    'heading' => 'Setup-Prüfung',

    'summary' => [
        'passed'   => '{n} bestanden',
        'warning'  => '{n} Warnung',
        'warnings' => '{n} Warnungen',
        'failed'   => '{n} fehlgeschlagen',
    ],

    'checks' => [
        'config'         => 'config.php',
        'db_config'      => 'db_config.php',
        'db_connection'  => 'Datenbankverbindung',
        'encryption_key' => 'Verschlüsselungsschlüssel',
        'ssl_verify'     => 'Überprüfung des HTTPS-Zertifikats',
        'ca_bundle_ini'  => 'CA-Bundle in der php.ini',
        'display_errors' => 'Fehleranzeige',
        'php_version'    => 'PHP-Version',
        'php_extension'  => 'PHP-Erweiterung: {ext}',
        'php_extension_optional' => 'PHP-Erweiterung: {ext} (optional)',
    ],

    'detail' => [
        'found'                    => 'Gefunden',
        'config_not_found'         => 'Nicht gefunden — kopieren Sie config.php in das Stammverzeichnis der Anwendung',
        'db_config_not_found'      => 'Nicht gefunden unter: {path}',
        'db_config_path_unset'     => 'Variable $db_config_path ist in config.php nicht gesetzt',
        'db_connected'             => 'Verbunden (Treiber: {driver})',
        'db_constants_undefined'   => 'Datenbankkonstanten nicht definiert — prüfen Sie db_config.php',
        'encryption_key_missing'   => 'Nicht gefunden unter: {path} — wird zum Verschlüsseln sensibler Einstellungen benötigt',
        'encryption_key_undefined' => 'ENCRYPTION_KEY_PATH ist in includes/encryption.php nicht definiert',
        'ssl_enabled'              => 'Aktiviert',
        'ssl_verified'             => 'Aktiv und funktionsfähig — bei einer echten HTTPS-Anfrage wurde das Zertifikat überprüft (CA-Bundle: {bundle})',
        'ssl_broken'               => 'Aktiv, aber der Server konnte kein Zertifikat überprüfen — ausgehende HTTPS-Verbindungen (E-Mail, KI, Webhooks, Anmeldung) werden fehlschlagen. Einfachste Lösung: Legen Sie eine Datei cacert.pem in den Ordner includes/ der Anwendung (Download unter https://curl.se/ca/cacert.pem) — Änderungen an der php.ini sind nicht nötig. Fehler: {error}',
        'ssl_untested'             => 'Aktiv, aber eine Testanfrage konnte nicht durchgeführt werden (keine ausgehende Netzwerkverbindung?), daher ließ sich die Überprüfung nicht bestätigen. Fehler: {error}',
        'ssl_bundle_system'        => 'Systemspeicher',
        'help_link'                => 'So beheben Sie das — Anleitung zu HTTPS-Zertifikaten →',
        'ca_ini_status'            => 'curl.cainfo: {curl} · openssl.cafile: {ossl}',
        'ca_ini_none'              => 'nicht gesetzt',
        'ca_ini_missing'           => '{path} (Datei fehlt!)',
        'ca_ini_note_fix'          => ' — korrigieren Sie den Pfad oder kommentieren Sie die Einstellung in der php.ini aus.',
        'ca_ini_note_fallback'     => ' — optional: FreeITSM greift ersatzweise auf seine mitgelieferte CA-Liste (Windows) oder den Zertifikatspeicher des Betriebssystems (Linux) zurück. Hinweis: Dies bezieht sich auf das PHP des Webservers; der Hintergrunddienst verwendet eine eigene CLI-php.ini.',
        'ssl_disabled'             => 'Deaktiviert — für den Produktivbetrieb aktivieren (SSL_VERIFY_PEER in config.php auf true setzen)',
        'ssl_undefined'            => 'SSL_VERIFY_PEER ist in config.php nicht definiert',
        'display_errors_enabled'   => 'Aktiviert — für den Produktivbetrieb deaktivieren (display_errors in config.php auf 0 setzen)',
        'display_errors_disabled'  => 'Deaktiviert',
        'php_version_ok'           => '{version}',
        'php_version_too_low'      => '{version} — PHP 7.4 oder höher ist erforderlich',
        'php_version_eol'          => '{version} — wird noch unterstützt, diese Version erhält jedoch seit dem Ende ihrer Lebensdauer keine Sicherheitsupdates mehr. Empfohlen wird PHP 8.3 oder 8.4.',
        'extension_loaded'         => 'Geladen',
        'extension_not_loaded'     => 'Nicht geladen — in der php.ini aktivieren',
        'pdo_mysql_not_loaded'     => 'Nicht geladen — aktivieren Sie pdo_mysql in der php.ini',
        'imap_not_loaded'          => 'Nicht geladen — wird nur für einfache IMAP-/SMTP-Postfächer benötigt. PHP 8.4 liefert diese Erweiterung nicht mehr mit; installieren Sie sie bei Bedarf über PECL.',
    ],

    'db_verify' => [
        'heading' => 'Datenbankprüfung',
        'intro'   => 'Prüft die Datenbank auf fehlende Tabellen oder Spalten und legt sie automatisch an.',
        'run'     => 'Ausführen',
    ],

    'login' => [
        'heading'  => 'Standard-Anmeldedaten',
        'intro'    => 'Bei der Datenbankprüfung wird ein Standard-Administratorkonto angelegt.',
        'username' => 'Benutzername:',
        'password' => 'Passwort:',
    ],

    'footer' => [
        'warning'   => 'Sobald Ihr System produktiv läuft, löschen Sie aus Sicherheitsgründen den Ordner {folder}.',
        'signature' => 'FreeITSM-Setup-Prüfung',
    ],

    'js' => [
        'running'        => 'Wird ausgeführt...',
        'run'            => 'Ausführen',
        'tables_checked' => '{n} Tabellen geprüft:',
        'ok'             => '{n} in Ordnung',
        'created'        => '{n} angelegt',
        'updated'        => '{n} aktualisiert',
        'errors'         => '{n} Fehler',
        'unknown_error'  => 'Unbekannter Fehler',
        'verify_failed'  => 'Datenbankprüfung konnte nicht ausgeführt werden: {error}',
    ],
];
