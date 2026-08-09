<?php
/**
 * Norsk nynorsk (nn) — Felles delte grensesnittstrengar.
 *
 * Brukt overalt. Hald fila lita — modulspesifikke strengar høyrer heime i lang/nn/<modul>.php.
 * Andre språk speglar strukturen i denne fila under lang/<locale>/common.php.
 */
return [
    // Left-panel visibility preference — shared labels reused by every module
    // that has a left panel (settings pages + System → Preferences). Only the
    // identical strings live here; per-module intro/description copy stays in
    // each module's own file.
    'left_panel' => [
        'tab'        => 'Venstrepanel',
        'visibility' => 'Synlegheit',
        'always'     => 'Alltid synleg',
        'hover'      => 'Vis ved peiking',
    ],

    // Shared AI provider/model/key panel (includes/ai_settings_panel.php),
    // reused by every module's AI settings tab.
    'ai' => [
        'provider'            => 'Leverandør',
        'provider_anthropic'  => 'Anthropic (Claude)',
        'provider_openai'     => 'OpenAI (GPT)',
        'provider_openrouter' => 'OpenRouter (éin nøkkel, mange modellar)',
        'openrouter_note'     => 'Med OpenRouter gir éin nøkkel tilgang til hundrevis av modellar. Merk at prompta går gjennom tenesta til OpenRouter.',
        'model'               => 'Modell',
        'model_placeholder'   => 'Skriv inn eller vel ein modell…',
        'model_set'           => 'Modell',
        'loading_models'      => 'Lastar modell-lista…',
        'no_models'           => 'Ingen modellar passar — du kan skrive inn kva modell-id som helst',
        'openrouter_pricing'  => 'Prisane er viste per 1M token (inn / ut).',
        'models_stale'        => 'mellomlagra',
        'api_key'             => 'API-nøkkel',
        'api_key_help'        => 'Lagra kryptert. La feltet stå tomt for å behalde den lagra nøkkelen.',
        'api_key_set'         => 'Ein nøkkel er lagra. La feltet stå tomt for å behalde han.',
        'verify_ssl'          => 'Kontroller SSL-sertifikat',
        'verify_ssl_help'     => 'Lat dette stå på i produksjon. Slå det av berre dersom serveren din ikkje klarer å validere sertifikatet til leverandøren.',
        'save'                => 'Lagre',
        'test'                => 'Test',
        'testing'             => 'Testar…',
        'test_ok'             => 'Tilkoplinga er OK',
        'test_failed'         => 'Testen feila',
        'saved'               => 'Lagra',
        'save_failed'         => 'Klarte ikkje å lagre',
    ],

    // Buttons
    'save'         => 'Lagre',
    'cancel'       => 'Avbryt',
    'delete'       => 'Slett',
    'add'          => 'Legg til',
    'edit'         => 'Rediger',
    'close'        => 'Lukk',
    'copy'         => 'Kopier',
    'copied'       => 'Kopiert',
    'retry'        => 'Prøv igjen',
    'export'       => 'Eksporter',
    'back'         => 'Tilbake',
    'open'         =>  'Opne',
    'apply'        => 'Bruk',

    // Confirm / state
    'yes'          => 'Ja',
    'no'           => 'Nei',
    'ok'           => 'OK',
    'loading'      => 'Lastar...',
    'saving'       => 'Lagrar...',
    'saved'        => 'Lagra',
    'unsaved'      => 'Ikkje lagra',
    'unsaved_changes' => 'Ulagra endringar',
    'failed'       => 'Feila',

    // Time / units (often inlined)
    'just_now'     => 'nettopp',
    'today'        => 'I dag',
    'yesterday'    => 'I går',

    // Form helpers
    'required'     => 'Påkravd',
    'optional'     => 'Valfritt',
    'select_one'   => 'Vel…',
    'search'       => 'Søk',

    // Errors
    'error_generic'        => 'Noko gjekk gale.',
    'error_network'        => 'Nettverksfeil',
    'error_not_logged_in'  => 'Du må vere logga inn.',

    // Home / landing page (index.php)
    'home' => [
        'header_title'     => 'Servicedesk',
        'browser_title'    => 'Servicedesk - ITSM',
        'welcome_heading'  => 'Kva vil du gjere?',
        'welcome_subtitle' => 'Vel ein modul for å kome i gang',
        'footer'           => 'Servicedesk ITSM',
    ],

    // Waffle module-switcher panel (shared header)
    'waffle' => [
        'title' => 'ITSM-modular',
    ],

    // Per-module display name + one-line description.
    // Used by the home cards (name + description tooltip) and the waffle panel (name only).
    'modules' => [
        'watchtower'     => ['name' => 'Vakttårn',    'description' => 'Samla oversikt over det som treng merksemd i alle modulane'],
        'tickets'        => ['name' => 'Saker',       'description' => 'Handter supportførespurnader, e-postar og brukarproblem'],
        'assets'         => ['name' => 'Ressursar',   'description' => 'Hald oversikt over IT-ressursar og kven dei er tildelte'],
        'knowledge'      => ['name' => 'Kunnskap',    'description' => 'Lag og bla gjennom artiklar i kunnskapsbasen'],
        'changes'        => ['name' => 'Endringar',   'description' => 'Planlegg, følg opp og handter IT-endringar'],
        'problems'       => ['name' => 'Problemhandtering', 'name_short' => 'Problem', 'description' => 'Finn den underliggjande årsaka bak gjentakande hendingar'],
        'calendar'       => ['name' => 'Kalender',    'description' => 'Hald oversikt over hendingar, fristar og planar'],
        'morning-checks' => ['name' => 'Kontrollar',  'description' => 'Registrer daglege infrastrukturkontrollar'],
        'reporting'      => ['name' => 'Rapportar',   'description' => 'Sjå systemloggar og analysar'],
        'software'       => ['name' => 'Programvare', 'description' => 'Bla gjennom programvarebehaldning og lisensiering'],
        'forms'          => ['name' => 'Skjema',      'description' => 'Utform eigne skjema og sjå innsendingar'],
        'contracts'      => ['name' => 'Kontraktar',  'description' => 'Handter leverandørar, kontaktar og kontraktar'],
        'service-status' => ['name' => 'Status',      'description' => 'Overvak tenestehelsa og følg opp hendingar'],
        'wiki'           => ['name' => 'Wiki',        'description' => 'Bla gjennom automatisk generert dokumentasjon av koden'],
        'lms'            => ['name' => 'LMS',         'description' => 'Læringsplattform med SCORM-kursspelar'],
        'process-mapper' => ['name' => 'Prosessar',   'description' => 'Visuelt verktøy for flytdiagram og prosesskartlegging'],
        'tasks'          => ['name' => 'Oppgåver',    'description' => 'Kanban-tavle og listevising for å følgje opp oppgåver'],
        'cmdb'           => ['name' => 'CMDB',        'description' => 'Database for konfigurasjonsstyring'],
        'network-mapper' => ['name' => 'Nettverk',    'description' => 'Utform og dokumenter nettverksdiagram'],
        'workflow'       => ['name' => 'Arbeidsflytar', 'description' => 'Automatisering på tvers av modular — utløysarar, vilkår, handlingar'],
        'system'         => ['name' => 'System',      'description' => 'Systemadministrasjon og konfigurasjon'],
    ],

    // Account / user menu in the shared header
    'account' => [
        'mail_check'      => 'Sjå etter nye e-postar',
        'preferences'     => 'Innstillingar',
        'appearance'      => 'Utsjånad',
        'change_password' => 'Byt passord',
        'mfa'             => 'Fleirfaktor-autentisering',
        'trusted_device'  => 'Klarert eining',
        'logout'          => 'Logg ut',
        'logout_confirm'  => 'Er du sikker på at du vil logge ut?',
        'badge_off'       => 'Av',
        'badge_on'        => 'På',
    ],

    // Change-password modal (static labels — dynamic JS toasts stay English for now)
    'password_modal' => [
        'title'            => 'Byt passord',
        'current_password' => 'Noverande passord',
        'new_password'     => 'Nytt passord',
        'confirm_password' => 'Stadfest nytt passord',
        'submit'           => 'Byt passord',
    ],

    // MFA modal (just the static title — the dynamic content is JS-rendered)
    'mfa_modal' => [
        'title' => 'Fleirfaktor-autentisering',
    ],

    // Calendar primitives — months, weekdays, navigation. Shared across any module
    // that renders a calendar (tickets/calendar.php today; top-level calendar/ next).
    'calendar' => [
        'previous'   => 'Førre',
        'next'       => 'Neste',
        'today'      => 'I dag',
        'view_month' => 'Månad',
        'view_week'  => 'Veke',
        'view_day'   => 'Dag',

        'months' => [
            'january'   => 'januar',
            'february'  => 'februar',
            'march'     => 'mars',
            'april'     => 'april',
            'may'       => 'mai',
            'june'      => 'juni',
            'july'      => 'juli',
            'august'    => 'august',
            'september' => 'september',
            'october'   => 'oktober',
            'november'  => 'november',
            'december'  => 'desember',
        ],

        'weekdays' => [
            'monday'    => 'måndag',
            'tuesday'   => 'tysdag',
            'wednesday' => 'onsdag',
            'thursday'  => 'torsdag',
            'friday'    => 'fredag',
            'saturday'  => 'laurdag',
            'sunday'    => 'sundag',
        ],
    ],
];
