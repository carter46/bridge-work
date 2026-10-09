<?php
define('HJ_CONTEXT', 'admin');
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$admin = hj_require_admin();

/** Validate posted category fields. Returns [values, errors]. */
function hj_category_input(array $post, int $id): array
{
    $v = [
        'name'        => hj_input($post, 'name', 120),
        'slug'        => hj_slugify(hj_input($post, 'slug', 80)),
        'description' => hj_input($post, 'description', 500) ?: null,
        'icon'        => strtolower(hj_input($post, 'icon', 60)) ?: 'work',
        'sort_order'  => (int)($post['sort_order'] ?? 0),
        'is_visible'  => !empty($post['is_visible']) ? 1 : 0,
    ];
    if ($v['slug'] === '' && $v['name'] !== '') {
        $v['slug'] = hj_slugify($v['name']);
    }
    $errors = [];
    if ($v['name'] === '') {
        $errors[] = 'Category name is required.';
    }
    if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $v['slug'])) {
        $errors[] = 'The URL name may only use lowercase letters, numbers and dashes.';
    } elseif ((int)hj_db_value('SELECT COUNT(*) FROM categories WHERE slug = ? AND id <> ?', [$v['slug'], $id]) > 0) {
        $errors[] = 'Another category already uses the URL name "' . $v['slug'] . '".';
    }
    if (!preg_match('/^[a-z0-9_]{1,60}$/', $v['icon'])) {
        $errors[] = 'Icon must be a Material Symbols name such as "work".';
    }
    return [$v, $errors];
}

/** Alias list for previous_slugs: unique, never the category's own slug, max 500 characters. */
function hj_category_alias_string(array $aliases, string $ownSlug): ?string
{
    $aliases = array_values(array_unique(array_diff($aliases, [$ownSlug, ''])));
    return $aliases ? hj_substr(implode(',', $aliases), 0, 500) : null;
}

/** A slug now in use by one category must stop being an old-link alias of another. */
function hj_category_release_alias(string $slug, int $exceptId): void
{
    $rows = hj_db_all('SELECT id, slug, previous_slugs FROM categories WHERE id <> ? AND FIND_IN_SET(?, previous_slugs) > 0', [$exceptId, $slug]);
    foreach ($rows as $row) {
        $aliases = array_diff(hj_category_aliases($row['previous_slugs']), [$slug]);
        hj_db_exec('UPDATE categories SET previous_slugs = ?, updated_at = ? WHERE id = ?', [hj_category_alias_string($aliases, $row['slug']), hj_now(), (int)$row['id']]);
    }
}

if (hj_is_post()) {
    hj_csrf_verify();
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'create') {
        list($v, $errors) = hj_category_input($_POST, 0);
        if ($errors) {
            foreach ($errors as $err) {
                hj_flash('error', $err);
            }
        } else {
            hj_db_exec(
                'INSERT INTO categories (slug, name, description, icon, sort_order, is_visible, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$v['slug'], $v['name'], $v['description'], $v['icon'], $v['sort_order'], $v['is_visible'], hj_now(), hj_now()]
            );
            $newId = (int)hj_db()->lastInsertId();
            hj_category_release_alias($v['slug'], $newId);
            hj_audit('category_created', 'category', $newId, $v['name'] . ' (' . $v['slug'] . ')');
            hj_refresh_public_data();
            hj_flash('success', 'Category "' . $v['name'] . '" added. It appears on the website once it has an active job.');
        }
        hj_redirect('categories.php');
    }

    $category = $id > 0 ? hj_db_one('SELECT * FROM categories WHERE id = ?', [$id]) : null;
    if ($category === null) {
        hj_flash('error', 'That category no longer exists.');
        hj_redirect('categories.php');
    }

    if ($action === 'update') {
        list($v, $errors) = hj_category_input($_POST, $id);
        if ($errors) {
            foreach ($errors as $err) {
                hj_flash('error', $category['name'] . ': ' . $err);
            }
            hj_redirect('categories.php#cat-' . $id);
        }
        // Old links like jobs.html?category=old-slug keep working through the alias list.
        $aliases = hj_category_aliases($category['previous_slugs']);
        if ($v['slug'] !== $category['slug']) {
            $aliases[] = $category['slug'];
        }
        $previous = hj_category_alias_string($aliases, $v['slug']);

        hj_db_exec(
            'UPDATE categories SET slug = ?, name = ?, description = ?, icon = ?, sort_order = ?, is_visible = ?, previous_slugs = ?, updated_at = ? WHERE id = ?',
            [$v['slug'], $v['name'], $v['description'], $v['icon'], $v['sort_order'], $v['is_visible'], $previous, hj_now(), $id]
        );
        hj_category_release_alias($v['slug'], $id);
        $changes = [];
        foreach (['name', 'slug', 'description', 'icon', 'sort_order', 'is_visible'] as $field) {
            if ((string)$category[$field] !== (string)$v[$field]) {
                $changes[] = $field;
            }
        }
        hj_audit('category_updated', 'category', $id, $v['name'] . ($changes ? ' changed: ' . implode(', ', $changes) : ' (no changes)'));
        hj_refresh_public_data();
        hj_flash('success', 'Category "' . $v['name'] . '" saved.');
        hj_redirect('categories.php#cat-' . $id);
    }

    if ($action === 'delete') {
        if (!hj_is_owner($admin)) {
            hj_flash('error', 'Only an owner can delete categories.');
            hj_redirect('categories.php');
        }
        $jobCount = (int)hj_db_value('SELECT COUNT(*) FROM jobs WHERE category_id = ?', [$id]);
        $target = null;
        if ($jobCount > 0) {
            $moveTo = (int)($_POST['move_to'] ?? 0);
            $target = $moveTo > 0 && $moveTo !== $id ? hj_db_one('SELECT * FROM categories WHERE id = ?', [$moveTo]) : null;
            if ($target === null) {
                hj_flash('error', 'Choose the category that the ' . $jobCount . ' job(s) in "' . $category['name'] . '" should move to, then delete again.');
                hj_redirect('categories.php#cat-' . $id);
            }
        }

        $pdo = hj_db();
        $pdo->beginTransaction();
        try {
            if ($target !== null) {
                hj_db_exec('UPDATE jobs SET category_id = ?, updated_at = ? WHERE category_id = ?', [(int)$target['id'], hj_now(), $id]);
                // Old links to the deleted category now open the category its jobs moved to.
                $aliases = array_merge(hj_category_aliases($target['previous_slugs']), [$category['slug']], hj_category_aliases($category['previous_slugs']));
                hj_db_exec('UPDATE categories SET previous_slugs = ?, updated_at = ? WHERE id = ?', [hj_category_alias_string($aliases, $target['slug']), hj_now(), (int)$target['id']]);
            }
            hj_db_exec('DELETE FROM categories WHERE id = ?', [$id]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $moved = $target !== null ? ' Its ' . $jobCount . ' job(s) moved to "' . $target['name'] . '".' : '';
        hj_audit('category_deleted', 'category', $id, $category['name'] . ' (' . $category['slug'] . ')' . $moved);
        hj_refresh_public_data();
        hj_flash('success', 'Category "' . $category['name'] . '" deleted.' . $moved);
        hj_redirect('categories.php');
    }

    hj_redirect('categories.php');
}

$categories = hj_categories_with_counts();
$nextSort = $categories ? max(array_map('intval', array_column($categories, 'sort_order'))) + 10 : 10;

hj_admin_header('Categories', 'categories');
hj_admin_page_title('Categories', 'Rename categories, change their icon and order, or hide them. To move a job, edit the job or use the bulk action on the Jobs page.');
?>
<div class="space-y-4">
  <?php foreach ($categories as $cat): $cid = (int)$cat['id']; ?>
    <form class="hj-card p-5" id="cat-<?= $cid ?>" method="post" action="categories.php">
      <?= hj_csrf_field() ?>
      <input type="hidden" name="id" value="<?= $cid ?>">
      <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
        <div class="flex items-center gap-3">
          <span class="material-symbols-outlined text-primary w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center" id="cat-icon-<?= $cid ?>"><?= e($cat['icon']) ?></span>
          <div>
            <h2 class="font-bold text-primary"><?= e($cat['name']) ?></h2>
            <p class="text-xs text-text-muted"><a class="underline" href="jobs.php?category=<?= $cid ?>"><?= (int)$cat['active_count'] ?> active / <?= (int)$cat['total_count'] ?> total jobs</a><?= (int)$cat['is_visible'] ? '' : ' · ' . hj_admin_badge('Hidden', 'amber') ?></p>
          </div>
        </div>
        <a class="text-xs text-text-subtle hover:underline" href="../jobs.html?category=<?= e($cat['slug']) ?>" target="_blank">View on site</a>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
        <div class="lg:col-span-2">
          <label class="hj-label">Name</label>
          <input class="hj-input" name="name" required maxlength="120" value="<?= e($cat['name']) ?>">
        </div>
        <div>
          <label class="hj-label">URL name</label>
          <input class="hj-input" name="slug" required maxlength="80" value="<?= e($cat['slug']) ?>">
        </div>
        <div>
          <label class="hj-label">Icon</label>
          <input class="hj-input" name="icon" maxlength="60" value="<?= e($cat['icon']) ?>" data-icon-preview="cat-icon-<?= $cid ?>">
        </div>
        <div>
          <label class="hj-label">Order</label>
          <input class="hj-input" name="sort_order" type="number" value="<?= (int)$cat['sort_order'] ?>">
        </div>
        <div class="flex items-end pb-2">
          <label class="inline-flex items-center gap-2 text-sm"><input class="rounded border-slate-300 text-primary" type="checkbox" name="is_visible" value="1" <?= (int)$cat['is_visible'] ? 'checked' : '' ?>> Visible on site</label>
        </div>
        <div class="sm:col-span-2 lg:col-span-6">
          <label class="hj-label">Description</label>
          <input class="hj-input" name="description" maxlength="500" value="<?= e($cat['description']) ?>">
        </div>
      </div>
      <?php if ($cat['previous_slugs']): ?>
        <p class="hj-help mt-2">Old URL names that still work: <?= e($cat['previous_slugs']) ?></p>
      <?php endif; ?>
      <div class="flex flex-wrap gap-2 mt-4">
        <button class="hj-btn hj-btn-primary hj-btn-sm" type="submit" name="action" value="update">Save</button>
        <?php if (hj_is_owner($admin) && (int)$cat['total_count'] === 0): ?>
          <button class="hj-btn hj-btn-danger hj-btn-sm" type="submit" name="action" value="delete" formnovalidate data-confirm="Delete the category <?= e($cat['name']) ?>?">Delete</button>
        <?php elseif (hj_is_owner($admin) && count($categories) > 1): ?>
          <div class="flex flex-wrap items-center gap-2 sm:ml-auto">
            <label class="text-xs text-text-muted" for="move-to-<?= $cid ?>">Delete and move its <?= (int)$cat['total_count'] ?> job(s) to</label>
            <select class="hj-input !w-auto" id="move-to-<?= $cid ?>" name="move_to">
              <option value="">Choose category…</option>
              <?php foreach ($categories as $other): if ((int)$other['id'] === $cid) continue; ?>
                <option value="<?= (int)$other['id'] ?>"><?= e($other['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="hj-btn hj-btn-danger hj-btn-sm" type="submit" name="action" value="delete" formnovalidate data-confirm="Move the <?= (int)$cat['total_count'] ?> job(s) and delete the category <?= e($cat['name']) ?>?">Delete</button>
          </div>
        <?php endif; ?>
      </div>
    </form>
  <?php endforeach; ?>

  <form class="hj-card p-5 border-dashed" method="post" action="categories.php">
    <?= hj_csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <h2 class="font-bold text-primary mb-4">Add a category</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
      <div class="lg:col-span-2">
        <label class="hj-label" for="new-name">Name</label>
        <input class="hj-input" id="new-name" name="name" required maxlength="120">
      </div>
      <div>
        <label class="hj-label" for="new-slug">URL name</label>
        <input class="hj-input" id="new-slug" name="slug" maxlength="80" placeholder="auto from name">
      </div>
      <div>
        <label class="hj-label" for="new-icon">Icon</label>
        <div class="flex items-center gap-2">
          <span class="material-symbols-outlined text-primary" id="new-icon-preview">work</span>
          <input class="hj-input" id="new-icon" name="icon" maxlength="60" value="work" data-icon-preview="new-icon-preview">
        </div>
      </div>
      <div>
        <label class="hj-label" for="new-sort">Order</label>
        <input class="hj-input" id="new-sort" name="sort_order" type="number" value="<?= (int)$nextSort ?>">
      </div>
      <div class="flex items-end pb-2">
        <label class="inline-flex items-center gap-2 text-sm"><input class="rounded border-slate-300 text-primary" type="checkbox" name="is_visible" value="1" checked> Visible on site</label>
      </div>
      <div class="sm:col-span-2 lg:col-span-6">
        <label class="hj-label" for="new-description">Description</label>
        <input class="hj-input" id="new-description" name="description" maxlength="500">
      </div>
    </div>
    <button class="hj-btn hj-btn-accent hj-btn-sm mt-4" type="submit"><span class="material-symbols-outlined text-[18px]">add</span>Add category</button>
  </form>
</div>
<?php
hj_admin_footer();
