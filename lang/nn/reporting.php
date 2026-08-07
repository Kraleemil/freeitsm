<?php
/**
 * Norsk nynorsk (nn) — tekstar for rapportmodulen.
 *
 * Manglande nøklar fell tilbake på verdien i lang/en/reporting.php,
 * nøkkel for nøkkel (sjå includes/i18n.php).
 *
 * Dekkjer landingssida, visaren for systemlogg, plasshaldaren for
 * saksdashboard, Intune-dashbordet (KPI-stripa, modulane, detaljvindauget)
 * og rettleiinga. Berre ramma er omsett — loggrader, sakstitlar og
 * einingsdata frå Intune står som dei er (det er data, ikkje grensesnitt).
 */
return [
    'title' => 'Rapportar',

    'nav' => [
        'logs'    => 'Loggar',
        'tickets' => 'Saker',
        'intune'  => 'Intune',
        'help'    => 'Hjelp',

        'logs_title'    => 'Systemloggar',
        'tickets_title' => 'Saksdashbord',
        'intune_title'  => 'Intune-dashbord',
        'help_title'    => 'Hjelp',
    ],

    'landing' => [
        'heading'  => 'Rapportar',
        'subtitle' => 'Vel eit rapportområde for å kome i gang',

        'logs_title'    => 'Systemloggar',
        'logs_desc'     => 'Sjå innloggingsforsøk, e-postimportar og andre loggar over systemaktivitet.',
        'tickets_title' => 'Saksdashbord',
        'tickets_desc'  => 'KPI-dashbord for sakshandsaming, løysingstider og arbeidsmengd i teamet.',
        'intune_title'  => 'Intune-dashbord',
        'intune_desc'   => 'Samsvar, kryptering, OS-fordeling, registreringstrend og tilstanden på siste synkronisering for kvar administrerte eining.',
    ],

    'logs' => [
        'heading'  => 'Systemloggar',
        'refresh'  => 'Oppdater',
        'tab_login'        => 'Brukarinnloggingar',
        'tab_email_import' => 'E-postimportar',

        'loading'        => 'Lastar loggar...',
        'no_logs'        => 'Fann ingen loggar',
        'load_error'     => 'Feil ved lasting av loggar: {error}',

        'col_datetime'    => 'Dato/tid',
        'col_username'    => 'Brukarnamn',
        'col_status'      => 'Status',
        'col_ip'          => 'IP-adresse',
        'col_user_agent'  => 'Brukaragent',
        'col_from'        => 'Frå',
        'col_subject'     => 'Emne',
        'col_type'        => 'Type',
        'col_attachments' => 'Vedlegg',

        'status_success' => 'Vellukka',
        'status_failed'  => 'Mislukka',
        'unknown'        => 'Ukjend',
        'no_subject'     => '(Utan emne)',
        'new_ticket'     => 'Ny sak',
        'reply'          => 'Svar',
        'none'           => 'Ingen',

        'row_title'  => 'Klikk for å sjå JSON-detaljar',

        'pagination' => 'Side {current} av {total} ({count} totalt)',
        'prev'       => 'Førre',
        'next'       => 'Neste',

        'modal_title' => 'Loggdetaljar (JSON)',
        'close'       => 'Lukk',
    ],

    'tickets' => [
        'heading' => 'Saksdashbord',
        'coming_soon' => 'KPI-dashbord og rapportar for sakshandsaming, løysingstider og arbeidsmengd i teamet kjem her snart.',
    ],

    'intune' => [
        'heading'      => 'Intune-dashbord',
        'loading_meta' => 'Lastar…',
        'refresh'      => 'Oppdater',
        'refresh_title'=> 'Oppdater data',
        'loading_data' => 'Lastar Intune-data…',

        'last_sync'    => 'Siste synkronisering: {when}',
        'error'        => 'Feil: {error}',
        'load_failed'  => 'Klarte ikkje å laste dashbordet: {error}',
        'no_devices_title' => 'Fann ingen Intune-einingar.',
        'no_devices_body'  => 'Køyr ei Intune-synkronisering frå utstyrsmodulen for å importere einingar, og kom så tilbake hit.',
        'no_data'      => 'Ingen data',
        'unknown'      => 'Ukjend',

        // KPI-stripa
        'kpi_total'            => 'Einingar totalt',
        'kpi_total_sub'        => 'Alle administrerte einingar',
        'kpi_compliant'        => 'I samsvar',
        'kpi_compliant_sub'    => '{count} av {total}',
        'kpi_encrypted'        => 'Krypterte',
        'kpi_encrypted_sub'    => '{count} av {total}',
        'kpi_stale'            => 'Forelda (30+ dagar)',
        'kpi_stale_sub'        => 'Inga synkronisering dei siste 30 dagane',
        'kpi_enrolled'         => 'Registrerte (30 dagar)',
        'kpi_enrolled_sub'     => 'Nye dei siste 30 dagane',

        'kpi_compliant_drill'  => 'Einingar i samsvar',
        'kpi_encrypted_drill'  => 'Krypterte einingar',
        'kpi_stale_drill'      => 'Forelda (30+ dagar)',
        'kpi_enrolled_drill'   => 'Registrerte dei siste 30 dagane',

        // Modular
        'w_compliance_title'   => 'Samsvar fordelt',
        'w_compliance_desc'    => 'Einingar etter samsvarsstatus',
        'w_os_title'           => 'Operativsystem',
        'w_os_desc'            => 'Einingar grupperte etter OS',
        'w_owner_title'        => 'Eigartype',
        'w_owner_desc'         => 'Verksemdseigde mot private einingar',
        'w_manufacturers_title'=> 'Dei største produsentane',
        'w_manufacturers_desc' => 'Einingar etter produsent (topp 10)',
        'w_os_versions_title'  => 'Dei vanlegaste OS-versjonane',
        'w_os_versions_desc'   => 'Dei vanlegaste kombinasjonane av OS + versjon',
        'w_last_sync_title'    => 'Vindauge for siste synkronisering',
        'w_last_sync_desc'     => 'Kor nyleg einingane melde seg inn',
        'w_enrolment_title'    => 'Registreringar (siste 90 dagar)',
        'w_enrolment_desc'     => 'Nye einingar registrerte per dag',
        'w_encryption_title'   => 'Kryptering etter OS',
        'w_encryption_desc'    => 'Kryptert mot ukryptert, per OS',

        // Hjelpetekstar / etikettar i diagramma
        'tooltip_enrolled'     => '{count} registrerte (klikk for detaljar)',
        'drill_enrolled_on'    => 'Registrerte {date}',

        // Detaljvindauget
        'drill_devices'        => 'Einingar',
        'drill_loading'        => 'Lastar…',
        'drill_count'          => '{count} eining',
        'drill_count_plural'   => '{count} einingar',
        'drill_no_match'       => 'Ingen einingar passar med dette filteret.',
        'drill_error'          => 'Feil: {error}',
        'drill_load_failed'    => 'Klarte ikkje å laste: {error}',
        'drill_page_info'      => 'Side {current} av {total}',
        'drill_prev'           => '‹ Førre',
        'drill_next'           => 'Neste ›',
        'drill_export'         => 'Eksporter CSV',
        'drill_close'          => 'Lukk',

        'drill_col_device'     => 'Eining',
        'drill_col_user'       => 'Brukar',
        'drill_col_os'         => 'OS',
        'drill_col_compliance' => 'Samsvar',
        'drill_col_encrypted'  => 'Kryptert',
        'drill_col_last_sync'  => 'Siste synkronisering',

        'never'                => 'Aldri',
        'yes'                  => 'Ja',
        'no'                   => 'Nei',
    ],

    'help' => [
        'page_title' => 'Rettleiing for rapportar',
        'guide'      => 'Rettleiing',

        'hero_heading' => 'Rettleiing for rapportar',
        'hero_sub'     => 'Gjer data frå brukarstøtta om til innsikt du kan handle på, med loggar, analysar og dashbord.',

        'nav_overview'           => 'Oversikt',
        'nav_ticket_reports'     => 'Saksrapportar',
        'nav_system_logs'        => 'Systemloggar',
        'nav_understanding_data' => 'Forstå dataa',
        'nav_settings_filters'   => 'Innstillingar og filter',
        'nav_tips'               => 'Snøggtips',

        // Del 1: Oversikt
        's1_heading' => 'Oversikt',
        's1_intro'   => 'Rapportmodulen samlar alt som skjer i brukarstøtta på éin stad. Følg med på sakshandsaminga, overvak systemaktiviteten, gå gjennom innloggingsforsøk og revider e-postimportar — alt frå éin modul som er laga for å hjelpe deg å sjå mønster og ta avgjerder bygde på data.',
        's1_card1_title' => 'Saksanalyse',
        's1_card1_body'  => 'Visualiser saksmengd, løysingstider, SLA-samsvar og arbeidsmengd i teamet gjennom interaktive dashbord som blir oppdaterte i sanntid.',
        's1_card2_title' => 'Systemloggar',
        's1_card2_body'  => 'Gå gjennom kvart innloggingsforsøk, kvar e-postimport og kvar systemhending i ein søkbar og filtrerbar tabell med tidsstempel og statusmerke.',
        's1_card3_title' => 'Aktivitetssporing',
        's1_card3_body'  => 'Overvak kva analytikarane gjer i plattforma — kven som loggar inn, kva saker det blir arbeidd med, og kvar tida går.',
        's1_card4_title' => 'Revisjonsspor',
        's1_card4_body'  => 'Kvar handling blir registrert med kven som gjorde henne, når, og kva som vart endra. Heilt naudsynt for samsvar, tryggleiksgjennomgangar og feilsøking.',

        // Del 2: Saksrapportar
        's2_heading' => 'Saksrapportar',
        's2_intro'   => 'Saksområdet i rapportane gjev deg KPI-dashbord som viser klart korleis brukarstøtta gjer det. Dashborda hentar data rett frå sakene dine og viser dei fram som diagram og samandragskort.',
        's2_card1_title' => 'Saksmengd',
        's2_card1_body'  => 'Sjå kor mange saker som blir oppretta, løyste og framleis står opne i kva periode som helst. Finn dei travle dagane og sesongmønstera.',
        's2_card2_title' => 'SLA-samsvar',
        's2_card2_body'  => 'Følg med på kor stor del av sakene som når måla for svar og løysing. Bor deg ned på prioritet eller kategori for å finne problemområda.',
        's2_card3_title' => 'Løysingstider',
        's2_card3_body'  => 'Mål gjennomsnittleg og median tid for å løyse saker. Samanlikn team, kategoriar eller prioritetsnivå for å finne flaskehalsane.',
        's2_card4_title' => 'Arbeidsmengd i teamet',
        's2_card4_body'  => 'Sjå korleis sakene er fordelte mellom analytikarane. Finn ut kven som har for mykje, og kven som har kapasitet til å ta meir.',
        's2_card5_title' => 'Fordeling på kategori',
        's2_card5_body'  => 'Forstå kva slags problem som skaper flest saker. Bruk det til å målrette opplæring, dokumentasjon eller betre sjølvbetening.',
        's2_card6_title' => 'Trendanalyse',
        's2_card6_body'  => 'Sjå saksdata over veker, månader eller kvartal for å finne trendar på lang sikt og måle verknaden av prosessforbetringar.',
        's2_tip'         => 'Du kjem til saksdashborda via fana Saker i toppmenyen. Bruk datofilter for å samanlikne ulike periodar side om side.',

        // Del 3: Systemloggar
        's3_heading' => 'Systemloggar',
        's3_intro'   => 'Loggområdet fangar opp alt som skjer bak kulissane i FreeITSM-installasjonen din. Kvart innloggingsforsøk, kvar e-postimport og kvar systemhending blir registrert med tidsstempel og status, slik at du alltid har eit fullstendig bilete av aktiviteten i plattforma.',
        's3_badge_login'  => 'INNLOGGING',
        's3_badge_email'  => 'E-POST',
        's3_badge_system' => 'SYSTEM',
        's3_badge_audit'  => 'REVISJON',
        's3_login_title'  => 'Innloggingsforsøk',
        's3_login_body'   => 'Kvar vellukka og mislukka innlogging blir registrert med namnet på analytikaren, IP-adressa og tidsstempelet. Mislukka forsøk er merkte med raudt, slik at du raskt ser uautoriserte tilgangsforsøk eller brukarar som er stengde ute.',
        's3_email_title'  => 'E-postimportar',
        's3_email_body'   => 'Når systemet gjer innkomande e-post om til saker, blir kvar import logga med avsendaradressa, emnelinja og om ho vart konvertert. Mislukka importar viser årsaka, slik at du kan undersøkje meldingar som kom i retur eller er feilforma.',
        's3_system_title' => 'Systemhendingar',
        's3_system_body'  => 'Bakgrunnsprosessar, planlagde oppgåver, endringar i oppsettet og API-aktivitet blir alt fanga opp her. Bruk desse loggane til å stadfeste at automatiske jobbar går som dei skal, og til å finne feil.',
        's3_audit_title'  => 'Revisjonsoppføringar',
        's3_audit_body'   => 'Sporing av endringar heilt ned på feltnivå i heile plattforma. Sjå nøyaktig kven som endra kva, når, og kva den førre verdien var. Uvurderleg for samsvarskrav og for å avklare usemje.',
        's3_step1_title' => 'Opne fana Loggar',
        's3_step1_body'  => 'klikk Loggar i toppmenyen for å opne visaren for systemlogg.',
        's3_step2_title' => 'Byt mellom loggtypar',
        's3_step2_body'  => 'bruk fanelinja øvst til å filtrere på innloggingsforsøk, e-postimportar eller systemhendingar.',
        's3_step3_title' => 'Gå gjennom detaljane',
        's3_step3_body'  => 'kvar rad viser eit tidsstempel, eit statusmerke (vellukka eller mislukka) og detaljar frå samanhengen, som IP-adresser, e-postemne eller skildringar av hendingar.',
        's3_tip'         => 'Sjekk innloggingsloggane jamleg for gjentekne mislukka forsøk frå ukjende IP-adresser. Det kan tyde på brute force-åtak eller kompromitterte påloggingsopplysningar som må handterast med ein gong.',

        // Del 4: Forstå dataa
        's4_heading' => 'Forstå dataa',
        's4_intro'   => 'Rådata blir først nyttige når du veit kva du skal sjå etter. Her er dei viktigaste måltala du bør følgje med på, og korleis du tolkar dei for å få reelle forbetringar i drifta av brukarstøtta.',
        's4_metric1_title' => 'Tid til første svar',
        's4_metric1_body'  => 'Kor lenge brukarane ventar før ein analytikar tek tak i saka deira. Ein stigande trend her tyder på at teamet er for lite bemanna, eller at sakene ikkje blir rutede godt nok. Mål: under SLA-grensa di.',
        's4_metric2_title' => 'Løysingsgrad',
        's4_metric2_body'  => 'Kor stor del av sakene som blir løyste i ein gitt periode, samanlikna med kor mange som blir oppretta. Kjem det inn fleire saker enn det går ut, veks etterslepet, og du må finne årsaka.',
        's4_metric3_title' => 'Gjentekne kontaktar',
        's4_metric3_body'  => 'Saker som blir opna att, eller brukarar som melder same problem fleire gonger. Høg del gjentekne kontaktar tyder på at rotårsaka ikkje blir teken tak i, eller at løysingane ikkje blir formidla tydeleg nok.',
        's4_metric4_title' => 'Kategoriar som skil seg ut',
        's4_metric4_body'  => 'Kva kategoriar som skaper flest saker over tid. Ein topp i éin kategori kan varsle om eit system som sviktar, ei dårleg programvareoppdatering eller eit hol i brukaropplæringa som må tettast.',
        's4_combine'     => 'Bruk måltala saman, ikkje kvar for seg. Til dømes kan høg løysingsgrad kombinert med mange gjentekne kontaktar tyde på at sakene blir lukka for raskt, utan at det underliggjande problemet er løyst.',
        's4_tip'         => 'Set av tid kvar veke til å gå gjennom dei viktigaste måltala saman med teamet. Mønster som er usynlege frå dag til dag, blir ofte tydelege veke for veke eller månad for månad.',

        // Del 5: Innstillingar og filter
        's5_heading' => 'Innstillingar og filter',
        's5_intro'   => 'Både loggvisaren og saksdashborda har ei rekkje filter som hjelper deg å snevre inn nøyaktig dei dataa du treng. Brukar du filtera godt, blir ein vegg av data til målretta informasjon du kan handle på.',
        's5_step1_title' => 'Datoperiodar',
        's5_step1_body'  => 'filtrer loggar og rapportar til eit bestemt tidsvindauge. Bruk ferdige periodar (i dag, denne veka, denne månaden) eller vel eigne frå- og til-datoar for full kontroll.',
        's5_step2_title' => 'Statusfilter',
        's5_step2_body'  => 'i loggvisaren kan du filtrere på vellukka eller mislukka for raskt å skilje ut problema. I saksrapportane kan du filtrere på open, løyst eller lukka.',
        's5_step3_title' => 'Søk',
        's5_step3_body'  => 'bruk søkjefeltet til å finne bestemte oppføringar med eit nøkkelord. I loggane søkjer det i analytikarnamn, IP-adresser, e-postemne og skildringar av hendingar.',
        's5_step4_title' => 'Gruppering på tid',
        's5_step4_body'  => 'i saksdashborda kan du gruppere data på dag, veke eller månad for å endre detaljnivået i diagramma. Dagsvisning viser kortvarige toppar; månadsvisning avdekkjer trendar på lang sikt.',
        's5_step5_title' => 'Avdelingsfilter',
        's5_step5_body'  => 'avgrens resultata i dashbordet til éi avdeling for å samanlikne korleis ulike delar av organisasjonen gjer det.',
        's5_tip'         => 'Kombiner fleire filter for målretta analyse. Til dømes kan du filtrere på éi avdeling og éin periode for å sjå korleis ei nyleg prosessendring verka inn på saksmengda til det teamet.',

        // Del 6: Snøggtips
        's6_heading' => 'Snøggtips',
        's6_tip1_title' => 'Sjå på dei jamleg',
        's6_tip1_body'  => 'Rapportar er mest verdt når du går gjennom dei jamt. Vel ein takt — kvar veke for driftstal, kvar månad for trendanalyse — og hald deg til han.',
        's6_tip2_title' => 'Undersøk avvik',
        's6_tip2_body'  => 'Ein brå topp eller eit brått fall i eit måltal er eit signal det er verdt å undersøkje. Sjå i loggane etter samanhengen — var det eit driftsavbrot, ei programvareutrulling eller ei endring i bemanninga?',
        's6_tip3_title' => 'Samanlikn periodar',
        's6_tip3_body'  => 'Bruk datofilter til å samanlikne denne veka med førre veke, eller denne månaden med same månad i fjor. Slike samanlikningar viser framgang eller tilbakegang tydelegare enn reine tal.',
        's6_tip4_title' => 'Følg med på tryggleiken',
        's6_tip4_body'  => 'Hald auge med mislukka innloggingsforsøk i systemloggane. Gjentekne feil frå same IP-adresse eller mot same konto kan tyde på eit tryggleiksproblem som må eskalerast.',
    ],
];
