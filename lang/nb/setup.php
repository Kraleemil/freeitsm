<?php
/**
 * Norsk bokmål (nb) — tekster for oppsettskontrollen (installasjonsprogrammet ved første kjøring).
 *
 * Dekker den ene siden setup/index.php: sidetittelen, oppsummeringsmerkene,
 * navn og detaljer for de enkelte kontrollene, seksjonen for databasekontroll,
 * blokken med standard innlogging, advarselen i bunnteksten og JS-tekstene
 * som brukes av runDbVerify().
 *
 * Dynamiske deler (stier, drivernavn, utvidelsesnavn, rå feilmeldinger) sendes
 * inn via {placeholder}-parametere i stedet for å oversettes.
 */
return [
    'title'   => 'FreeITSM-oppsett',
    'heading' => 'Oppsettskontroll',

    'summary' => [
        'passed'   => '{n} bestått',
        'warning'  => '{n} advarsel',
        'warnings' => '{n} advarsler',
        'failed'   => '{n} feilet',
    ],

    'checks' => [
        'config'         => 'config.php',
        'db_config'      => 'db_config.php',
        'db_connection'  => 'Databasetilkobling',
        'encryption_key' => 'Krypteringsnøkkel',
        'ssl_verify'     => 'Verifisering av HTTPS-sertifikat',
        'ca_bundle_ini'  => 'CA-pakke i php.ini',
        'display_errors' => 'Vis feil',
        'php_version'    => 'PHP-versjon',
        'php_extension'  => 'PHP-utvidelse: {ext}',
        'php_extension_optional' => 'PHP-utvidelse: {ext} (valgfri)',
    ],

    'detail' => [
        'found'                    => 'Funnet',
        'config_not_found'         => 'Ikke funnet — kopier config.php til programmets rotmappe',
        'db_config_not_found'      => 'Ikke funnet på: {path}',
        'db_config_path_unset'     => 'Variabelen $db_config_path er ikke satt i config.php',
        'db_connected'             => 'Tilkoblet (driver: {driver})',
        'db_constants_undefined'   => 'Databasekonstantene er ikke definert — sjekk db_config.php',
        'encryption_key_missing'   => 'Ikke funnet på: {path} — kreves for å kryptere sensitive innstillinger',
        'encryption_key_undefined' => 'ENCRYPTION_KEY_PATH er ikke definert i includes/encryption.php',
        'ssl_enabled'              => 'Aktivert',
        'ssl_verified'             => 'På og fungerer — sertifikatet ble verifisert i en reell HTTPS-forespørsel (CA-pakke: {bundle})',
        'ssl_broken'               => 'På, men serveren klarte ikke å verifisere et sertifikat — utgående HTTPS (e-post, KI, webhooks, innlogging) vil feile. Enkleste løsning: legg en cacert.pem-fil i programmets includes/-mappe (last ned fra https://curl.se/ca/cacert.pem) — ingen endringer i php.ini er nødvendig. Feil: {error}',
        'ssl_untested'             => 'På, men en reell testforespørsel kunne ikke fullføres (ingen utgående nettverkstilgang?), så verifiseringen kunne ikke bekreftes. Feil: {error}',
        'ssl_bundle_system'        => 'systemlageret',
        'help_link'                => 'Slik retter du dette — veiledning for HTTPS-sertifikater →',
        'ca_ini_status'            => 'curl.cainfo: {curl} · openssl.cafile: {ossl}',
        'ca_ini_none'              => 'ikke satt',
        'ca_ini_missing'           => '{path} (filen mangler!)',
        'ca_ini_note_fix'          => ' — rett stien eller kommenter ut innstillingen i php.ini.',
        'ca_ini_note_fallback'     => ' — valgfritt: FreeITSM faller tilbake på sin egen medfølgende CA-liste (Windows) eller operativsystemets tillitslager (Linux). Merk: dette gjelder PHP i webserveren; bakgrunnsprosessen bruker en egen php.ini for CLI.',
        'ssl_disabled'             => 'Deaktivert — bør slås på i produksjon (sett SSL_VERIFY_PEER til true i config.php)',
        'ssl_undefined'            => 'SSL_VERIFY_PEER er ikke definert i config.php',
        'display_errors_enabled'   => 'Aktivert — bør slås av i produksjon (sett display_errors til 0 i config.php)',
        'display_errors_disabled'  => 'Deaktivert',
        'php_version_ok'           => '{version}',
        'php_version_too_low'      => '{version} — PHP 7.4 eller nyere kreves',
        'php_version_eol'          => '{version} — fortsatt støttet, men denne versjonen har ikke fått sikkerhetsoppdateringer siden den nådde slutten på levetiden. PHP 8.3 eller 8.4 anbefales.',
        'extension_loaded'         => 'Lastet',
        'extension_not_loaded'     => 'Ikke lastet — aktiver den i php.ini',
        'pdo_mysql_not_loaded'     => 'Ikke lastet — aktiver pdo_mysql i php.ini',
        'imap_not_loaded'          => 'Ikke lastet — kreves bare for enkle IMAP/SMTP-postkasser. PHP 8.4 leverer ikke lenger denne utvidelsen; installer den via PECL hvis du bruker en slik postkasse.',

        // Tvillinger uten sti, som vises i stedet for detaljene over når siden verken
        // vises for en ny installasjon eller en innlogget administrator. Samme
        // resultat (bestått/advarsel/feil), men uten filstruktur eller kontonavn.
        'db_config_not_found_masked'     => 'Ikke funnet på stien som er satt i config.php',
        'ssl_verified_masked'            => 'På og fungerer — sertifikatet ble verifisert i en reell HTTPS-forespørsel',
        'ssl_broken_masked'              => 'På, men serveren klarte ikke å verifisere et sertifikat — utgående HTTPS (e-post, KI, webhooks, innlogging) vil feile. Logg inn som administrator for å se feilen.',
        'ssl_untested_masked'            => 'På, men en reell testforespørsel kunne ikke fullføres, så verifiseringen kunne ikke bekreftes.',
        'db_error_masked'                => 'Kunne ikke koble til — logg inn som administrator for å se hele feilmeldingen',
        'encryption_key_missing_masked'  => 'Ikke funnet — kreves for å kryptere sensitive innstillinger',
        'ca_ini_masked_ok'               => 'Konfigurert',
        'ca_ini_masked_broken'           => 'Satt, men peker på en fil som ikke finnes — rett stien eller kommenter ut innstillingen i php.ini.',
    ],

    'locked' => [
        'notice' => 'Oppsettet er fullført på denne installasjonen, så stier, tilkoblingsfeil og påloggingsdetaljer er skjult. Logg inn som administrator for å se alle detaljer.',
    ],

    'db_verify' => [
        'heading' => 'Databasekontroll',
        'intro'   => 'Sjekk databasen og opprett automatisk tabeller eller kolonner som mangler.',
        'run'     => 'Kjør',
    ],

    'login' => [
        'heading'  => 'Standard innlogging',
        'intro'    => 'En standard administratorkonto opprettes når du kjører databasekontrollen.',
        'username' => 'Brukernavn:',
        'password' => 'Passord:',
    ],

    'footer' => [
        'warning'   => 'Når systemet er satt i produksjon, bør du slette mappen {folder} av sikkerhetshensyn.',
        'signature' => 'FreeITSM oppsettskontroll',
    ],

    'js' => [
        'running'        => 'Kjører ...',
        'run'            => 'Kjør',
        'tables_checked' => '{n} tabeller sjekket:',
        'ok'             => '{n} OK',
        'created'        => '{n} opprettet',
        'updated'        => '{n} oppdatert',
        'errors'         => '{n} feil',
        'unknown_error'  => 'Ukjent feil',
        'verify_failed'  => 'Kunne ikke kjøre databasekontrollen: {error}',
    ],
];
