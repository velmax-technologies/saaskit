<?php

return [
    'organization' => [
        'invitation_expire' => (int) env(
            'ORGANIZATION_INVITATION_EXPIRE',
            7,
        ),
    ],
];
