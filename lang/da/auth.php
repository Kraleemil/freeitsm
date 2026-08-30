<?php
/**
 * Danish (da) — Analyst sign-in page strings.
 *
 * Mirrors lang/en/auth.php. Sentence case, "du" register, one-word buttons,
 * matching lang/da/common.php and lang/da/self-service.php.
 */
return [
    'browser_title' => 'Log ind på Service Desk',
    'heading'       => 'ITSM-login',

    'username'          => 'Brugernavn',
    'username_or_email' => 'Brugernavn eller e-mail',
    'password'      => 'Adgangskode',
    'sign_in'       => 'Log ind',
    'forgot'        => 'Glemt adgangskode?',

    'email'             => 'E-mail',
    'email_placeholder' => 'dig@eksempel.dk',
    'continue'          => 'Fortsæt',
    'or'                => 'eller',

    'reveal_local_ldap'  => 'Log ind med brugernavn og adgangskode',
    'reveal_local_plain' => 'Log ind med en lokal konto',

    'mfa_heading'     => 'Bekræftelse',
    'mfa_prompt'      => 'Indtast den 6-cifrede kode fra din godkendelsesapp',
    'mfa_placeholder' => '------',
    'mfa_verify'      => 'Bekræft',
    'mfa_verifying'   => 'Bekræfter...',
    'mfa_failed'      => 'Bekræftelsen mislykkedes. Prøv igen.',
    'mfa_cancel'      => 'Annullér og vend tilbage til login',

    'portal_link'   => 'Gå til selvbetjeningsportalen',

    'err_missing'   => 'Indtast både brugernavn og adgangskode',
    'err_invalid'   => 'Forkert brugernavn eller adgangskode',
    'err_exception' => 'Loginfejl: {message}',

    'err_throttled_hours_one'     => 'For mange mislykkede forsøg. Prøv igen om 1 time.',
    'err_throttled_hours_many'    => 'For mange mislykkede forsøg. Prøv igen om {n} timer.',
    'err_throttled_minutes_one'   => 'For mange mislykkede forsøg. Prøv igen om 1 minut.',
    'err_throttled_minutes_many'  => 'For mange mislykkede forsøg. Prøv igen om {n} minutter.',
    'err_locked_one'              => 'Kontoen er låst. Prøv igen om 1 minut.',
    'err_locked_many'             => 'Kontoen er låst. Prøv igen om {n} minutter.',

    'err_sso_account'   => 'Denne konto logger ind med single sign-on. Brug knappen ovenfor.',
    'err_not_analyst'   => 'Din konto har ikke medarbejderadgang. Brug selvbetjeningsportalen.',
    'err_no_group'      => 'Din konto er ikke medlem af en gruppe, der giver adgang til FreeITSM.',

    'js_need_email'     => 'Indtast din e-mail.',
    'js_no_provider'    => 'Der er ikke opsat en single sign-on-udbyder til den e-mail. Kontakt din administrator.',
];
