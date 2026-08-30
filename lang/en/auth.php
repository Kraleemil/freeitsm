<?php
/**
 * English (en) — Analyst sign-in page strings (auth/login.php).
 *
 * The analyst sign-in page was the last screen in the product still written in
 * hardcoded English. The self-service portal's own sign-in page has been
 * translated since it was built, which meant the requester-facing door was
 * localised and the staff-facing front door was not.
 *
 * ⚠️ This page must never fail. It is the one screen where a PHP error locks
 * every analyst out of the product, so auth/login.php loads i18n behind a guard
 * and falls back to English if anything here cannot be read.
 */
return [
    'browser_title' => 'Service Desk Login',
    'heading'       => 'ITSM Login',

    // Local username/password form
    'username'          => 'Username',
    // Shown instead when a directory provider is configured: the credential being
    // asked for is the directory's, and it may well be an address.
    'username_or_email' => 'Username or email',
    'password'      => 'Password',
    'sign_in'       => 'Sign In',
    'forgot'        => 'Forgot password?',

    // Email-first form, shown when single sign-on is configured
    'email'             => 'Email',
    'email_placeholder' => 'you@example.com',
    'continue'          => 'Continue',
    'or'                => 'or',

    // The link that reveals the local form underneath the SSO buttons. Which one
    // is used depends on whether a directory (LDAP/AD) provider is configured —
    // with one, "local account" would be actively misleading, because the
    // username and password being asked for are the directory's.
    'reveal_local_ldap'  => 'Sign in with a username and password',
    'reveal_local_plain' => 'Sign in with a local account',

    // Multi-factor challenge
    'mfa_heading'     => 'Verification',
    'mfa_prompt'      => 'Enter the 6-digit code from your authenticator app',
    'mfa_placeholder' => '------',
    'mfa_verify'      => 'Verify',
    'mfa_verifying'   => 'Verifying...',
    'mfa_failed'      => 'Verification failed. Please try again.',
    'mfa_cancel'      => 'Cancel and return to login',

    // The way across to the requester portal (discussion #82)
    'portal_link'   => 'Go to the Self-Service Portal',

    // --- Messages -----------------------------------------------------------
    'err_missing'   => 'Please enter both username and password',
    'err_invalid'   => 'Invalid username or password',
    // ⚠️ {message} is a raw exception string. It is shown as-is, exactly as the
    // English did; do not translate what is substituted into it.
    'err_exception' => 'Login error: {message}',

    // Lockout and throttling. Separate singular and plural keys rather than an
    // "(s)" suffix, because most languages cannot form a plural that way.
    'err_throttled_hours_one'     => 'Too many failed attempts. Try again in 1 hour.',
    'err_throttled_hours_many'    => 'Too many failed attempts. Try again in {n} hours.',
    'err_throttled_minutes_one'   => 'Too many failed attempts. Try again in 1 minute.',
    'err_throttled_minutes_many'  => 'Too many failed attempts. Try again in {n} minutes.',
    'err_locked_one'              => 'Account locked. Try again in 1 minute.',
    'err_locked_many'             => 'Account locked. Try again in {n} minutes.',

    // Wrong door / wrong credentials for this door
    'err_sso_account'   => 'This account signs in with single sign-on. Please use the sign-in button above.',
    'err_not_analyst'   => 'Your account does not have analyst access. Please use the self-service portal.',
    'err_no_group'      => 'Your account is not a member of a group that grants access to FreeITSM.',

    // Email-first lookup, in the page's JavaScript
    'js_need_email'     => 'Please enter your email.',
    'js_no_provider'    => 'No single sign-on provider is set up for that email. Please contact your administrator.',
];
