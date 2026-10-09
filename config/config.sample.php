<?php
/**
 * Copy this file to config/config.php and fill in your values.
 * config/config.php is git-ignored and blocked from the web by config/.htaccess.
 */
return [
    'db' => [
        'host'    => 'localhost',
        'port'    => 3306,
        'name'    => 'your_database_name',
        'user'    => 'your_database_user',
        'pass'    => 'your_database_password',
    ],

    // 64 hex characters (32 random bytes). Encrypts the SMTP password and signs form tokens.
    // Leave empty to have one generated automatically in storage/app.key on first use.
    // Never change it after SMTP settings have been saved, or the saved password can't be decrypted.
    'app_key' => '',

    // Public site address used in email links, e.g. https://www.hubjobplatform.com
    // Leave empty to detect it from the current request.
    'app_url' => '',

    // Absolute paths. Point these outside the web root if your host allows it.
    // Empty = storage/cvs and storage/backups inside the site.
    'cv_storage_path'     => '',
    'backup_storage_path' => '',

    // Optional: force SMTP settings from this file instead of Admin -> Settings.
    // 'smtp_override' => [
    //     'host' => 'smtp.example.com', 'port' => 587, 'username' => '', 'password' => '',
    //     'encryption' => 'tls', 'from_email' => 'noreply@example.com', 'from_name' => 'Hubjob Platform',
    // ],
    'smtp_override' => null,

    // Show PHP errors in the browser. Keep false in production.
    'debug' => false,
];
