<?php
/**
 * War room — fallback chat for when Teams/Slack is unavailable.
 *
 * ⚠️ ENGLISH ONLY at time of writing. The other 23 locales have no
 * lang/<locale>/war-room.php yet, which is SAFE but not finished:
 * I18n::loadNamespace() returns [] for a missing file and resolve() then falls
 * back to the English value, so a French analyst sees this module in English
 * rather than a fatal or a wall of raw keys. Translating it is outstanding work,
 * not an optional extra — see the Internationalisation developer guide.
 */
return [
    'title' => 'War room',

    'nav' => [
        'war_room' => 'War room',
        'settings' => 'Settings',
    ],

    // The all-hands channel. Always present, always first, cannot be removed:
    // in a real outage everyone needs one obvious place to gather rather than
    // six team rooms and an argument about which one to use.
    'channel' => [
        'all_hands' => 'Everyone',
        'heading'   => 'Channels',
    ],

    // Shown once at the top so somebody who has never opened this page knows
    // what it is for within one sentence.
    'intro' => 'A simple chat that runs on your own server, for when Teams, Slack or the internet are unavailable. Messages stay on this server and are not sent anywhere else.',

    'composer' => [
        'placeholder' => 'Type a message…',
        'send'        => 'Send',
    ],

    'empty'          => 'No messages yet. Say something to get started.',
    'former_analyst' => 'Former analyst',

    'presence' => [
        'nobody' => 'Nobody else is here right now',
        'here'   => 'Here now: {names}',
    ],

    'error' => [
        'load' => 'Could not load messages',
        'send' => 'Could not send that message',
        // Shown in place of the usual presence line when polling stops
        // succeeding — the one thing you must not do in this module is let
        // somebody believe their message was delivered when it was not.
        'offline' => 'Lost contact with the server — messages may not be arriving',
    ],

    'settings' => [
        'title'             => 'War room settings',
        'heading'           => 'Message retention',
        'intro'             => 'How long war room messages are kept. Old messages are removed automatically as new ones are posted, so there is no scheduled job to set up.',
        'retention_label'   => 'Keep messages for',
        'retention_forever' => 'Keep forever',
        'retention_days'    => '{count} days',
        'retention_hint'    => 'Set to "Keep forever" to disable automatic removal.',
        'save'              => 'Save',
        'saved'             => 'Saved',
        'save_failed'       => 'Could not save the setting',
    ],
];
