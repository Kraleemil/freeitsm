<?php
/**
 * Norsk bokmål (nb) — tekster for rapporteringsmodulen.
 *
 * Manglende nøkler faller tilbake til lang/en/reporting.php nøkkel for nøkkel
 * (se includes/i18n.php).
 *
 * Dekker landingssiden, visningen av systemlogger, plassholderen for
 * saksoversiktene, Intune-oversikten (KPI-stripe, moduler, dialog for
 * detaljvisning) og hjelpeveilederen. Bare rammeverket rundt er oversatt —
 * loggrader, sakstitler og Intune-enhetsdata står som de er (de er data, ikke
 * grensesnitt).
 */
return [
    'title' => 'Rapportering',

    'nav' => [
        'logs'    => 'Logger',
        'tickets' => 'Saker',
        'intune'  => 'Intune',
        'help'    => 'Hjelp',

        'logs_title'    => 'Systemlogger',
        'tickets_title' => 'Saksoversikter',
        'intune_title'  => 'Intune-oversikt',
        'help_title'    => 'Hjelp',
    ],

    'landing' => [
        'heading'  => 'Rapportering',
        'subtitle' => 'Velg et rapporteringsområde for å komme i gang',

        'logs_title'    => 'Systemlogger',
        'logs_desc'     => 'Se innloggingsforsøk, e-postimporter og andre logger over systemaktivitet.',
        'tickets_title' => 'Saksoversikter',
        'tickets_desc'  => 'KPI-oversikter for saksytelse, løsningstider og arbeidsmengde i teamet.',
        'intune_title'  => 'Intune-oversikt',
        'intune_desc'   => 'Samsvar, kryptering, OS-fordeling, registreringstrend og status for siste synkronisering på tvers av alle administrerte enheter.',
    ],

    'logs' => [
        'heading'  => 'Systemlogger',
        'refresh'  => 'Oppdater',
        'tab_login'        => 'Brukerinnlogginger',
        'tab_email_import' => 'E-postimporter',

        'loading'        => 'Laster logger ...',
        'no_logs'        => 'Ingen logger funnet',
        'load_error'     => 'Feil ved lasting av logger: {error}',

        'col_datetime'    => 'Dato/tid',
        'col_username'    => 'Brukernavn',
        'col_status'      => 'Status',
        'col_ip'          => 'IP-adresse',
        'col_user_agent'  => 'Nettleseragent',
        'col_from'        => 'Fra',
        'col_subject'     => 'Emne',
        'col_type'        => 'Type',
        'col_attachments' => 'Vedlegg',

        'status_success' => 'Vellykket',
        'status_failed'  => 'Mislyktes',
        'unknown'        => 'Ukjent',
        'no_subject'     => '(Uten emne)',
        'new_ticket'     => 'Ny sak',
        'reply'          => 'Svar',
        'none'           => 'Ingen',

        'row_title'  => 'Klikk for å se detaljer i JSON',

        'pagination' => 'Side {current} av {total} ({count} totalt)',
        'prev'       => 'Forrige',
        'next'       => 'Neste',

        'modal_title' => 'Loggdetaljer (JSON)',
        'close'       => 'Lukk',
    ],

    'tickets' => [
        'heading' => 'Saksoversikter',
        'coming_soon' => 'KPI-oversikter og rapportering for saksytelse, løsningstider og arbeidsmengde i teamet blir tilgjengelig her snart.',
    ],

    'intune' => [
        'heading'      => 'Intune-oversikt',
        'loading_meta' => 'Laster …',
        'refresh'      => 'Oppdater',
        'refresh_title'=> 'Oppdater data',
        'loading_data' => 'Laster Intune-data …',

        'last_sync'    => 'Siste synkronisering: {when}',
        'error'        => 'Feil: {error}',
        'load_failed'  => 'Klarte ikke å laste oversikten: {error}',
        'no_devices_title' => 'Ingen Intune-enheter funnet.',
        'no_devices_body'  => 'Kjør en Intune-synkronisering fra Ressurser-modulen for å importere enheter, og kom så tilbake hit.',
        'no_data'      => 'Ingen data',
        'unknown'      => 'Ukjent',

        // KPI strip
        'kpi_total'            => 'Enheter totalt',
        'kpi_total_sub'        => 'Alle administrerte enheter',
        'kpi_compliant'        => 'I samsvar',
        'kpi_compliant_sub'    => '{count} av {total}',
        'kpi_encrypted'        => 'Krypterte',
        'kpi_encrypted_sub'    => '{count} av {total}',
        'kpi_stale'            => 'Utdaterte (30+ dager)',
        'kpi_stale_sub'        => 'Ingen synkronisering siste 30 dager',
        'kpi_enrolled'         => 'Registrerte (30 dager)',
        'kpi_enrolled_sub'     => 'Nye siste 30 dager',

        'kpi_compliant_drill'  => 'Enheter i samsvar',
        'kpi_encrypted_drill'  => 'Krypterte enheter',
        'kpi_stale_drill'      => 'Utdaterte (30+ dager)',
        'kpi_enrolled_drill'   => 'Registrert siste 30 dager',

        // Widgets
        'w_compliance_title'   => 'Samsvarsfordeling',
        'w_compliance_desc'    => 'Enheter etter samsvarsstatus',
        'w_os_title'           => 'Operativsystem',
        'w_os_desc'            => 'Enheter gruppert etter OS',
        'w_owner_title'        => 'Eiertype',
        'w_owner_desc'         => 'Virksomhetseide mot private enheter',
        'w_manufacturers_title'=> 'Største produsenter',
        'w_manufacturers_desc' => 'Enheter etter produsent (topp 10)',
        'w_os_versions_title'  => 'Vanligste OS-versjoner',
        'w_os_versions_desc'   => 'De vanligste kombinasjonene av OS + versjon',
        'w_last_sync_title'    => 'Siste synkroniseringsvindu',
        'w_last_sync_desc'     => 'Hvor nylig enhetene sjekket inn',
        'w_enrolment_title'    => 'Registreringer (siste 90 dager)',
        'w_enrolment_desc'     => 'Nye enheter registrert per dag',
        'w_encryption_title'   => 'Kryptering etter OS',
        'w_encryption_desc'    => 'Kryptert mot ukryptert, per OS',

        // Chart tooltips / labels
        'tooltip_enrolled'     => '{count} registrert (klikk for detaljer)',
        'drill_enrolled_on'    => 'Registrert {date}',

        // Drill-down modal
        'drill_devices'        => 'Enheter',
        'drill_loading'        => 'Laster …',
        'drill_count'          => '{count} enhet',
        'drill_count_plural'   => '{count} enheter',
        'drill_no_match'       => 'Ingen enheter passer med dette filteret.',
        'drill_error'          => 'Feil: {error}',
        'drill_load_failed'    => 'Klarte ikke å laste: {error}',
        'drill_page_info'      => 'Side {current} av {total}',
        'drill_prev'           => '‹ Forrige',
        'drill_next'           => 'Neste ›',
        'drill_export'         => 'Eksporter CSV',
        'drill_close'          => 'Lukk',

        'drill_col_device'     => 'Enhet',
        'drill_col_user'       => 'Bruker',
        'drill_col_os'         => 'OS',
        'drill_col_compliance' => 'Samsvar',
        'drill_col_encrypted'  => 'Kryptert',
        'drill_col_last_sync'  => 'Siste synkronisering',

        'never'                => 'Aldri',
        'yes'                  => 'Ja',
        'no'                   => 'Nei',
    ],

    'help' => [
        'page_title' => 'Veiledning for rapportering',
        'guide'      => 'Veiledning',

        'hero_heading' => 'Veiledning for rapportering',
        'hero_sub'     => 'Gjør dataene fra brukerstøtten om til innsikt du kan handle på, med logger, analyser og oversikter.',

        'nav_overview'           => 'Oversikt',
        'nav_ticket_reports'     => 'Saksrapporter',
        'nav_system_logs'        => 'Systemlogger',
        'nav_understanding_data' => 'Forstå dataene',
        'nav_settings_filters'   => 'Innstillinger og filtre',
        'nav_tips'               => 'Raske tips',

        // Section 1: Overview
        's1_heading' => 'Oversikt',
        's1_intro'   => 'Rapporteringsmodulen samler alt som skjer i brukerstøtten på ett sted. Følg saksytelsen, overvåk systemaktivitet, gjennomgå innloggingsforsøk og revider e-postimporter — alt fra én modul laget for å hjelpe deg med å oppdage trender og ta beslutninger basert på data.',
        's1_card1_title' => 'Saksanalyse',
        's1_card1_body'  => 'Visualiser saksvolum, løsningstider, SLA-oppfyllelse og arbeidsmengde i teamet gjennom interaktive oversikter som oppdateres i sanntid.',
        's1_card2_title' => 'Systemlogger',
        's1_card2_body'  => 'Gå gjennom hvert innloggingsforsøk, hver e-postimport og hver systemhendelse i en søkbar og filtrerbar tabell med tidsstempler og statusmerker.',
        's1_card3_title' => 'Aktivitetssporing',
        's1_card3_body'  => 'Følg analytikernes aktivitet i hele plattformen — hvem som logger inn, hvilke saker det jobbes med, og hvor tiden brukes.',
        's1_card4_title' => 'Revisjonsspor',
        's1_card4_body'  => 'Hver handling registreres med hvem som gjorde den, når, og hva som ble endret. Avgjørende for etterlevelse, sikkerhetsgjennomganger og feilsøking.',

        // Section 2: Ticket reports
        's2_heading' => 'Saksrapporter',
        's2_intro'   => 'Saker-området i rapporteringen gir deg KPI-oversikter som viser klart hvordan brukerstøtten presterer. Oversiktene henter data direkte fra saksregistreringene og presenterer dem i diagrammer og sammendragskort.',
        's2_card1_title' => 'Saksvolum',
        's2_card1_body'  => 'Se hvor mange saker som opprettes, løses og fortsatt er åpne i en valgfri periode. Finn travle dager og sesongmønstre.',
        's2_card2_title' => 'SLA-oppfyllelse',
        's2_card2_body'  => 'Følg hvor stor andel av sakene som når målene for svar- og løsningstid. Bor deg ned etter prioritet eller kategori for å finne problemområdene.',
        's2_card3_title' => 'Løsningstider',
        's2_card3_body'  => 'Mål gjennomsnittlig og median tid for å løse saker. Sammenlign på tvers av team, kategorier eller prioritetsnivåer for å finne flaskehalser.',
        's2_card4_title' => 'Arbeidsmengde i teamet',
        's2_card4_body'  => 'Se hvordan sakene er fordelt mellom analytikerne. Finn ut hvem som er overbelastet, og hvem som har kapasitet til å ta mer.',
        's2_card5_title' => 'Kategorifordeling',
        's2_card5_body'  => 'Forstå hvilke typer problemer som skaper flest saker. Bruk det til å målrette opplæring, dokumentasjon eller forbedringer i selvbetjeningen.',
        's2_card6_title' => 'Trendanalyse',
        's2_card6_body'  => 'Se saksdata over uker, måneder eller kvartaler for å oppdage langsiktige trender og måle effekten av prosessforbedringer.',
        's2_tip'         => 'Saksoversiktene åpnes fra Saker-fanen i toppmenyen. Bruk datointervallfiltre for å sammenligne ulike perioder side om side.',

        // Section 3: System logs
        's3_heading' => 'Systemlogger',
        's3_intro'   => 'Logger-området fanger opp alt som skjer bak kulissene i FreeITSM-installasjonen din. Hvert innloggingsforsøk, hver e-postimport og hver systemhendelse registreres med tidsstempel og status, slik at du alltid har et fullstendig bilde av aktiviteten på plattformen.',
        's3_badge_login'  => 'INNLOGGING',
        's3_badge_email'  => 'E-POST',
        's3_badge_system' => 'SYSTEM',
        's3_badge_audit'  => 'REVISJON',
        's3_login_title'  => 'Innloggingsforsøk',
        's3_login_body'   => 'Hver vellykket og mislykket innlogging registreres med navnet på analytikeren, IP-adressen og tidsstempelet. Mislykkede forsøk merkes med rødt, slik at du raskt oppdager uautoriserte tilgangsforsøk eller utestengte brukere.',
        's3_email_title'  => 'E-postimporter',
        's3_email_body'   => 'Når systemet gjør innkommende e-post om til saker, logges hver import med avsenderadresse, emnefelt og om konverteringen lyktes. Mislykkede importer viser årsaken, slik at du kan undersøke returnerte eller feilformede meldinger.',
        's3_system_title' => 'Systemhendelser',
        's3_system_body'  => 'Bakgrunnsprosesser, planlagte oppgaver, konfigurasjonsendringer og API-aktivitet fanges alle opp her. Bruk disse loggene til å bekrefte at automatiske jobber kjører som de skal, og til å diagnostisere problemer.',
        's3_audit_title'  => 'Revisjonsoppføringer',
        's3_audit_body'   => 'Sporing av endringer på feltnivå i hele plattformen. Se nøyaktig hvem som endret hva, når, og hva den forrige verdien var. Uvurderlig for etterlevelseskrav og for å avklare uenigheter.',
        's3_step1_title' => 'Åpne Logger-fanen',
        's3_step1_body'  => 'klikk på Logger i toppmenyen for å åpne visningen av systemlogger.',
        's3_step2_title' => 'Bytt mellom loggtyper',
        's3_step2_body'  => 'bruk fanelinjen øverst til å filtrere på innloggingsforsøk, e-postimporter eller systemhendelser.',
        's3_step3_title' => 'Gå gjennom detaljene',
        's3_step3_body'  => 'hver rad viser et tidsstempel, et statusmerke (vellykket eller mislyktes) og kontekstdetaljer som IP-adresser, e-postemner eller hendelsesbeskrivelser.',
        's3_tip'         => 'Sjekk innloggingsloggene jevnlig for gjentatte mislykkede forsøk fra ukjente IP-adresser. Det kan tyde på råstyrkeangrep eller kompromitterte påloggingsdetaljer som må håndteres umiddelbart.',

        // Section 4: Understanding the data
        's4_heading' => 'Forstå dataene',
        's4_intro'   => 'Rådata blir først nyttige når du vet hva du skal se etter. Her er nøkkeltallene du bør følge med på, og hvordan du tolker dem for å skape reelle forbedringer i driften av brukerstøtten.',
        's4_metric1_title' => 'Tid til første svar',
        's4_metric1_body'  => 'Hvor lenge brukerne venter før en analytiker bekrefter saken deres. En stigende trend her kan bety at teamet er underbemannet, eller at sakene ikke rutes effektivt. Mål: under SLA-grensen din.',
        's4_metric2_title' => 'Løsningsgrad',
        's4_metric2_body'  => 'Andelen saker som løses i en gitt periode, sammenlignet med dem som opprettes. Kommer det inn flere saker enn det går ut, vokser etterslepet, og du bør undersøke årsaken.',
        's4_metric3_title' => 'Gjentatte henvendelser',
        's4_metric3_body'  => 'Saker som gjenåpnes, eller brukere som melder inn det samme problemet flere ganger. Mange gjentatte henvendelser tyder på at rotårsaken ikke blir håndtert, eller at løsningene ikke formidles tydelig nok.',
        's4_metric4_title' => 'Kategorier som skiller seg ut',
        's4_metric4_body'  => 'Hvilke kategorier som skaper flest saker over tid. En topp i én bestemt kategori kan varsle om et system som svikter, en dårlig programvareoppdatering eller et hull i brukeropplæringen som må tettes.',
        's4_combine'     => 'Bruk disse nøkkeltallene sammen, ikke hver for seg. En høy løsningsgrad kombinert med mange gjentatte henvendelser kan for eksempel bety at saker lukkes for raskt uten at det underliggende problemet løses.',
        's4_tip'         => 'Sett opp en ukentlig gjennomgang av nøkkeltallene sammen med teamet. Mønstre som er usynlige fra dag til dag, blir ofte tydelige når du ser dem uke for uke eller måned for måned.',

        // Section 5: Settings & filters
        's5_heading' => 'Innstillinger og filtre',
        's5_intro'   => 'Både loggvisningen og saksoversiktene støtter en rekke filtre som hjelper deg å snevre inn nøyaktig de dataene du trenger. God bruk av filtre gjør en vegg av data om til målrettet informasjon du kan handle på.',
        's5_step1_title' => 'Datointervaller',
        's5_step1_body'  => 'filtrer logger og rapporter til et bestemt tidsrom. Bruk ferdige intervaller (i dag, denne uken, denne måneden) eller angi egne start- og sluttdatoer for presis kontroll.',
        's5_step2_title' => 'Statusfiltre',
        's5_step2_body'  => 'i loggvisningen kan du filtrere på vellykket eller mislykket for raskt å isolere problemer. I saksrapportene kan du filtrere på åpen, løst eller lukket.',
        's5_step3_title' => 'Søk',
        's5_step3_body'  => 'bruk søkefeltet til å finne bestemte oppføringer med et søkeord. I loggene søkes det i analytikernavn, IP-adresser, e-postemner og hendelsesbeskrivelser.',
        's5_step4_title' => 'Tidsgruppering',
        's5_step4_body'  => 'i saksoversiktene kan du gruppere data etter dag, uke eller måned for å endre detaljnivået i diagrammene. Dagsvisning viser kortsiktige topper; månedsvisning avdekker langsiktige trender.',
        's5_step5_title' => 'Avdelingsfiltre',
        's5_step5_body'  => 'begrens resultatene i oversikten til én avdeling for å sammenligne ytelsen mellom ulike deler av organisasjonen.',
        's5_tip'         => 'Kombiner flere filtre for målrettet analyse. Filtrer for eksempel på en bestemt avdeling og et datointervall for å se hvordan en nylig prosessendring påvirket saksvolumet til det teamet.',

        // Section 6: Quick tips
        's6_heading' => 'Raske tips',
        's6_tip1_title' => 'Gå gjennom jevnlig',
        's6_tip1_body'  => 'Rapporter er mest verdt når de gjennomgås regelmessig. Bestem en rytme — ukentlig for driftstall, månedlig for trendanalyse — og hold deg til den.',
        's6_tip2_title' => 'Undersøk avvik',
        's6_tip2_body'  => 'En plutselig topp eller et brått fall i et nøkkeltall er et signal verdt å undersøke. Sjekk loggene for sammenheng — var det et driftsavbrudd, en programvareutrulling eller en bemanningsendring?',
        's6_tip3_title' => 'Sammenlign perioder',
        's6_tip3_body'  => 'Bruk datofiltre til å sammenligne denne uken med forrige uke, eller denne måneden med samme måned i fjor. Relative sammenligninger viser forbedring eller tilbakegang tydeligere enn rene tall.',
        's6_tip4_title' => 'Overvåk sikkerheten',
        's6_tip4_body'  => 'Hold øye med mislykkede innloggingsforsøk i systemloggene. Gjentatte feil fra samme IP-adresse eller mot samme konto kan tyde på et sikkerhetsproblem som må eskaleres.',
    ],
];
