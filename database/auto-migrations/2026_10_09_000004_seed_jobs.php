<?php
/**
 * Seed the 30 original listings. INSERT IGNORE on ref_code: running twice never creates
 * duplicates and never overwrites jobs an admin has edited.
 */

return [
    'id' => '2026_10_09_000004_seed_jobs',
    'description' => 'Seed the 30 original job listings',
    'up' => function (PDO $db) {
        $jobs = require HJ_ROOT . '/database/fixtures/jobs.php';

        $categoryIds = [];
        foreach ($db->query('SELECT id, slug FROM categories')->fetchAll() as $row) {
            $categoryIds[$row['slug']] = (int)$row['id'];
        }

        $stmt = $db->prepare(
            'INSERT IGNORE INTO jobs (ref_code, title, company_name, category_id, employment_types, work_arrangement,
                applicant_region, location_text, schedule, schedule_note, pay_min, pay_max, pay_currency, pay_period,
                skills, icon, badge, description, employer_verified, status, is_featured, sort_order, posted_at,
                source_text, created_at)
             VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, 0, \'active\', ?, ?, ?, ?, ?)'
        );
        $now = hj_now();
        foreach ($jobs as $job) {
            if (!isset($categoryIds[$job['category']])) {
                throw new RuntimeException('Missing category "' . $job['category'] . '" for ' . $job['ref_code']);
            }
            $stmt->execute([
                $job['ref_code'], $job['title'], $categoryIds[$job['category']], $job['employment_types'],
                $job['work_arrangement'], $job['applicant_region'], $job['location_text'], $job['schedule'],
                $job['schedule_note'], $job['pay_min'], $job['pay_max'], $job['pay_currency'], $job['pay_period'],
                $job['skills'], $job['icon'], $job['badge'], $job['is_featured'], $job['sort_order'], $now,
                $job['source_text'], $now,
            ]);
        }
    },
];
