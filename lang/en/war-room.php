<?php
/**
 * War room — chat that still works when Teams, Slack and the internet do not.
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

    'channel' => [
        // The all-hands channel. Always present, always first, cannot be removed:
        // in a real outage everyone needs one obvious place to gather rather than
        // six team rooms and an argument about which one to use.
        'all_hands'    => 'Everyone',
        'heading'      => 'Channels',
        'teams'        => 'Your teams',
        'channels'     => 'Channels',
        'direct'       => 'Direct messages',
        'new'          => 'New channel',
        'new_dm'       => 'New message',
        'archived'     => 'Archived',
        'show_archived'=> 'Show archived',
        'private'      => 'Private',
        'topic'        => 'Topic',
        'members'      => '{count} members',
    ],

    // Shown once at the top so somebody who has never opened this page knows
    // what it is for within one sentence.
    'intro' => 'A chat that runs on your own server, for when Teams, Slack or the internet are unavailable. Messages stay on this server and are not sent anywhere else.',

    'composer' => [
        'placeholder' => 'Type a message…',
        'send'        => 'Send',
        'attach'      => 'Attach a file',
        'archived'    => 'This channel is archived. You can read it, but not post.',
    ],

    'empty'          => 'No messages yet. Say something to get started.',
    'former_analyst' => 'Former analyst',

    'presence' => [
        'nobody'    => 'Nobody else is here right now',
        'here'      => 'Here now',
        // "In the war room but in another channel" is a genuinely useful thing to
        // know mid-incident: it is the difference between nobody is reading this
        // and everybody is in the other room.
        'elsewhere' => 'Elsewhere in the war room',
    ],

    'create' => [
        'heading'      => 'New channel',
        'name'         => 'Name',
        'name_hint'    => 'What is this channel for? For example, "Exchange outage".',
        'topic'        => 'Topic (optional)',
        'private'      => 'Private — only the people you choose can see it',
        'members'      => 'Who can see it',
        'create'       => 'Create',
        'cancel'       => 'Cancel',
        'name_required'=> 'Give the channel a name',
        'failed'       => 'Could not create that channel',
    ],

    'manage' => [
        'heading'    => 'Channel settings',
        'rename'     => 'Rename',
        'archive'    => 'Archive',
        'restore'    => 'Restore',
        'save'       => 'Save',
        // Said plainly, because "archive" means different things in different
        // tools and here it deliberately does NOT hide anything.
        'archive_hint' => 'Archiving stops new messages. The conversation stays readable.',
        'failed'     => 'Could not change that channel',
    ],

    'dm' => [
        'heading'  => 'New message',
        'search'   => 'Search people',
        'nobody'   => 'No other analysts to message',
        'here_now' => 'here now',
        'failed'   => 'Could not open that conversation',
    ],

    'search' => [
        'heading'      => 'Search',
        'placeholder'  => 'Search the war room…',
        'this_channel' => 'This channel only',
        'everywhere'   => 'Everywhere I can see',
        'no_results'   => 'Nothing found',
        'searching'    => 'Searching…',
        'results'      => '{count} results',
        'failed'       => 'Could not search',
        'jump'         => 'Open',
    ],

    'attach' => [
        'too_many'  => 'You can attach up to {count} files to one message',
        'rejected'  => 'Not attached: {names}',
        'download'  => 'Download',
    ],

    // The AI briefing. Named "situation report" rather than "summary" because
    // that is what the reader is being asked to produce, and it sets the
    // expectation that it is a draft to check rather than an answer.
    'sitrep' => [
        'heading'     => 'Situation report',
        'button'      => 'Situation report',
        'intro'       => 'Reads the chat and drafts the update you would send to the business.',
        'since'       => 'Covering the last',
        'hours'       => '{count} hours',
        'hour'        => '1 hour',
        'minutes'     => '{count} minutes',
        'scope_all'   => 'Everywhere I can see',
        'scope_this'  => 'This channel only',
        'generate'    => 'Write it',
        'working'     => 'Reading the conversation…',
        'copy'        => 'Copy',
        'copied'      => 'Copied',
        'empty'       => 'Nothing was said in that period, so there is nothing to report.',
        'footer'      => 'Drafted from {messages} messages by {model}. Check it before you send it.',
        // Two honest failure messages rather than one vague one. During an outage
        // "we could not reach the AI provider" is very likely the true and
        // expected answer, not a fault in FreeITSM, and saying so stops somebody
        // wasting minutes on it mid-incident.
        'not_configured' => 'No AI provider is set up for the war room yet. An administrator can add one in War room → Settings → Situation report.',
        'unreachable'    => 'Could not reach the AI provider. If the internet is what has failed, this part will not work until it is back — the chat itself is unaffected.',
        'failed'         => 'Could not write the report',
    ],

    // Mentions. Named after what they do to the reader, not after the @ symbol.
    'mention' => [
        'everyone'  => 'everyone',
        'heading'   => 'Mentions',
        'none'      => 'Nobody has mentioned you',
        'hint'      => 'Type @ and start typing a name. Pick it from the list, or keep typing — a first name on its own works too.',
        'desktop'   => 'Show a desktop notification when I am mentioned',

        // How the picker writes a name into the box. A personal typing preference,
        // so it is per-analyst rather than an administrator's decision.
        'style_label' => 'When I pick a name, insert',
        'style_short' => 'First name, unless two people share it',
        'style_full'  => 'Always the full name',
        'style_strip' => 'Full name; backspace removes the surname first',
        // Said once, next to the setting, because it is the bit that is not
        // obvious and the bit that was annoying before.
        'style_hint'  => 'Backspace at the end of a name removes the whole mention in one press.',
        'desktop_blocked' => 'Your browser has blocked notifications for this site. You will need to allow them in the browser\'s own settings.',
    ],

    // Warbot. Always labelled, never disguised as a colleague — somebody arriving
    // mid-incident must not mistake a machine's answer for a person's, especially
    // when that answer is about to be repeated to the business.
    'warbot' => [
        'tag'      => 'bot',
        'thinking' => 'Looking that up…',
        'intro'    => 'Ask @Warbot a question, or use a command like /p1 or /status.',
    ],

    'message' => [
        'edit'           => 'Edit',
        'delete'         => 'Delete',
        'edited'         => 'edited',
        'edit_heading'   => 'Edit message',
        'edit_hint'      => 'The message will be marked as edited, because this is the record of what was said during an incident.',
        'delete_heading' => 'Delete message',
        'delete_confirm' => 'Delete this message?',
        // Said out loud: this is not what most chat tools do, and somebody
        // deleting a pasted password deserves to know exactly what remains.
        'delete_hint'    => 'The text and any attached files are destroyed. A line saying you deleted a message stays in its place, so the conversation does not have an unexplained gap.',
        'deleted_by'     => 'Message deleted by {name}',
        'failed'         => 'Could not change that message',
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
        'retention_hint'    => 'Set to "Keep forever" to disable automatic removal. Files attached to a message are deleted with it.',
        'save'              => 'Save',
        'saved'             => 'Saved',
        'save_failed'       => 'Could not save the setting',

        'ai_heading' => 'Situation report',
        'ai_intro'   => 'The situation report reads the war room chat and drafts the update a service delivery manager would send to the business — where things stand, what has changed, who is doing what and what is still unknown.',
        'ai_caveat'  => 'This is the one part of the war room that needs the internet, and it is optional. Without it the chat works exactly as it does now. The transcript of the channels the person can read is sent to the provider you choose here.',
    ],
];
