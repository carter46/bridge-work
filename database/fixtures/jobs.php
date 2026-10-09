<?php
/**
 * The 30 listings exactly as they appeared on the static jobs.html before the migration.
 *
 * Only facts stated in each listing's visible text are used:
 * - "Remote…" in the location line  -> work_arrangement = remote
 * - "Remote Worldwide"              -> applicant_region = worldwide
 * - "Remote Europe / UK" etc.       -> applicant_region = uk_europe
 * - plain "Remote"                  -> applicant_region = unspecified
 * - OPS-01 "Full-Time / Part-Time"  -> employment_types = full_time,part_time
 * - OPS-04 "Flexible Shift"         -> schedule = flexible
 * Everything else (company, employment type, region, verified status) stays unspecified.
 * The redesign's data-work tags (worldwide/ukeu/flexible) were not source data and are ignored.
 *
 * Columns: ref, title, new category, original category, pay min, pay max (USD/hour), skills line,
 *          skills icon, location line (null if none), schedule line (null if none), badge shown on the card, featured
 * Used by the seed migration and the verify report. Do not edit after deployment.
 */

$rows = [
    ['TECH-01', 'Frontend Developer',           'tech',       'Tech & Development',   35, 90, 'Full-Stack / UI',            'laptop_mac',            'Remote Worldwide',   null,                    'Posted Today',     true],
    ['TECH-02', 'Backend Developer',            'tech',       'Tech & Development',   40, 85, 'Node / Python / Cloud',      'cloud',                 'Remote',             null,                    'Verified Partner', false],
    ['TECH-03', 'Full-Stack Developer',         'tech',       'Tech & Development',   45, 90, 'React / TypeScript / Go',    'code',                  'Remote',             null,                    null,               false],
    ['TECH-04', 'Web Tester',                   'tech',       'Tech & Development',   35, 50, 'QA / Automated Testing',     'bug_report',            'Remote Europe / UK', null,                    null,               false],
    ['TECH-05', 'Technical Support Specialist', 'support',    'Tech & Development',   35, 55, 'Tier 2 / Client Systems',    'lan',                   'Remote',             null,                    null,               false],
    ['OPS-01',  'Data Entry Clerk',             'operations', 'Operations & Support', 25, 35, 'Operations & Admin',         'description',           null,                 'Full-Time / Part-Time', 'High Volume',      false],
    ['OPS-02',  'Executive Assistant',          'operations', 'Operations & Support', 30, 50, 'Executive Liaison',          'badge',                 'Remote UK / Europe', null,                    null,               false],
    ['OPS-03',  'Operations Coordinator',       'operations', 'Operations & Support', 32, 52, 'Workflow & Logistics',       'account_tree',          'Remote',             null,                    null,               true],
    ['OPS-04',  'Customer Support Agent',       'support',    'Operations & Support', 25, 40, 'Multilingual Support',       'translate',             null,                 'Flexible Shift',        null,               false],
    ['OPS-05',  'Virtual Assistant',            'operations', 'Operations & Support', 25, 45, 'Digital Coordination',       'assistant',             'Remote',             null,                    null,               false],
    ['MKT-01',  'Social Media Manager',         'marketing',  'Marketing & Content',  30, 55, 'Brand & Growth',             'share',                 'Remote',             null,                    null,               false],
    ['MKT-02',  'Content Writer',               'marketing',  'Marketing & Content',  32, 60, 'Editorial & Copy',           'edit_note',             'Remote Worldwide',   null,                    null,               true],
    ['MKT-03',  'Email Marketing Assistant',    'marketing',  'Marketing & Content',  30, 48, 'Automation & Lifecycle',     'mail',                  'Remote',             null,                    null,               false],
    ['MKT-04',  'SEO Specialist',               'marketing',  'Marketing & Content',  35, 65, 'Technical SEO & Traffic',    'troubleshoot',          'Remote',             null,                    null,               false],
    ['MKT-05',  'Video Editor',                 'media',      'Marketing & Content',  35, 60, 'Motion & Post-Production',   'movie',                 'Remote',             null,                    null,               false],
    ['SLS-01',  'Sales Representative',         'sales',      'Sales & Growth',       30, 65, 'B2B Client Acquisition',     'handshake',             'Remote',             null,                    null,               true],
    ['SLS-02',  'Lead Generator',               'sales',      'Sales & Growth',       30, 50, 'Outbound Prospecting',       'contact_mail',          'Remote',             null,                    null,               false],
    ['SLS-03',  'Business Development Rep',     'sales',      'Sales & Growth',       35, 70, 'Enterprise Corridors',       'corporate_fare',        'Remote',             null,                    null,               false],
    ['SLS-04',  'Affiliate Manager',            'sales',      'Sales & Growth',       35, 68, 'Partnership Network',        'hub',                   'Remote',             null,                    null,               false],
    ['SLS-05',  'Account Executive',            'sales',      'Sales & Growth',       40, 75, 'Client Management',          'supervised_user_circle', 'Remote',            null,                    null,               false],
    ['DES-01',  'Graphic Designer',             'design',     'Design & Creative',    30, 55, 'Visual Identity',            'brush',                 'Remote',             null,                    null,               false],
    ['DES-02',  'UI/UX Designer',               'design',     'Design & Creative',    38, 70, 'Product & Web Interface',    'devices',               'Remote',             null,                    null,               true],
    ['DES-03',  'Presentation Designer',        'design',     'Design & Creative',    32, 55, 'Corporate Pitch Decks',      'slideshow',             'Remote',             null,                    null,               false],
    ['DES-04',  'Brand Designer',               'design',     'Design & Creative',    35, 65, 'Typography & Systems',       'draw',                  'Remote',             null,                    null,               false],
    ['DES-05',  'Motion Graphics Artist',       'media',      'Design & Creative',    38, 70, '2D/3D Animation',            'animation',             'Remote',             null,                    null,               false],
    ['EDU-01',  'Online Tutor',                 'education',  'Education & Training', 18, 35, 'Academic & Test Prep',       'menu_book',             'Remote',             null,                    null,               true],
    ['EDU-02',  'Course Content Assistant',     'education',  'Education & Training', 18, 30, 'Curriculum Production',      'auto_stories',          'Remote',             null,                    null,               false],
    ['EDU-03',  'eLearning Coordinator',        'education',  'Education & Training', 22, 35, 'LMS & Digital Learning',     'cast_for_education',    'Remote',             null,                    null,               false],
    ['EDU-04',  'Language Coach',               'education',  'Education & Training', 20, 35, 'ESL & Business English',     'record_voice_over',     'Remote',             null,                    null,               false],
    ['EDU-05',  'Academic Proofreader',         'education',  'Education & Training', 15, 28, 'Editorial Review',           'spellcheck',            'Remote',             null,                    null,               false],
];

$jobs = [];
foreach ($rows as $index => $r) {
    list($ref, $title, $category, $originalCategory, $min, $max, $skills, $icon, $location, $scheduleLine, $badge, $featured) = $r;

    $region = 'unspecified';
    if ($location !== null && stripos($location, 'worldwide') !== false) {
        $region = 'worldwide';
    } elseif ($location !== null && stripos($location, 'europe') !== false) {
        $region = 'uk_europe';
    }

    $sourceParts = array_filter([$ref, $badge, $title, '$' . $min . '–$' . $max . '/hr', $skills, $location, $scheduleLine], function ($v) {
        return $v !== null && $v !== '';
    });

    $jobs[] = [
        'ref_code'          => $ref,
        'title'             => $title,
        'category'          => $category,
        'original_category' => $originalCategory,
        'pay_min'           => $min,
        'pay_max'           => $max,
        'pay_currency'      => 'USD',
        'pay_period'        => 'hour',
        'skills'            => $skills,
        'icon'              => $icon,
        'location_text'     => $location,
        'work_arrangement'  => ($location !== null && stripos($location, 'remote') === 0) ? 'remote' : 'unspecified',
        'applicant_region'  => $region,
        'schedule'          => $scheduleLine === 'Flexible Shift' ? 'flexible' : 'unspecified',
        'schedule_note'     => $scheduleLine,
        'employment_types'  => $scheduleLine === 'Full-Time / Part-Time' ? 'full_time,part_time' : '',
        // "Verified Partner" is not shown until an admin ticks "Employer verified"; it stays in source_text.
        'badge'             => $badge === 'Verified Partner' ? null : $badge,
        'is_featured'       => $featured ? 1 : 0,
        'sort_order'        => ($index + 1) * 10,
        'source_text'       => implode(' | ', $sourceParts) . ' | Original category: ' . $originalCategory,
    ];
}

return $jobs;
