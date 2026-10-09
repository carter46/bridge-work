<?php
/**
 * Admin page chrome: <head>, sidebar, flash messages and database-update banners.
 */

function hj_admin_nav_items(?array $admin): array
{
    $items = [
        ['key' => 'dashboard', 'href' => 'index.php', 'icon' => 'dashboard', 'label' => 'Dashboard'],
        ['key' => 'jobs', 'href' => 'jobs.php', 'icon' => 'work', 'label' => 'Jobs'],
        ['key' => 'categories', 'href' => 'categories.php', 'icon' => 'category', 'label' => 'Categories'],
        ['key' => 'applications', 'href' => 'applications.php', 'icon' => 'inbox', 'label' => 'Applications'],
    ];
    if (hj_is_owner($admin)) {
        $items[] = ['key' => 'settings', 'href' => 'settings.php', 'icon' => 'settings', 'label' => 'Settings'];
        $items[] = ['key' => 'users', 'href' => 'users.php', 'icon' => 'group', 'label' => 'Admin users'];
        $items[] = ['key' => 'updates', 'href' => 'updates.php', 'icon' => 'database', 'label' => 'Database updates'];
        $items[] = ['key' => 'verify', 'href' => 'verify.php', 'icon' => 'fact_check', 'label' => 'Job data check'];
    }
    $items[] = ['key' => 'account', 'href' => 'account.php', 'icon' => 'person', 'label' => 'My account'];
    return $items;
}

function hj_admin_head(string $title): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    $siteName = hj_setting('site_name', 'Hubjob Platform');
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> — <?= e($siteName) ?> Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&amp;display=block" rel="stylesheet">
<script src="https://cdn.tailwindcss.com?plugins=forms"></script>
<script src="../assets/js/tailwind-config.js?v=4"></script>
<style>
  .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; vertical-align: middle; }
  .hj-table th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #64748b; font-weight: 700; padding: .65rem .75rem; background: #f8fafc; white-space: nowrap; }
  .hj-table td { padding: .7rem .75rem; border-top: 1px solid #eef2f7; vertical-align: top; font-size: 14px; }
  .hj-input { width: 100%; border-radius: .5rem; border-color: #cbd5e1; font-size: 14px; padding: .55rem .75rem; }
  .hj-input:focus { border-color: #070235; --tw-ring-color: #070235; }
  .hj-label { display: block; font-size: 13px; font-weight: 600; color: #070235; margin-bottom: .35rem; }
  .hj-help { font-size: 12px; color: #787680; margin-top: .3rem; }
  .hj-btn { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; border-radius: .5rem; font-size: 14px; font-weight: 600; padding: .55rem 1rem; transition: background-color .15s; cursor: pointer; }
  .hj-btn-primary { background: #070235; color: #fff; } .hj-btn-primary:hover { background: #1e1b4b; }
  .hj-btn-accent { background: #ab351b; color: #fff; } .hj-btn-accent:hover { background: #8f2913; }
  .hj-btn-light { background: #fff; color: #070235; border: 1px solid #cbd5e1; } .hj-btn-light:hover { background: #f8fafc; }
  .hj-btn-danger { background: #fff; color: #ba1a1a; border: 1px solid #fca5a5; } .hj-btn-danger:hover { background: #fef2f2; }
  .hj-btn-sm { padding: .35rem .7rem; font-size: 13px; }
  .hj-card { background: #fff; border: 1px solid #e2e8f0; border-radius: .9rem; }
</style>
</head>
<?php
}

/** Full admin page header with navigation. */
function hj_admin_header(string $title, string $active = ''): void
{
    $admin = hj_current_admin();
    hj_admin_head($title);
    $siteName = hj_setting('site_name', 'Hubjob Platform');
    ?>
<body class="bg-slate-50 text-text-main font-sans antialiased">
<div class="min-h-screen md:flex">

<aside class="hidden left-0 md:flex md:w-64 md:flex-col md:fixed md:inset-y-0 bg-primary text-white z-40" id="admin-sidebar">
  <div class="h-16 flex items-center justify-between px-5 border-b border-white/10">
    <a class="font-extrabold tracking-tight" href="index.php"><?= e($siteName) ?> <span class="text-orange-300 font-semibold text-sm">Admin</span></a>
    <button aria-label="Close menu" class="md:hidden text-white/80" data-sidebar-close type="button"><span class="material-symbols-outlined">close</span></button>
  </div>
  <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1 text-[14px]">
    <?php foreach (hj_admin_nav_items($admin) as $item):
        $isActive = $item['key'] === $active; ?>
      <a class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors <?= $isActive ? 'bg-white/15 text-white font-semibold' : 'text-white/75 hover:bg-white/10 hover:text-white' ?>" href="<?= e($item['href']) ?>">
        <span class="material-symbols-outlined text-[20px]"><?= e($item['icon']) ?></span><?= e($item['label']) ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="px-5 py-4 border-t border-white/10 text-xs text-white/70">
    <?php if ($admin): ?>
      <p class="font-semibold text-white truncate"><?= e($admin['name']) ?></p>
      <p class="truncate"><?= e($admin['email']) ?> · <?= e(ucfirst($admin['role'])) ?></p>
      <form action="logout.php" class="mt-3" method="post">
        <?= hj_csrf_field() ?>
        <button class="inline-flex items-center gap-1.5 text-white/80 hover:text-white" type="submit"><span class="material-symbols-outlined text-[18px]">logout</span>Sign out</button>
      </form>
    <?php endif; ?>
    <a class="inline-flex items-center gap-1.5 mt-2 text-white/60 hover:text-white" href="../index.html" target="_blank"><span class="material-symbols-outlined text-[16px]">open_in_new</span>View website</a>
  </div>
</aside>
<div class="hidden fixed inset-0 bg-black/40 z-30 md:hidden" data-sidebar-backdrop></div>

<div class="flex-1 md:ml-64 min-w-0">
  <header class="md:hidden sticky top-0 z-20 h-14 bg-primary text-white flex items-center justify-between px-4">
    <button aria-label="Open menu" data-sidebar-open type="button"><span class="material-symbols-outlined">menu</span></button>
    <span class="font-bold"><?= e($title) ?></span>
    <a aria-label="View website" href="../index.html" target="_blank"><span class="material-symbols-outlined">open_in_new</span></a>
  </header>
  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 md:py-8">
    <?php hj_admin_banners($admin); ?>
<?php
}

function hj_admin_footer(): void
{
    ?>
  </main>
</div>
</div>
<script src="assets/js/admin.js?v=4"></script>
</body>
</html>
<?php
}

/** Minimal centred page for login and first-time setup. */
function hj_admin_guest_header(string $title): void
{
    hj_admin_head($title);
    ?>
<body class="bg-slate-50 text-text-main font-sans antialiased min-h-screen flex items-center justify-center px-4 py-10">
<div class="w-full max-w-md">
  <div class="text-center mb-6">
    <p class="text-xl font-extrabold text-primary tracking-tight"><?= e(hj_setting('site_name', 'Hubjob Platform')) ?> <span class="text-secondary">Admin</span></p>
  </div>
  <?php hj_admin_banners(null); ?>
<?php
}

function hj_admin_guest_footer(): void
{
    ?>
</div>
<script src="assets/js/admin.js?v=4"></script>
</body>
</html>
<?php
}

/** Flash messages plus the results of any automatic database update on this request. */
function hj_admin_banners(?array $admin): void
{
    $tones = [
        'success' => 'bg-emerald-50 border-emerald-200 text-emerald-800',
        'error'   => 'bg-red-50 border-red-200 text-red-800',
        'warning' => 'bg-amber-50 border-amber-200 text-amber-900',
        'info'    => 'bg-sky-50 border-sky-200 text-sky-900',
    ];

    if (!empty($_SESSION['hj_mig_applied'])) {
        echo '<div class="mb-4 rounded-lg border px-4 py-3 text-sm ' . $tones['success'] . '"><p class="font-semibold mb-1">Database updated automatically</p><ul class="list-disc pl-5">';
        foreach ((array)$_SESSION['hj_mig_applied'] as $line) {
            echo '<li>' . e($line) . '</li>';
        }
        echo '</ul></div>';
        unset($_SESSION['hj_mig_applied']);
    }
    if (!empty($_SESSION['hj_mig_errors'])) {
        echo '<div class="mb-4 rounded-lg border px-4 py-3 text-sm ' . $tones['error'] . '"><p class="font-semibold mb-1">A database update could not be applied</p><ul class="list-disc pl-5">';
        foreach ((array)$_SESSION['hj_mig_errors'] as $line) {
            echo '<li>' . e($line) . '</li>';
        }
        echo '</ul><p class="mt-2">It will be retried on the next page load. ';
        if ($admin !== null && hj_is_owner($admin)) {
            echo 'See <a class="underline font-semibold" href="updates.php">Database updates</a> for details and backups.';
        } else {
            echo 'Please tell the site owner.';
        }
        echo '</p></div>';
    }

    foreach (hj_take_flashes() as $flash) {
        $tone = $tones[$flash['type']] ?? $tones['info'];
        echo '<div class="mb-4 rounded-lg border px-4 py-3 text-sm ' . $tone . '" role="status">' . e($flash['message']) . '</div>';
    }
}

/** Page title row with optional action buttons (raw HTML). */
function hj_admin_page_title(string $title, string $subtitle = '', string $actionsHtml = ''): void
{
    echo '<div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-6"><div>';
    echo '<h1 class="text-2xl font-extrabold text-primary tracking-tight">' . e($title) . '</h1>';
    if ($subtitle !== '') {
        echo '<p class="text-sm text-text-muted mt-1">' . e($subtitle) . '</p>';
    }
    echo '</div>';
    if ($actionsHtml !== '') {
        echo '<div class="flex flex-wrap gap-2">' . $actionsHtml . '</div>';
    }
    echo '</div>';
}

function hj_admin_badge(string $text, string $tone = 'slate'): string
{
    $tones = [
        'slate'   => 'bg-slate-100 text-slate-700',
        'green'   => 'bg-emerald-100 text-emerald-800',
        'amber'   => 'bg-amber-100 text-amber-800',
        'red'     => 'bg-red-100 text-red-700',
        'blue'    => 'bg-sky-100 text-sky-800',
        'indigo'  => 'bg-indigo-100 text-indigo-800',
    ];
    return '<span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap ' . ($tones[$tone] ?? $tones['slate']) . '">' . e($text) . '</span>';
}

function hj_admin_status_badge(string $group, ?string $value): string
{
    if ($value === null || $value === '') {
        return hj_admin_badge('—');
    }
    $label = hj_labels($group)[$value] ?? $value;
    $toneMap = [
        'active' => 'green', 'draft' => 'amber', 'closed' => 'slate',
        'new' => 'blue', 'reviewed' => 'indigo', 'shortlisted' => 'green', 'rejected' => 'red', 'hired' => 'green',
        'sent' => 'green', 'failed' => 'red', 'pending' => 'amber', 'skipped' => 'slate', 'disabled' => 'slate',
        'success' => 'green',
    ];
    return hj_admin_badge($label, $toneMap[$value] ?? 'slate');
}

/** Build a query string link, keeping the given params and dropping empty ones. */
function hj_admin_url(string $path, array $params = []): string
{
    $params = array_filter($params, function ($v) {
        return $v !== null && $v !== '';
    });
    return $path . ($params ? '?' . http_build_query($params) : '');
}

function hj_admin_pagination(string $path, array $params, int $page, int $pages): void
{
    if ($pages <= 1) {
        return;
    }
    echo '<nav class="flex items-center justify-between gap-3 mt-4 text-sm" aria-label="Pagination">';
    echo '<span class="text-text-muted">Page ' . $page . ' of ' . $pages . '</span><div class="flex gap-2">';
    if ($page > 1) {
        echo '<a class="hj-btn hj-btn-light hj-btn-sm" href="' . e(hj_admin_url($path, array_merge($params, ['page' => $page - 1]))) . '">Previous</a>';
    }
    if ($page < $pages) {
        echo '<a class="hj-btn hj-btn-light hj-btn-sm" href="' . e(hj_admin_url($path, array_merge($params, ['page' => $page + 1]))) . '">Next</a>';
    }
    echo '</div></nav>';
}

function hj_admin_select(string $name, array $options, $selected, string $attrs = ''): string
{
    $html = '<select class="hj-input" name="' . e($name) . '" ' . $attrs . '>';
    foreach ($options as $value => $label) {
        $html .= '<option value="' . e($value) . '"' . ((string)$value === (string)$selected ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    return $html . '</select>';
}

function hj_admin_format_bytes($bytes): string
{
    $bytes = (int)$bytes;
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024) . ' KB';
    }
    return $bytes . ' B';
}
