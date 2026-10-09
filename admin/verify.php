<?php
/**
 * Compares the database with the 30 original listings (database/fixtures/jobs.php)
 * so the migration can be signed off, and shows later admin edits clearly.
 */
define('HJ_CONTEXT', 'admin');
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$admin = hj_require_admin('owner');

$fixtures = require HJ_ROOT . '/database/fixtures/jobs.php';
$fixtureCategories = require HJ_ROOT . '/database/fixtures/categories.php';

$rows = hj_db_all('SELECT j.*, c.slug AS category_slug FROM jobs j JOIN categories c ON c.id = j.category_id');
$byRef = [];
foreach ($rows as $row) {
    $byRef[$row['ref_code']] = $row;
}

$fields = [
    'title' => 'Title', 'category_slug' => 'Category', 'pay_min' => 'Pay min', 'pay_max' => 'Pay max',
    'pay_currency' => 'Currency', 'pay_period' => 'Pay period', 'skills' => 'Skills', 'icon' => 'Icon',
    'location_text' => 'Location', 'work_arrangement' => 'Arrangement', 'applicant_region' => 'Region',
    'schedule' => 'Schedule', 'schedule_note' => 'Schedule note', 'employment_types' => 'Employment type',
    'badge' => 'Badge', 'is_featured' => 'Featured', 'source_text' => 'Original listing text',
];

function hj_verify_norm(string $field, $value): string
{
    if ($value === null) {
        return '';
    }
    if (in_array($field, ['pay_min', 'pay_max'], true)) {
        return number_format((float)$value, 2, '.', '');
    }
    return (string)$value;
}

$report = [];
$missing = 0;
$edited = 0;
foreach ($fixtures as $fx) {
    $fx['category_slug'] = $fx['category'];
    $row = $byRef[$fx['ref_code']] ?? null;
    if ($row === null) {
        $missing++;
        $report[] = ['ref' => $fx['ref_code'], 'title' => $fx['title'], 'state' => 'missing', 'diffs' => [], 'row' => null];
        continue;
    }
    $diffs = [];
    foreach ($fields as $field => $label) {
        $expected = hj_verify_norm($field, $fx[$field]);
        $actual = hj_verify_norm($field, $row[$field]);
        if ($expected !== $actual) {
            $diffs[] = ['label' => $label, 'expected' => $expected, 'actual' => $actual, 'protected' => $field === 'source_text'];
        }
    }
    if ($diffs) {
        $edited++;
    }
    $report[] = ['ref' => $fx['ref_code'], 'title' => $fx['title'], 'state' => $diffs ? 'edited' : 'match', 'diffs' => $diffs, 'row' => $row];
}

$fixtureRefs = array_column($fixtures, 'ref_code');
$extra = array_values(array_filter($rows, function ($r) use ($fixtureRefs) { return !in_array($r['ref_code'], $fixtureRefs, true); }));
$sourceChanged = count(array_filter($report, function ($r) {
    foreach ($r['diffs'] as $d) {
        if ($d['protected']) {
            return true;
        }
    }
    return false;
}));
$categorySlugs = array_column(hj_db_all('SELECT slug FROM categories'), 'slug');
$missingCategories = array_values(array_diff(array_column($fixtureCategories, 'slug'), $categorySlugs));
$duplicateRefs = (int)hj_db_value('SELECT COUNT(*) FROM (SELECT ref_code FROM jobs GROUP BY ref_code HAVING COUNT(*) > 1) d');

$snapshotPath = hj_snapshot_dir() . '/' . HJ_SNAPSHOT_JOBS;
$snapshot = is_file($snapshotPath) ? json_decode((string)file_get_contents($snapshotPath), true) : null;
$snapshotCount = is_array($snapshot) && isset($snapshot['jobs']) ? count($snapshot['jobs']) : null;
$liveCount = count(hj_public_jobs_payload()['jobs']);

$checks = [
    ['All ' . count($fixtures) . ' original jobs are in the database', $missing === 0, $missing . ' missing'],
    ['No duplicate reference codes', $duplicateRefs === 0, $duplicateRefs . ' duplicated'],
    ['All ' . count($fixtureCategories) . ' categories exist', !$missingCategories, $missingCategories ? 'missing: ' . implode(', ', $missingCategories) : ''],
    ['Original listing text untouched', $sourceChanged === 0, $sourceChanged . ' changed'],
    ['Public backup file matches live jobs', $snapshotCount === $liveCount, 'file has ' . ($snapshotCount === null ? 'no data' : $snapshotCount) . ', live has ' . $liveCount],
];

hj_admin_header('Job data check', 'verify');
hj_admin_page_title('Job data check', 'Compares the database with the 30 listings from the old website. Differences in editable fields are expected once admins update jobs.');
?>
<section class="hj-card p-5 mb-6">
  <ul class="space-y-2 text-sm">
    <?php foreach ($checks as $c): ?>
      <li class="flex items-start gap-2">
        <span class="material-symbols-outlined text-[20px] <?= $c[1] ? 'text-emerald-600' : 'text-red-600' ?>"><?= $c[1] ? 'check_circle' : 'cancel' ?></span>
        <span><?= e($c[0]) ?><?= !$c[1] && $c[2] !== '' ? ' — <strong>' . e($c[2]) . '</strong>' : '' ?></span>
      </li>
    <?php endforeach; ?>
  </ul>
  <p class="text-sm text-text-muted mt-4"><?= count($fixtures) - $missing - $edited ?> unchanged · <?= $edited ?> edited since import · <?= $missing ?> missing · <?= count($extra) ?> added by admins</p>
</section>

<section class="hj-card overflow-hidden mb-6">
  <div class="overflow-x-auto">
    <table class="hj-table w-full">
      <thead><tr><th>Ref</th><th>Job</th><th>State</th><th>Differences from the original</th></tr></thead>
      <tbody>
      <?php foreach ($report as $r): ?>
        <tr>
          <td class="font-mono text-xs whitespace-nowrap"><?= e($r['ref']) ?></td>
          <td class="text-sm"><?= $r['row'] ? '<a class="text-primary underline" href="job-edit.php?id=' . (int)$r['row']['id'] . '">' . e($r['row']['title']) . '</a> ' . hj_admin_status_badge('job_status', $r['row']['status']) : e($r['title']) ?></td>
          <td><?= $r['state'] === 'match' ? hj_admin_badge('Matches', 'green') : ($r['state'] === 'edited' ? hj_admin_badge('Edited', 'amber') : hj_admin_badge('Missing', 'red')) ?></td>
          <td class="text-xs">
            <?php if ($r['state'] === 'missing'): ?>
              Not in the database. Open <a class="underline" href="updates.php">Database updates</a> to check the seed update.
            <?php elseif (!$r['diffs']): ?>
              <span class="text-text-subtle">—</span>
            <?php else: ?>
              <ul class="space-y-0.5">
                <?php foreach ($r['diffs'] as $d): ?>
                  <li class="<?= $d['protected'] ? 'text-red-700' : '' ?>"><strong><?= e($d['label']) ?>:</strong> <?= $d['expected'] === '' ? '<em>empty</em>' : e($d['expected']) ?> → <?= $d['actual'] === '' ? '<em>empty</em>' : e($d['actual']) ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php if ($extra): ?>
<section class="hj-card p-5">
  <h2 class="font-bold text-primary mb-2">Jobs added by admins</h2>
  <ul class="text-sm space-y-1">
    <?php foreach ($extra as $x): ?>
      <li><a class="text-primary underline" href="job-edit.php?id=<?= (int)$x['id'] ?>"><?= e($x['ref_code'] . ' — ' . $x['title']) ?></a> <?= hj_admin_status_badge('job_status', $x['status']) ?></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
<?php
hj_admin_footer();
