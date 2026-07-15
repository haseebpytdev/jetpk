<?php

$enabled = filter_var(env('OTA_DEVELOPER_CP_ENABLED', false), FILTER_VALIDATE_BOOL);

return [
    /*
    |--------------------------------------------------------------------------
    | Developer control panel (product owner / deployer only)
    |--------------------------------------------------------------------------
    |
    | Not for client platform admins. Access uses developer_users + session
    | key dev_cp_user_id. Disabled by default in all environments.
    |
    */
    'enabled' => $enabled,

    /** @deprecated Use enabled; kept for one sprint of backward compatibility. */
    'control_panel_enabled' => $enabled,
];
