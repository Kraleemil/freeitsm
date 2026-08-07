<?php
/**
 * Norsk bokmål (nb) — tekster for Vakttårn-modulen.
 *
 * Faller tilbake per nøkkel til lang/en/watchtower.php for det som mangler her
 * (se includes/i18n.php). Nøkkelrekkefølgen følger lang/en/watchtower.php.
 *
 * Vakttårn er et dashbord som samler alt som krever oppmerksomhet på tvers av
 * modulene. Dekker toppfeltet, rammen rundt dashbordet, etiketter, nøkkeltall og
 * oppmerksomhetslinjer på modulkortene som tegnes av innebygd JS, samt hele
 * hjelpeveiledningen.
 *
 * IKKE dekket her (data hentes direkte fra andre moduler): saksemner,
 * hendelsestitler, artikkeltitler, tjenestenavn og lignende.
 */
return [
    'title' => 'Vakttårn',

    'nav' => [
        'dashboard' => 'Dashbord',
        'help'      => 'Hjelp',
    ],

    'dashboard' => [
        'heading'      => 'Oversikt over det som krever oppmerksomhet',
        'refresh'      => 'Oppdater',
        'updated'      => 'Oppdatert {time}',
    ],

    // Per-module card names shown in the card header (links to each module).
    'cards' => [
        'morning_checks' => 'Morgenkontroller',
        'tickets'        => 'Saker',
        'changes'        => 'Endringer',
        'calendar'       => 'Kalender',
        'service_status' => 'Tjenestestatus',
        'contracts'      => 'Kontrakter',
        'knowledge'      => 'Kunnskap',
        'assets'         => 'Ressurser',
        'tasks'          => 'Oppgaver',
        'workflows'      => 'Arbeidsflyter',
    ],

    // Workflows card. The engine swallows its own errors by design (so a broken
    // workflow can't break the ticket save that triggered it) — which means a
    // failing workflow is silent. This card is what breaks that silence.
    'workflows' => [
        'all_clear'     => 'Ingen feil i arbeidsflyter',
        'failed'        => '<span class="wt-attention-bold">{count}</span> arbeidsflytkjøringer feilet siste 24 t',
        'aborted'       => '<span class="wt-attention-bold">{count}</span> kjøringer ble avbrutt av løkkebeskyttelsen siste 24 t',
        'dead_webhooks' => '<span class="wt-attention-bold">{count}</span> webhooks ga opp etter gjentatte forsøk — meldingen kom aldri fram',
        'failures'      => '{count} feil',
    ],

    // Morning Checks card.
    'mc' => [
        'metric_done' => 'Utført',
        'metric_ok'   => 'OK',
        'metric_warn' => 'Advarsel',
        'metric_fail' => 'Feil',
        'not_started'      => 'Kontrollene er ikke startet i dag',
        'pending'          => '{count} kontroller gjenstår',
        'failed'           => '{count} kontroller feilet',
        'warnings'         => '{count} kontroller med advarsler',
        'all_passing'      => 'Alle kontroller er utført og bestått',
    ],

    // Tickets card.
    'tickets' => [
        'metric_open'   => 'Åpne',
        'metric_new'    => 'Nye',
        'metric_active' => 'Aktive',
        'metric_hold'   => 'På vent',
        'urgent_high'   => '<span class="wt-attention-bold">{count}</span> saker med haster/høy prioritet',
        'unassigned'    => '<span class="wt-attention-bold">{count}</span> saker uten tildeling',
        'paused_one'    => '<span class="wt-attention-bold">{count}</span> sak har stått på pause i over {hours} t (SLA-klokken er stoppet)',
        'paused_many'   => '<span class="wt-attention-bold">{count}</span> saker har stått på pause i over {hours} t (SLA-klokken er stoppet)',
        'all_clear'     => 'Ingen hastesaker',
    ],

    // Changes card.
    'changes' => [
        'metric_next_7d' => 'Neste 7 d',
        'metric_active'  => 'Aktive',
        'metric_pending' => 'Venter',
        'awaiting'       => '<span class="wt-attention-bold">{count}</span> endringer venter på godkjenning',
        'in_progress'    => '{count} endringer pågår nå',
        'scheduled'      => '{count} endringer er planlagt denne uken',
        'all_clear'      => 'Ingen kommende endringer',
    ],

    // Calendar card.
    'calendar' => [
        'metric_today' => 'I dag',
        'metric_week'  => 'Denne uken',
        'all_day'      => 'Hele dagen',
        'no_events'    => 'Ingen hendelser i dag',
    ],

    // Service Status card.
    'service' => [
        'all_operational' => 'Alle systemer fungerer normalt',
        'active_incidents' => '<span class="wt-attention-bold">{count}</span> aktive hendelser',
    ],

    // Contracts card.
    'contracts' => [
        'metric_30d'     => '30 dager',
        'metric_90d'     => '90 dager',
        'metric_notices' => 'Frister',
        'expiring'       => '<span class="wt-attention-bold">{count}</span> kontrakter utløper innen 30 dager',
        'notices'        => '<span class="wt-attention-bold">{count}</span> oppsigelsesfrister nærmer seg',
        'all_clear'      => 'Ingen kontrakter krever oppmerksomhet',
    ],

    // Knowledge card.
    'knowledge' => [
        'overdue'         => '<span class="wt-attention-bold">{count}</span> artikler er forfalt til gjennomgang',
        'published_week'  => 'Publisert denne uken',
        'up_to_date'      => 'Kunnskapsbasen er oppdatert',
    ],

    // Assets card.
    'assets' => [
        'metric_total'    => 'Totalt',
        'metric_offline'  => 'Frakoblet',
        'metric_warranty' => 'Garanti',
        'warranty'        => '<span class="wt-attention-bold">{count}</span> ressurser har utløpt garanti, eller garanti som utløper innen {days} dager',
        'offline'         => '<span class="wt-attention-bold">{count}</span> ressurser er ikke sett på 7 dager eller mer',
        'all_active'      => 'Alle ressurser har vært aktive nylig',
    ],

    // Tasks card.
    'tasks' => [
        'metric_todo'   => 'Å gjøre',
        'metric_active' => 'Aktive',
        'overdue'       => '<span class="wt-attention-bold">{count}</span> forfalte oppgaver',
        'due_today'     => '<span class="wt-attention-bold">{count}</span> har frist i dag',
        'all_clear'     => 'Ingen forfalte oppgaver',
    ],

    // Help guide.
    'help' => [
        'page_title'   => 'Vakttårn-veiledning',
        'sidebar_label' => 'Veiledning',
        'hero_title'   => 'Vakttårn-veiledning',
        'hero_subtitle' => 'Et samlet dashbord som viser alt som krever handling fra alle moduler, på ett blikk.',

        'nav_overview'  => 'Oversikt',
        'nav_layout'    => 'Oppsettet på dashbordet',
        'nav_dots'      => 'Slik leser du statusprikkene',
        'nav_cards'     => 'Modulkortene forklart',
        'nav_refresh'   => 'Automatisk oppdatering',
        'nav_tips'      => 'Raske tips',

        // Section 1 — Overview
        's1_title' => 'Oversikt',
        's1_intro' => 'Vakttårn er ditt ene vindu inn i IT-driften. I stedet for å åpne hver modul for seg for å lete etter hastesaker, henter Vakttårn den viktigste informasjonen fra alle moduler inn i ett dashbord. På ett blikk ser du hva som krever oppmerksomhet, hva som går som det skal, og hvor du bør bruke tiden din.',
        's1_feat1_title' => 'Oppmerksomhetstavle',
        's1_feat1_desc'  => 'Se hva som trenger fokus på tvers av alle moduler, på ett sted. Morgenkontroller, saker, endringer, kalenderhendelser, tjenestestatus, kontrakter, kunnskapsartikler og ressurser er oppsummert på én skjerm.',
        's1_feat2_title' => 'Fargekodet status',
        's1_feat2_desc'  => 'Hvert modulkort viser en grønn, gul eller rød statusprikk for rask triagering. Du ser umiddelbart hvilke områder som er i god stand, hvilke som trenger oppmerksomhet, og hvilke som krever handling med en gang.',
        's1_feat3_title' => 'Automatisk oppdatering',
        's1_feat3_desc'  => 'Dashbordet oppdateres automatisk hvert 5. minutt, så informasjonen holder seg fersk uten at du gjør noe. La Vakttårn stå åpent, så holder det seg oppdatert i bakgrunnen.',
        's1_feat4_title' => 'Klikk deg videre',
        's1_feat4_desc'  => 'Hopp rett inn i en modul fra kortet. Hvert modulnavn er en lenke som tar deg direkte til riktig område, så du kan håndtere problemet uten å lete etter riktig side.',

        // Section 2 — Dashboard layout
        's2_title' => 'Oppsettet på dashbordet',
        's2_p1' => 'Vakttårn-dashbordet bruker et responsivt rutenett med 3 kolonner av modulkort. På mindre skjermer går rutenettet ned til 2 kolonner eller én kolonne, så det fungerer på alle enheter. Over rutenettet ligger tittellinjen med en oppdateringsknapp og et «Oppdatert»-tidsstempel som viser når dataene sist ble hentet.',
        's2_p2' => 'Hvert kort i rutenettet følger den samme strukturen, så du raskt kan skumme gjennom dem:',
        's2_diagram_name'   => 'Modulnavn',
        's2_diagram_open'   => 'ÅPNE',
        's2_diagram_active' => 'AKTIVE',
        's2_diagram_hold'   => 'PÅ VENT',
        's2_diagram_clear'  => 'Alt i orden — ingen hastesaker',
        's2_field_icon'    => '<strong>Farget ikon</strong> &mdash; et lite firkantet ikon i modulens temafarge (turkis for Morgenkontroller, blå for Saker osv.) slik at du kjenner igjen hvert kort med en gang.',
        's2_field_name'    => '<strong>Modulnavn</strong> &mdash; en lenke som går rett til modulen. Klikk for å hoppe inn og gjøre noe med det.',
        's2_field_dot'     => '<strong>Statusprikk</strong> &mdash; en grønn, gul eller rød prikk øverst til høyre som viser det samlede hastenivået for modulen.',
        's2_field_metrics' => '<strong>Nøkkeltall</strong> &mdash; store tall som oppsummerer de viktigste antallene (for eksempel åpne saker, utførte kontroller, kontrakter som utløper).',
        's2_field_attention' => '<strong>Oppmerksomhetspunkter</strong> &mdash; fargekodede meldingslinjer som fremhever nøyaktig hva som krever oppmerksomhet i den modulen.',
        's2_tip' => 'Kortoppsettet er laget for å skummes, ikke for dyp analyse. Bruk Vakttårn til å finne ut hvilke moduler som trenger oppmerksomhet, og klikk deg deretter inn i selve modulen for alle detaljene.',

        // Section 3 — Status dots
        's3_title' => 'Slik leser du statusprikkene',
        's3_intro' => 'Hvert modulkort viser en statusprikk i toppen. Prikken gir en umiddelbar visuell pekepinn på om det området av IT-driften trenger oppmerksomhet. Fargen settes automatisk ut fra dataene hver modul returnerer.',
        's3_green_label' => 'Grønn',
        's3_green_desc'  => 'Alt er i orden. Ingen handling nødvendig. Modulen er i god stand, uten utestående problemer eller punkter som krever oppmerksomhet.',
        's3_green_examples' => '<strong>Eksempler:</strong> Alle morgenkontroller bestått, ingen hastesaker, alle systemer fungerer normalt, ingen kontrakter utløper snart.',
        's3_amber_label' => 'Gul',
        's3_amber_desc'  => 'Noe trenger oppmerksomhet, men det er ikke kritisk. Det finnes punkter du bør se på når du får anledning, men ingenting brenner.',
        's3_amber_examples' => '<strong>Eksempler:</strong> Kontroller med advarsler, saker uten tildeling, endringer som venter på godkjenning, kontrakter som utløper innen 90 dager.',
        's3_red_label' => 'Rød',
        's3_red_desc'  => 'Hastepunkter krever handling nå. Noe har feilet, er forfalt eller er kritisk påvirket og må håndteres umiddelbart.',
        's3_red_examples' => '<strong>Eksempler:</strong> Morgenkontroller som ikke er startet eller har feilet, saker med haster/høy prioritet, større tjenesteavbrudd, kontrakter som utløper innen 30 dager.',
        's3_tip' => 'Tenk på prikkene som et trafikklys. Grønt betyr at du kan gå videre med dagen, gult betyr se på det når du får tid, og rødt betyr legg fra deg det du holder på med og undersøk. Målet er å holde alle prikkene grønne.',

        // Section 4 — Module cards explained
        's4_title' => 'Modulkortene forklart',
        's4_intro' => 'Vakttårn overvåker åtte moduler. Hvert kort er tilpasset for å vise det mest relevante for sitt område. Her er hva hvert kort viser, og hva som utløser fargen på statusprikken.',
        's4_mc_title'    => 'Morgenkontroller',
        's4_mc_desc'     => 'Viser fremdrift (for eksempel 8/10 utført) pluss antall resultater med OK, Advarsel og Feil. Oppmerksomhetspunktene flagger når kontrollene ikke er startet, eller når noen har feilet.',
        's4_mc_triggers' => '<strong>Rød:</strong> Kontrollene er ikke startet i dag, eller noen kontroller har feilet. <strong>Gul:</strong> Kontrollene er ikke fullført, eller det finnes advarsler. <strong>Grønn:</strong> Alle kontroller er utført og bestått.',
        's4_tk_title'    => 'Saker',
        's4_tk_desc'     => 'Viser totalt antall åpne saker fordelt på Nye, Aktive og På vent. Oppmerksomhetspunktene fremhever saker med haster/høy prioritet og saker uten tildeling.',
        's4_tk_triggers' => '<strong>Rød:</strong> Det finnes saker med haster eller høy prioritet. <strong>Gul:</strong> Det finnes saker uten tildeling. <strong>Grønn:</strong> Ingen hastesaker og ingen saker uten tildeling.',
        's4_ch_title'    => 'Endringer',
        's4_ch_desc'     => 'Viser hvor mange endringer som er planlagt de neste 7 dagene, hvor mange som pågår nå, og hvor mange som venter på godkjenning. Oppmerksomhetspunktene peker ut endringer som ikke er godkjent, og endringer som pågår.',
        's4_ch_triggers' => '<strong>Gul:</strong> Endringer venter på godkjenning. <strong>Grønn:</strong> Ingen endringer uten godkjenning.',
        's4_cal_title'    => 'Kalender',
        's4_cal_desc'     => 'Viser antall hendelser i dag og denne uken. Er det hendelser i dag, listes de opp med klokkeslett (eller «Hele dagen» for heldagshendelser).',
        's4_cal_triggers' => '<strong>Gul:</strong> Det er hendelser i dag. <strong>Grønn:</strong> Ingen hendelser i dag.',
        's4_ss_title'    => 'Tjenestestatus',
        's4_ss_desc'     => 'Viser antall aktive hendelser og lister opp berørte tjenester med merker for påvirkningsgrad (Større avbrudd, Delvis avbrudd, Redusert, Vedlikehold). Når alt er i orden, vises et grønt banner med «Alle systemer fungerer normalt».',
        's4_ss_triggers' => '<strong>Rød:</strong> Større eller delvis avbrudd på en tjeneste. <strong>Gul:</strong> Redusert ytelse eller vedlikehold. <strong>Grønn:</strong> Alle systemer fungerer normalt.',
        's4_ct_title'    => 'Kontrakter',
        's4_ct_desc'     => 'Viser kontrakter som utløper innen 30 dager, kontrakter som utløper innen 90 dager, og oppsigelsesfrister som nærmer seg. Oppmerksomhetspunktene varsler om nært forestående utløp og kommende oppsigelsesfrister.',
        's4_ct_triggers' => '<strong>Rød:</strong> Kontrakter utløper innen 30 dager. <strong>Gul:</strong> Kontrakter utløper innen 90 dager, eller oppsigelsesfrister nærmer seg. <strong>Grønn:</strong> Ingen kontrakter krever oppmerksomhet.',
        's4_kb_title'    => 'Kunnskap',
        's4_kb_desc'     => 'Viser hvor mange artikler som er forfalt til gjennomgang, og lister opp artikler som er publisert denne uken. Når ingen gjennomganger er forfalt og kunnskapsbasen er oppdatert, viser kortet en melding om at alt er i orden.',
        's4_kb_triggers' => '<strong>Gul:</strong> Artikler er forfalt til gjennomgang. <strong>Grønn:</strong> Kunnskapsbasen er oppdatert.',
        's4_as_title'    => 'Ressurser',
        's4_as_desc'     => 'Viser totalt antall ressurser som følges opp, og hvor mange som ikke er sett på 7 dager eller mer. Det hjelper deg å finne enheter som kan være frakoblet, utrangert eller mistet.',
        's4_as_triggers' => '<strong>Gul:</strong> Ressurser er ikke sett på 7 dager eller mer. <strong>Grønn:</strong> Alle ressurser har vært aktive nylig.',

        // Section 5 — Auto-refresh
        's5_title' => 'Automatisk og manuell oppdatering',
        's5_intro' => 'Vakttårn er laget som et passivt overvåkingsverktøy du kan la stå åpent i en nettleserfane hele dagen. Dashbordet holder seg oppdatert gjennom automatiske oppdateringer.',
        's5_step1' => '<strong>Automatisk oppdatering</strong> &mdash; dashbordet henter ferske data fra alle moduler hvert 5. minutt. Du trenger ikke laste siden på nytt eller klikke på noe; kortene og statusprikkene oppdateres stille i bakgrunnen.',
        's5_step2' => '<strong>Manuell oppdatering</strong> &mdash; klikk på <strong>Oppdater</strong> øverst til høyre for å hente de nyeste dataene med en gang. Ikonet på knappen snurrer mens forespørselen pågår, som en bekreftelse på at nye data lastes inn.',
        's5_step3' => '<strong>Tidsstempel</strong> &mdash; ved siden av oppdateringsknappen viser et tidsstempel når dataene sist ble hentet (for eksempel «Oppdatert 09:15»). Da vet du nøyaktig hvor ferske opplysningene på skjermen er.',
        's5_tip' => 'Ha Vakttårn åpent i en egen nettleserfane for passiv overvåking. Oppdatering hvert 5. minutt betyr at du alltid har et nærmest sanntids bilde av IT-driften, uten å måtte sjekke hver modul manuelt.',

        // Section 6 — Quick tips
        's6_title' => 'Raske tips',
        's6_tip1_title' => 'Start dagen her',
        's6_tip1_desc'  => 'Åpne Vakttårn som det første du gjør hver morgen for en rask driftsoversikt. På sekunder ser du om morgenkontrollene er gjort, om noen saker haster, og om alle tjenester fungerer som de skal.',
        's6_tip2_title' => 'Røde prikker først',
        's6_tip2_desc'  => 'Ta de røde statusprikkene før alt annet. De peker på hastepunkter som krever oppmerksomhet med en gang &mdash; kontroller som har feilet, saker med høy prioritet eller tjenesteavbrudd som rammer brukerne akkurat nå.',
        's6_tip3_title' => 'Klikk deg inn',
        's6_tip3_desc'  => 'Klikk på et modulnavn på et kort for å gå rett til den modulen. Du trenger verken hovedmenyen eller modulvelgeren &mdash; Vakttårn er en snarvei rett dit oppmerksomheten trengs.',
        's6_tip4_title' => 'Trykk Oppdater for det nyeste',
        's6_tip4_desc'  => 'Selv om dashbordet oppdaterer seg selv hvert 5. minutt, kan du klikke på Oppdater når som helst for å få helt ferske data. Nyttig etter at du har løst noe, for å bekrefte at statusprikken har endret seg.',
        's6_tip5_title' => 'Bruk det i teammøter',
        's6_tip5_desc'  => 'Vis Vakttårn på storskjerm under stand-up eller driftsmøter. De fargekodede prikkene gjør det enkelt å snakke om hvilke områder som trenger oppmerksomhet, og å fordele ansvaret for gule og røde punkter.',
        's6_tip6_title' => 'Grønt betyr alt i orden',
        's6_tip6_desc'  => 'Når alle prikkene på dashbordet er grønne, er IT-driften i god form. Ingen hastesaker, ingen kontroller som har feilet, ingen kontrakter som utløper, og alle tjenester i drift. Det er målet.',
    ],
];
