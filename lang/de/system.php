<?php
/**
 * German (de) — Texte des Bereichs „System“.
 *
 * ⚠️ TEILÜBERSETZUNG. Enthalten sind bisher: nav, landing, branding, colours,
 * db_verify, debug, demo, encryption, modules und preferences. Die Abschnitte
 * integrations, security, sso, companies und routing_test fehlen noch und
 * fallen auf Englisch zurück — das ist bewusst so und kein Fehler: ein
 * fehlender Schlüssel greift auf den englischen Wert zurück, statt einen rohen
 * Token anzuzeigen. Fortschritt prüfen mit:
 *     php scripts/i18n_audit.php de
 *
 * Konventionen (aus den bestehenden de-Dateien übernommen): Sie-Form;
 * „Ticket“, „Asset“, „Analyst“, „Workflow“, „Dashboard“ und „Service Desk“
 * bleiben englisch; „KI“ statt „AI“.
 *
 * ⚠️ Die *_keywords-Werte sind SUCHBEGRIFFE, keine Beschriftungen — sie werden
 * nie angezeigt, sondern nur durchsucht. Sie enthalten deshalb absichtlich
 * BEIDE Sprachen: Ein deutscher Administrator tippt genauso oft „SSO“ oder
 * „Backup“ wie „Anmeldung“ oder „Sicherung“, und nur die Suche nach dem
 * jeweils anderen Wort dürfte nicht ins Leere laufen.
 */
return [
    'nav' => [
        'encryption'  => 'Verschlüsselung',
        'modules'     => 'Module',
        'db_verify'   => 'DB-Prüfung',
        'colours'     => 'Farben',
        'branding'    => 'Branding',
        'security'    => 'Sicherheit',
        'preferences' => 'Einstellungen',
        'demo_data'   => 'Demodaten',
        'debug_tools' => 'Debug-Werkzeuge',
    ],

    'landing' => [
        'heading'  => 'Systemverwaltung',
        'subtitle' => 'Systemweite Einstellungen und Zugriffsrechte konfigurieren',

        'search_placeholder' => 'Systembereiche durchsuchen…',
        'no_results'         => 'Keine Systembereiche passen zu Ihrer Suche.',

        'help_title' => 'Hilfe & Anleitungen',
        'help_desc'  => 'Schritt-für-Schritt-Anleitungen für jeden Systembereich, einschließlich der Einrichtung von Single Sign-on.',

        'topology_title'    => 'Topologie',
        'topology_desc'     => 'Sehen Sie, wie Unternehmen, Postfächer, Domänen, Anmeldung und Analysten zusammenhängen',
        'topology_keywords' => 'topologie topology karte übersicht baum beziehungen unternehmen firmen postfächer mailboxes domänen domains analysten struktur diagramm graph',

        'orphaned_title'    => 'Verwaiste Tickets',
        'orphaned_desc'     => 'Tickets finden, die in einer gelöschten Abteilung hängen, und sie neu zuweisen',
        'orphaned_keywords' => 'verwaist orphaned tickets abteilung department fehlend gelöscht versteckt neu zuweisen reparieren hängen verloren defekt',

        'encryption_title'  => 'Verschlüsselung',
        'encryption_desc'   => 'Den Schlüssel erzeugen und verwalten, mit dem sensible Daten wie API-Schlüssel und Zugangsdaten geschützt werden.',
        'encryption_keywords' => 'verschlüsselung encryption schlüssel key hauptschlüssel krypto geheimnisse secrets zugangsdaten credentials api chiffre',
        'analysts_title'    => 'Analysten',
        'analysts_desc'     => 'Analystenkonten verwalten — Benutzer anlegen, bearbeiten und deaktivieren, Passwörter zurücksetzen, Single Sign-on und Teams zuweisen sowie den Zugriff je Unternehmen festlegen.',
        'analysts_keywords' => 'analysten analysts benutzer users konten accounts personal mitarbeiter agenten anmeldung login passwörter zurücksetzen sso team mitgliedschaft zugriff benutzerverwaltung',
        'teams_title'       => 'Teams',
        'teams_desc'        => 'Die Teams verwalten, denen Analysten angehören. Teams werden in Tickets, Aufgaben, Verträgen und Workflows für Zuweisung und Zugriff verwendet.',
        'teams_keywords'    => 'teams gruppen groups zuweisung assignment routing zugriff mitglieder analysten abteilungen departments',
        'roles_title'       => 'Rollen',
        'roles_desc'        => 'Nicht-Administratoren das Recht geben, die Einstellungen bestimmter Module zu verwalten — ohne sie zu vollwertigen Systemadministratoren zu machen.',
        'roles_keywords'    => 'rollen roles berechtigungen permissions rbac zugriffssteuerung access control einstellungen verwalten rechte delegieren lms manager',
        'modules_title'     => 'Modulzugriff',
        'modules_desc'      => 'Steuern, auf welche Module jeder Analyst zugreifen kann. Schränkt die Sichtbarkeit auf der Startseite und im Navigationsmenü ein.',
        'modules_keywords'  => 'modulzugriff module access berechtigungen analyst rechte sichtbarkeit rollen aktivieren deaktivieren',
        'db_verify_title'   => 'Datenbankprüfung',
        'db_verify_desc'    => 'Prüfen, ob alle Tabellen und Spalten in der Datenbank vorhanden sind. Fehlende werden automatisch angelegt.',
        'db_verify_keywords' => 'datenbank database prüfung verify schema tabellen spalten migration reparieren sql db',
        'colours_title'     => 'Farben',
        'colours_desc'      => 'Das Farbschema jedes Moduls anpassen. Die Änderungen gelten für Kopfzeilen, Symbole und die Startseite.',
        'colours_keywords'  => 'farben colours colors thema theme palette darstellung anpassen branding',
        'branding_title'    => 'Branding',
        'branding_desc'     => 'Das Logo der Organisation hochladen und Standardtexte für Kopf- und Fußzeilen von Diagrammen und exportierten Dokumenten festlegen.',
        'branding_keywords' => 'branding logo kopfzeile header fußzeile footer organisation unternehmen export dokumente',
        'security_title'    => 'Sicherheit',
        'security_desc'     => 'Richtlinien für vertrauenswürdige Geräte, Passwortablauf und Kontosperrung konfigurieren.',
        'security_keywords' => 'sicherheit security passwort ablauf sperrung lockout vertrauenswürdiges gerät mfa 2fa anmeldung richtlinie brute force',
        'sso_title'         => 'Authentifizierung',
        'sso_desc'          => 'Festlegen, wie sich Personen anmelden: per Single Sign-on über einen Identitätsanbieter (OpenID Connect) oder gegen Ihr LDAP bzw. Active Directory.',
        'sso_keywords'      => 'authentifizierung authentication anmeldung sso single sign-on single sign on oidc openid connect saml identitätsanbieter identity provider idp keycloak entra azure ad okta google oauth föderation login ldap active directory domäne verzeichnis bind samba openldap',
        'api_title'         => 'API',
        'api_desc'          => 'API-Schlüssel mit fein abgestuften Berechtigungen erstellen und die REST-API mit einer interaktiven Dokumentation erkunden.',
        'api_keywords'      => 'api rest schlüssel keys tokens integration webhook endpunkte dokumentation swagger entwickler extern',
        'webhooks_title'    => 'Webhook-Warteschlange',
        'webhooks_desc'     => 'Die Zustellung ausgehender Webhooks überwachen: prüfen, ob der Dienst läuft, Nutzdaten und Antwort jeder Sendung einsehen und beliebige davon erneut senden.',
        'webhooks_keywords' => 'webhooks webhook warteschlange queue ausgehend zustellung dienst worker cron slack teams discord nutzdaten payload erneut senden wiederholungen hmac signatur integration workflow',
        'preferences_title' => 'Einstellungen',
        'preferences_desc'  => 'Persönliche Einstellungen wie die Position von Benachrichtigungen. Sie werden in Ihrem Konto gespeichert und gelten nur für Sie.',
        'preferences_keywords' => 'einstellungen preferences persönlich benachrichtigungen toast position',
        'demo_data_title'   => 'Demodaten',
        'demo_data_desc'    => 'Realistische Beispieldaten für alle Module importieren. Ideal zum Ausprobieren und Testen auf einer frischen Installation.',
        'demo_data_keywords' => 'demo demodaten beispieldaten sample seed test evaluierung import beispiel',
        'debug_tools_title' => 'Debug-Werkzeuge',
        'debug_tools_desc'  => 'Sammlung von Diagnosen zur Fehlersuche bei fehlgeschlagenen Abläufen. Auf Anfrage ausführen und die Ausgabe an den Support senden.',
        'debug_tools_keywords' => 'debug werkzeuge tools diagnose diagnostics fehlersuche troubleshoot protokolle logs fehler errors support beheben',
        'companies_title'   => 'Unternehmen',
        'companies_desc'    => 'Die Kundenunternehmen verwalten, die diese Installation bedient.',
        'companies_keywords' => 'unternehmen firmen companies kunden clients mandanten tenants mandantenfähigkeit multi-tenancy organisationen msp',
        'routing_test_title' => 'Test der E-Mail-Zuordnung',
        'routing_test_desc'  => 'Eine eingehende E-Mail testweise durchlaufen lassen, um zu sehen, welchem Unternehmen sie zugeordnet würde — und warum.',
        'routing_test_keywords' => 'e-mail email zuordnung routing test probelauf dry run postfach absender domäne triage mandant eingehend diagnose',
        'integrations_title'    => 'Integrationen',
        'integrations_desc'     => 'FreeITSM mit den Issue-Trackern Ihres Entwicklungsteams verbinden, damit ein Ticket, das sich als Fehler herausstellt, eskaliert und verfolgt werden kann, ohne den Service Desk zu verlassen.',
        'integrations_keywords' => 'integrationen integrations integration jira atlassian issue tracker fehler bug eskalieren eskalation github gitlab azure devops konnektor entwickler entwicklungsteam verknüpfen verknüpftes issue',
    ],

    'branding' => [
        'title'    => 'Branding',
        'subtitle' => 'Das Logo der Organisation sowie Standardtexte für Kopf- und Fußzeilen von Diagrammen und exportierten Dokumenten festlegen',

        'logo_heading'  => 'Firmenlogo',
        'logo_desc'     => 'Wird als Token {code} in jedem Kopf- oder Fußzeilenfeld verwendet. PNG, JPG oder SVG, max. 2&nbsp;MB. Für scharfen Druck und Export wird SVG empfohlen.',
        'no_logo'       => 'Kein Logo',
        'remove'        => 'Entfernen',
        'logo_hint'     => 'Wählen Sie eine Datei, um das aktuelle Logo zu ersetzen. Das neue Bild wird beim Speichern übernommen.',

        'header_heading' => 'Kopfzeile',
        'header_desc'    => 'Drei Felder, die oben auf der Seite ausgegeben werden. Lassen Sie ein Feld leer, um es wegzulassen.',
        'footer_heading' => 'Fußzeile',
        'footer_desc'    => 'Drei Felder, die unten auf der Seite ausgegeben werden.',
        'col_left'       => 'Links',
        'col_centre'     => 'Mitte',
        'col_right'      => 'Rechts',
        'row_header'     => 'Kopfzeile',
        'row_footer'     => 'Fußzeile',

        'tokens_heading' => 'Verfügbare Token',
        'tokens_intro'   => 'Diese werden ersetzt, wenn die Kopf- bzw. Fußzeile in einem Diagramm oder Export ausgegeben wird:',
        'token_logo'     => 'das Bild des Firmenlogos',
        'token_title'    => 'der Titel des Diagramms oder Dokuments',
        'token_author'   => 'der Name des Autors',
        'token_version'  => 'die Versionsbezeichnung',
        'token_modified' => 'das Datum der letzten Änderung',
        'tokens_example_prefix' => 'Mischen Sie Token mit normalem Text — z. B.',
        'tokens_example_suffix' => 'wird ausgegeben als',
        'tokens_example_render' => 'Autor: Ed Mozley',

        'save'             => 'Speichern',
        'reset_defaults'   => 'Auf Standard zurücksetzen',

        'load_failed'         => 'Branding konnte nicht geladen werden: {error}',
        'load_failed_generic' => 'Branding-Einstellungen konnten nicht geladen werden',
        'logo_too_large'      => 'Logo zu groß (max. 2 MB)',
        'reset_hint'          => 'Felder auf Standard zurückgesetzt — zum Übernehmen speichern',
        'saved'               => 'Branding gespeichert',
        'error'               => 'Fehler: {error}',
        'save_failed'         => 'Branding konnte nicht gespeichert werden',
    ],

    'colours' => [
        'title'     => 'Modulfarben',
        'subtitle'  => 'Das Farbschema jedes Moduls für Kopfzeilen, Symbole und die Startseite anpassen',
        'save'      => 'Speichern',
        'primary'   => 'Primär',
        'secondary' => 'Sekundär',
        'reset'     => 'Zurücksetzen',
        'saved'     => 'Modulfarben gespeichert',
        'error'     => 'Fehler: {error}',
        'save_failed' => 'Farben konnten nicht gespeichert werden',
    ],

    'db_verify' => [
        'heading'     => 'Datenbankprüfung',
        'intro'       => 'Prüft, ob alle Tabellen und Spalten vorhanden sind. Fehlende werden automatisch angelegt.',
        'run'         => 'Prüfung ausführen',
        'verifying'   => 'Wird geprüft...',
        'checking'    => 'Tabellen werden geprüft...',
        'placeholder' => 'Klicken Sie auf „Prüfung ausführen“, um Ihr Datenbankschema zu überprüfen',

        'count_ok'      => 'In Ordnung',
        'count_created' => 'Angelegt',
        'count_updated' => 'Aktualisiert',
        'count_errors'  => 'Fehler',

        'col_table'   => 'Tabelle',
        'col_status'  => 'Status',
        'col_details' => 'Details',

        'status_ok' => 'In Ordnung',

        'fix'         => 'Beheben',
        'fixing'      => 'Wird behoben…',
        'fix_confirm' => '{count} verwaiste Zeile(n) aus {table} endgültig löschen? Der übergeordnete Datensatz existiert nicht mehr, diese Daten sind also nicht mehr erreichbar.',
        'fix_failed'  => 'Behebung fehlgeschlagen: {message}',

        'error'        => 'Fehler: {message}',
        'connect_fail' => 'Verbindung fehlgeschlagen: {message}',
    ],

    'debug' => [
        'heading' => 'Debug-Werkzeuge',
        'intro'   => 'Sammlung eigenständiger Diagnosen. Wenn etwas nicht funktioniert, führen Sie das passende Werkzeug aus und senden Sie die Ausgabe an den Support — jede Diagnose erfasst genug Umgebungs- und Laufzeitdetails, um die Ursache ohne Rückfragen zu erkennen.',
        'how_label' => 'So verwenden Sie sie:',
        'how_text'  => 'Der Support nennt Ihnen die auszuführende Diagnose (z. B. „führen Sie D001 aus“). Klicken Sie auf {run}, warten Sie, bis die Ausgabe erscheint, klicken Sie dann auf {copy} und fügen Sie den vollständigen Bericht in Ihre Antwort ein. Die Diagnosen lesen überwiegend nur; wenn eine in die Datenbank schreibt, steht das auf ihrer Karte.',
        'checks_label'   => 'Was geprüft wird',
        'runtime_label'  => 'Laufzeit:',
        'side_effects_label' => 'Nebenwirkungen:',
        'run'     => 'Ausführen',
        'running' => 'Wird ausgeführt…',
        'copy'    => 'Kopieren',
        'copied'  => 'Kopiert',
        'output_running' => 'Diagnose wird ausgeführt…',
        'fetch_failed'   => 'Diagnose konnte nicht abgerufen werden: {message}',
        'input_required' => 'Bitte geben Sie einen Wert ein, bevor Sie dieses Werkzeug ausführen.',
        'search_placeholder' => 'Debug-Werkzeuge durchsuchen…',
        'no_results'         => 'Keine Debug-Werkzeuge passen zu Ihrer Suche.',
    ],

    'demo' => [
        'heading'  => 'Demodaten',
        'subtitle' => 'Realistische Beispieldaten Modul für Modul importieren. Importieren Sie zuerst „Kern“ und wählen Sie dann, welche Module gefüllt werden sollen.',

        'warning_strong' => 'Nur für frische Installationen gedacht.',
        'warning_text'   => 'Der Import von Demodaten in ein System, das bereits echte Daten enthält, kann zu Konflikten führen. Jedes Modul kann nur einmal importiert werden.',
        'tip_text_prefix' => 'Importieren Sie sowohl',
        'tip_text_and'    => 'als auch',
        'tip_text_suffix' => ', um eine zusätzliche Option freizuschalten, die installierte Software mit Computern verknüpft.',
        'tip_assets'      => 'Assets',
        'tip_software'    => 'Software',

        'step1' => 'Schritt 1 — Erforderlich',
        'step2' => 'Schritt 2 — Module auswählen',
        'step3_cross' => 'Schritt 3 — Modulübergreifende Daten',
        'step3_dashboards' => 'Schritt 3 — Dashboards',

        'import'           => 'Importieren',
        'importing'        => 'Wird importiert...',
        'imported_count'   => '{total} importiert',
        'already_imported' => 'Bereits importiert',

        'delete_title'   => 'Löschen',
        'delete_confirm' => 'Damit werden die vorhandenen Demodaten für {module} gelöscht und neu importiert. Fortfahren?',
        'delete_ok'      => 'Löschen',
        'connection_failed' => 'Verbindung fehlgeschlagen: {message}',
    ],

    'encryption' => [
        'title'    => 'Verschlüsselung',
        'subtitle' => 'Den Schlüssel verwalten, mit dem gespeicherte sensible Daten geschützt werden',
        'checking' => 'Verschlüsselungsstatus wird geprüft...',

        'how_heading'   => 'So funktioniert die Verschlüsselung',
        'how_point1'    => 'FreeITSM schützt sensible Daten in der Datenbank — etwa API-Schlüssel, vCenter-Zugangsdaten und Postfachverbindungen — mit authentifizierter {strong}-Verschlüsselung.',
        'how_point1_strong' => 'AES-256-GCM',
        'how_point2'    => 'Der Schlüssel ist eine 64-stellige Hexadezimalzeichenfolge (256 Bit) und liegt in einer Datei {strong}, damit er nicht über einen Browser erreichbar ist.',
        'how_point2_strong' => 'außerhalb des Web-Stammverzeichnisses',
        'how_point3'    => 'Speicherort der Schlüsseldatei:',
        'how_point4'    => 'Verschlüsselte Werte in der Datenbank beginnen mit {enc}, gefolgt vom base64-kodierten Chiffretext. Unverschlüsselte Werte bleiben unverändert, sodass eine schrittweise Umstellung möglich ist.',

        'backup_strong' => 'Sichern Sie Ihren Verschlüsselungsschlüssel.',
        'backup_text'   => 'Geht der Schlüssel verloren, lassen sich damit verschlüsselte Daten nicht wiederherstellen. Bewahren Sie eine Kopie an einem sicheren Ort außerhalb dieses Servers auf.',

        'whats_heading'    => 'Was verschlüsselt wird',
        'group_settings'   => 'Systemeinstellungen',
        'group_mailbox'    => 'Postfachverbindungen',

        'status_ok_title'      => 'Verschlüsselung ist eingerichtet',
        'status_ok_detail'     => 'Der Schlüssel ist unter {path} vorhanden und gültig. Sensible Daten werden mit AES-256-GCM verschlüsselt gespeichert.',
        'status_invalid_title' => 'Ungültiger Verschlüsselungsschlüssel',
        'status_invalid_detail'=> 'Unter {path} wurde eine Schlüsseldatei gefunden, sie enthält jedoch keine gültige 64-stellige Hexadezimalzeichenfolge. Der Schlüssel muss aus genau 64 Hexadezimalzeichen (256 Bit) bestehen.',
        'generate_valid'       => 'Gültigen Schlüssel erzeugen',
        'status_missing_title' => 'Kein Verschlüsselungsschlüssel gefunden',
        'status_missing_detail'=> 'Unter {path} existiert keine Schlüsseldatei. Sensible Daten können erst verschlüsselt werden, wenn ein Schlüssel erzeugt wurde. Klicken Sie unten, um automatisch einen zu erzeugen.',
        'generate'             => 'Verschlüsselungsschlüssel erzeugen',
        'generating'           => 'Wird erzeugt...',

        'check_failed' => 'Verschlüsselungsstatus konnte nicht geprüft werden',
        'error'        => 'Fehler: {error}',
        'generate_failed' => 'Schlüssel konnte nicht erzeugt werden',
        'error_prefix' => 'Fehler: {message}',
    ],

    'modules' => [
        'title'    => 'Modulzugriff',
        'subtitle' => 'Steuern, welche Module jeder Analyst auf der Startseite und in der Navigation sieht',

        'info_text' => 'Standardmäßig haben alle Analysten Zugriff auf jedes Modul. Schalten Sie {all_access} aus, um einen Analysten auf bestimmte Module zu beschränken. Das Modul „System“ lässt sich nicht deaktivieren.',
        'all_access_strong' => 'Vollzugriff',

        'loading' => 'Analysten werden geladen...',

        'empty_heading' => 'Keine Analysten gefunden',
        'empty_text'    => 'Legen Sie Analysten zuerst in den Einstellungen des Ticket-Moduls an.',

        'col_analyst'    => 'Analyst',
        'col_all_access' => 'Vollzugriff',

        'load_failed' => 'Daten konnten nicht geladen werden',
        'save_failed' => 'Speichern fehlgeschlagen',
    ],

    'preferences' => [
        'title'    => 'Einstellungen',
        'subtitle' => 'Persönliche Einstellungen, die in Ihrem Konto gespeichert werden — sie gelten in jedem Browser.',

        'language_heading' => 'Sprache der Oberfläche',
        'language_desc'    => 'Die in der Oberfläche von FreeITSM verwendete Sprache. Für Texte, die in Ihrer Sprache noch nicht vorliegen, wird auf Englisch zurückgegriffen. Die Seite wird bei einer Änderung neu geladen.',
        'saving'           => 'Wird gespeichert…',

        'timezone_heading' => 'Zeitzone',
        'timezone_desc'    => 'Datum und Uhrzeit werden in FreeITSM in dieser Zeitzone angezeigt. Bis Sie eine auswählen, gilt die Zeitzone des Servers.',
        'timezone_saved'   => 'Zeitzone gespeichert',

        'position_heading' => 'Position der Benachrichtigungen',
        'position_desc'    => 'Wo Hinweismeldungen auf dem Bildschirm erscheinen.',

        'animation_heading' => 'Animation der Benachrichtigungen',
        'animation_desc'    => 'Wie Benachrichtigungen ein- und ausgeblendet werden.',
        'anim_slide'        => 'Schieben',
        'anim_fade'         => 'Überblenden',

        'panels_heading' => 'Linke Leisten',
        'panels_desc'    => 'Legen Sie je Modul fest, ob die linke Leiste dauerhaft geöffnet bleibt oder zu einem schmalen Streifen zusammenklappt, der sich beim Überfahren ausklappt. Module mit einer eigenen Einstellungsseite bieten dies auch dort unter „Linke Leiste“ an.',
        'panel_knowledge'         => 'Wissensdatenbank',
        'panel_process_mapper'    => 'Process Mapper',
        'panel_contracts'         => 'Verträge',
        'panel_calendar'          => 'Kalender',
        'panel_tasks'             => 'Aufgaben',
        'panel_cmdb'              => 'CMDB',
        'panel_change_management' => 'Änderungsmanagement',
        'panel_asset_management'  => 'Asset-Verwaltung',
        'panel_system_wiki'       => 'System-Wiki',

        'multiselect_heading'      => 'Mehrere Tickets auswählen',
        'multiselect_desc'         => 'Im Ticket-Posteingang können Sie mehrere Tickets gleichzeitig auswählen — Strg+Klick für einzelne, Umschalt+Klick für einen Block. Hier legen Sie fest, was angezeigt wird, solange mehr als eines ausgewählt ist.',
        'multiselect_summary'      => 'Übersichtsbereich',
        'multiselect_keep'         => 'Das Ticket geöffnet lassen',
        'multiselect_bar'          => 'Leiste über der Liste',
        'multiselect_summary_hint' => 'Der Lesebereich listet Ihre Auswahl auf und zeigt die Sammelaktionen dazu an.',
        'multiselect_keep_hint'    => 'Der Lesebereich zeigt weiterhin das geöffnete Ticket, mit dem Hinweis, dass eine Aktion alle ausgewählten betrifft.',
        'multiselect_bar_hint'     => 'Über der Ticketliste erscheint ein schmaler Streifen mit der Anzahl und den Aktionen.',

        'mc_heading' => 'Balkenfüllung der Morgenprüfungen',
        'mc_desc'    => 'Einfarbige Füllung oder Verlauf für das 30-Tage-Diagramm der Morgenprüfungen. Auch auf deren Einstellungsseite verfügbar.',
        'fill_plain'    => 'Einfarbig',
        'fill_gradient' => 'Verlauf',

        'pos_top_left'      => 'Oben links',
        'pos_top_center'    => 'Oben mittig',
        'pos_top_right'     => 'Oben rechts',
        'pos_middle_left'   => 'Mittig links',
        'pos_middle_center' => 'Mittig zentriert',
        'pos_middle_right'  => 'Mittig rechts',
        'pos_bottom_left'   => 'Unten links',
        'pos_bottom_center' => 'Unten mittig',
        'pos_bottom_right'  => 'Unten rechts',

        'pos_preview'   => 'Benachrichtigungen erscheinen hier',
        'anim_preview'  => 'Vorschau: Animation {anim}',
        'save_failed'   => 'Speichern fehlgeschlagen',
    ],
];
