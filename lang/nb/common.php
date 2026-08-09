<?php
/**
 * Norsk bokmål (nb) — felles tekststrenger for grensesnittet.
 *
 * Brukes overalt. Hold filen liten — modulspesifikke strenger hører hjemme i lang/nb/<modul>.php.
 * Andre språk speiler strukturen i denne filen under lang/<språk>/common.php.
 */
return [
    // Innstilling for synlighet av venstrepanelet — felles etiketter som gjenbrukes av
    // alle moduler med venstrepanel (innstillingssider + System → Preferanser). Bare de
    // identiske strengene ligger her; modulspesifikk introduksjons- og beskrivelsestekst
    // blir liggende i modulens egen fil.
    'left_panel' => [
        'tab'        => 'Venstrepanel',
        'visibility' => 'Synlighet',
        'always'     => 'Alltid synlig',
        'hover'      => 'Vis ved peking',
    ],

    // Felles panel for AI-leverandør, modell og nøkkel (includes/ai_settings_panel.php),
    // gjenbrukt av AI-innstillingsfanen i hver modul.
    'ai' => [
        'provider'            => 'Leverandør',
        'provider_anthropic'  => 'Anthropic (Claude)',
        'provider_openai'     => 'OpenAI (GPT)',
        'provider_openrouter' => 'OpenRouter (én nøkkel, mange modeller)',
        'openrouter_note'     => 'Med OpenRouter gir én enkelt nøkkel tilgang til hundrevis av modeller. Merk at forespørslene rutes gjennom tjenesten til OpenRouter.',
        'model'               => 'Modell',
        'model_placeholder'   => 'Skriv inn eller velg en modell…',
        'model_set'           => 'Modell',
        'loading_models'      => 'Laster modelliste…',
        'no_models'           => 'Ingen modeller passer — du kan skrive inn hvilken som helst modell-id',
        'openrouter_pricing'  => 'Priser vises per 1 mill. tokens (inn / ut).',
        'models_stale'        => 'mellomlagret',
        'api_key'             => 'API-nøkkel',
        'api_key_help'        => 'Lagres kryptert. La feltet stå tomt for å beholde den lagrede nøkkelen.',
        'api_key_set'         => 'En nøkkel er lagret. La feltet stå tomt for å beholde den.',
        'verify_ssl'          => 'Verifiser SSL-sertifikat',
        'verify_ssl_help'     => 'La den stå på i produksjon. Slå den av bare hvis serveren din ikke kan validere sertifikatet til leverandøren.',
        'save'                => 'Lagre',
        'test'                => 'Test',
        'testing'             => 'Tester…',
        'test_ok'             => 'Tilkoblingen er i orden',
        'test_failed'         => 'Testen mislyktes',
        'saved'               => 'Lagret',
        'save_failed'         => 'Kunne ikke lagre',
    ],

    // Knapper
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
    'open'         =>  'Åpne',
    'apply'        => 'Bruk',

    // Bekreftelse / tilstand
    'yes'          => 'Ja',
    'no'           => 'Nei',
    'ok'           => 'OK',
    'loading'      => 'Laster...',
    'saving'       => 'Lagrer...',
    'saved'        => 'Lagret',
    'unsaved'      => 'Ikke lagret',
    'unsaved_changes' => 'Ulagrede endringer',
    'failed'       => 'Mislyktes',

    // Tid / enheter (ofte satt inn i teksten)
    'just_now'     => 'akkurat nå',
    'today'        => 'I dag',
    'yesterday'    => 'I går',

    // Hjelpetekster for skjemaer
    'required'     => 'Påkrevd',
    'optional'     => 'Valgfritt',
    'select_one'   => 'Velg…',
    'search'       => 'Søk',

    // Feil
    'error_generic'        => 'Noe gikk galt.',
    'error_network'        => 'Nettverksfeil',
    'error_not_logged_in'  => 'Du må være logget inn.',

    // Hjem / forside (index.php)
    'home' => [
        'header_title'     => 'Brukerstøtte',
        'browser_title'    => 'Brukerstøtte - ITSM',
        'welcome_heading'  => 'Hva vil du gjøre?',
        'welcome_subtitle' => 'Velg en modul for å komme i gang',
        'footer'           => 'Brukerstøtte ITSM',
    ],

    // Vaffelpanelet for modulbytte (felles topptekst)
    'waffle' => [
        'title' => 'ITSM-moduler',
    ],

    // Visningsnavn og énlinjes beskrivelse per modul.
    // Brukes av kortene på hjemmesiden (navn + beskrivelse som hjelpetekst) og vaffelpanelet (bare navn).
    'modules' => [
        'watchtower'     => ['name' => 'Vakttårn',    'description' => 'Samlet oversikt over alt som krever oppmerksomhet på tvers av modulene'],
        'tickets'        => ['name' => 'Saker',       'description' => 'Håndter støtteforespørsler, e-post og brukerproblemer'],
        'assets'         => ['name' => 'Utstyr',      'description' => 'Hold oversikt over IT-utstyr og hvem som har hva'],
        'knowledge'      => ['name' => 'Kunnskap',    'description' => 'Opprett og les artikler i kunnskapsbasen'],
        'changes'        => ['name' => 'Endringer',   'description' => 'Planlegg, følg opp og håndter IT-endringer'],
        'problems'       => ['name' => 'Problemhåndtering', 'name_short' => 'Problemer', 'description' => 'Finn og følg opp den bakenforliggende årsaken til gjentakende hendelser'],
        'calendar'       => ['name' => 'Kalender',    'description' => 'Hold oversikt over hendelser, frister og planer'],
        'morning-checks' => ['name' => 'Kontroller',  'description' => 'Registrer daglige kontroller av infrastrukturen'],
        'reporting'      => ['name' => 'Rapporter',   'description' => 'Se systemlogger og analyser'],
        'software'       => ['name' => 'Programvare', 'description' => 'Bla i programvarebeholdningen og lisensene'],
        'forms'          => ['name' => 'Skjemaer',    'description' => 'Utform egne skjemaer og se innsendinger'],
        'contracts'      => ['name' => 'Kontrakter',  'description' => 'Håndter leverandører, kontakter og kontrakter'],
        'service-status' => ['name' => 'Status',      'description' => 'Overvåk tjenestenes tilstand og følg opp hendelser'],
        'wiki'           => ['name' => 'Wiki',        'description' => 'Bla i automatisk generert dokumentasjon av kodebasen'],
        'lms'            => ['name' => 'LMS',         'description' => 'Læringsplattform med SCORM-kursspiller'],
        'process-mapper' => ['name' => 'Prosesser',   'description' => 'Visuelt verktøy for flytdiagrammer og prosesskartlegging'],
        'tasks'          => ['name' => 'Oppgaver',    'description' => 'Kanban-tavle og listevisning for oppfølging av oppgaver'],
        'cmdb'           => ['name' => 'CMDB',        'description' => 'Konfigurasjonsdatabase'],
        'network-mapper' => ['name' => 'Nettverk',    'description' => 'Utform og dokumenter nettverksdiagrammer'],
        'workflow'       => ['name' => 'Arbeidsflyt', 'description' => 'Automatisering på tvers av moduler — utløsere, betingelser, handlinger'],
        'system'         => ['name' => 'System',      'description' => 'Systemadministrasjon og konfigurasjon'],
    ],

    // Konto- / brukermeny i den felles toppteksten
    'account' => [
        'mail_check'      => 'Se etter ny e-post',
        'preferences'     => 'Preferanser',
        'appearance'      => 'Utseende',
        'change_password' => 'Bytt passord',
        'mfa'             => 'Tofaktorpålogging',
        'trusted_device'  => 'Klarert enhet',
        'logout'          => 'Logg ut',
        'logout_confirm'  => 'Er du sikker på at du vil logge ut?',
        'badge_off'       => 'Av',
        'badge_on'        => 'På',
    ],

    // Vindu for passordbytte (faste etiketter — dynamiske JS-varsler er foreløpig på engelsk)
    'password_modal' => [
        'title'            => 'Bytt passord',
        'current_password' => 'Nåværende passord',
        'new_password'     => 'Nytt passord',
        'confirm_password' => 'Bekreft nytt passord',
        'submit'           => 'Bytt passord',
    ],

    // MFA-vindu (bare den faste tittelen — innholdet lages av JS)
    'mfa_modal' => [
        'title' => 'Tofaktorpålogging',
    ],

    // Kalenderelementer — måneder, ukedager, navigasjon. Deles av alle moduler
    // som viser en kalender (tickets/calendar.php i dag; øverstenivå-calendar/ neste).
    'calendar' => [
        'previous'   => 'Forrige',
        'next'       => 'Neste',
        'today'      => 'I dag',
        'view_month' => 'Måned',
        'view_week'  => 'Uke',
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
            'monday'    => 'mandag',
            'tuesday'   => 'tirsdag',
            'wednesday' => 'onsdag',
            'thursday'  => 'torsdag',
            'friday'    => 'fredag',
            'saturday'  => 'lørdag',
            'sunday'    => 'søndag',
        ],
    ],
];
