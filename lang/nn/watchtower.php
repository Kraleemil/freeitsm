<?php
/**
 * Norsk nynorsk (nn) — tekststrengar for Vakttårn-modulen.
 *
 * Manglande nøklar fell tilbake til lang/en/watchtower.php per nøkkel
 * (sjå includes/i18n.php).
 *
 * Vakttårnet er eit dashbord som samlar det som treng merksemd på tvers av
 * modulane. Dekkjer toppteksten, ramma rundt dashbordet, etikettane,
 * måltala og merksemdslinjene på kvart modulkort som blir skrivne ut av
 * JavaScript på sida, og heile hjelpeguiden.
 *
 * IKKJE dekt her (data blir henta direkte frå dei andre modulane):
 * emne på saker, titlar på hendingar, artikkeltitlar, tenestenamn osv.
 */
return [
    'title' => 'Vakttårn',

    'nav' => [
        'dashboard' => 'Dashbord',
        'help'      => 'Hjelp',
    ],

    'dashboard' => [
        'heading'      => 'Oversikt over det som treng merksemd',
        'refresh'      => 'Oppdater',
        'updated'      => 'Oppdatert {time}',
    ],

    // Per-module card names shown in the card header (links to each module).
    'cards' => [
        'morning_checks' => 'Morgonsjekkar',
        'tickets'        => 'Saker',
        'changes'        => 'Endringar',
        'calendar'       => 'Kalender',
        'service_status' => 'Tenestestatus',
        'contracts'      => 'Kontraktar',
        'knowledge'      => 'Kunnskap',
        'assets'         => 'Ressursar',
        'tasks'          => 'Oppgåver',
        'workflows'      => 'Arbeidsflytar',
    ],

    // Workflows card. The engine swallows its own errors by design (so a broken
    // workflow can't break the ticket save that triggered it) — which means a
    // failing workflow is silent. This card is what breaks that silence.
    'workflows' => [
        'all_clear'     => 'Ingen feil i arbeidsflytane',
        'failed'        => '<span class="wt-attention-bold">{count}</span> arbeidsflytkøyringar feila siste 24 timane',
        'aborted'       => '<span class="wt-attention-bold">{count}</span> køyringar vart avbrotne av loopvernet siste 24 timane',
        'dead_webhooks' => '<span class="wt-attention-bold">{count}</span> webhookar gav opp å prøve på nytt — meldinga kom aldri fram',
        'failures'      => '{count} feil',
    ],

    // Morning Checks card.
    'mc' => [
        'metric_done' => 'Utførte',
        'metric_ok'   => 'OK',
        'metric_warn' => 'Åtvaring',
        'metric_fail' => 'Feil',
        'not_started'      => 'Sjekkane er ikkje starta i dag',
        'pending'          => '{count} sjekkar står framleis att',
        'failed'           => '{count} sjekkar feila',
        'warnings'         => '{count} sjekkar med åtvaringar',
        'all_passing'      => 'Alle sjekkar er fullførte og godkjende',
    ],

    // Tickets card.
    'tickets' => [
        'metric_open'   => 'Opne',
        'metric_new'    => 'Nye',
        'metric_active' => 'Aktive',
        'metric_hold'   => 'På vent',
        'urgent_high'   => '<span class="wt-attention-bold">{count}</span> saker med hastande eller høg prioritet',
        'unassigned'    => '<span class="wt-attention-bold">{count}</span> saker som ikkje er tildelte',
        'paused_one'    => '<span class="wt-attention-bold">{count}</span> sak har vore pausa i meir enn {hours} t (SLA-klokka står)',
        'paused_many'   => '<span class="wt-attention-bold">{count}</span> saker har vore pausa i meir enn {hours} t (SLA-klokka står)',
        'all_clear'     => 'Ingen hastesaker',
    ],

    // Changes card.
    'changes' => [
        'metric_next_7d' => 'Neste 7 d',
        'metric_active'  => 'Aktive',
        'metric_pending' => 'Ventar',
        'awaiting'       => '<span class="wt-attention-bold">{count}</span> endringar ventar på godkjenning',
        'in_progress'    => '{count} endringar er i gang no',
        'scheduled'      => '{count} endringar er planlagde denne veka',
        'all_clear'      => 'Ingen endringar på veg',
    ],

    // Calendar card.
    'calendar' => [
        'metric_today' => 'I dag',
        'metric_week'  => 'Denne veka',
        'all_day'      => 'Heile dagen',
        'no_events'    => 'Ingen hendingar i dag',
    ],

    // Service Status card.
    'service' => [
        'all_operational' => 'Alle system er i drift',
        'active_incidents' => '<span class="wt-attention-bold">{count}</span> aktive hendingar',
    ],

    // Contracts card.
    'contracts' => [
        'metric_30d'     => '30 dagar',
        'metric_90d'     => '90 dagar',
        'metric_notices' => 'Varsel',
        'expiring'       => '<span class="wt-attention-bold">{count}</span> kontraktar går ut innan 30 dagar',
        'notices'        => '<span class="wt-attention-bold">{count}</span> oppseiingsfristar nærmar seg',
        'all_clear'      => 'Ingen kontraktar treng merksemd',
    ],

    // Knowledge card.
    'knowledge' => [
        'overdue'         => '<span class="wt-attention-bold">{count}</span> artiklar er forfalne til gjennomgang',
        'published_week'  => 'Publisert denne veka',
        'up_to_date'      => 'Kunnskapsbasen er oppdatert',
    ],

    // Assets card.
    'assets' => [
        'metric_total'    => 'Totalt',
        'metric_offline'  => 'Fråkopla',
        'metric_warranty' => 'Garanti',
        'warranty'        => '<span class="wt-attention-bold">{count}</span> ressursar har garanti som har gått ut eller går ut innan {days} dagar',
        'offline'         => '<span class="wt-attention-bold">{count}</span> ressursar er ikkje sedde på 7 dagar eller meir',
        'all_active'      => 'Alle ressursar har vore aktive nyleg',
    ],

    // Tasks card.
    'tasks' => [
        'metric_todo'   => 'Å gjere',
        'metric_active' => 'Aktive',
        'overdue'       => '<span class="wt-attention-bold">{count}</span> oppgåver over fristen',
        'due_today'     => '<span class="wt-attention-bold">{count}</span> med frist i dag',
        'all_clear'     => 'Ingen oppgåver over fristen',
    ],

    // Help guide.
    'help' => [
        'page_title'   => 'Rettleiing for Vakttårnet',
        'sidebar_label' => 'Rettleiing',
        'hero_title'   => 'Rettleiing for Vakttårnet',
        'hero_subtitle' => 'Eit samla dashbord som viser det du kan handle på frå alle modulane i eitt blikk.',

        'nav_overview'  => 'Oversikt',
        'nav_layout'    => 'Oppsettet på dashbordet',
        'nav_dots'      => 'Slik les du statusprikkane',
        'nav_cards'     => 'Modulkorta forklarte',
        'nav_refresh'   => 'Automatisk oppdatering',
        'nav_tips'      => 'Snøggtips',

        // Section 1 — Overview
        's1_title' => 'Oversikt',
        's1_intro' => 'Vakttårnet er den eine skjermen din for IT-drifta. I staden for å opne kvar modul for seg for å sjå etter hastesaker, hentar Vakttårnet den viktigaste informasjonen frå alle modulane inn i eitt dashbord. I eitt blikk ser du kva som treng merksemd, kva som går knirkefritt, og kvar du bør bruke tida di.',
        's1_feat1_title' => 'Merksemdstavle',
        's1_feat1_desc'  => 'Sjå kva som treng fokuset ditt på tvers av alle modulane på éin stad. Morgonsjekkar, saker, endringar, kalenderhendingar, tenestestatus, kontraktar, kunnskapsartiklar og ressursar er oppsummerte på éin skjerm.',
        's1_feat2_title' => 'Fargekoda status',
        's1_feat2_desc'  => 'Kvart modulkort viser ein grøn, gul eller raud statusprikk for rask sortering. Du ser med det same kva for område som er friske, kva som treng merksemd, og kva som krev handling med ein gong.',
        's1_feat3_title' => 'Automatisk oppdatering',
        's1_feat3_desc'  => 'Dashbordet oppdaterer seg sjølv kvart 5. minutt, så informasjonen held seg fersk utan at du gjer noko. Lat Vakttårnet stå ope, så held det seg oppdatert i bakgrunnen.',
        's1_feat4_title' => 'Klikk deg vidare',
        's1_feat4_desc'  => 'Hopp rett inn i kva modul som helst frå kortet. Kvart modulnamn er ei lenkje som tek deg direkte til rett område, slik at du kan handle på problemet utan å leite etter rett side.',

        // Section 2 — Dashboard layout
        's2_title' => 'Oppsettet på dashbordet',
        's2_p1' => 'Dashbordet i Vakttårnet brukar eit responsivt rutenett med tre kolonnar av modulkort. På mindre skjermar går rutenettet ned til to kolonnar eller éin, så det verkar på kva eining som helst. Over rutenettet ligg tittellinja med ein oppdateringsknapp og eit «Oppdatert»-tidsstempel som viser når data sist vart henta.',
        's2_p2' => 'Kvart kort i rutenettet har same oppbygging, slik at du kan skumme dei raskt:',
        's2_diagram_name'   => 'Modulnamn',
        's2_diagram_open'   => 'OPNE',
        's2_diagram_active' => 'AKTIVE',
        's2_diagram_hold'   => 'PÅ VENT',
        's2_diagram_clear'  => 'Alt i orden — ingen hastesaker',
        's2_field_icon'    => '<strong>Farga ikon</strong> &mdash; eit lite firkanta ikon i temafargen til modulen (turkis for Morgonsjekkar, blå for Saker osv.) slik at du kjenner att kvart kort med det same.',
        's2_field_name'    => '<strong>Modulnamn</strong> &mdash; ei lenkje som går rett til den modulen. Klikk for å hoppe inn og gjere noko med det.',
        's2_field_dot'     => '<strong>Statusprikk</strong> &mdash; ein grøn, gul eller raud prikk oppe til høgre som viser kor mykje det hastar i den modulen.',
        's2_field_metrics' => '<strong>Nøkkeltal</strong> &mdash; store tal som oppsummerer dei viktigaste tellingane (til dømes opne saker, fullførte sjekkar, kontraktar som går ut).',
        's2_field_attention' => '<strong>Merksemdspunkt</strong> &mdash; fargekoda meldingslinjer som framhevar kva som konkret treng merksemda di i den modulen.',
        's2_tip' => 'Kortoppsettet er laga for å skummast, ikkje for djup analyse. Bruk Vakttårnet til å finne ut kva for modular som treng merksemda di, og klikk deg deretter inn i sjølve modulen for alle detaljane.',

        // Section 3 — Status dots
        's3_title' => 'Slik les du statusprikkane',
        's3_intro' => 'Kvart modulkort viser ein statusprikk i toppen. Prikken er eit visuelt varsel som seier med det same om det området av IT-drifta treng merksemd. Fargen blir sett automatisk ut frå dataa modulen leverer.',
        's3_green_label' => 'Grøn',
        's3_green_desc'  => 'Alt er i orden. Ingenting må gjerast. Modulen er i god stand, utan uløyste problem eller punkt som treng merksemd.',
        's3_green_examples' => '<strong>Døme:</strong> Alle morgonsjekkar er godkjende, ingen hastesaker, alle system i drift, ingen kontraktar som går ut snart.',
        's3_amber_label' => 'Gul',
        's3_amber_desc'  => 'Noko treng merksemd, men det er ikkje kritisk. Det finst punkt du bør sjå på når du får høve til det, men ingenting brenn.',
        's3_amber_examples' => '<strong>Døme:</strong> Sjekkar med åtvaringar, saker som ikkje er tildelte, endringar som ventar på godkjenning, kontraktar som går ut innan 90 dagar.',
        's3_red_label' => 'Raud',
        's3_red_desc'  => 'Hastepunkt som krev handling med ein gong. Noko har feila, er over fristen eller er kritisk påverka og må ordnast no.',
        's3_red_examples' => '<strong>Døme:</strong> Morgonsjekkar som ikkje er starta eller som feila, saker med hastande eller høg prioritet, store tenestebrot, kontraktar som går ut innan 30 dagar.',
        's3_tip' => 'Tenk på prikkane som eit trafikklys. Grøn tyder at du kan halde fram med dagen, gul tyder at du bør sjå på det når du kan, og raud tyder at du bør leggje frå deg det du held på med og undersøkje. Målet er å halde alle prikkane grøne.',

        // Section 4 — Module cards explained
        's4_title' => 'Modulkorta forklarte',
        's4_intro' => 'Vakttårnet overvakar åtte modular. Kvart kort er tilpassa for å vise det mest relevante for det området. Her er kva kvart kort viser, og kva som avgjer fargen på statusprikken.',
        's4_mc_title'    => 'Morgonsjekkar',
        's4_mc_desc'     => 'Viser kor langt du har komme (til dømes 8/10 utførte) saman med talet på OK, åtvaring og feil. Merksemdspunkta varslar når sjekkane ikkje er starta, eller når nokon av dei har feila.',
        's4_mc_triggers' => '<strong>Raud:</strong> Sjekkane er ikkje starta i dag, eller nokon av dei har feila. <strong>Gul:</strong> Sjekkane er ikkje fullførte, eller det finst åtvaringar. <strong>Grøn:</strong> Alle sjekkar er fullførte og godkjende.',
        's4_tk_title'    => 'Saker',
        's4_tk_desc'     => 'Viser talet på opne saker fordelt på Nye, Aktive og På vent. Merksemdspunkta framhevar saker med hastande eller høg prioritet og saker som ikkje er tildelte.',
        's4_tk_triggers' => '<strong>Raud:</strong> Det finst saker med hastande eller høg prioritet. <strong>Gul:</strong> Det finst saker som ikkje er tildelte. <strong>Grøn:</strong> Ingen hastesaker og ingen utildelte saker.',
        's4_ch_title'    => 'Endringar',
        's4_ch_desc'     => 'Viser kor mange endringar som er planlagde dei neste 7 dagane, kor mange som er i gang no, og kor mange som ventar på godkjenning. Merksemdspunkta peikar på endringar som ikkje er godkjende, og på aktive endringar.',
        's4_ch_triggers' => '<strong>Gul:</strong> Endringar ventar på godkjenning. <strong>Grøn:</strong> Ingen endringar utan godkjenning.',
        's4_cal_title'    => 'Kalender',
        's4_cal_desc'     => 'Viser kor mange hendingar det er i dag og denne veka. Er det hendingar i dag, blir dei lista opp med klokkeslett (eller «Heile dagen» for heildagshendingar).',
        's4_cal_triggers' => '<strong>Gul:</strong> Det er hendingar i dag. <strong>Grøn:</strong> Ingen hendingar i dag.',
        's4_ss_title'    => 'Tenestestatus',
        's4_ss_desc'     => 'Viser talet på aktive hendingar og listar opp tenestene som er råka, med merke for kor stor påverknaden er (Stort brot, Delvis brot, Redusert, Vedlikehald). Når alt er friskt, kjem det opp eit grønt banner med «Alle system er i drift».',
        's4_ss_triggers' => '<strong>Raud:</strong> Stort eller delvis brot på ei teneste. <strong>Gul:</strong> Redusert drift eller vedlikehald. <strong>Grøn:</strong> Alle system er i drift.',
        's4_ct_title'    => 'Kontraktar',
        's4_ct_desc'     => 'Viser kontraktar som går ut innan 30 dagar, innan 90 dagar, og oppseiingsfristar som nærmar seg. Merksemdspunkta varslar om utløp som står for døra og om oppseiingsfristar som kjem.',
        's4_ct_triggers' => '<strong>Raud:</strong> Kontraktar går ut innan 30 dagar. <strong>Gul:</strong> Kontraktar går ut innan 90 dagar, eller oppseiingsfristar nærmar seg. <strong>Grøn:</strong> Ingen kontraktar treng merksemd.',
        's4_kb_title'    => 'Kunnskap',
        's4_kb_desc'     => 'Viser kor mange artiklar som er forfalne til gjennomgang, og listar opp artiklar som er publiserte denne veka. Når ingen gjennomgangar er forfalne og kunnskapsbasen er oppdatert, viser kortet ei melding om at alt er i orden.',
        's4_kb_triggers' => '<strong>Gul:</strong> Artiklar er forfalne til gjennomgang. <strong>Grøn:</strong> Kunnskapsbasen er oppdatert.',
        's4_as_title'    => 'Ressursar',
        's4_as_desc'     => 'Viser kor mange ressursar som er følgde opp totalt, og kor mange som ikkje er sedde på 7 dagar eller meir. Det hjelper deg å finne einingar som kan vere fråkopla, utrangerte eller borte.',
        's4_as_triggers' => '<strong>Gul:</strong> Ressursar er ikkje sedde på 7 dagar eller meir. <strong>Grøn:</strong> Alle ressursar har vore aktive nyleg.',

        // Section 5 — Auto-refresh
        's5_title' => 'Automatisk og manuell oppdatering',
        's5_intro' => 'Vakttårnet er laga som eit passivt overvakingsverktøy som du kan la stå ope i ei nettlesarfane heile dagen. Dashbordet held seg ferskt gjennom automatiske oppdateringar.',
        's5_step1' => '<strong>Automatisk oppdatering</strong> &mdash; dashbordet hentar ferske data frå alle modulane kvart 5. minutt. Du treng ikkje laste sida på nytt eller klikke på noko; korta og statusprikkane oppdaterer seg stille i bakgrunnen.',
        's5_step2' => '<strong>Manuell oppdatering</strong> &mdash; klikk på knappen <strong>Oppdater</strong> oppe til høgre for å hente dei nyaste dataa med ein gong. Ikonet på knappen snurrar medan førespurnaden går, så du ser at nye data blir lasta.',
        's5_step3' => '<strong>Tidsstempel</strong> &mdash; ved sida av oppdateringsknappen viser eit tidsstempel når data sist vart henta (til dømes «Oppdatert 09:15»). Då veit du nøyaktig kor ferske opplysningane på skjermen er.',
        's5_tip' => 'Lat Vakttårnet stå ope i ei eiga nettlesarfane for passiv overvaking. Oppdatering kvart 5. minutt gjer at du alltid har eit nær sanntidsbilete av IT-drifta utan å måtte sjekke kvar modul manuelt.',

        // Section 6 — Quick tips
        's6_title' => 'Snøggtips',
        's6_tip1_title' => 'Start dagen her',
        's6_tip1_desc'  => 'Opne Vakttårnet med det same kvar morgon for ei rask oversikt over drifta. På nokre sekund ser du om morgonsjekkane er gjorde, om nokon saker hastar, og om alle tenestene er friske.',
        's6_tip2_title' => 'Raude prikkar først',
        's6_tip2_desc'  => 'Ta dei raude statusprikkane før alt anna. Dei viser hastepunkt som treng merksemd med ein gong &mdash; sjekkar som har feila, saker med høg prioritet eller tenestebrot som råkar brukarane akkurat no.',
        's6_tip3_title' => 'Klikk deg inn',
        's6_tip3_desc'  => 'Klikk på eit modulnamn på eit kort for å gå rett til den modulen. Du treng ikkje hovudmenyen eller modulvelaren &mdash; Vakttårnet er ein direkte snarveg dit merksemda trengst.',
        's6_tip4_title' => 'Trykk Oppdater for det nyaste',
        's6_tip4_desc'  => 'Sjølv om dashbordet oppdaterer seg kvart 5. minutt, kan du klikke på Oppdater når som helst for å få heilt ferske data. Nyttig etter at du har løyst noko, for å sjå at statusprikken har skifta.',
        's6_tip5_title' => 'Bruk det i teammøte',
        's6_tip5_desc'  => 'Vis Vakttårnet på storskjerm under stand-up eller driftsmøte. Dei fargekoda prikkane gjer det lett å snakke om kva for område som treng merksemd, og å fordele ansvaret for dei gule og raude punkta.',
        's6_tip6_title' => 'Grønt tyder alt i orden',
        's6_tip6_desc'  => 'Når kvar einaste prikk på dashbordet er grøn, er IT-drifta i god form. Ingen hastesaker, ingen sjekkar som har feila, ingen kontraktar som går ut, og alle tenester i drift. Det er målet.',
    ],
];
