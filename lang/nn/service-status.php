<?php
/**
 * Norsk nynorsk (nn) — tekstar for modulen Tenestestatus.
 *
 * Manglande nøklar fell tilbake til verdien i lang/en/service-status.php,
 * nøkkel for nøkkel (sjå includes/i18n.php).
 *
 * Dekkjer statusdashbordet, hendingsdialogen, innstillingssida (fanene for
 * tenester, statusar og påverknadsnivå saman med den delte oppslagsdialogen),
 * navigasjonen i modultoppteksten og hjelpeguiden.
 *
 * MERK: Tenestenamn, hendingstitlar/kommentarar og dei NAMNA på statusar og
 * påverknadsnivå som er lagra i databasen, er brukardata og blir IKKJE omsette
 * her — berre tekstar appen sjølv definerer, ligg i denne fila.
 */
return [
    'title' => 'Tenestestatus',

    'nav' => [
        'status'   => 'Status',
        'settings' => 'Innstillingar',
        'help'     => 'Hjelp',
    ],

    'board' => [
        'services'        => 'Tenester',
        'service_count'   => '{count} tenester',
        'loading'         => 'Lastar...',
        'no_services'     => 'Ingen tenester er sette opp. Gå til Innstillingar for å leggje til tenester.',
        'incidents'       => 'Hendingar',
        'new'             => 'Ny',
        'col_title'       => 'Tittel',
        'col_status'      => 'Status',
        'col_affected'    => 'Påverka tenester',
        'col_updated'     => 'Oppdatert',
        'no_incidents'    => 'Ingen hendingar å vise.',
        'none'            => 'Ingen',
    ],

    'modal' => [
        'new_incident'        => 'Ny hending',
        'edit_incident'       => 'Rediger hending',
        'title'               => 'Tittel',
        'title_placeholder'   => 'Kort skildring av hendinga',
        'status'              => 'Status',
        'comment'             => 'Kommentar',
        'comment_placeholder' => 'Detaljar om hendinga...',
        'affected_services'   => 'Påverka tenester',
        'add_service'         => '+ Legg til teneste',
        'delete'              => 'Slett',
        'cancel'              => 'Avbryt',
        'save'                => 'Lagre',
    ],

    'toast' => [
        'incident_saved'   => 'Hendinga er lagra',
        'incident_deleted' => 'Hendinga er sletta',
        'save_failed'      => 'Lagringa mislukkast',
        'delete_failed'    => 'Slettinga mislukkast',
        'save_incident_failed'   => 'Klarte ikkje å lagre hendinga',
        'delete_incident_failed' => 'Klarte ikkje å slette hendinga',
        'saved'            => 'Lagra',
        'deleted'          => 'Sletta',
        'save_service_failed'    => 'Klarte ikkje å lagre tenesta',
        'delete_service_failed'  => 'Klarte ikkje å slette tenesta',
    ],

    'confirm' => [
        'delete_incident_title'   => 'Slett hending',
        'delete_incident_message' => 'Vil du slette denne hendinga?',
        'delete_title'            => 'Slett',
        'delete_message'          => 'Vil du slette "{name}"?',
        'delete_label'            => 'Slett',
    ],

    'settings' => [
        'tab_services'     => 'Tenester',
        'tab_statuses'     => 'Statusar',
        'tab_impacts'      => 'Påverknadsnivå',

        'services_heading' => 'Tenester',
        'statuses_heading' => 'Hendingsstatusar',
        'impacts_heading'  => 'Påverknadsnivå',
        'add'              => 'Legg til',
        'loading'          => 'Lastar...',
        'no_services'      => 'Ingen tenester enno. Klikk Legg til for å opprette ei.',
        'no_items'         => 'Fann ingen element',
        'load_failed'      => 'Klarte ikkje å laste data',
        'error_prefix'     => 'Feil: {message}',

        'statuses_intro_html' => 'Arbeidsflyttilstandar for tenestehendingar. Statusar som er merkte som <em>løyst</em>, lukkar hendinga — <code>resolved_datetime</code> blir sett automatisk, og hendinga forsvinn frå det aktive dashbordet. Nøyaktig éin status er standard for nye hendingar.',
        'impacts_intro_html'  => 'Alvorsnivå som blir viste som merket på kvart tenestekort. <strong>Alvorsrekkjefølgja</strong> styrer sorteringa etter "verste gjeldande påverknad" på dashbordet — lågare = verre (1 = større driftsstans, 5 = i drift). To rader kan dele same rekkjefølgje.',

        'col_name'        => 'Namn',
        'col_description' => 'Skildring',
        'col_order'       => 'Rekkjefølgje',
        'col_status'      => 'Status',
        'col_actions'     => 'Handlingar',
        'col_colour'      => 'Farge',
        'col_resolved'    => 'Løyst',
        'col_default'     => 'Standard',
        'col_severity'    => 'Alvorsgrad',

        'active'          => 'Aktiv',
        'inactive'        => 'Inaktiv',
        'yes'             => 'Ja',
        'no'              => 'Nei',
        'edit'            => 'Rediger',
        'delete'          => 'Slett',

        'kind_status'     => 'status',
        'kind_impact'     => 'påverknadsnivå',

        // Service modal
        'add_service'     => 'Legg til teneste',
        'edit_service'    => 'Rediger teneste',
        'field_name'      => 'Namn',
        'field_description' => 'Skildring',
        'field_order'     => 'Visingsrekkjefølgje',
        'field_active'    => 'Aktiv',

        // Lookup modal (statuses + impact levels)
        'add_item'        => 'Legg til element',
        'add_kind'        => 'Legg til {kind}',
        'edit_kind'       => 'Rediger {kind}',
        'field_colour'    => 'Farge',
        'field_resolved'  => 'Tel som løyst',
        'resolved_help_html' => 'Hendingar med denne statusen får <code>resolved_datetime</code> sett automatisk og forsvinn frå det aktive dashbordet.',
        'field_severity'  => 'Alvorsrekkjefølgje',
        'severity_help'   => '1 = verst (større driftsstans). Høgare = mindre alvorleg.',
        'field_default'   => 'Standard',

        'cancel'          => 'Avbryt',
        'save'            => 'Lagre',
    ],

    'help' => [
        'page_title' => 'Guide for tenestestatus',
        'guide'      => 'Guide',

        'nav_overview'  => 'Oversikt',
        'nav_dashboard' => 'Statusdashbordet',
        'nav_services'  => 'Handtere tenester',
        'nav_history'   => 'Hendingshistorikk',
        'nav_settings'  => 'Innstillingar',
        'nav_tips'      => 'Raske tips',

        'hero_title' => 'Guide for tenestestatus',
        'hero_sub'   => 'Overvak IT-tenestene dine, kommuniser hendingar og hald interessentane oppdaterte i sanntid.',

        // Section 1: Overview
        'overview_heading' => 'Oversikt',
        'overview_intro'   => 'Modulen Tenestestatus gjev deg ei sentral oversikt over helsa til kvar einaste IT-teneste organisasjonen din er avhengig av. Når noko går gale, kan du registrere hendingar, oppdatere påverka tenester og halde brukarane informerte gjennom heile løysingsprosessen.',
        'feature_dashboard_title' => 'Statusdashbord',
        'feature_dashboard_desc'  => 'Sjå den gjeldande helsa til kvar teneste med eitt blikk. Fargekoda merke viser om ei teneste er i drift, har redusert yting, er under vedlikehald eller har driftsstans.',
        'feature_incident_title'  => 'Hendingsoppfølging',
        'feature_incident_desc'   => 'Registrer hendingar med tittel, statusoppdateringar og kommentarar. Knyt påverka tenester til kvar hending, slik at alle veit nøyaktig kva som er råka og kvifor.',
        'feature_management_title' => 'Tenestehandtering',
        'feature_management_desc'  => 'Set opp tenestekatalogen din i innstillingane. Legg til tenester med namn, skildring og visingsrekkjefølgje. Aktiver eller deaktiver tenester etter kvart som infrastrukturen din endrar seg.',
        'feature_comms_title' => 'Kommunikasjon',
        'feature_comms_desc'  => 'Hald interessentane oppdaterte med statusoppdateringar i sanntid. Kvar hending har ein status og ei rekkje kommentarar, slik at brukarane kan følgje framgangen utan å måtte mase på brukarstøtta.',

        // Section 2: Dashboard
        'dashboard_heading' => 'Statusdashbordet',
        'dashboard_p1'      => 'Dashbordet er det fyrste du ser når du opnar modulen Tenestestatus. Det viser eit rutenett med tenestekort, der kvart kort har tenestenamnet, ei kort skildring og eit fargekoda påverknadsmerke som speglar den verste gjeldande statusen. Under rutenettet ligg hendingstabellen med alle nylege og aktive hendingar.',
        'dashboard_p2_html' => 'Kvart tenestekort speglar automatisk det mest alvorlege påverknadsnivået som er tildelt frå ei aktiv (uløyst) hending. Når alle hendingar som påverkar ei teneste er løyste, går tenesta tilbake til <strong>I drift</strong>.',
        'status_levels'     => 'Statusnivå',
        'level_operational_name' => 'I drift',
        'level_operational_desc' => 'Tenesta køyrer normalt utan kjende problem. Dette er standardtilstanden for alle friske tenester.',
        'level_degraded_name'    => 'Redusert yting',
        'level_degraded_desc'    => 'Tenesta er tilgjengeleg, men går tregare enn venta eller har redusert funksjonalitet. Brukarane kan merke forseinkingar.',
        'level_maintenance_name' => 'Under vedlikehald',
        'level_maintenance_desc' => 'Planlagd nedetid eller vedlikehaldsvindauge. Tenesta kan vere mellombels utilgjengeleg medan arbeidet blir gjort.',
        'level_outage_name'      => 'Større driftsstans',
        'level_outage_desc'      => 'Tenesta er heilt utilgjengeleg. Dette er den mest alvorlege statusen, og han bør utløyse gransking med det same.',
        'dashboard_tip'     => 'Påverknadsnivåa er hierarkiske. Er ei teneste knytt til fleire aktive hendingar, viser dashbordet den verste påverknaden. Til dømes: éi hending merkjer tenesta som Redusert yting og ei anna som Større driftsstans — då blir Større driftsstans vist.',

        // Section 3: Managing services
        'services_heading_html' => 'Handtere tenester &amp; registrere hendingar',
        'services_intro'        => 'Tenestene er byggjeklossane i statussida di. Kvar av dei representerer ei IT-teneste, eit system eller ein infrastrukturkomponent som brukarane dine er avhengige av. Når noko går gale, opprettar du ei hending og knyter henne til dei påverka tenestene.',
        'add_incident_heading'  => 'Leggje til ei ny hending',
        'add_incident_step1_html' => '<strong>Klikk "Ny"</strong> på dashbordet for å opne hendingsskjemaet.',
        'add_incident_step2_html' => '<strong>Skriv inn ein tittel</strong> &mdash; ei kort og tydeleg skildring av problemet. Til dømes: "Forseinka e-postlevering" eller "VPN-gateway er ikkje tilgjengeleg".',
        'add_incident_step3_html' => '<strong>Vel status</strong> &mdash; vel Undersøkjer, Identifisert, Tredjepart, Overvakar eller Løyst. Start med Undersøkjer og oppdater etter kvart som du veit meir.',
        'add_incident_step4_html' => '<strong>Legg til ein kommentar</strong> &mdash; skildre kva som er kjent så langt, kva tiltak som blir sette i verk, og eventuelle mellombelse løysingar brukarane kan nytte.',
        'add_incident_step5_html' => '<strong>Knyt til påverka tenester</strong> &mdash; legg til ei eller fleire tenester og vel påverknadsnivå for kvar av dei (Større driftsstans, Delvis driftsstans, Redusert yting, Vedlikehald, I drift eller Inga forstyrring).',
        'add_incident_step6_html' => '<strong>Lagre</strong> &mdash; hendinga dukkar opp i tabellen, og korta for dei påverka tenestene blir oppdaterte med ein gong på dashbordet.',
        'workflow_heading'  => 'Statusflyt for hendingar',
        'workflow_investigating' => 'Undersøkjer',
        'workflow_identified'    => 'Identifisert',
        'workflow_monitoring'    => 'Overvakar',
        'workflow_resolved'      => 'Løyst',
        'workflow_note_html'     => 'Bruk <strong>Tredjepart</strong> når rotårsaka ligg hjå ein ekstern leverandør.',
        'services_tip'      => 'Du kan redigere kva hending som helst ved å klikke på tittelen i tabellen. Oppdater statusen, legg til nye kommentarar eller endre påverka tenester etter kvart som situasjonen utviklar seg. Å halde hendingane oppdaterte er nøkkelen til open kommunikasjon.',

        // Section 4: Incident history
        'history_heading' => 'Hendingshistorikk',
        'history_p1'      => 'Hendingstabellen på dashbordet viser både aktive og løyste hendingar, og gjev deg ei fullstendig tidslinje over tenestehelsa. Kvar rad viser hendingstittelen, gjeldande status, påverka tenester med påverknadsnivåa sine og tidspunktet for siste endring.',
        'history_field_title_html'    => '<strong>Tittel</strong> &mdash; ei klikkbar lenkje som opnar hendinga for redigering. Bruk tydelege, skildrande titlar, så er historikken lett å skumme gjennom.',
        'history_field_status_html'   => '<strong>Status</strong> &mdash; fargekoda merke som viser kva fase granskinga er i (Undersøkjer, Identifisert, Tredjepart, Overvakar eller Løyst).',
        'history_field_affected_html' => '<strong>Påverka tenester</strong> &mdash; merke som viser kvar knytt teneste med fargen til påverknadsnivået sitt. Med eitt blikk ser du kva som er råka, og kor alvorleg det er.',
        'history_field_updated_html'  => '<strong>Oppdatert</strong> &mdash; tidspunktet for den siste endringa. Løyste hendingar får dempa tekst, slik at aktive hendingar skil seg ut visuelt.',
        'history_p2'      => 'Løyste hendingar blir verande synlege i tabellen som eit historisk arkiv. Det gjer det enkelt å oppdage gjentakande problem, sjå korleis tidlegare hendingar vart handterte, og finne mønster som kan peike mot underliggjande problem.',
        'history_tip'     => 'Å gå gjennom hendingshistorikken jamleg hjelper deg å finne tenester som ofte blir forstyrra. Dukkar den same tenesta opp i fleire hendingar, kan det vere på tide å granske rotårsaka grundigare eller planleggje ei oppgradering av infrastrukturen.',

        // Section 5: Settings
        'settings_heading' => 'Innstillingar',
        'settings_p1'      => 'På innstillingssida byggjer og vedlikeheld du tenestekatalogen din. Kvar teneste som skal vise att på statusdashbordet, må setjast opp her fyrst.',
        'settings_step1_html' => '<strong>Legg til ei teneste</strong> &mdash; klikk "Legg til" og oppgje eit namn (t.d. "E-post", "VPN", "ERP-system") og ei valfri skildring av kva tenesta gjer.',
        'settings_step2_html' => '<strong>Set visingsrekkjefølgja</strong> &mdash; rekkjefølgjenummeret styrer kvar tenesta hamnar i rutenettet på dashbordet. Lågare tal kjem fyrst, så legg dei mest kritiske tenestene øvst.',
        'settings_step3_html' => '<strong>Slå aktiv/inaktiv av og på</strong> &mdash; å deaktivere ei teneste fjernar henne frå dashbordet utan å slette tenesta. Det er nyttig for utfasa tenester eller sesongbaserte system.',
        'settings_step4_html' => '<strong>Rediger eller slett</strong> &mdash; bruk handlingsknappane på kvar rad for å oppdatere tenestedetaljar eller fjerne ei teneste heilt. Redigering er alltid å føretrekkje framfor sletting, slik at historiske hendingskoplingar held seg intakte.',
        'settings_tip'     => 'Tenk på tenestekatalogen som fundamentet for statussida di. Bruk tid på å få namn og skildringar rette &mdash; det er dette brukarane og interessentane dine ser når dei sjekkar helsa til IT-miljøet ditt.',

        // Section 6: Quick tips
        'tips_heading' => 'Raske tips',
        'tip_communicate_title' => 'Kommuniser tidleg',
        'tip_communicate_desc'  => 'Legg ut ei hending så snart du veit at noko er gale, sjølv om du ikkje har alle detaljane enno. Å vedgå eit problem raskt byggjer tillit hjå brukarane.',
        'tip_update_title' => 'Oppdater ofte',
        'tip_update_desc'  => 'Jamlege statusoppdateringar &mdash; sjølv om ingenting har endra seg &mdash; viser brukarane at det blir jobba aktivt med problemet. Stille skaper frustrasjon og supportsaker.',
        'tip_review_title' => 'Sjå etter mønster',
        'tip_review_desc'  => 'Gå gjennom hendingshistorikken jamleg. Dukkar den same tenesta opp gong på gong, kan det peike mot eit djupare infrastrukturproblem som er verdt å ta tak i på førehand.',
        'tip_maintenance_title' => 'Planlegg vedlikehald',
        'tip_maintenance_desc'  => 'Bruk påverknadsnivået Vedlikehald for planlagt arbeid. Å opprette ei hending på førehand lèt brukarane vite om planlagd nedetid før det skjer.',
    ],
];
