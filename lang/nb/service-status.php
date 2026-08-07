<?php
/**
 * Norsk bokmål (nb) — tekster for modulen Tjenestestatus.
 *
 * Faller tilbake per nøkkel til lang/en/service-status.php for det som mangler
 * her (se includes/i18n.php). Nøkkelrekkefølgen følger lang/en/service-status.php.
 *
 * Dekker statusoversikten, hendelsesdialogen, innstillingssiden (fanene for
 * tjenester, statuser og påvirkningsnivåer samt den felles oppslagsdialogen),
 * modulens toppnavigasjon og hjelpeveiledningen.
 *
 * MERK: Tjenestenavn, hendelsestitler/kommentarer og de konfigurerbare NAVNENE
 * på statuser og påvirkningsnivåer som ligger i databasen, er brukerdata og
 * oversettes IKKE her — bare app-definerte tekster hører hjemme i denne filen.
 */
return [
    'title' => 'Tjenestestatus',

    'nav' => [
        'status'   => 'Status',
        'settings' => 'Innstillinger',
        'help'     => 'Hjelp',
    ],

    'board' => [
        'services'        => 'Tjenester',
        'service_count'   => '{count} tjenester',
        'loading'         => 'Laster ...',
        'no_services'     => 'Ingen tjenester er satt opp. Gå til Innstillinger for å legge til tjenester.',
        'incidents'       => 'Hendelser',
        'new'             => 'Ny',
        'col_title'       => 'Tittel',
        'col_status'      => 'Status',
        'col_affected'    => 'Berørte tjenester',
        'col_updated'     => 'Oppdatert',
        'no_incidents'    => 'Ingen hendelser å vise.',
        'none'            => 'Ingen',
    ],

    'modal' => [
        'new_incident'        => 'Ny hendelse',
        'edit_incident'       => 'Rediger hendelse',
        'title'               => 'Tittel',
        'title_placeholder'   => 'Kort beskrivelse av hendelsen',
        'status'              => 'Status',
        'comment'             => 'Kommentar',
        'comment_placeholder' => 'Detaljer om hendelsen ...',
        'affected_services'   => 'Berørte tjenester',
        'add_service'         => '+ Legg til tjeneste',
        'delete'              => 'Slett',
        'cancel'              => 'Avbryt',
        'save'                => 'Lagre',
    ],

    'toast' => [
        'incident_saved'   => 'Hendelsen er lagret',
        'incident_deleted' => 'Hendelsen er slettet',
        'save_failed'      => 'Lagring mislyktes',
        'delete_failed'    => 'Sletting mislyktes',
        'save_incident_failed'   => 'Kunne ikke lagre hendelsen',
        'delete_incident_failed' => 'Kunne ikke slette hendelsen',
        'saved'            => 'Lagret',
        'deleted'          => 'Slettet',
        'save_service_failed'    => 'Kunne ikke lagre tjenesten',
        'delete_service_failed'  => 'Kunne ikke slette tjenesten',
    ],

    'confirm' => [
        'delete_incident_title'   => 'Slett hendelse',
        'delete_incident_message' => 'Vil du slette denne hendelsen?',
        'delete_title'            => 'Slett',
        'delete_message'          => 'Vil du slette «{name}»?',
        'delete_label'            => 'Slett',
    ],

    'settings' => [
        'tab_services'     => 'Tjenester',
        'tab_statuses'     => 'Statuser',
        'tab_impacts'      => 'Påvirkningsnivåer',

        'services_heading' => 'Tjenester',
        'statuses_heading' => 'Hendelsesstatuser',
        'impacts_heading'  => 'Påvirkningsnivåer',
        'add'              => 'Legg til',
        'loading'          => 'Laster ...',
        'no_services'      => 'Ingen tjenester ennå. Klikk Legg til for å opprette en.',
        'no_items'         => 'Ingen elementer funnet',
        'load_failed'      => 'Kunne ikke laste data',
        'error_prefix'     => 'Feil: {message}',

        'statuses_intro_html' => 'Arbeidsflyttilstander for tjenestehendelser. Statuser som er merket som <em>løst</em>, lukker hendelsen — <code>resolved_datetime</code> settes automatisk, og hendelsen fjernes fra den aktive oversikten. Nøyaktig én status er standard for nye hendelser.',
        'impacts_intro_html'  => 'Alvorlighetsnivåer som vises som merke på hvert tjenestekort. <strong>Alvorlighetsrekkefølgen</strong> styrer sorteringen etter «verste gjeldende påvirkning» i oversikten — lavere = verre (1 = alvorlig driftsstans, 5 = i drift). To rader kan ha samme rekkefølge.',

        'col_name'        => 'Navn',
        'col_description' => 'Beskrivelse',
        'col_order'       => 'Rekkefølge',
        'col_status'      => 'Status',
        'col_actions'     => 'Handlinger',
        'col_colour'      => 'Farge',
        'col_resolved'    => 'Løst',
        'col_default'     => 'Standard',
        'col_severity'    => 'Alvorlighet',

        'active'          => 'Aktiv',
        'inactive'        => 'Inaktiv',
        'yes'             => 'Ja',
        'no'              => 'Nei',
        'edit'            => 'Rediger',
        'delete'          => 'Slett',

        'kind_status'     => 'status',
        'kind_impact'     => 'påvirkningsnivå',

        // Service modal
        'add_service'     => 'Legg til tjeneste',
        'edit_service'    => 'Rediger tjeneste',
        'field_name'      => 'Navn',
        'field_description' => 'Beskrivelse',
        'field_order'     => 'Visningsrekkefølge',
        'field_active'    => 'Aktiv',

        // Lookup modal (statuses + impact levels)
        'add_item'        => 'Legg til element',
        'add_kind'        => 'Legg til {kind}',
        'edit_kind'       => 'Rediger {kind}',
        'field_colour'    => 'Farge',
        'field_resolved'  => 'Regnes som løst',
        'resolved_help_html' => 'Hendelser med denne statusen får automatisk satt <code>resolved_datetime</code> og forsvinner fra den aktive oversikten.',
        'field_severity'  => 'Alvorlighetsrekkefølge',
        'severity_help'   => '1 = verst (alvorlig driftsstans). Høyere = mindre alvorlig.',
        'field_default'   => 'Standard',

        'cancel'          => 'Avbryt',
        'save'            => 'Lagre',
    ],

    'help' => [
        'page_title' => 'Veiledning for tjenestestatus',
        'guide'      => 'Veiledning',

        'nav_overview'  => 'Oversikt',
        'nav_dashboard' => 'Statusoversikten',
        'nav_services'  => 'Administrere tjenester',
        'nav_history'   => 'Hendelseshistorikk',
        'nav_settings'  => 'Innstillinger',
        'nav_tips'      => 'Raske tips',

        'hero_title' => 'Veiledning for tjenestestatus',
        'hero_sub'   => 'Følg med på IT-tjenestene dine, formidle hendelser og hold interessentene oppdatert i sanntid.',

        // Section 1: Overview
        'overview_heading' => 'Oversikt',
        'overview_intro'   => 'Modulen Tjenestestatus gir deg én samlet oversikt over tilstanden til alle IT-tjenester virksomheten er avhengig av. Når noe går galt, kan du registrere hendelser, oppdatere berørte tjenester og holde brukerne informert gjennom hele løsningsarbeidet.',
        'feature_dashboard_title' => 'Statusoversikt',
        'feature_dashboard_desc'  => 'Se tilstanden til hver tjeneste med ett blikk. Fargekodede merker viser om tjenesten er i drift, har redusert ytelse, er under vedlikehold eller er nede.',
        'feature_incident_title'  => 'Hendelsessporing',
        'feature_incident_desc'   => 'Registrer hendelser med tittel, statusoppdateringer og kommentarer. Knytt berørte tjenester til hver hendelse, slik at alle vet nøyaktig hva som er berørt og hvorfor.',
        'feature_management_title' => 'Tjenesteadministrasjon',
        'feature_management_desc'  => 'Sett opp tjenestekatalogen i innstillingene. Legg til tjenester med navn, beskrivelse og visningsrekkefølge. Aktiver eller deaktiver tjenester etter hvert som infrastrukturen endrer seg.',
        'feature_comms_title' => 'Kommunikasjon',
        'feature_comms_desc'  => 'Hold interessentene oppdatert med statusoppdateringer i sanntid. Hver hendelse har en status og en kommentarhistorikk, slik at brukerne kan følge arbeidet uten å måtte purre på brukerstøtten.',

        // Section 2: Dashboard
        'dashboard_heading' => 'Statusoversikten',
        'dashboard_p1'      => 'Oversikten er det første du ser når du åpner modulen Tjenestestatus. Den viser et rutenett av tjenestekort, hvert med tjenestens navn, en kort beskrivelse og et fargekodet påvirkningsmerke som gjenspeiler den verste gjeldende statusen. Under rutenettet ligger hendelsestabellen med alle nylige og aktive hendelser.',
        'dashboard_p2_html' => 'Hvert tjenestekort gjenspeiler automatisk det alvorligste påvirkningsnivået tjenesten er gitt i en aktiv (uløst) hendelse. Når alle hendelser som berører en tjeneste er løst, går den tilbake til <strong>I drift</strong>.',
        'status_levels'     => 'Statusnivåer',
        'level_operational_name' => 'I drift',
        'level_operational_desc' => 'Tjenesten fungerer normalt og har ingen kjente problemer. Dette er standardtilstanden for alle friske tjenester.',
        'level_degraded_name'    => 'Redusert ytelse',
        'level_degraded_desc'    => 'Tjenesten er tilgjengelig, men går tregere enn forventet eller har redusert funksjonalitet. Brukerne kan merke forsinkelser.',
        'level_maintenance_name' => 'Under vedlikehold',
        'level_maintenance_desc' => 'Planlagt nedetid eller vedlikeholdsvindu. Tjenesten kan være midlertidig utilgjengelig mens arbeidet pågår.',
        'level_outage_name'      => 'Alvorlig driftsstans',
        'level_outage_desc'      => 'Tjenesten er helt utilgjengelig. Dette er den alvorligste statusen og bør utløse undersøkelser umiddelbart.',
        'dashboard_tip'     => 'Påvirkningsnivåene er hierarkiske. Er en tjeneste knyttet til flere aktive hendelser, viser oversikten den verste påvirkningen. Hvis for eksempel én hendelse merker tjenesten som Redusert ytelse og en annen som Alvorlig driftsstans, er det Alvorlig driftsstans som vises.',

        // Section 3: Managing services
        'services_heading_html' => 'Administrere tjenester &amp; registrere hendelser',
        'services_intro'        => 'Tjenestene er byggeklossene i statussiden din. Hver av dem representerer en IT-tjeneste, et system eller en infrastrukturkomponent som brukerne er avhengige av. Når noe går galt, oppretter du en hendelse og knytter den til de berørte tjenestene.',
        'add_incident_heading'  => 'Legge til en ny hendelse',
        'add_incident_step1_html' => '<strong>Klikk «Ny»</strong> i oversikten for å åpne hendelsesskjemaet.',
        'add_incident_step2_html' => '<strong>Skriv inn en tittel</strong> &mdash; en kort og tydelig beskrivelse av problemet. For eksempel: «Forsinket e-postlevering» eller «VPN-gateway er utilgjengelig».',
        'add_incident_step3_html' => '<strong>Angi status</strong> &mdash; velg Undersøkes, Identifisert, Tredjepart, Overvåkes eller Løst. Start med Undersøkes og oppdater etter hvert som du vet mer.',
        'add_incident_step4_html' => '<strong>Legg til en kommentar</strong> &mdash; beskriv hva som er kjent så langt, hvilke tiltak som gjøres, og eventuelle midlertidige løsninger for brukerne.',
        'add_incident_step5_html' => '<strong>Knytt til berørte tjenester</strong> &mdash; legg til én eller flere tjenester og velg påvirkningsnivå for hver av dem (Alvorlig driftsstans, Delvis driftsstans, Redusert ytelse, Vedlikehold, I drift eller Ingen forstyrrelse).',
        'add_incident_step6_html' => '<strong>Lagre</strong> &mdash; hendelsen dukker opp i tabellen, og kortene for berørte tjenester oppdateres umiddelbart i oversikten.',
        'workflow_heading'  => 'Arbeidsflyt for hendelsesstatus',
        'workflow_investigating' => 'Undersøkes',
        'workflow_identified'    => 'Identifisert',
        'workflow_monitoring'    => 'Overvåkes',
        'workflow_resolved'      => 'Løst',
        'workflow_note_html'     => 'Bruk <strong>Tredjepart</strong> når rotårsaken ligger hos en ekstern leverandør.',
        'services_tip'      => 'Du kan redigere en hendelse ved å klikke på tittelen i tabellen. Oppdater statusen, legg til nye kommentarer eller endre berørte tjenester etter hvert som situasjonen utvikler seg. Å holde hendelsene oppdatert er nøkkelen til åpen kommunikasjon.',

        // Section 4: Incident history
        'history_heading' => 'Hendelseshistorikk',
        'history_p1'      => 'Hendelsestabellen i oversikten viser både aktive og løste hendelser, og gir deg en komplett tidslinje over tjenestenes tilstand. Hver rad viser hendelsens tittel, gjeldende status, berørte tjenester med påvirkningsnivå og tidspunktet for siste oppdatering.',
        'history_field_title_html'    => '<strong>Tittel</strong> &mdash; en lenke som åpner hendelsen for redigering. Bruk tydelige og beskrivende titler, så blir historikken lett å lese.',
        'history_field_status_html'   => '<strong>Status</strong> &mdash; fargekodet merke som viser hvilken fase undersøkelsen er i (Undersøkes, Identifisert, Tredjepart, Overvåkes eller Løst).',
        'history_field_affected_html' => '<strong>Berørte tjenester</strong> &mdash; merker som viser hver tilknyttede tjeneste i fargen til påvirkningsnivået. Med ett blikk ser du hva som er berørt og hvor alvorlig det er.',
        'history_field_updated_html'  => '<strong>Oppdatert</strong> &mdash; tidspunktet for siste endring. Løste hendelser vises med dempet tekst, slik at de aktive skiller seg tydelig ut.',
        'history_p2'      => 'Løste hendelser blir værende i tabellen som historikk. Det gjør det enkelt å oppdage gjentakende problemer, se hvordan tidligere hendelser ble håndtert og finne mønstre som kan peke mot underliggende problemer.',
        'history_tip'     => 'Å gå gjennom hendelseshistorikken jevnlig hjelper deg å se hvilke tjenester som ofte får forstyrrelser. Dukker den samme tjenesten opp i flere hendelser, kan det være på tide å grave dypere i rotårsaken eller planlegge en oppgradering av infrastrukturen.',

        // Section 5: Settings
        'settings_heading' => 'Innstillinger',
        'settings_p1'      => 'På innstillingssiden bygger og vedlikeholder du tjenestekatalogen. Alle tjenester som skal vises i statusoversikten, må settes opp her først.',
        'settings_step1_html' => '<strong>Legg til en tjeneste</strong> &mdash; klikk «Legg til» og oppgi et navn (f.eks. «E-post», «VPN», «ERP-system») og en valgfri beskrivelse av hva tjenesten gjør.',
        'settings_step2_html' => '<strong>Angi visningsrekkefølge</strong> &mdash; rekkefølgenummeret styrer hvor tjenesten havner i rutenettet. Lavere tall kommer først, så legg de mest kritiske tjenestene øverst.',
        'settings_step3_html' => '<strong>Slå av og på aktiv/inaktiv</strong> &mdash; å deaktivere en tjeneste fjerner den fra oversikten uten å slette den. Det er nyttig for utfasede tjenester eller sesongbaserte systemer.',
        'settings_step4_html' => '<strong>Rediger eller slett</strong> &mdash; bruk handlingsknappene på hver rad for å oppdatere detaljer eller fjerne en tjeneste helt. Rediger heller enn å slette, så beholder du koblingene til tidligere hendelser.',
        'settings_tip'     => 'Se på tjenestekatalogen som fundamentet for statussiden din. Bruk tid på å få navn og beskrivelser riktige &mdash; det er dette brukerne og interessentene ser når de sjekker tilstanden til IT-miljøet ditt.',

        // Section 6: Quick tips
        'tips_heading' => 'Raske tips',
        'tip_communicate_title' => 'Kommuniser tidlig',
        'tip_communicate_desc'  => 'Legg ut en hendelse så snart du vet at noe er galt, selv om du ennå ikke har alle detaljene. Å erkjenne et problem raskt bygger tillit hos brukerne.',
        'tip_update_title' => 'Oppdater ofte',
        'tip_update_desc'  => 'Jevnlige statusoppdateringer &mdash; selv når ingenting har endret seg &mdash; viser brukerne at det jobbes aktivt med problemet. Stillhet skaper frustrasjon og flere saker.',
        'tip_review_title' => 'Se etter mønstre',
        'tip_review_desc'  => 'Gå gjennom hendelseshistorikken jevnlig. Hvis den samme tjenesten stadig dukker opp, kan det peke mot et dypere infrastrukturproblem som bør tas tak i på forhånd.',
        'tip_maintenance_title' => 'Planlegg vedlikehold',
        'tip_maintenance_desc'  => 'Bruk påvirkningsnivået Vedlikehold for planlagt arbeid. Oppretter du hendelsen i forkant, får brukerne vite om nedetiden før den skjer.',
    ],
];
