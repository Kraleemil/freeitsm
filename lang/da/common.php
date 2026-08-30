<?php
/**
 * Danish (da) — Common shared UI strings.
 *
 * Mirrors lang/en/common.php. Keep the key structure identical: I18n::t() splits
 * on every dot, so a nested group here must stay nested.
 *
 * Danish notes for whoever translates the next namespace:
 *  - Nouns are NOT capitalised. Sentence case throughout, unlike German.
 *  - Month and weekday names are lower case, and their abbreviations carry a
 *    full stop ("25. aug. 2026"), which is why months_short reads "aug." here.
 *  - "du"/"din" is the normal register for software; there is no formal "De".
 */
return [
    'subscribe' => [
        'insecure'      => 'Dette system bruger ikke HTTPS, så dette link — og alt, hvad det viser — sendes ubeskyttet over netværket, hver gang din kalender opdaterer. Bed din administrator om at slå HTTPS til, før du bruger det uden for et betroet netværk.',
        'copied'        => 'Link kopieret',
        'reset'         => 'Nulstil',
        'reset_confirm' => 'Alle enheder, der allerede abonnerer, holder op med at opdatere, indtil du giver dem det nye link. Vil du fortsætte?',
        'reset_done'    => 'Linket er nulstillet — det gamle virker ikke længere',
    ],

    'left_panel' => [
        'tab'        => 'Venstre panel',
        'visibility' => 'Synlighed',
        'always'     => 'Altid synligt',
        'hover'      => 'Vis ved markørover',
    ],

    'ai' => [
        'provider'            => 'Udbyder',
        'provider_anthropic'  => 'Anthropic (Claude)',
        'provider_openai'     => 'OpenAI (GPT)',
        'provider_openrouter' => 'OpenRouter (én nøgle, mange modeller)',
        'openrouter_note'     => 'Med OpenRouter når en enkelt nøgle hundredvis af modeller. Bemærk, at prompts sendes gennem OpenRouters tjeneste.',
        'model'               => 'Model',
        'model_placeholder'   => 'Skriv eller vælg en model…',
        'model_set'           => 'Model',
        'loading_models'      => 'Henter modelliste…',
        'no_models'           => 'Ingen modeller matcher — du kan skrive et hvilket som helst model-id',
        'openrouter_pricing'  => 'Priser vist pr. 1 mio. tokens (ind / ud).',
        'models_stale'        => 'cachelagret',
        'api_key'             => 'API-nøgle',
        'api_key_help'        => 'Gemmes krypteret. Lad feltet stå tomt for at beholde den gemte nøgle.',
        'api_key_set'         => 'En nøgle er gemt. Lad feltet stå tomt for at beholde den.',
        'verify_ssl'          => 'Kontrollér SSL-certifikat',
        'verify_ssl_help'     => 'Lad den være slået til i drift. Slå den kun fra, hvis din server ikke kan validere udbyderens certifikat.',
        'save'                => 'Gem',
        'test'                => 'Test',
        'testing'             => 'Tester…',
        'test_ok'             => 'Forbindelsen er i orden',
        'test_failed'         => 'Testen mislykkedes',
        'saved'               => 'Gemt',
        'save_failed'         => 'Kunne ikke gemme',
    ],

    // Buttons
    'save'         => 'Gem',
    'cancel'       => 'Annullér',
    'delete'       => 'Slet',
    'add'          => 'Tilføj',
    'edit'         => 'Redigér',
    'close'        => 'Luk',
    'dismiss'      => 'Afvis',
    'copy'         => 'Kopiér',
    'copied'       => 'Kopieret',
    'retry'        => 'Prøv igen',
    'export'       => 'Eksportér',
    'back'         => 'Tilbage',
    'open'         => 'Åbn',
    'apply'        => 'Anvend',

    // Confirm / state
    'yes'          => 'Ja',
    'no'           => 'Nej',
    'ok'           => 'OK',
    'loading'      => 'Indlæser...',
    'saving'       => 'Gemmer...',
    'saved'        => 'Gemt',
    'unsaved'      => 'Ikke gemt',
    'unsaved_changes' => 'Ugemte ændringer',
    'failed'       => 'Mislykkedes',

    // Time / units
    'just_now'     => 'lige nu',
    'today'        => 'I dag',
    'yesterday'    => 'I går',

    // Form helpers
    'required'     => 'Påkrævet',
    'optional'     => 'Valgfrit',
    'select_one'   => 'Vælg…',
    'search'       => 'Søg',

    // Errors
    'error_generic'        => 'Noget gik galt.',
    'error_network'        => 'Netværksfejl',
    'error_not_logged_in'  => 'Du skal være logget ind.',

    'home' => [
        'header_title'     => 'Service Desk',
        'browser_title'    => 'Service Desk - ITSM',
        'welcome_heading'  => 'Hvad vil du gerne?',
        'welcome_subtitle' => 'Vælg et modul for at komme i gang',
        'footer'           => 'Service Desk ITSM',
    ],

    'waffle' => [
        'title' => 'ITSM-moduler',
    ],

    'modules' => [
        'watchtower'     => ['name' => 'Vagttårn',    'description' => 'Samlet overblik over alt, der kræver opmærksomhed, på tværs af moduler'],
        'tickets'        => ['name' => 'Sager',       'description' => 'Håndtér supporthenvendelser, e-mails og brugerproblemer'],
        'assets'         => ['name' => 'Aktiver',     'description' => 'Hold styr på it-aktiver og hvem der har dem'],
        'knowledge'      => ['name' => 'Viden',       'description' => 'Opret og gennemse artikler i videnbasen'],
        'changes'        => ['name' => 'Ændringer',   'description' => 'Planlæg, følg og håndtér it-ændringer'],
        'problems'       => ['name' => 'Problemstyring', 'name_short' => 'Problemer', 'description' => 'Find den bagvedliggende årsag til gentagne hændelser'],
        'calendar'       => ['name' => 'Kalender',    'description' => 'Hold styr på begivenheder, frister og planer'],
        'morning-checks' => ['name' => 'Tjek',        'description' => 'Registrér daglige tjek af infrastrukturen'],
        'reporting'      => ['name' => 'Rapportering','description' => 'Se systemlogge og analyser'],
        'software'       => ['name' => 'Software',    'description' => 'Gennemse softwareoversigt og licenser'],
        'forms'          => ['name' => 'Formularer',  'description' => 'Design egne formularer og se besvarelser'],
        'contracts'      => ['name' => 'Kontrakter',  'description' => 'Håndtér leverandører, kontakter og kontrakter'],
        'service-status' => ['name' => 'Status',      'description' => 'Overvåg tjenesternes tilstand og følg hændelser'],
        'war-room'       => ['name' => 'Krisecenter', 'description' => 'Reservechat til når Teams eller Slack er nede'],
        'wiki'           => ['name' => 'Wiki',        'description' => 'Gennemse automatisk genereret dokumentation af koden'],
        'lms'            => ['name' => 'LMS',         'description' => 'Læringsplatform med SCORM-kursusafspiller'],
        'process-mapper' => ['name' => 'Processer',   'description' => 'Værktøj til rutediagrammer og proceskortlægning'],
        'tasks'          => ['name' => 'Opgaver',     'description' => 'Kanban-tavle og listevisning til at følge opgaver'],
        'cmdb'           => ['name' => 'CMDB',        'description' => 'Konfigurationsdatabase'],
        'network-mapper' => ['name' => 'Netværk',     'description' => 'Tegn og dokumentér netværksdiagrammer'],
        'workflow'       => ['name' => 'Arbejdsgange','description' => 'Automatisering på tværs af moduler — udløsere, betingelser, handlinger'],
        'system'         => ['name' => 'System',      'description' => 'Systemadministration og konfiguration'],
    ],

    'documents' => [
        'heading'        => 'Dokumenter',
        'count_one'      => '1 dokument',
        'count_many'     => '{n} dokumenter',
        'none'           => 'Der er endnu ikke vedhæftet dokumenter.',
        'drop'           => 'Slip en fil her, eller klik for at vælge en',
        'drop_or'        => 'eller indsæt et link til den i dit dokumentsystem nedenfor',
        'link_url'       => 'https://link-til-dit-dokument',
        'link_title'     => 'Hvad er det? (valgfrit)',
        'add_link'       => 'Tilføj link',
        'open'           => 'Åbn',
        'download'       => 'Download',
        'remove'         => 'Fjern',
        'remove_confirm' => 'Fjern "{name}" fra denne post?',
        'removed_last'   => 'Det var det sidste sted, dokumentet var vedhæftet, så det er blevet slettet.',
        'also_on'        => 'Også på {label}',
        'uploading'      => 'Uploader…',
        'show_more'      => 'Vis flere',
        'failed'         => 'Noget gik galt.',
        'by'             => 'af {name}',
        'loading'        => 'Indlæser…',
        'close'          => 'Luk',
        'info_title'     => 'Dokumentoplysninger',
        'attached_to'    => 'Vedhæftet',
        'attached_none'  => 'Ikke vedhæftet noget, du har adgang til.',
        'attached_hidden' => 'Og {n} andre poster, du ikke har adgang til.',
        'kind_link'      => 'Et link til et eksternt dokument',
        'idx_ok'          => 'Kan søges frem — {n} tegn tekst er indekseret.',
        'idx_pending'     => 'Kan ikke søges frem endnu — teksten læses stadig.',
        'idx_unsupported' => 'Indholdet kan ikke læses, så kun navn og beskrivelse kan søges frem.',
        'idx_failed'      => 'Indholdet kunne ikke læses.',
        'find_existing'   => 'Eller vedhæft et dokument, der allerede findes i FreeITSM — begynd at skrive navnet',
        'find_none'       => 'Ingen dokumenter matcher, som du kan se, og som ikke allerede er her.',
        'currently_on'    => 'aktuelt på {where}',
    ],

    'notifications' => [
        'title'       => 'Notifikationer',
        'aria'        => 'Notifikationer',
        'mark_all'    => 'Markér alle som læst',
        'clear_all'         => 'Ryd alle',
        'clear_one'         => 'Ryd denne notifikation',
        'clear_one_title'   => 'Ryd denne notifikation?',
        'clear_one_msg'     => 'Den fjernes fra din klokke for altid. Det kan ikke fortrydes.',
        'clear_title'       => 'Ryd notifikationer?',
        'clear_msg'         => 'Det fjerner dem fra din klokke for altid. Det kan ikke fortrydes.',
        'clear_msg_read'    => 'Det fjerner læste notifikationer fra din klokke for altid. Det kan ikke fortrydes.',
        'clear_unread'      => 'Ryd også de {n}, du endnu ikke har læst',
        'clear_unread_one'  => 'Ryd også den ene, du endnu ikke har læst',
        'clear_ok'          => 'Ryd',
        'clear_failed'      => 'Kunne ikke rydde notifikationer.',
        'clear_nothing'     => 'Intet at rydde — alle disse er ulæste.',
        'empty'       => 'Ikke noget nyt.',
        'loading'     => 'Indlæser…',
        'load_failed' => 'Kunne ikke hente notifikationer.',
        'someone'     => 'Nogen',
        'just_now'    => 'lige nu',
        'minutes'     => 'for {n} min. siden',
        'hours'       => 'for {n} t. siden',
        'days'        => 'for {n} d. siden',

        // ⚠️ NESTED BY ENTITY — see the English file. A flat 'ticket.assigned'
        // key here would be unreachable, because I18n::t() splits on every dot.
        'event' => [
            'ticket' => [
                'assigned'         => 'Tildelt dig af {actor}',
                'reply_received'   => 'Anmelderen har svaret',
                'note_added'       => '{actor} tilføjede en note',
                'status_changed'   => '{actor} ændrede status',
                'priority_changed' => '{actor} ændrede prioriteten',
                'created'          => 'Oprettet af {actor}',
            ],
            'sla' => [
                'warning'  => 'Nærmer sig sit SLA-mål',
                'breached' => 'SLA-målet er overskredet',
            ],
            'task' => [
                'assigned'  => 'Tildelt dig af {actor}',
                'created'   => '{actor} oprettede en opgave til dig',
                'completed' => '{actor} fuldførte en opgave',
            ],
        ],

        'pref' => [
            'ticket' => [
                'assigned'         => 'En sag tildeles mig',
                'reply_received'   => 'En anmelder svarer på min sag',
                'note_added'       => 'Nogen tilføjer en note til min sag',
                'status_changed'   => 'Nogen ændrer status på min sag',
                'priority_changed' => 'Nogen ændrer prioriteten på min sag',
                'created'          => 'En sag oprettes',
            ],
            'sla' => [
                'warning'  => 'Min sag nærmer sig sit SLA-mål',
                'breached' => 'Min sag overskrider sit SLA-mål',
            ],
            'task' => [
                'assigned'  => 'En opgave tildeles mig',
                'created'   => 'En opgave oprettes til mig',
                'completed' => 'En af mine opgaver fuldføres',
            ],
        ],
    ],

    'account' => [
        'mail_check'      => 'Se efter nye e-mails',
        'preferences'     => 'Indstillinger',
        'appearance'      => 'Udseende',
        'change_password' => 'Skift adgangskode',
        'mfa'             => 'Flerfaktorgodkendelse',
        'trusted_device'  => 'Betroet enhed',
        'logout'          => 'Log ud',
        'logout_confirm'  => 'Er du sikker på, at du vil logge ud?',
        'badge_off'       => 'Fra',
        'badge_on'        => 'Til',
    ],

    'password_modal' => [
        'title'            => 'Skift adgangskode',
        'current_password' => 'Nuværende adgangskode',
        'new_password'     => 'Ny adgangskode',
        'confirm_password' => 'Bekræft ny adgangskode',
        'submit'           => 'Skift adgangskode',
    ],

    'mfa_modal' => [
        'title' => 'Flerfaktorgodkendelse',
    ],

    'calendar' => [
        'previous'   => 'Forrige',
        'next'       => 'Næste',
        'today'      => 'I dag',
        'view_month' => 'Måned',
        'view_week'  => 'Uge',
        'view_day'   => 'Dag',

        // Danish month and weekday names are lower case.
        'months' => [
            'january'   => 'januar',
            'february'  => 'februar',
            'march'     => 'marts',
            'april'     => 'april',
            'may'       => 'maj',
            'june'      => 'juni',
            'july'      => 'juli',
            'august'    => 'august',
            'september' => 'september',
            'october'   => 'oktober',
            'november'  => 'november',
            'december'  => 'december',
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

        // Danish abbreviations take a full stop. "maj" is not abbreviated
        // because the full word is already three letters.
        'months_short' => [
            'january'   => 'jan.',
            'february'  => 'feb.',
            'march'     => 'mar.',
            'april'     => 'apr.',
            'may'       => 'maj',
            'june'      => 'jun.',
            'july'      => 'jul.',
            'august'    => 'aug.',
            'september' => 'sep.',
            'october'   => 'okt.',
            'november'  => 'nov.',
            'december'  => 'dec.',
        ],

        'weekdays_short' => [
            'monday'    => 'man.',
            'tuesday'   => 'tir.',
            'wednesday' => 'ons.',
            'thursday'  => 'tor.',
            'friday'    => 'fre.',
            'saturday'  => 'lør.',
            'sunday'    => 'søn.',
        ],
    ],
];
