<?php
/**
 * Seed default settings (current contact details from the static site).
 * INSERT IGNORE: existing values edited in Admin -> Settings are never overwritten.
 */

return [
    'id' => '2026_10_09_000002_seed_settings',
    'description' => 'Seed default site settings (contact details, email, spam and retention defaults)',
    'up' => function (PDO $db) {
        // sendmail.php keeps accepting posts from cached copies of the old form for 30 days after deployment.
        HJ_AutoMigrate::ensureSetting($db, 'legacy_form_until', gmdate('Y-m-d H:i:s', time() + 30 * 86400));
        foreach (hj_setting_defaults() as $key => $value) {
            HJ_AutoMigrate::ensureSetting($db, $key, (string)$value);
        }
    },
];
