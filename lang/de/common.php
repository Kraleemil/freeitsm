<?php
/**
 * Deutsch (de) — Common shared UI strings.
 * Falls back per-key to lang/en/common.php for anything missing here.
 */
return [
    // Left-panel visibility preference — shared labels (mirrors lang/en/common.php).
    'left_panel' => [
        'tab'        => 'Linke Leiste',
        'visibility' => 'Sichtbarkeit',
        'always'     => 'Immer sichtbar',
        'hover'      => 'Bei Mauszeiger anzeigen',
    ],

    'save'         => 'Speichern',
    'cancel'       => 'Abbrechen',
    'delete'       => 'Löschen',
    'add'          => 'Hinzufügen',
    'edit'         => 'Bearbeiten',
    'close'        => 'Schließen',
    'copy'         => 'Kopieren',
    'copied'       => 'Kopiert',
    'retry'        => 'Erneut versuchen',
    'export'       => 'Exportieren',
    'back'         => 'Zurück',
    'open'         =>  'Öffnen',
    'apply'        => 'Anwenden',

    'yes'          => 'Ja',
    'no'           => 'Nein',
    'ok'           => 'OK',
    'loading'      => 'Lädt…',
    'saving'       => 'Speichert…',
    'saved'        => 'Gespeichert',
    'unsaved'      => 'Nicht gespeichert',
    'unsaved_changes' => 'Nicht gespeicherte Änderungen',
    'failed'       => 'Fehlgeschlagen',

    'just_now'     => 'gerade eben',
    'today'        => 'Heute',
    'yesterday'    => 'Gestern',

    'required'     => 'Erforderlich',
    'optional'     => 'Optional',
    'select_one'   => 'Auswählen…',
    'search'       => 'Suchen',

    'error_generic'       => 'Etwas ist schiefgelaufen.',
    'error_network'       => 'Netzwerkfehler',
    'error_not_logged_in' => 'Sie müssen angemeldet sein.',

    // Home / landing page (index.php)
    'home' => [
        'header_title'     => 'Service Desk',
        'browser_title'    => 'Service Desk - ITSM',
        'welcome_heading'  => 'Was möchten Sie tun?',
        'welcome_subtitle' => 'Wählen Sie ein Modul, um zu beginnen',
        'footer'           => 'Service Desk ITSM',
    ],

    // Waffle module-switcher panel (shared header)
    'waffle' => [
        'title' => 'ITSM-Module',
    ],

    // Per-module display name + one-line description.
    'modules' => [
        'watchtower'     => ['name' => 'Wachtturm',    'description' => 'Einheitliches Übersichts-Dashboard für alle Module'],
        'tickets'        => ['name' => 'Tickets',      'description' => 'Verwalten Sie Supportanfragen, E-Mails und Benutzerprobleme'],
        'assets'         => ['name' => 'Assets',       'description' => 'Verfolgen Sie IT-Assets und Benutzerzuweisungen'],
        'knowledge'      => ['name' => 'Wissen',       'description' => 'Erstellen und durchsuchen Sie Wissensdatenbank-Artikel'],
        'changes'        => ['name' => 'Änderungen',   'description' => 'IT-Änderungen planen, verfolgen und verwalten'],
        'calendar'       => ['name' => 'Kalender',     'description' => 'Termine, Fristen und Zeitpläne verfolgen'],
        'morning-checks' => ['name' => 'Checks',       'description' => 'Tägliche Infrastrukturprüfungen erfassen'],
        'reporting'      => ['name' => 'Berichte',     'description' => 'Systemprotokolle und Analysen ansehen'],
        'software'       => ['name' => 'Software',     'description' => 'Software-Inventar und Lizenzierung durchsuchen'],
        'forms'          => ['name' => 'Formulare',    'description' => 'Eigene Formulare entwerfen und Einreichungen anzeigen'],
        'contracts'      => ['name' => 'Verträge',     'description' => 'Lieferanten, Kontakte und Verträge verwalten'],
        'service-status' => ['name' => 'Status',       'description' => 'Servicezustand überwachen und Vorfälle verfolgen'],
        'wiki'           => ['name' => 'Wiki',         'description' => 'Automatisch generierte Codebasis-Dokumentation durchsuchen'],
        'lms'            => ['name' => 'LMS',          'description' => 'Lernmanagementsystem mit SCORM-Kursplayer'],
        'process-mapper' => ['name' => 'Prozesse',     'description' => 'Visuelles Flussdiagramm- und Prozess-Mapping-Tool'],
        'tasks'          => ['name' => 'Aufgaben',     'description' => 'Kanban-Board und Listenansicht zur Aufgabenverfolgung'],
        'cmdb'           => ['name' => 'CMDB',         'description' => 'Konfigurations-Management-Datenbank'],
        'network-mapper' => ['name' => 'Netzwerk',     'description' => 'Netzwerkdiagramme entwerfen und dokumentieren'],
        'system'         => ['name' => 'System',       'description' => 'Systemadministration und Konfiguration'],
        // ⚠️ „Problems“ hat zusätzlich einen Kurznamen für die Waffel-Kachel —
        // „Problemmanagement“ passt dort nicht in eine Zeile.
        'problems'       => ['name' => 'Problemmanagement', 'name_short' => 'Probleme', 'description' => 'Die Ursache hinter wiederkehrenden Störungen verfolgen'],
        'war-room'       => ['name' => 'Lagezentrum',  'description' => 'Ausweich-Chat für den Fall, dass Teams oder Slack nicht verfügbar sind'],
        'workflow'       => ['name' => 'Workflows',    'description' => 'Modulübergreifende Automatisierung — Auslöser, Bedingungen, Aktionen'],
    ],

    // Account / user menu in the shared header
    'account' => [
        'preferences' => 'Einstellungen',
        'appearance'  => 'Darstellung',
        'mail_check'      => 'Auf neue E-Mails prüfen',
        'change_password' => 'Passwort ändern',
        'mfa'             => 'Multi-Faktor-Auth.',
        'trusted_device'  => 'Vertrauenswürdiges Gerät',
        'logout'          => 'Abmelden',
        'logout_confirm'  => 'Möchten Sie sich wirklich abmelden?',
        'badge_off'       => 'Aus',
        'badge_on'        => 'Ein',
    ],

    // Change-password modal
    'password_modal' => [
        'title'            => 'Passwort ändern',
        'current_password' => 'Aktuelles Passwort',
        'new_password'     => 'Neues Passwort',
        'confirm_password' => 'Neues Passwort bestätigen',
        'submit'           => 'Passwort ändern',
    ],

    // MFA modal
    'mfa_modal' => [
        'title' => 'Multi-Faktor-Authentifizierung',
    ],

    // Calendar primitives — months, weekdays, navigation.
    'calendar' => [
        'view_month' => 'Monat',
        'view_week'  => 'Woche',
        'view_day'   => 'Tag',
        'previous' => 'Zurück',
        'next'     => 'Weiter',
        'today'    => 'Heute',

        'months' => [
            'january'   => 'Januar',
            'february'  => 'Februar',
            'march'     => 'März',
            'april'     => 'April',
            'may'       => 'Mai',
            'june'      => 'Juni',
            'july'      => 'Juli',
            'august'    => 'August',
            'september' => 'September',
            'october'   => 'Oktober',
            'november'  => 'November',
            'december'  => 'Dezember',
        ],

        'weekdays' => [
            'monday'    => 'Montag',
            'tuesday'   => 'Dienstag',
            'wednesday' => 'Mittwoch',
            'thursday'  => 'Donnerstag',
            'friday'    => 'Freitag',
            'saturday'  => 'Samstag',
            'sunday'    => 'Sonntag',
        ],
    ],

    // Gemeinsames Bedienfeld für den KI-Anbieter (renderAiSettingsPanel).
    // ⚠️ Anbieternamen und Produktbezeichnungen bleiben unverändert.
    'ai' => [
        'provider'            => 'Anbieter',
        'provider_anthropic'  => 'Anthropic (Claude)',
        'provider_openai'     => 'OpenAI (GPT)',
        'provider_openrouter' => 'OpenRouter (ein Schlüssel, viele Modelle)',
        'openrouter_note'     => 'Mit OpenRouter erreichen Sie über einen einzigen Schlüssel Hunderte von Modellen. Beachten Sie, dass die Anfragen dabei über die Server von OpenRouter laufen.',
        'model'               => 'Modell',
        'model_placeholder'   => 'Modell eingeben oder auswählen…',
        'model_set'           => 'Modell',
        'loading_models'      => 'Modellliste wird geladen…',
        'no_models'           => 'Keine passenden Modelle — Sie können jede Modell-ID eingeben',
        'openrouter_pricing'  => 'Preise je 1 Mio. Token (Eingang / Ausgang).',
        'models_stale'        => 'zwischengespeichert',
        'api_key'             => 'API-Schlüssel',
        'api_key_help'        => 'Wird verschlüsselt gespeichert. Leer lassen, um den gespeicherten Schlüssel beizubehalten.',
        'api_key_set'         => 'Ein Schlüssel ist gespeichert. Leer lassen, um ihn beizubehalten.',
        'verify_ssl'          => 'SSL-Zertifikat überprüfen',
        'verify_ssl_help'     => 'Im Produktivbetrieb eingeschaltet lassen. Nur abschalten, wenn Ihr Server das Zertifikat des Anbieters nicht prüfen kann.',
        'save'                => 'Speichern',
        'test'                => 'Testen',
        'testing'             => 'Wird getestet…',
        'test_ok'             => 'Verbindung in Ordnung',
        'test_failed'         => 'Test fehlgeschlagen',
        'saved'               => 'Gespeichert',
        'save_failed'         => 'Speichern fehlgeschlagen',
    ],
];
