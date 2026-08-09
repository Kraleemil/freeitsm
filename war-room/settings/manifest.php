<?php
/**
 * War room — settings manifest. See includes/capabilities.php.
 *
 * One tab, one decision. USING the war room is deliberately plain module
 * access: during an outage you do not want a permission sitting between an
 * analyst and the only chat that still works. The only thing worth gating is
 * how long the messages are kept, which is a data-retention decision.
 */
require_once __DIR__ . '/../../includes/capabilities.php';

return [
    'module'   => 'war-room',
    'label'    => 'War room',
    'umbrella' => [
        'cap'   => Cap::WAR_ROOM_MANAGE,
        'grant' => 'Manage war room settings',
    ],
    'tabs' => [
        [
            'id'        => 'retention',
            'cap'       => Cap::WAR_ROOM_MANAGE,
            'label_key' => 'war-room.settings.heading',
            'grant'     => 'Set how long war room messages are kept',
            // Declaring the key here is what authorises it: settingKeyOwners()
            // derives ownership from the manifest, so the tab that shows the
            // setting and the capability that guards it cannot disagree — and
            // the generic settings writer refuses any key nobody owns.
            'setting_keys' => ['warroom_retention_days'],
        ],
    ],
];
