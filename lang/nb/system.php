<?php
/**
 * Norsk bokmål (nb) — tekststrenger for System-modulen.
 *
 * Nøkler som mangler her, faller tilbake til verdien i lang/en/system.php
 * (se includes/i18n.php).
 *
 * Dekker System-forsiden, den delte navigasjonen i toppen og hver av
 * undersidene: merkevare, farger, db-verify, feilsøkingsverktøy, demodata,
 * kryptering, moduler (tilgang), preferanser, sikkerhet og SSO.
 *
 * Holdt utenfor oversettelsen (beholdt som litteralt innhold): lagrede
 * konfigurasjonsverdier, kortinnhold per modul i demodata, identifikatorer
 * for krypterte nøkler og innstillinger, modul- og tilgangsnøkler, enum-koder
 * og loggtekster fra serveren.
 */
return [
    'title' => 'System',

    // Delt navigasjon i toppen (system/includes/header.php)
    'nav' => [
        'encryption'  => 'Kryptering',
        'modules'     => 'Moduler',
        'db_verify'   => 'Verifiser DB',
        'colours'     => 'Farger',
        'branding'    => 'Merkevare',
        'security'    => 'Sikkerhet',
        'preferences' => 'Preferanser',
        'demo_data'   => 'Demodata',
        'debug_tools' => 'Feilsøkingsverktøy',
    ],

    // Forsiden for System (system/index.php)
    'landing' => [
        'heading'  => 'Systemadministrasjon',
        'subtitle' => 'Konfigurer innstillinger på systemnivå og tilgangskontroll',

        // Søkefeltet for kortene. Nøkkelordene under er søkesynonymer, slik at
        // f.eks. «oidc» finner single sign-on. De vises aldri, de bare treffes.
        'search_placeholder' => 'Søk i systemområder …',
        'no_results'         => 'Ingen systemområder passer med søket ditt.',

        'help_title' => 'Hjelp og veiledninger',
        'help_desc'  => 'Trinnvise veiledninger for hvert systemområde, inkludert oppsett av single sign-on.',

        'topology_title'    => 'Topologi',
        'topology_desc'     => 'Se hvordan selskaper, postkasser, domener, innlogging og analytikere henger sammen',
        'topology_keywords' => 'topology map overview tree relationships companies mailboxes domains analysts structure diagram graph topologi kart oversikt selskaper postkasser domener analytikere struktur',

        'orphaned_title'    => 'Foreldreløse saker',
        'orphaned_desc'     => 'Finn saker som står fast i en slettet avdeling, og tildel dem på nytt',
        'orphaned_keywords' => 'orphaned tickets department missing deleted hidden reassign fix stuck lost broken foreldrelose saker avdeling slettet skjult tildel fast',

        'encryption_title'  => 'Kryptering',
        'encryption_desc'   => 'Generer og administrer krypteringsnøkkelen som beskytter sensitive data som API-nøkler og påloggingsdetaljer.',
        'encryption_keywords' => 'encryption key master key crypto secrets credentials api keys cipher kryptering nokkel hemmeligheter',
        'analysts_title'    => 'Analytikere',
        'analysts_desc'     => 'Administrer analytikerkontoer — opprett, rediger og deaktiver brukere, tilbakestill passord, tildel single sign-on og team, og styr tilgang per selskap.',
        'analysts_keywords' => 'analysts users accounts staff agents people login passwords reset sso team membership access user management analytikere brukere kontoer passord team tilgang',
        'teams_title'       => 'Team',
        'teams_desc'        => 'Administrer teamene analytikerne tilhører. Team brukes på tvers av saker, oppgaver, kontrakter og arbeidsflyter for tildeling og tilgang.',
        'teams_keywords'    => 'teams groups squads assignment routing access members analysts departments team grupper tildeling tilgang medlemmer avdelinger',
        'roles_title'       => 'Roller',
        'roles_desc'        => 'Gi andre enn administratorer rett til å styre innstillingene for bestemte moduler — uten å gjøre dem til fulle systemadministratorer.',
        'roles_keywords'    => 'roles permissions rbac capabilities access control manage settings grant admin rights delegate lms manager roller rettigheter tilgangskontroll innstillinger delegere',
        'modules_title'     => 'Modultilgang',
        'modules_desc'      => 'Styr hvilke moduler hver analytiker har tilgang til. Begrens hva som vises på startsiden og i navigasjonsmenyen.',
        'modules_keywords'  => 'module access permissions analyst rights visibility roles enable disable modultilgang rettigheter synlighet',
        'db_verify_title'   => 'Verifiser database',
        'db_verify_desc'    => 'Kontroller at alle tabeller og kolonner finnes i databasen. Alt som mangler blir opprettet automatisk.',
        'db_verify_keywords' => 'database verify schema tables columns migration repair sql db verifiser skjema tabeller kolonner reparer',
        'colours_title'     => 'Farger',
        'colours_desc'      => 'Tilpass fargetemaet for hver modul. Endringene gjelder for topplinjer, ikoner og startsiden.',
        'colours_keywords'  => 'colours colors theme palette appearance customise branding farger tema palett utseende',
        'branding_title'    => 'Merkevare',
        'branding_desc'     => 'Last opp organisasjonens logo og angi standard topp- og bunntekst for diagrammer og eksporterte dokumenter.',
        'branding_keywords' => 'branding logo header footer organisation company export documents merkevare topptekst bunntekst organisasjon eksport dokumenter',
        'security_title'    => 'Sikkerhet',
        'security_desc'     => 'Konfigurer regler for klarerte enheter, passordutløp og sperring av kontoer.',
        'security_keywords' => 'security password expiry lockout trusted device mfa 2fa login policy brute force sikkerhet passord utlop sperring klarert enhet innlogging',
        'sso_title'         => 'Autentisering',
        'sso_desc'          => 'Velg hvordan folk logger inn: single sign-on via en identitetsleverandør (OpenID Connect), eller mot din LDAP / Active Directory.',
        'sso_keywords'      => 'authentication auth sso single sign-on single sign on oidc openid connect saml identity provider idp keycloak entra azure ad okta google oauth federation login ldap active directory ad domain directory bind samba openldap autentisering palogging identitetsleverandor katalog domene',
        'api_title'         => 'API',
        'api_desc'          => 'Opprett API-nøkler med detaljerte rettigheter, og utforsk REST-API-et med interaktiv dokumentasjon.',
        'api_keywords'      => 'api rest keys tokens integration webhook endpoints documentation swagger developer external nokler integrasjon dokumentasjon utvikler',
        'webhooks_title'    => 'Webhook-kø',
        'webhooks_desc'     => 'Følg med på utgående webhook-leveranser: sjekk at arbeideren kjører, se innhold og svar for hver sending, og kjør hvilken som helst av dem på nytt.',
        'webhooks_keywords' => 'webhooks webhook queue outbound deliveries delivery worker cron slack teams discord payload replay retries hmac signature integration workflow ko utgaende leveranser signatur integrasjon arbeidsflyt',
        'preferences_title' => 'Preferanser',
        'preferences_desc'  => 'Personlige innstillinger som hvor varsler vises. De lagres per nettleser og gjelder bare deg.',
        'preferences_keywords' => 'preferences personal settings notifications toast position per-browser preferanser personlige innstillinger varsler posisjon nettleser',
        'demo_data_title'   => 'Demodata',
        'demo_data_desc'    => 'Importer realistiske eksempeldata på tvers av alle moduler. Perfekt for evaluering og testing på en ny installasjon.',
        'demo_data_keywords' => 'demo data sample seed test evaluation import fixtures example demodata eksempeldata evaluering importer',
        'debug_tools_title' => 'Feilsøkingsverktøy',
        'debug_tools_desc'  => 'Bibliotek med diagnoser for å feilsøke flyter som ikke virker. Kjør dem på forespørsel og send resultatet tilbake til support.',
        'debug_tools_keywords' => 'debug tools diagnostics troubleshoot logs errors support fix feilsoking diagnose logger feil support',
        'companies_title'   => 'Selskaper',
        'companies_desc'    => 'Administrer kundeselskapene denne installasjonen betjener.',
        'companies_keywords' => 'companies clients tenants multi-tenancy multi tenant organisations msp selskaper kunder organisasjoner',
        'routing_test_title' => 'Test av e-postruting',
        'routing_test_desc'  => 'Simuler en innkommende e-post og se hvilket selskap den ville blitt lagt til, og hvorfor.',
        'routing_test_keywords' => 'email routing test dry run mailbox sender domain triage tenant inbound diagnostic e-post ruting test postkasse avsender domene innkommende',
        'integrations_title'    => 'Integrasjoner',
        'integrations_desc'     => 'Koble FreeITSM til issue-trackerne utviklingsteamet ditt bruker, slik at en sak som viser seg å være en feil kan eskaleres og følges opp uten å forlate servicedesken.',
        'integrations_keywords' => 'integrations integration jira atlassian issue tracker bug escalate escalation github gitlab azure devops connector developer dev team link linked issue integrasjoner feil eskalere utvikling koblet sak',
    ],

    // Integrasjoner (system/integrations/)
    'integrations' => [
        'title'    => 'Integrasjoner',
        'subtitle' => 'Koble FreeITSM til en ekstern issue-tracker, slik at en sak som viser seg å være en feil kan meldes til utviklingsteamet og følges opp herfra.',

        'needs_db_verify' => 'Tabellene for integrasjoner finnes ikke i databasen ennå. Kjør Verifiser database under System før du legger til en tilkobling.',

        'jira_blurb'     => 'Opprett Jira-issues fra saker og se statusen deres uten å forlate FreeITSM. Fungerer med både Jira Cloud og Jira Data Center.',
        'jira_url_label' => 'URL til Jira-området',
        'azuredevops_blurb'     => 'Opprett arbeidselementer i Azure DevOps fra saker og se tilstanden deres uten å forlate FreeITSM. Fungerer med Azure DevOps Services og Azure DevOps Server.',
        'azuredevops_url_label' => 'URL til organisasjonen',
        'field_resolved_means'  => 'Når et arbeidselement merkes som Resolved',
        // Bevisst formulert ut fra hva innmelderen opplever. «Koble Resolved til
        // en statuskategori» er riktig, men sier en administrator ingenting om
        // hva han bør velge.
        'field_resolved_means_hint' => 'Azure DevOps har en Resolved-tilstand som betyr at en utvikler mener det er fikset, men at ingen har kontrollert det ennå. Feil bruker den; brukerhistorier gjør det ikke.',
        'resolved_in_progress'  => 'Behandle den som fortsatt under arbeid',
        'resolved_done'         => 'Behandle den som ferdig',

        'one_connection' => '1 tilkobling',
        'n_connections'  => '{n} tilkoblinger',
        'no_connections' => 'Ingen tilkoblinger ennå.',

        'connections_heading' => 'Tilkoblinger',
        'connections_desc'    => 'Hver tilkobling er ett område du kan opprette issues i. Legg til flere hvis ulike team eller kunder bruker hvert sitt område.',

        'col_name'    => 'Navn',
        'col_url'     => 'URL til området',
        'col_company' => 'Selskap',
        'col_status'  => 'Status',

        'add_heading'  => 'Legg til tilkobling',
        'edit_heading' => 'Rediger tilkobling',

        'field_email'      => 'E-postadresse',
        'field_email_hint' => 'Bare Jira Cloud — kontoen API-tokenet tilhører. La feltet stå tomt for Jira Data Center, som bruker et personlig tilgangstoken alene.',
        'field_token'      => 'API-token',
        'field_pat'        => 'Personlig tilgangstoken',
        'creds_keep_hint'  => 'La tokenfeltet stå tomt for å beholde det som allerede er lagret.',

        'company_shared' => 'Alle selskaper',
        'company_hint'   => 'La den stå på alle selskaper for en tracker ditt eget team bruker. Velg et selskap for å begrense den til den kunden — saker fra andre selskaper kan da ikke eskaleres dit.',

        'active_label'   => 'Aktiv',
        'inactive_label' => 'Av',
        // {name} er leverandøren — denne siden er delt, så teksten kan ikke låse
        // seg til «Jira» slik den Jira-spesifikke ordlyden i designdokumentet gjør.
        'inbound_badge'  => 'Oppdateringer på',
        // Kobling (V3) — hva verdiene våre betyr i trackerens vokabular.
        'help_link'         => 'Slik setter du opp {name}',
        'mapping_help_link' => 'Usikker på hva disse betyr?',
        'mapping_title'        => 'Kobling',
        'mapping_intro'        => 'Bestem hva verdiene dine betyr i {name}, så slipper alle å skrive en prosjektnøkkel inn i hver regel. Alt som står tomt, blir rett og slett ikke sendt.',
        'map_projects'         => 'Hvilket prosjekt issues havner i',
        'map_projects_hint'    => 'Sett en standard, og legg til unntak der du trenger dem. Den mest spesifikke regelen vinner: en avdeling slår et selskap, og et selskap slår standarden.',
        'map_group_default'    => 'Standard',
        'map_group_dept'       => 'Unntak per avdeling — disse slår alt under',
        'map_group_company'    => 'Unntak per selskap',
        'map_types'            => 'Sakstype blir issue-type',
        'map_types_hint'       => 'Issue-typer varierer fra prosjekt til prosjekt, så dette er forslag fra standardprosjektet ditt — du kan skrive hvilken som helst verdi.',
        'map_priorities'       => 'Prioritet',
        'map_priorities_hint'  => 'Det finnes bevisst ingen standard her: å merke hver issue som hastesak hjelper ingen. En prioritet som ikke er koblet, vises fortsatt som tekst i beskrivelsen. Hvis et prosjekt avviser en prioritet, blir issuen likevel opprettet, bare uten prioritet.',
        'map_default'          => 'Alt annet',
        'map_none'             => 'Ikke koblet',
        'map_saved'            => 'Koblingen er lagret',
        'map_needs_verify'     => 'Kjør Verifiser database først — koblingstabellen finnes ikke på denne installasjonen ennå.',
        'map_load_failed'      => 'Kunne ikke laste koblingen.',
        // ⚠️ \' — IKKE \x27. PHP tolker ikke hex-escapes inne i enkle
        // anførselstegn, så \x27 ble vist bokstavelig på skjermen.
        'attach_label'   => 'Send vedleggene fra saken til {name}',
        'attach_hint'    => 'I en feilrapport ER skjermbildet som regel selve rapporten. Bilder som ligger inne i en e-post — signaturer, logoer, sporingspiksler — sendes aldri, bare filer noen bevisst har lagt ved. Store filer hoppes over i stedet for at eskaleringen feiler, og du ser alltid listen før noe sendes.',
        'inbound_label'  => 'Ta imot oppdateringer fra {name}',
        'inbound_hint'   => 'Kommentarer som skrives i {name}, kommer inn på den koblede saken som interne notater. De vises aldri for innmelderen. Den første kontrollen etter at du slår dette på, henter ingenting — den markerer bare startpunktet, så det å slå det på kan ikke dra inn et etterslep av gamle kommentarer.',

        'test'           => 'Test',
        'save_failed'    => 'Kunne ikke lagre tilkoblingen.',
        'saved'          => 'Tilkoblingen er lagret.',
        'deleted'        => 'Tilkoblingen er slettet.',
        'delete_title'   => 'Slett tilkobling',
        'confirm_delete' => 'Vil du slette denne tilkoblingen?',
        'confirm_delete_named' => 'Vil du slette tilkoblingen «{name}»? Saker som allerede er koblet til issues på den, beholder koblingene sine.',

        // ── Slack ────────────────────────────────────────────────────────────
        // Ligger under Integrasjoner fordi det er der folk leter, men under
        // panseret er det en meldingsKANAL — ordlyden sier derfor bevisst
        // «arbeidsområde» og «kanal», aldri «tilkobling» eller «prosjekt».
        'slack_blurb' => 'Gjør en melding i en Slack-kanal om til en sak, og svar på den fra innboksen uten å forlate tråden.',
        'slack_workspaces'      => 'Slack-arbeidsområder',
        'slack_workspaces_desc' => 'Hvert av dem er en Slack-app du installerer i ditt eget arbeidsområde. FreeITSM ser aldri Slack-trafikken din — appen snakker rett med denne serveren.',
        'slack_col_channel'  => 'Overvåker',
        'slack_any_channel'  => 'Alle kanaler den blir invitert til',
        'slack_empty'        => 'Ingen Slack-arbeidsområder ennå. Legg til ett, og hent appen fra Slack etterpå.',
        'slack_needs_setup'  => 'Mangler oppsett',

        'slack_add_title'  => 'Legg til et Slack-arbeidsområde',
        'slack_edit_title' => 'Rediger Slack-arbeidsområde',
        'slack_name_hint'  => 'Brukes bare i denne listen, og som navn på Slack-appen du oppretter.',
        'slack_name_required' => 'Gi dette arbeidsområdet et navn.',
        'slack_unchanged'  => 'Uendret — la stå tomt for å beholde',

        'slack_bot_token'      => 'OAuth-token for botbrukeren',
        'slack_bot_token_hint' => 'Fra Slack-appen din, under OAuth &amp; Permissions. Begynner med xoxb-. Du får det først etter at appen er installert, så la det stå tomt første gang du lagrer.',
        'slack_signing_secret' => 'Signeringsnøkkel',
        'slack_signing_secret_hint' => 'Fra Slack-appen din, under Basic Information. Det er slik FreeITSM beviser at en melding virkelig kom fra Slack, så ingenting godtas før den er satt.',
        'slack_watch_channel'  => 'Overvåk bare denne kanalen',
        'slack_watch_channel_hint' => 'En Slack-kanal-ID, for eksempel C08ABCDEF. La feltet stå tomt for å opprette sak fra alle kanaler appen blir invitert til — noe som i en travel kanal betyr én sak per melding, så de fleste oppgir én hjelpekanal her.',

        'slack_company_shared' => 'Delt — avgjøres av avsenderen',
        'slack_company_hint'   => 'Knytt dette arbeidsområdet til ett selskap, eller la det være delt og la avsenderen avgjøre.',

        'slack_delete_confirm' => 'Vil du slette Slack-arbeidsområdet «{name}»? Saker som allerede er opprettet fra det, beholder historikken sin; det er bare nye meldinger som slutter å komme inn.',

        // Helsesjekken. Ordlyden er viktig her: det meste den finner er noe som
        // er stille feil, ikke åpenbart ødelagt.
        'slack_diag_title'     => 'Helsesjekk for Slack',
        'slack_diag_desc'      => 'Kontrollerer alt som kan være stille feil — ikke bare om tokenet virker. Hvert resultat sier hva du bør gjøre med det.',
        'slack_diag_running'   => 'Kontrollerer — dette snakker med Slack og med din egen offentlige adresse, så gi det noen sekunder …',
        'slack_diag_rerun'     => 'Kjør på nytt',
        'slack_diag_all_ok'    => 'Alt er i orden. Meldinger som legges ut i den overvåkede Slack-kanalen, blir til saker, og svar går tilbake i tråden.',
        'slack_diag_some_warn' => 'Fungerer, men det er noe du bør vite om. Ingenting under stopper saker fra å komme inn, men det er verdt å lese.',
        'slack_diag_some_fail' => 'Noe er galt, og meldinger vil ikke komme inn. Kontrollene som feilet under, sier hva du skal gjøre.',

        // Vinduet for oppsett av appen.
        'slack_app_title'      => 'Slack-appen',
        'slack_copy_manifest'  => 'Kopier manifestet',
        'slack_scopes_heading' => 'Hva appen får lov til å gjøre',
        'slack_scopes_desc'    => 'Slack-administratoren din ser denne listen når appen skal godkjennes. Ingenting her lar den opprette kanaler, invitere folk eller poste noe sted den ikke er invitert.',
        'slack_step1' => 'Gå til <strong>api.slack.com/apps</strong> i Slack og velg <strong>Create New App → From a manifest</strong>. Velg arbeidsområdet du vil ha servicedesken i.',
        'slack_step2' => 'Lim inn dette manifestet. Det setter navnet, rettighetene og adressen Slack sender meldinger til, så det er ingenting å huke av manuelt.',
        'slack_step3' => 'Klikk <strong>Install to Workspace</strong> og godkjenn. Kopier deretter <strong>Bot User OAuth Token</strong> og <strong>Signing Secret</strong> tilbake i redigeringsskjemaet her — Slack viser ikke tokenet igjen. <strong>Hvis Slack viser et gult &ldquo;Reinstall to Workspace&rdquo;-banner, klikk på det først</strong> — ellers mangler tokenet de fleste rettighetene sine, og saker kommer inn uten navnet på avsenderen.',
        'slack_step4' => 'Kontroller at forespørsels-URL-en under er den samme som Slack viser under <strong>Event Subscriptions</strong>. Slack verifiserer den i det øyeblikket du lagrer appen, så denne serveren må være tilgjengelig fra internett.',
        'slack_step5' => 'Inviter appen til kanalen den skal overvåke, i Slack: <strong>/invite @DinApp</strong>. Den kan ikke lese en kanal den ikke er med i.',
    ],

    // Merkevaresiden (system/branding/index.php)
    'branding' => [
        'title'    => 'Merkevare',
        'subtitle' => 'Angi organisasjonens logo og standard topp- og bunntekst som brukes på diagrammer og eksporterte dokumenter',

        'logo_heading'  => 'Selskapets logo',
        'logo_desc'     => 'Brukes som {code}-token i alle felt i topp- og bunnteksten. PNG, JPG eller SVG, maks 2&nbsp;MB. SVG anbefales for skarp utskrift og eksport.',
        'no_logo'       => 'Ingen logo',
        'remove'        => 'Fjern',
        'logo_hint'     => 'Velg en fil som skal erstatte dagens logo. Det nye bildet lagres når du trykker Lagre.',

        'header_heading' => 'Topptekst',
        'header_desc'    => 'Tre felt som vises langs toppen av siden. La et felt stå tomt for å utelate det.',
        'footer_heading' => 'Bunntekst',
        'footer_desc'    => 'Tre felt som vises langs bunnen av siden.',
        'col_left'       => 'Venstre',
        'col_centre'     => 'Midten',
        'col_right'      => 'Høyre',
        'row_header'     => 'Topptekst',
        'row_footer'     => 'Bunntekst',

        'tokens_heading' => 'Tilgjengelige tokener',
        'tokens_intro'   => 'disse erstattes når topp- eller bunnteksten vises på et diagram eller en eksport:',
        'token_logo'     => 'logobildet til selskapet',
        'token_title'    => 'tittelen på diagrammet eller dokumentet',
        'token_author'   => 'navnet på forfatteren',
        'token_version'  => 'versjonsmerket',
        'token_modified' => 'datoen for siste endring',
        'tokens_example_prefix' => 'Bland tokener med vanlig tekst — f.eks.',
        'tokens_example_suffix' => 'vises som',
        'tokens_example_render' => 'Forfatter: Ed Mozley',

        'save'             => 'Lagre',
        'reset_defaults'   => 'Tilbakestill til standard',

        'load_failed'         => 'Klarte ikke å laste merkevaren: {error}',
        'load_failed_generic' => 'Klarte ikke å laste merkevareinnstillingene',
        'logo_too_large'      => 'Logoen er for stor (maks 2 MB)',
        'reset_hint'          => 'Feltene er tilbakestilt til standard — trykk Lagre for å ta dem i bruk',
        'saved'               => 'Merkevaren er lagret',
        'error'               => 'Feil: {error}',
        'save_failed'         => 'Klarte ikke å lagre merkevaren',
    ],

    // Modulfarger (system/colours/index.php)
    'colours' => [
        'title'     => 'Modulfarger',
        'subtitle'  => 'Tilpass fargetemaet for hver modul på topplinjer, ikoner og startsiden',
        'save'      => 'Lagre',
        'primary'   => 'Primær',
        'secondary' => 'Sekundær',
        'reset'     => 'Tilbakestill',
        'saved'     => 'Modulfargene er lagret',
        'error'     => 'Feil: {error}',
        'save_failed' => 'Klarte ikke å lagre fargene',
    ],

    // Databaseverifisering (system/db-verify/index.php)
    'db_verify' => [
        'heading'     => 'Databaseverifisering',
        'intro'       => 'Kontroller at alle tabeller og kolonner finnes. Alt som mangler blir opprettet automatisk.',
        'run'         => 'Kjør verifisering',
        'verifying'   => 'Verifiserer ...',
        'checking'    => 'Kontrollerer tabeller ...',
        'placeholder' => 'Klikk «Kjør verifisering» for å kontrollere databaseskjemaet ditt',

        'count_ok'      => 'OK',
        'count_created' => 'Opprettet',
        'count_updated' => 'Oppdatert',
        'count_errors'  => 'Feil',

        'col_table'   => 'Tabell',
        'col_status'  => 'Status',
        'col_details' => 'Detaljer',

        'status_ok' => 'OK',

        'fix'         => 'Reparer',
        'fixing'      => 'Reparerer …',
        'fix_confirm' => 'Vil du slette {count} foreldreløs(e) rad(er) fra {table} permanent? Overordnet post finnes ikke lenger, så disse dataene er utilgjengelige.',
        'fix_failed'  => 'Reparasjonen feilet: {message}',

        'error'        => 'Feil: {message}',
        'connect_fail' => 'Klarte ikke å koble til: {message}',
    ],

    // Feilsøkingsverktøy (system/debug-tools/index.php)
    'debug' => [
        'heading' => 'Feilsøkingsverktøy',
        'intro'   => 'Bibliotek med selvstendige diagnoser. Når noe ikke virker, kjør det aktuelle verktøyet og send resultatet tilbake til support — hver diagnose fanger opp nok detaljer om miljø og kjøretid til å finne årsaken uten frem og tilbake.',
        'how_label' => 'Slik bruker du det:',
        'how_text'  => 'Support forteller deg hvilken diagnose du skal kjøre (f.eks. «kjør D001»). Klikk {run}, vent til resultatet vises, klikk deretter {copy} og lim hele rapporten inn i svaret ditt. Diagnosene leser stort sett bare — de som skriver til databasen, sier fra om det på kortet.',
        'checks_label'   => 'Hva den kontrollerer',
        'runtime_label'  => 'Kjøretid:',
        'side_effects_label' => 'Sideeffekter:',
        'run'     => 'Kjør',
        'running' => 'Kjører …',
        'copy'    => 'Kopier',
        'copied'  => 'Kopiert',
        'output_running' => 'Kjører diagnosen …',
        'fetch_failed'   => 'Klarte ikke å hente diagnosen: {message}',
        'input_required' => 'Skriv inn en verdi før du kjører dette verktøyet.',
        'search_placeholder' => 'Søk i feilsøkingsverktøy …',
        'no_results'         => 'Ingen feilsøkingsverktøy passer med søket ditt.',
    ],

    // Demodata (system/demo-data/index.php)
    'demo' => [
        'heading'  => 'Demodata',
        'subtitle' => 'Importer realistiske eksempeldata modul for modul. Importer Core først, og velg deretter hvilke moduler som skal fylles.',

        'warning_strong' => 'Laget bare for nye installasjoner.',
        'warning_text'   => 'Å importere demodata i et system som allerede inneholder ekte data, kan skape konflikter. Hver modul kan bare importeres én gang.',
        'tip_text_prefix' => 'Importer både',
        'tip_text_and'    => 'og',
        'tip_text_suffix' => 'for å låse opp et ekstravalg som kobler installert programvare til datamaskiner.',
        'tip_assets'      => 'Ressurser',
        'tip_software'    => 'Programvare',

        'step1' => 'Trinn 1 — Påkrevd',
        'step2' => 'Trinn 2 — Velg moduler',
        'step3_cross' => 'Trinn 3 — Data på tvers av moduler',
        'step3_dashboards' => 'Trinn 3 — Dashbord',

        'import'           => 'Importer',
        'importing'        => 'Importerer ...',
        'imported_count'   => '{total} importert',
        'already_imported' => 'Allerede importert',

        'delete_title'   => 'Slett',
        'delete_confirm' => 'Dette sletter eksisterende demodata for {module} og importerer nye. Vil du fortsette?',
        'delete_ok'      => 'Slett',
        'connection_failed' => 'Tilkoblingen feilet: {message}',
    ],

    // Kryptering (system/encryption/index.php)
    'encryption' => [
        'title'    => 'Kryptering',
        'subtitle' => 'Administrer krypteringsnøkkelen som beskytter sensitive data i hvile',
        'checking' => 'Kontrollerer krypteringsstatus ...',

        'how_heading'   => 'Slik fungerer krypteringen',
        'how_point1'    => 'FreeITSM bruker autentisert {strong}-kryptering for å beskytte sensitive data i databasen, som API-nøkler, vCenter-påloggingsdetaljer og tilkoblingsdetaljer for postkasser.',
        'how_point1_strong' => 'AES-256-GCM',
        'how_point2'    => 'Krypteringsnøkkelen er en heksadesimal streng på 64 tegn (256 bit) som lagres i en fil {strong}, slik at den ikke kan hentes via en nettleser.',
        'how_point2_strong' => 'utenfor web-roten',
        'how_point3'    => 'Plassering av nøkkelfilen:',
        'how_point4'    => 'Krypterte verdier i databasen har prefikset {enc} etterfulgt av base64-kodet chiffertekst. Ukrypterte verdier står som de er, slik at migreringen kan skje gradvis.',

        'backup_strong' => 'Ta sikkerhetskopi av krypteringsnøkkelen.',
        'backup_text'   => 'Hvis nøkkelen går tapt, kan ingen data som er kryptert med den, gjenopprettes. Oppbevar en kopi et trygt sted utenfor denne serveren.',

        'whats_heading'    => 'Hva som krypteres',
        'group_settings'   => 'Systeminnstillinger',
        'group_mailbox'    => 'Postkassetilkoblinger',

        'status_ok_title'      => 'Krypteringen er satt opp',
        'status_ok_detail'     => 'Krypteringsnøkkelen finnes og er gyldig i {path}. Sensitive data krypteres i hvile med AES-256-GCM.',
        'status_invalid_title' => 'Ugyldig krypteringsnøkkel',
        'status_invalid_detail'=> 'Det ble funnet en nøkkelfil i {path}, men den er ikke en gyldig heksadesimal streng på 64 tegn. Nøkkelen må bestå av nøyaktig 64 heksadesimale tegn (256 bit).',
        'generate_valid'       => 'Generer gyldig nøkkel',
        'status_missing_title' => 'Fant ingen krypteringsnøkkel',
        'status_missing_detail'=> 'Det finnes ingen krypteringsnøkkelfil i {path}. Sensitive data kan ikke krypteres før en nøkkel er generert. Klikk på knappen under for å generere en automatisk.',
        'generate'             => 'Generer krypteringsnøkkel',
        'generating'           => 'Genererer ...',

        'check_failed' => 'Klarte ikke å kontrollere krypteringsstatusen',
        'error'        => 'Feil: {error}',
        'generate_failed' => 'Klarte ikke å generere nøkkelen',
        'error_prefix' => 'Feil: {message}',
    ],

    // Modultilgang (system/modules/index.php)
    'modules' => [
        'title'    => 'Modultilgang',
        'subtitle' => 'Styr hvilke moduler hver analytiker ser på startsiden og i navigasjonen',

        'info_text' => 'Som standard har alle analytikere tilgang til alle moduler. Slå av {all_access} for å begrense en analytiker til bestemte moduler. System-modulen kan ikke deaktiveres.',
        'all_access_strong' => 'Full tilgang',

        'loading' => 'Laster analytikere ...',

        'empty_heading' => 'Fant ingen analytikere',
        'empty_text'    => 'Legg til analytikere i innstillingene for Saker-modulen først.',

        'col_analyst'    => 'Analytiker',
        'col_all_access' => 'Full tilgang',

        'load_failed' => 'Klarte ikke å laste dataene',
        'save_failed' => 'Klarte ikke å lagre',
    ],

    // Preferanser (system/preferences/index.php)
    'preferences' => [
        'title'    => 'Preferanser',
        'subtitle' => 'Personlige innstillinger som lagres på kontoen din — de følger deg mellom nettlesere.',

        'language_heading' => 'Språk i grensesnittet',
        'language_desc'    => 'Språket som brukes i hele FreeITSM. Tekster som ennå ikke er oversatt til språket du velger, faller tilbake til engelsk. Siden lastes på nytt når du endrer valget.',
        'saving'           => 'Lagrer …',

        'timezone_heading' => 'Tidssone',
        'timezone_desc'    => 'Datoer og klokkeslett i hele FreeITSM vises i denne tidssonen. Serverens tidssone brukes til du velger en selv.',
        'timezone_saved'   => 'Tidssonen er lagret',

        'position_heading' => 'Plassering av varsler',
        'position_desc'    => 'Hvor varslene vises på skjermen.',

        'animation_heading' => 'Animasjon av varsler',
        'animation_desc'    => 'Hvordan varsler kommer inn på og ut av skjermen.',
        'anim_slide'        => 'Skyving',
        'anim_fade'         => 'Toning',

        'panels_heading' => 'Venstrepaneler',
        'panels_desc'    => 'Velg per modul om venstrepanelet står fast åpent eller trekker seg sammen til en smal stripe som utvider seg når du holder musepekeren over. Moduler med egen innstillingsside tilbyr dette også på sin egen Venstrepanel-fane.',
        'panel_knowledge'         => 'Kunnskap',
        'panel_process_mapper'    => 'Process Mapper',
        'panel_contracts'         => 'Kontrakter',
        'panel_calendar'          => 'Kalender',
        'panel_tasks'             => 'Oppgaver',
        'panel_cmdb'              => 'CMDB',
        'panel_change_management' => 'Endringshåndtering',
        'panel_asset_management'  => 'Ressursforvaltning',
        'panel_system_wiki'       => 'Systemwiki',

        // Saksinnboksen: hva som skjer når flere saker er valgt samtidig.
        'multiselect_heading'      => 'Når du velger flere saker',
        'multiselect_desc'         => 'I saksinnboksen kan du velge flere saker samtidig — Ctrl+klikk for å plukke dem én og én, Shift+klikk for en hel blokk. Dette bestemmer hva skjermen viser mens mer enn én er valgt.',
        'multiselect_summary'      => 'Sammendragspanel',
        'multiselect_keep'         => 'Hold saken åpen',
        'multiselect_bar'          => 'Linje over listen',
        'multiselect_summary_hint' => 'Lesefeltet lister opp det du har valgt, med massehandlingene i seg.',
        'multiselect_keep_hint'    => 'Lesefeltet fortsetter å vise saken du åpnet, med en påminnelse om at en handling vil treffe alle sammen.',
        'multiselect_bar_hint'     => 'En kompakt stripe med antall og handlinger vises over sakslisten.',

        'mc_heading' => 'Søylefyll i morgensjekker',
        'mc_desc'    => 'Ensfarget fyll eller gradient i 30-dagers trenddiagrammet for Morgensjekker. Også tilgjengelig på innstillingssiden for Morgensjekker.',
        'fill_plain'    => 'Ensfarget',
        'fill_gradient' => 'Gradient',

        'pos_top_left'      => 'Øverst til venstre',
        'pos_top_center'    => 'Øverst i midten',
        'pos_top_right'     => 'Øverst til høyre',
        'pos_middle_left'   => 'Midt til venstre',
        'pos_middle_center' => 'Midt på',
        'pos_middle_right'  => 'Midt til høyre',
        'pos_bottom_left'   => 'Nederst til venstre',
        'pos_bottom_center' => 'Nederst i midten',
        'pos_bottom_right'  => 'Nederst til høyre',

        'pos_preview'   => 'Varsler vises her',
        'anim_preview'  => 'Forhåndsvisning: {anim}-animasjon',
        'save_failed'   => 'Klarte ikke å lagre',
    ],

    // Sikkerhet (system/security/index.php)
    'security' => [
        'title'    => 'Sikkerhet',
        'subtitle' => 'Konfigurer regler for autentisering og beskyttelse av kontoer',

        'selfreg_heading' => 'Selvregistrering',
        'selfreg_desc'    => 'Om folk kan opprette sin egen konto i selvbetjeningsportalen fra innloggingssiden. Av som standard. Når det er på, blir registreringen fortsatt bekreftet med en e-postlenke før noe passord settes.',
        'selfreg_label'   => 'Tillat selvregistrering',
        'selfreg_hint'    => 'Av = bare kontoer du oppretter, kan logge inn i portalen',
        'trusted_heading' => 'Klarert enhet',
        'trusted_desc'    => 'La brukere hoppe over OTP-verifisering i klarerte nettlesere. Brukerne velger dette selv fra avatarmenyen sin. Sett verdien til 0 for å slå av funksjonen helt.',
        'trust_duration'  => 'Varighet på klarering',
        'trust_duration_hint' => 'Hvor lenge en enhet forblir klarert etter OTP-verifisering',

        'password_heading' => 'Passordregler',
        'password_desc'    => 'Krev at brukerne bytter passord med jevne mellomrom. Når et passord utløper, sendes brukeren til et obligatorisk skjermbilde for passordbytte ved neste innlogging. Sett verdien til 0 for å slå det av.',
        'password_expiry'  => 'Passordutløp',
        'password_expiry_hint' => 'Hvor gammelt et passord kan bli før det må byttes',

        'lockout_heading' => 'Kontosperring',
        'lockout_desc'    => 'Sperr kontoer etter gjentatte mislykkede innloggingsforsøk for å hindre brute force-angrep. Sett maks antall forsøk til 0 for å slå av sperringen.',
        'max_attempts'    => 'Maks mislykkede forsøk',
        'max_attempts_hint' => 'Antall feil passord før kontoen sperres',
        'lockout_duration' => 'Varighet på sperring',
        'lockout_duration_hint' => 'Hvor lenge kontoen forblir sperret (telleren nullstilles etter opplåsing)',

        'ipban_heading' => 'IP-blokkering',
        'ipban_desc'    => 'Blokker automatisk IP-adresser som gjentatte ganger prøver å logge inn på kontoer som ikke finnes eller er sperret. Hver blokkering varer i 24 timer. Etter hver blokkering senkes terskelen med 1 (ned til minimum), slik at gjengangere blir vanskeligere å misbruke. Sett maks antall forsøk til 0 for å slå det av.',
        'first_ban'     => 'Terskel for første blokkering',
        'first_ban_hint' => 'Mislykkede forsøk før IP-adressen blokkeres første gang',
        'min_threshold' => 'Minste terskel',
        'min_threshold_hint' => 'Terskelen slutter å synke når den når denne bunnen',
        'ipban_example_strong' => 'Eksempel:',
        'ipban_example_text'   => 'Med maks 5 og minimum 2 utløses den første blokkeringen etter 5 mislykkede forsøk, den andre etter 4, så 3, så 2. Den blir stående på 2 for hver påfølgende blokkering. Bare forsøk mot brukernavn som ikke finnes, eller kontoer som allerede er sperret, telles.',

        'attachments_heading'       => 'Vedlegg',
        'attachments_desc'          => 'FreeITSM tar bare imot vedleggstyper systemet kjenner igjen. Alt annet — et program, et skript, en nettside — lagres aldri under sitt eget navn, og kan derfor ikke kjøres.',
        'rejected_behaviour'        => 'Når en filtype ikke godtas',
        'rejected_behaviour_hint'   => 'Gjelder for e-post, portalen og chat-kanaler.',
        'rejected_store'            => 'Behold den, kun nedlasting',
        'rejected_drop'             => 'Ikke behold den',
        'rejected_note'             => 'Uansett hva du velger, står det i saken hva som skjedde og hvorfor, så ingen trenger å lure på hvor filen deres ble av.',

        'unit_days'     => 'dager',
        'unit_attempts' => 'forsøk',
        'unit_minutes'  => 'minutter',

        'save'        => 'Lagre',
        'saved'       => 'Sikkerhetsinnstillingene er lagret',
        'error'       => 'Feil: {error}',
        'save_failed' => 'Klarte ikke å lagre innstillingene',
    ],

    // Autentisering (system/sso/index.php)
    'sso' => [
        'title'    => 'Autentisering',
        'subtitle' => 'La brukere logge inn via en ekstern identitetsleverandør (OpenID Connect) som Keycloak, Microsoft Entra, Okta eller Google, eller mot din LDAP / Active Directory — ved siden av lokale kontoer.',

        'global_heading' => 'Globale innstillinger',
        'global_desc'    => 'Hovedbrytere for innlogging i hele systemet.',
        'enable_sso'     => 'Slå på single sign-on',
        'enable_sso_desc'=> 'Vis knappene for de oppsatte leverandørene på innloggingssiden. Slå det av for å falle øyeblikkelig tilbake til lokal innlogging overalt (nødløsning).',
        'allow_local'    => 'Tillat lokal innlogging',
        'allow_local_desc' => 'Behold skjemaet med brukernavn og passord tilgjengelig. La det stå på, slik at en feilkonfigurert eller nede leverandør aldri kan stenge alle ute.',
        'save'           => 'Lagre',

        'redirect_heading' => 'Redirect-URI',
        'redirect_desc'    => 'Registrer nøyaktig denne URL-en hos hver identitetsleverandør som en tillatt redirect- eller callback-URL. Det er hit leverandøren sender brukerne tilbake etter innlogging.',
        'copy'             => 'Kopier',

        'providers_heading' => 'Innloggingsmetoder',
        'providers_desc'    => 'Hver rad er én måte å logge inn på — en identitetsleverandør (SSO) eller en katalog (LDAP). Tildel ulike brukere til ulike metoder for å kjøre piloter parallelt.',
        'add'               => '+ Legg til',

        'col_name'        => 'Navn',
        'col_company'     => 'Selskap',
        'col_issuer'      => 'Utsteder',
        'col_status'      => 'Status',
        'col_auto_create' => 'Auto-opprett',
        'col_actions'     => 'Handlinger',
        'global_badge'    => 'Global',

        'loading'        => 'Laster …',
        'no_providers'   => 'Ingen leverandører ennå. Klikk {add} for å sette opp en.',
        'add_strong'     => 'Legg til',
        'enabled'        => 'På',
        'disabled'       => 'Av',
        'jit_on'         => 'JIT på',
        'jit_off'        => 'Av',
        'edit'           => 'Rediger',
        'delete'         => 'Slett',

        'modal_add_title'  => 'Legg til leverandør',
        'modal_edit_title' => 'Rediger leverandør',
        'field_display_name' => 'Visningsnavn',
        'field_display_name_hint' => 'Vises på innloggingsknappen, f.eks. «Logg inn med Keycloak»',
        'field_display_name_placeholder' => 'Logg inn med Keycloak',
        'field_issuer'     => 'URL til utsteder',
        'field_issuer_hint'=> 'Basis-URL-en til leverandøren, f.eks. http://localhost:8080/realms/freeitsm',
        'field_issuer_placeholder' => 'https://your-idp/realms/your-realm',
        'test'             => 'Test',
        'field_client_id'  => 'Client ID',
        'field_client_id_hint' => 'Identifikatoren for klienten/appen som er opprettet hos leverandøren, f.eks. freeitsm-app',
        'field_client_secret' => 'Client secret',
        'field_client_secret_hint' => 'Klientens hemmelighet fra leverandøren. Lagres kryptert.',
        'field_scopes'     => 'Scopes',
        'field_scopes_hint'=> 'OIDC-scopes atskilt med mellomrom. La standarden stå med mindre leverandøren din trenger flere.',
        'cb_enabled'       => 'På',
        'cb_enabled_desc'  => 'Vis knappen for denne leverandøren på innloggingssiden',
        'cb_autocreate'    => 'Opprett brukere automatisk ved første innlogging (JIT)',
        'cb_autocreate_desc' => 'Opprett en analytiker automatisk første gang noen logger inn via denne leverandøren. La det stå av for stramt styrte piloter der bare forhåndsopprettede brukere skal slippe inn.',
        'cb_verified'      => 'Krev en claim om verifisert e-post',
        'cb_verified_desc' => 'Nekt innlogging med mindre leverandøren sender {claim}. La det stå av for leverandører som utelater claimen helt (f.eks. org-serveren til Okta). En eksplisitt {claim_false} nektes alltid, uansett hva denne innstillingen står på. Slå det bare på for identitetsleverandører der brukere kan registrere seg selv med uverifiserte adresser.',
        'field_default_modules' => 'Standard modultilgang for automatisk opprettede brukere',
        'field_default_modules_hint' => 'Modulnøkler atskilt med komma som gis til analytikere opprettet via JIT (f.eks. {example}). {strong} — sett dette for piloter, slik at automatisk opprettede brukere ikke blir administratorer.',
        'field_default_modules_strong' => 'La feltet stå tomt, så får de full tilgang til alle moduler',
        'field_default_modules_placeholder' => 'tickets, knowledge',
        'field_company'        => 'Selskap',
        'field_company_hint'   => 'Hvilket kundeselskap som eier denne identitetsleverandøren — innmelderne derfra rutes hit i selvbetjeningsportalen. La den stå på Global for en leverandør som brukes internt (f.eks. innlogging for analytikere).',
        'field_company_global' => 'Global (intern / alle)',
        'cancel'           => 'Avbryt',

        // --- LDAP / Active Directory ---
        'field_protocol'       => 'Type',
        'field_protocol_hint'  => 'Hvordan folk logger inn med denne leverandøren.',
        'protocol_oidc'        => 'OpenID Connect (single sign-on)',
        'protocol_ldap'        => 'LDAP / Active Directory',
        'col_type'             => 'Type',
        'ldap_badge'           => 'LDAP',
        'oidc_badge'           => 'OIDC',

        'ldap_preset'          => 'Forhåndsoppsett',
        'ldap_preset_hint'     => 'Fyller inn de vanlige filter- og attributtnavnene for katalogen din. Du kan fortsatt endre alt under.',
        'ldap_preset_ad'       => 'Active Directory',
        'ldap_preset_openldap' => 'OpenLDAP',
        'ldap_preset_applied'  => 'Forhåndsoppsettet er tatt i bruk',

        'field_ldap_host'      => 'Server',
        'field_ldap_host_hint' => 'Vertsnavn eller IP til en domenekontroller, f.eks. dc1.example.local',
        'field_ldap_port'      => 'Port',
        'field_ldap_encryption'=> 'Kryptering',
        'ldap_enc_none'        => 'Ingen (vanlig LDAP)',
        'ldap_enc_starttls'    => 'STARTTLS',
        'ldap_enc_ldaps'       => 'LDAPS (SSL)',
        'ldap_enc_hint'        => 'Passord går over nettverket ved hver innlogging. Bruk STARTTLS eller LDAPS i produksjon — mange Active Directory-servere nekter uansett passord-bind over vanlig LDAP.',

        'field_ldap_bind_dn'   => 'Tjenestekonto',
        'field_ldap_bind_dn_hint' => 'En konto med bare lesetilgang som vi binder oss som for å slå opp brukere — dette er IKKE måten folk logger inn på. Active Directory godtar bruker@domene; OpenLDAP vil ha et fullt DN som cn=svc,dc=example,dc=com. La feltet stå tomt for å søke anonymt (sjelden tillatt).',
        'field_ldap_bind_password' => 'Passord for tjenestekontoen',
        'field_ldap_bind_password_hint' => 'Lagres kryptert. Denne kontoen trenger bare rettighet til å LESE katalogen — gi den aldri skrivetilgang.',
        'bind_password_stored_hint' => 'Et passord er lagret. La feltet stå tomt for å beholde det.',

        'field_ldap_base_dn'   => 'Base DN',
        'field_ldap_base_dn_hint' => 'Hvor i treet søket skal starte, f.eks. DC=example,DC=local. Tjenestekontoen må ha lov til å lese det — hvis ikke, svarer de fleste kataloger «no such object» i stedet for en rettighetsfeil.',
        'field_ldap_filter'    => 'Brukerfilter',
        'field_ldap_filter_hint' => 'Hvordan vi finner personen som logger inn. {token} erstattes av det vedkommende skrev, så ved å liste opp flere attributter kan de bruke brukernavnet sitt ELLER e-postadressen sin.',

        'ldap_attrs_heading'   => 'Attributter',
        'ldap_attrs_desc'      => 'Hvilke felt i katalogen som tilsvarer en FreeITSM-konto. Forhåndsoppsettene over passer for de fleste.',
        'field_ldap_attr_username' => 'Brukernavn',
        'field_ldap_attr_email'    => 'E-post',
        'field_ldap_attr_name'     => 'Fullt navn',
        'field_ldap_attr_guid'     => 'Unik ID',
        'field_ldap_attr_guid_hint'=> 'Et attributt som aldri endrer seg, brukt til å beholde koblingen til FreeITSM-kontoen når noen får nytt navn eller blir flyttet. {ad} i Active Directory, {openldap} i OpenLDAP.',

        'ldap_groups_heading'  => 'Tilgang etter gruppe',
        'ldap_groups_desc'     => 'Oppgi katalog-gruppene som gir tilgang. {strong} Den som ikke er i noen av gruppene, kommer ikke inn, selv med riktig passord — og det er dette som hindrer at automatisk oppretting gjør hver eneste ansatte i katalogen til analytiker.',
        'ldap_groups_desc_strong' => 'La begge stå tomme, så blir alle katalogen kjenner igjen, analytiker.',
        'field_ldap_analyst_group' => 'Analytikergruppe',
        'field_ldap_user_group'    => 'Gruppe for selvbetjeningsbrukere',
        'field_ldap_group_filter'  => 'Gruppefilter — hvordan vi finner gruppene noen er med i. %s erstattes av DN-et deres.',
        'field_ldap_group_base_dn' => 'Base DN for grupper (valgfritt — bruker base DN over som standard)',
        'field_ldap_group_base_dn_placeholder' => 'OU=Groups,DC=example,DC=local',
        'ldap_test_groups'     => 'Grupper: {groups}',
        'ldap_test_role'       => 'Tilgang: {role}',
        'ldap_role_analyst'    => 'analytiker',
        'ldap_role_user'       => 'selvbetjeningsbruker',
        'ldap_role_none'       => 'NEKTET — ikke med i noen av gruppene',

        'ldap_test_heading'    => 'Test',
        'ldap_test_desc'       => 'Kontroller innstillingene før du lagrer. La brukerfeltet stå tomt for bare å teste at tjenestekontoen får koblet seg til og lest base DN.',
        'ldap_test_user'       => 'Brukernavn for test (valgfritt)',
        'ldap_test_pass'       => 'Passord for test',
        'ldap_test_running'    => 'Tester …',
        'ldap_test_found'      => 'Fant: {name} <{email}>',
        'ldap_required_fields' => 'Server, base DN og brukerfilter er påkrevd',
        'ldap_ext_missing'     => 'PHP-utvidelsen «ldap» er ikke aktivert på denne serveren, så LDAP-leverandører kan ikke brukes ennå. Aktiver extension=ldap i php.ini og start webserveren på nytt.',

        'global_saved'   => 'De globale innstillingene er lagret',
        'error'          => 'Feil: {error}',
        'save_failed'    => 'Klarte ikke å lagre',
        'redirect_copied'=> 'Redirect-URI kopiert',
        'enter_issuer'   => 'Skriv inn en URL til utsteder først.',
        'discovery_ok'   => '✓ Discovery OK — utsteder: {issuer}',
        'discovery_err'  => '✗ {error}',
        'request_failed' => '✗ Forespørselen feilet',
        'secret_stored_placeholder' => '•••••••• (la stå tomt for å beholde dagens)',
        'secret_stored_hint' => 'En hemmelighet er allerede lagret. La feltet stå tomt for å beholde den, eller skriv en ny for å erstatte den.',
        'required_fields' => 'Visningsnavn, URL til utsteder og Client ID er påkrevd',
        'provider_saved'  => 'Leverandøren er lagret',
        'delete_confirm'  => 'Vil du slette «{name}»? Brukere som er tildelt den, går tilbake til lokal innlogging.',
        'delete_this'     => 'denne leverandøren',
        'provider_deleted'=> 'Leverandøren er slettet',
        'delete_failed'   => 'Klarte ikke å slette',
    ],

    // Selskaper (system/companies/index.php). «Selskap» er ordet brukerne ser
    // for en tenant; tabellen og koden under heter fortsatt `tenants`.
    'companies' => [
        'title'    => 'Selskaper',
        'subtitle' => 'Kundeselskapene denne installasjonen betjener. Hvert nytt selskap er et eget rom; standardselskapet er alltid aktivt.',

        'add' => 'Legg til',

        'col_name'    => 'Navn',
        'col_domains' => 'E-postdomener',
        'col_status'  => 'Status',
        'col_actions' => 'Handlinger',
        'domains_dash'  => '—',

        'loading'      => 'Laster …',
        'no_companies' => 'Ingen selskaper ennå. Klikk {add} for å opprette ett.',
        'add_strong'   => 'Legg til',
        'default'      => 'Standard',
        'active'       => 'Aktivt',
        'inactive'     => 'Inaktivt',
        'edit'         => 'Rediger',

        'modal_add_title'  => 'Legg til selskap',
        'modal_edit_title' => 'Rediger selskap',
        'field_name'       => 'Navn',
        'field_name_hint'  => 'Selskapsnavnet som vises i hele appen.',
        'field_name_placeholder' => 'Acme AS',
        'cb_active'        => 'Aktivt',
        'cb_active_desc'   => 'Inaktive selskaper skjules fra daglig bruk. Standardselskapet er alltid aktivt.',
        'cancel'          => 'Avbryt',
        'save'            => 'Lagre',

        'required_name' => 'Navn er påkrevd',
        'company_saved' => 'Selskapet er lagret',
        'error'         => 'Feil: {error}',
        'save_failed'   => 'Klarte ikke å lagre',

        // E-postdomener (ruting fra delt mottak)
        'domains_label'       => 'E-postdomener',
        'domains_hint'        => 'E-post fra en postkasse med delt mottak rutes til dette selskapet når avsenderens domene stemmer med ett av disse. Offentlige leverandører (gmail.com og lignende) kan ikke legges til — den posten sorteres manuelt fra triagekøen.',
        'domains_save_first'  => 'Lagre selskapet først, og legg til e-postdomenene etterpå.',
        'domains_none'        => 'Ingen domener ennå.',
        'domain_placeholder'  => 'acme.com',
        'domain_add'          => 'Legg til',
        'domain_remove'       => 'Fjern',
        'domain_added'        => 'Domenet er lagt til',
        'domain_removed'      => 'Domenet er fjernet',
        'domain_add_failed'   => 'Klarte ikke å legge til domenet',
        'domain_remove_failed'=> 'Klarte ikke å fjerne domenet',

        // Bestemte avsendere (ruting fra delt mottak, på adressenivå)
        'senders_label'       => 'Bestemte avsendere',
        'senders_hint'        => 'Enkeltadresser som rutes til dette selskapet, sjekket før domenet. Bruk dette for folk hos offentlige leverandører (jane@gmail.com) der domenet ikke kan kobles — posten deres når likevel riktig selskap i stedet for å havne i triage.',
        'senders_none'        => 'Ingen bestemte avsendere ennå.',
        'sender_placeholder'  => 'jane@gmail.com',
        'sender_add'          => 'Legg til',
        'sender_remove'       => 'Fjern',
        'sender_added'        => 'Avsenderen er lagt til',
        'sender_removed'      => 'Avsenderen er fjernet',
        'sender_add_failed'   => 'Klarte ikke å legge til avsenderen',
        'sender_remove_failed'=> 'Klarte ikke å fjerne avsenderen',

        // «Slik når e-post dette selskapet» — avledet, skrivebeskyttet oppsummering.
        'routing_label'        => 'Slik når e-post dette selskapet',
        'routing_hint'         => 'En skrivebeskyttet oppsummering, utledet fra postkassene og domenene over. Svar går alltid ut fra den samme postkassen meldingen kom inn på.',
        'routing_loading'      => 'Regner ut rutingen …',
        'routing_pinned'       => 'Dedikert postkasse',
        'routing_pinned_desc'  => 'E-post til {address} tilhører alltid dette selskapet. Svar går ut fra denne adressen.',
        'routing_shared'       => 'Delt mottak',
        'routing_shared_desc'  => 'E-post til {address} rutes hit når avsenderens domene er {domains}. Svar går ut fra denne adressen.',
        'routing_shared_desc_senders' => 'E-post til {address} rutes hit når avsenderen er {senders}. Svar går ut fra denne adressen.',
        'routing_shared_desc_both'    => 'E-post til {address} rutes hit når avsenderens domene er {domains}, eller avsenderen er {senders}. Svar går ut fra denne adressen.',
        'routing_reply_from'   => 'Svar fra {address}',
        'routing_inactive'     => 'inaktiv',
        'routing_unauth'       => 'ikke autentisert',
        'routing_default_note' => 'Som standardselskap mottar det også all post som ikke traff noe annet selskap (triagekøen).',
        'routing_warn_no_route'   => 'Ingen automatisk e-postrute. Post til dette selskapet må sorteres manuelt fra triagekøen. Knytt en postkasse til det, eller registrer et e-postdomene så delt mottak kan treffe på det.',
        'routing_warn_domains_no_shared' => 'Det er registrert domener, men det finnes ingen aktiv postkasse med delt mottak å matche dem mot. Legg til en, eller knytt en postkasse til dette selskapet.',
        'routing_warn_unauth'     => 'En postkasse i en av rutene over er ikke autentisert, så posten flyter ikke før den er koblet til på nytt under Innstillinger.',
        'routing_failed'       => 'Kunne ikke laste oppsummeringen av rutingen.',

        // Offentlige e-postdomener (globalt, kan bare legges til)
        'freemail_title'          => 'Offentlige e-postdomener',
        'freemail_hint'           => 'Post fra offentlige leverandører som Gmail og Outlook rutes aldri automatisk til et selskap — to kunder kan bruke samme leverandør, så den havner i triage for å sorteres manuelt. De vanlige leverandørene er alltid med; legg til andre som kundene dine bruker, så behandles de på samme måte.',
        'freemail_placeholder'    => 'example-isp.com',
        'freemail_add'            => 'Legg til',
        'freemail_remove'         => 'Fjern',
        'freemail_none'           => 'Ingen ekstra domener lagt til — bare de innebygde leverandørene under.',
        'freemail_added'          => 'Domenet er lagt til',
        'freemail_removed'        => 'Domenet er fjernet',
        'freemail_add_failed'     => 'Klarte ikke å legge til domenet',
        'freemail_remove_failed'  => 'Klarte ikke å fjerne domenet',
        'freemail_builtin_toggle' => 'Vis de {count} innebygde leverandørene',
    ],

    // Test av e-postruting — simulering (system/email-routing-test/).
    'routing_test' => [
        'title'    => 'Test av e-postruting',
        'subtitle' => 'Lat som om en e-post kom inn, og se hvor en ny sak ville blitt lagt — hvilket selskap, eller triagekøen — og hvilken regel som avgjorde det. Ingenting opprettes; dette leser bare innstillingene dine for postkasser og domener.',
        'single_company_note' => 'Denne installasjonen har bare ett selskap, så all e-post legges til det. Legg til et selskap nummer to for at rutingen skal ha noe å avgjøre.',

        'from_label'       => 'Avsenderadresse',
        'from_hint'        => 'Adressen e-posten kommer fra. Ruting fra delt mottak matcher først på nøyaktig adresse, deretter på domenet.',
        'from_placeholder' => 'jane@acme.com',
        'mailbox_label'    => 'Kommer inn på postkassen',
        'mailbox_hint'     => 'Postkassen som mottok e-posten. En knyttet postkasse avgjør selskapet direkte; en med delt mottak ruter etter avsenderens domene.',
        'mailbox_loading'  => 'Laster …',
        'mailbox_choose'   => 'Velg en postkasse …',
        'no_mailboxes'     => 'Ingen postkasser er satt opp',
        'opt_pinned'       => 'Knyttet til {company}',
        'opt_shared'       => 'Delt mottak',
        'run'              => 'Test',
        'pick_mailbox'     => 'Velg en postkasse først',
        'failed'           => 'Rutingtesten feilet',

        'placeholder'          => 'Kjør en test for å se hvor en e-post ville havnet.',
        'result_company_label' => 'Lagt til selskapet',
        'result_triage_label'  => 'Sendt til',
        'result_triage_value'  => 'Triagekøen (ikke tildelt)',
        'steps_title'          => 'Slik ble det avgjort',

        'step_reply'          => 'Svar på en eksisterende sak?',
        'step_reply_detail'   => 'Sjekkes først i virkeligheten (et svar arver selskapet til saken sin), men det avhenger av at emnefeltet inneholder en saksreferanse — så det kan ikke testes ut fra avsender og postkasse alene.',
        'step_single'         => 'Installasjon med bare ett selskap',
        'step_single_detail'  => 'Det finnes bare ett selskap, så all post legges til {company}.',
        'step_pinned'         => 'Knyttet postkasse?',
        'step_pinned_fired'   => '{mailbox} er knyttet til {company}, så e-posten legges dit. Avsenderen ignoreres.',
        'step_pinned_skipped' => '{mailbox} er en postkasse med delt mottak, så rutingen går videre til avsenderen.',
        'step_sender'         => 'Er avsenderadressen koblet til et selskap?',
        'step_sender_fired'   => 'Adressen {address} står på listen over bestemte avsendere for {company}, så e-posten legges dit. Sjekkes før domenet.',
        'step_sender_nomatch' => 'Ingen selskaper har {address} på listen sin over bestemte avsendere, så rutingen går videre til avsenderens domene.',
        'step_sender_noaddress'=> 'Det ble ikke oppgitt noen avsenderadresse, så det er ingenting å matche på.',
        'step_domain'         => 'Stemmer avsenderdomenet med et selskap?',
        'step_domain_fired'   => 'Domenet {domain} er registrert på {company}.',
        'step_domain_freemail'=> '{domain} er en offentlig e-postleverandør, som aldri registreres på et selskap — så den kan ikke treffe her og går til triage.',
        'step_domain_nomatch' => 'Ingen selskaper har registrert {domain}.',
        'step_domain_nodomain'=> 'Det ble ikke oppgitt noe avsenderdomene, så det er ingenting å matche på.',
        'step_triage'         => 'Triagekø',
        'step_triage_detail'  => 'Ingenting traff, så saken blir stående uten selskap og venter i triagekøen på å bli sortert manuelt. Ingenting går tapt.',
    ],
];
