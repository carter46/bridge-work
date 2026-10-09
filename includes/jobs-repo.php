<?php

const HJ_EMPLOYMENT_TYPES = ['full_time', 'part_time', 'contract', 'temporary'];

/** All categories (admin), with active and total job counts. */
function hj_categories_with_counts(): array
{
    return hj_db_all(
        "SELECT c.*,
                (SELECT COUNT(*) FROM jobs j WHERE j.category_id = c.id AND j.status = 'active') AS active_count,
                (SELECT COUNT(*) FROM jobs j WHERE j.category_id = c.id) AS total_count
         FROM categories c
         ORDER BY c.sort_order, c.name"
    );
}

function hj_category_options(): array
{
    return hj_db_all('SELECT id, name, slug FROM categories ORDER BY sort_order, name');
}

function hj_category_aliases(?string $previousSlugs): array
{
    if ($previousSlugs === null || trim($previousSlugs) === '') {
        return [];
    }
    return array_values(array_filter(array_map('trim', explode(',', $previousSlugs)), 'strlen'));
}

function hj_job_find(int $id): ?array
{
    return hj_db_one('SELECT * FROM jobs WHERE id = ?', [$id]);
}

function hj_employment_types_array(?string $set): array
{
    if ($set === null || $set === '') {
        return [];
    }
    return array_values(array_intersect(HJ_EMPLOYMENT_TYPES, explode(',', $set)));
}

function hj_job_pay_label(array $job): string
{
    return hj_format_pay(
        $job['pay_min'] !== null ? (float)$job['pay_min'] : null,
        $job['pay_max'] !== null ? (float)$job['pay_max'] : null,
        (string)$job['pay_currency'],
        (string)$job['pay_period']
    );
}

/** Shape of one job in api/jobs.php and jobs-snapshot.json. No internal fields (source_text, notes). */
function hj_job_public(array $row): array
{
    $min = $row['pay_min'] !== null ? (float)$row['pay_min'] : null;
    $max = $row['pay_max'] !== null ? (float)$row['pay_max'] : null;
    return [
        'id'                => (int)$row['id'],
        'ref_code'          => $row['ref_code'],
        'title'             => $row['title'],
        'company_name'      => $row['company_name'] !== null && $row['company_name'] !== '' ? $row['company_name'] : null,
        'category'          => $row['category_slug'],
        'employment_types'  => hj_employment_types_array($row['employment_types']),
        'work_arrangement'  => $row['work_arrangement'],
        'applicant_region'  => $row['applicant_region'],
        'location_text'     => $row['location_text'],
        'schedule'          => $row['schedule'],
        'schedule_note'     => $row['schedule_note'],
        'pay_min'           => $min,
        'pay_max'           => $max,
        'pay_currency'      => $row['pay_currency'],
        'pay_period'        => $row['pay_period'],
        'pay_label'         => hj_job_pay_label($row),
        'hourly_min'        => $row['pay_currency'] === 'USD' ? hj_hourly_equivalent($min, $row['pay_period']) : null,
        'hourly_max'        => $row['pay_currency'] === 'USD' ? hj_hourly_equivalent($max, $row['pay_period']) : null,
        'skills'            => $row['skills'],
        'icon'              => $row['icon'] ?: 'work',
        'badge'             => $row['badge'],
        'description'       => $row['description'],
        'employer_verified' => (bool)(int)$row['employer_verified'],
        'is_featured'       => (bool)(int)$row['is_featured'],
        'posted_at'         => $row['posted_at'] ? gmdate('c', strtotime($row['posted_at'] . ' UTC')) : null,
    ];
}

/**
 * Everything the public pages need: visible categories that have active jobs, and the active jobs.
 */
function hj_public_jobs_payload(): array
{
    $categories = hj_db_all('SELECT * FROM categories WHERE is_visible = 1 ORDER BY sort_order, name');
    $rows = hj_db_all(
        "SELECT j.*, c.slug AS category_slug
         FROM jobs j
         JOIN categories c ON c.id = j.category_id
         WHERE j.status = 'active' AND c.is_visible = 1
         ORDER BY c.sort_order, c.name, j.sort_order, j.id"
    );

    $jobs = array_map('hj_job_public', $rows);

    $byCategory = [];
    foreach ($jobs as $job) {
        $byCategory[$job['category']][] = $job;
    }

    $publicCategories = [];
    foreach ($categories as $c) {
        $list = $byCategory[$c['slug']] ?? [];
        if (!$list) {
            continue;
        }
        $publicCategories[] = [
            'slug'        => $c['slug'],
            'name'        => $c['name'],
            'description' => $c['description'],
            'icon'        => $c['icon'] ?: 'work',
            'count'       => count($list),
            'pay_label'   => hj_range_label($list),
            'aliases'     => hj_category_aliases($c['previous_slugs']),
        ];
    }

    return [
        'generated_at' => gmdate('c'),
        'categories'   => $publicCategories,
        'jobs'         => $jobs,
        'stats'        => [
            'total_jobs'      => count($jobs),
            'total_sectors'   => count($publicCategories),
            'pay_label'       => hj_range_label($jobs),
        ],
    ];
}

/**
 * "$15–$90/hr" across a list of public jobs, only when they share one currency and period.
 */
function hj_range_label(array $jobs): string
{
    $currencies = [];
    $periods = [];
    $mins = [];
    $maxs = [];
    foreach ($jobs as $job) {
        if ($job['pay_min'] === null && $job['pay_max'] === null) {
            continue;
        }
        $currencies[$job['pay_currency']] = true;
        $periods[$job['pay_period']] = true;
        $mins[] = $job['pay_min'] !== null ? $job['pay_min'] : $job['pay_max'];
        $maxs[] = $job['pay_max'] !== null ? $job['pay_max'] : $job['pay_min'];
    }
    if (!$mins || count($currencies) !== 1 || count($periods) !== 1) {
        return '';
    }
    return hj_format_pay(min($mins), max($maxs), (string)key($currencies), (string)key($periods));
}

/** Dashboard counts. */
function hj_job_counts(): array
{
    $row = hj_db_one(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(status = 'active'), 0) AS active,
                COALESCE(SUM(status = 'draft'), 0) AS draft,
                COALESCE(SUM(status = 'closed'), 0) AS closed,
                COALESCE(SUM(is_featured = 1 AND status = 'active'), 0) AS featured
         FROM jobs"
    );
    return array_map('intval', $row ?: []);
}

/** Next free ref code for a category prefix, e.g. TECH-06. */
function hj_suggest_ref_code(string $prefix): string
{
    $prefix = strtoupper(preg_replace('/[^A-Za-z]/', '', $prefix)) ?: 'JOB';
    $codes = hj_db_all('SELECT ref_code FROM jobs WHERE ref_code LIKE ?', [$prefix . '-%']);
    $max = 0;
    foreach ($codes as $c) {
        if (preg_match('/-(\d+)$/', $c['ref_code'], $m)) {
            $max = max($max, (int)$m[1]);
        }
    }
    return sprintf('%s-%02d', $prefix, $max + 1);
}
