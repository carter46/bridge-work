<?php
/**
 * Seed the 8 job categories. INSERT IGNORE on slug: never duplicates, never overwrites admin renames.
 */

return [
    'id' => '2026_10_09_000003_seed_categories',
    'description' => 'Seed the 8 job categories',
    'up' => function (PDO $db) {
        $categories = require HJ_ROOT . '/database/fixtures/categories.php';
        $stmt = $db->prepare(
            'INSERT IGNORE INTO categories (slug, name, description, icon, sort_order, is_visible, created_at)
             VALUES (?, ?, ?, ?, ?, 1, ?)'
        );
        $now = hj_now();
        foreach ($categories as $c) {
            $stmt->execute([$c['slug'], $c['name'], $c['description'], $c['icon'], $c['sort'], $now]);
        }
    },
];
