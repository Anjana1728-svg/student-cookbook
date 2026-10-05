<?php
/** Page 8 - Profile: account settings, saved recipes and meal plan history. */
require_once 'includes/functions.php';
require_login();

$uid    = current_user_id();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $email  = trim($_POST['email'] ?? '');
        $diet   = $_POST['dietary_preference'] ?? 'none';
        $budget = (float) ($_POST['budget'] ?? 0);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
        if (!array_key_exists($diet, DIETS)) $diet = 'none';
        if ($budget < 0 || $budget > 1000) $errors[] = 'Weekly budget must be between $0 and $1000.';

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND user_id <> ?');
        $stmt->execute([$email, $uid]);
        if ($stmt->fetchColumn() > 0) $errors[] = 'Another account already uses that email.';

        if (!$errors) {
            $pdo->prepare('UPDATE users SET email = ?, dietary_preference = ?, budget = ? WHERE user_id = ?')
                ->execute([$email, $diet, $budget, $uid]);
            flash('Profile saved.');
            redirect('profile.php');
        }
    }

    if ($action === 'change_password') {
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = ?');
        $stmt->execute([$uid]);
        $hash = $stmt->fetchColumn();
        $new  = $_POST['new_password'] ?? '';

        if (!password_verify($_POST['current_password'] ?? '', $hash)) {
            $errors[] = 'Your current password is not right.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif ($new !== ($_POST['confirm_password'] ?? '')) {
            $errors[] = 'The new passwords do not match.';
        } else {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
            flash('Password changed.');
            redirect('profile.php');
        }
    }

    if ($action === 'unsave') {
        $pdo->prepare('DELETE FROM saved_recipes WHERE user_id = ? AND recipe_id = ?')->execute([$uid, (int) $_POST['recipe_id']]);
        flash('Removed from your favourites.', 'info');
        redirect('profile.php#saved');
    }
}

$user = current_user($pdo);

$stmt = $pdo->prepare(str_replace('FROM recipes r', 'FROM saved_recipes s JOIN recipes r ON r.recipe_id = s.recipe_id', RECIPE_LIST_SQL)
    . ' WHERE s.user_id = ? GROUP BY r.recipe_id ORDER BY s.saved_at DESC');
$stmt->execute([$uid]);
$saved = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT mp.*, COUNT(mpi.item_id) AS meals, COALESCE(SUM(r.cost_per_serving), 0) AS cost
                       FROM meal_plans mp
                       LEFT JOIN meal_plan_items mpi ON mpi.meal_plan_id = mp.meal_plan_id
                       LEFT JOIN recipes r ON r.recipe_id = mpi.recipe_id
                       WHERE mp.user_id = ? GROUP BY mp.meal_plan_id ORDER BY mp.start_date DESC');
$stmt->execute([$uid]);
$history = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT (SELECT COUNT(*) FROM recipes WHERE created_by = ?) AS shared,
                              (SELECT COUNT(*) FROM reviews WHERE user_id = ?) AS reviewed');
$stmt->execute([$uid, $uid]);
$counts = $stmt->fetch();

$pageTitle  = 'Profile';
$activePage = 'profile';
require 'includes/header.php';
?>
<section class="wrap section">
    <div class="profile-head">
        <span class="avatar" aria-hidden="true"><?= e(strtoupper(substr($user['username'], 0, 1))) ?></span>
        <div>
            <h1><?= e($user['username']) ?></h1>
            <p class="muted">Member since <?= date('F Y', strtotime($user['created_at'])) ?>.
                <?= count($history) ?> meal plan<?= count($history) === 1 ? '' : 's' ?>,
                <?= (int) $counts['shared'] ?> recipe<?= $counts['shared'] == 1 ? '' : 's' ?> shared,
                <?= (int) $counts['reviewed'] ?> review<?= $counts['reviewed'] == 1 ? '' : 's' ?> written.</p>
        </div>
    </div>

    <?php if ($errors): ?>
        <div class="form-errors" role="alert"><?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?></div>
    <?php endif; ?>

    <div class="profile-grid">
        <form method="post" class="form panel" data-validate novalidate>
            <h2>Your details</h2>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_profile">
            <label>Email
                <input type="email" name="email" required value="<?= e($user['email']) ?>" data-error="Enter a valid email address.">
            </label>
            <label>Dietary preference
                <select name="dietary_preference">
                    <?php foreach (DIETS as $val => $label): ?>
                        <option value="<?= $val ?>"<?= $user['dietary_preference'] === $val ? ' selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Weekly food budget ($)
                <input type="number" name="budget" min="0" max="1000" step="1" value="<?= e((int) $user['budget']) ?>">
            </label>
            <button class="btn btn-primary" type="submit">Save profile</button>
        </form>

        <form method="post" class="form panel" data-validate novalidate>
            <h2>Change password</h2>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_password">
            <label>Current password
                <input type="password" name="current_password" required autocomplete="current-password" data-error="Enter your current password.">
            </label>
            <label>New password
                <input type="password" name="new_password" id="new-password" required minlength="8" autocomplete="new-password" data-error="At least 8 characters.">
            </label>
            <label>Confirm new password
                <input type="password" name="confirm_password" required data-match="new-password" autocomplete="new-password" data-error="Passwords must match.">
            </label>
            <button class="btn btn-primary" type="submit">Change password</button>
        </form>
    </div>

    <h2 id="saved">Saved recipes</h2>
    <?php if ($saved): ?>
        <div class="recipe-grid">
            <?php foreach ($saved as $r) echo recipe_card($r); ?>
        </div>
    <?php else: ?>
        <p class="muted">Nothing saved yet. Tap "Save recipe" on any recipe to keep it here.</p>
    <?php endif; ?>

    <h2>Meal plan history</h2>
    <?php if ($history): ?>
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th scope="col">Plan</th><th scope="col">Week</th><th scope="col">Meals</th><th scope="col">Cost per person</th><th scope="col"><span class="visually-hidden">Links</span></th></tr></thead>
                <tbody>
                <?php foreach ($history as $h): ?>
                    <tr>
                        <td><?= e($h['plan_name']) ?></td>
                        <td><?= date('j M', strtotime($h['start_date'])) ?> to <?= date('j M Y', strtotime($h['end_date'])) ?></td>
                        <td><?= (int) $h['meals'] ?> of 21</td>
                        <td><?= money($h['cost']) ?></td>
                        <td class="row-actions">
                            <a class="text-link" href="planner.php?plan=<?= (int) $h['meal_plan_id'] ?>">Open plan</a>
                            <a class="text-link" href="shopping-list.php?plan=<?= (int) $h['meal_plan_id'] ?>">Shopping list</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="muted">No meal plans yet. <a href="planner.php">Start your first plan</a>.</p>
    <?php endif; ?>
</section>
<?php require 'includes/footer.php'; ?>
