<?php
/** Page 5 - Weekly meal planner (7 days x breakfast, lunch, dinner). */
require_once 'includes/functions.php';
require_login();

$uid  = current_user_id();
$user = current_user($pdo);

/** Return the plan if it belongs to the signed-in user. */
function find_plan(PDO $pdo, int $planId, int $uid): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM meal_plans WHERE meal_plan_id = ? AND user_id = ?');
    $stmt->execute([$planId, $uid]);
    return $stmt->fetch() ?: null;
}

/* ---------- Handle form actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    $planId = (int) ($_POST['plan_id'] ?? 0);

    if ($action === 'create_plan') {
        $name  = trim($_POST['plan_name'] ?? '');
        $start = $_POST['start_date'] ?? '';
        $date  = DateTime::createFromFormat('Y-m-d', $start);
        if ($name === '' || strlen($name) > 80) {
            flash('Give the plan a name of up to 80 characters.', 'error');
        } elseif (!$date) {
            flash('Choose a start date for the plan.', 'error');
        } else {
            // Plans run Monday to Sunday, so move the start back to that week's Monday
            $weekday = (int) $date->format('N');
            $date->modify('-' . ($weekday - 1) . ' days');
            $start = $date->format('Y-m-d');
            $pdo->prepare('INSERT INTO meal_plans (user_id, plan_name, start_date, end_date) VALUES (?, ?, ?, DATE_ADD(?, INTERVAL 6 DAY))')
                ->execute([$uid, $name, $start, $start]);
            $planId = (int) $pdo->lastInsertId();
            flash("Plan \"$name\" created. Choose a recipe for each meal.");
        }
    } elseif ($plan = find_plan($pdo, $planId, $uid)) {
        if ($action === 'set_item') {
            $day      = (int) ($_POST['day'] ?? 0);
            $meal     = $_POST['meal_type'] ?? '';
            $recipeId = (int) ($_POST['recipe_id'] ?? 0);
            if (isset(DAYS[$day]) && in_array($meal, MEAL_TYPES, true) && $recipeId > 0) {
                $pdo->prepare('INSERT INTO meal_plan_items (meal_plan_id, recipe_id, day_of_week, meal_type) VALUES (?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE recipe_id = VALUES(recipe_id)')
                    ->execute([$planId, $recipeId, $day, $meal]);
            }
        } elseif ($action === 'remove_item') {
            $pdo->prepare('DELETE FROM meal_plan_items WHERE item_id = ? AND meal_plan_id = ?')
                ->execute([(int) $_POST['item_id'], $planId]);
        } elseif ($action === 'clear_plan') {
            $pdo->prepare('DELETE FROM meal_plan_items WHERE meal_plan_id = ?')->execute([$planId]);
            flash('All meals removed from the plan.', 'info');
        } elseif ($action === 'delete_plan') {
            $pdo->prepare('DELETE FROM meal_plans WHERE meal_plan_id = ?')->execute([$planId]);
            flash('Plan "' . $plan['plan_name'] . '" deleted.', 'info');
            redirect('planner.php');
        }
    }
    redirect('planner.php' . ($planId ? "?plan=$planId" : ''));
}

/* ---------- Load plans ---------- */
$stmt = $pdo->prepare('SELECT * FROM meal_plans WHERE user_id = ? ORDER BY start_date DESC, meal_plan_id DESC');
$stmt->execute([$uid]);
$plans = $stmt->fetchAll();

$plan = null;
if (isset($_GET['plan'])) {
    $plan = find_plan($pdo, (int) $_GET['plan'], $uid);
}
if (!$plan && $plans) {
    $plan = $plans[0];
}

$grid      = [];   // $grid[day][meal] = item
$weekCost  = 0;
if ($plan) {
    $stmt = $pdo->prepare('SELECT mpi.item_id, mpi.day_of_week, mpi.meal_type, r.recipe_id, r.title, r.cost_per_serving, r.prep_time, r.cook_time
                           FROM meal_plan_items mpi JOIN recipes r ON r.recipe_id = mpi.recipe_id
                           WHERE mpi.meal_plan_id = ?');
    $stmt->execute([$plan['meal_plan_id']]);
    foreach ($stmt->fetchAll() as $item) {
        $grid[$item['day_of_week']][$item['meal_type']] = $item;
        $weekCost += $item['cost_per_serving'];
    }
}
$mealsPlanned = array_sum(array_map('count', $grid));
$budget       = (float) $user['budget'];
$budgetPct    = $budget > 0 ? min(100, round($weekCost / $budget * 100)) : 0;
$overBudget   = $budget > 0 && $weekCost > $budget;

$recipes = $pdo->query('SELECT recipe_id, title, cost_per_serving FROM recipes ORDER BY title')->fetchAll();

$pageTitle  = 'Meal planner';
$activePage = 'planner';
require 'includes/header.php';
?>
<section class="wrap section">
    <div class="section-head">
        <h1>Meal planner</h1>
        <?php if ($plans): ?>
            <form method="get" class="plan-switch">
                <label for="plan-select">Plan</label>
                <select name="plan" id="plan-select" data-autosubmit-select>
                    <?php foreach ($plans as $p): ?>
                        <option value="<?= (int) $p['meal_plan_id'] ?>"<?= $plan && $p['meal_plan_id'] == $plan['meal_plan_id'] ? ' selected' : '' ?>>
                            <?= e($p['plan_name']) ?> (<?= date('j M', strtotime($p['start_date'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <noscript><button class="btn btn-ghost" type="submit">Open</button></noscript>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($plan): ?>
        <div class="plan-summary">
            <div>
                <h2><?= e($plan['plan_name']) ?></h2>
                <p class="muted"><?= date('D j M', strtotime($plan['start_date'])) ?> to <?= date('D j M Y', strtotime($plan['end_date'])) ?>. <?= $mealsPlanned ?> of 21 meals planned.</p>
            </div>
            <div class="budget-meter<?= $overBudget ? ' is-over' : '' ?>">
                <p><strong><?= money($weekCost) ?></strong> of your <?= money($budget) ?> weekly budget</p>
                <div class="meter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $budgetPct ?>" aria-label="Budget used">
                    <span style="width: <?= $budgetPct ?>%"></span>
                </div>
                <p class="small"><?= $overBudget ? 'Over budget by ' . money($weekCost - $budget) . '. Swap in a cheaper recipe or two.' : money(max(0, $budget - $weekCost)) . ' left to spend' ?></p>
            </div>
            <a class="btn btn-primary" href="shopping-list.php?plan=<?= (int) $plan['meal_plan_id'] ?>">Make shopping list</a>
        </div>

        <div class="planner-grid" role="table" aria-label="Meals for the week">
            <div class="planner-row planner-header" role="row">
                <span role="columnheader">Day</span>
                <?php foreach (MEAL_TYPES as $meal): ?><span role="columnheader"><?= ucfirst($meal) ?></span><?php endforeach; ?>
            </div>
            <?php foreach (DAYS as $dayNum => $dayName):
                $date = date('j M', strtotime($plan['start_date'] . ' +' . ($dayNum - 1) . ' days')); ?>
                <div class="planner-row" role="row">
                    <span class="planner-day" role="rowheader"><?= $dayName ?><small><?= $date ?></small></span>
                    <?php foreach (MEAL_TYPES as $meal): $item = $grid[$dayNum][$meal] ?? null; ?>
                        <div class="planner-cell<?= $item ? ' is-filled' : '' ?>" role="cell" data-meal="<?= ucfirst($meal) ?>">
                            <?php if ($item): ?>
                                <a href="recipe-detail.php?id=<?= (int) $item['recipe_id'] ?>" class="cell-title"><?= e($item['title']) ?></a>
                                <span class="cell-meta"><?= money($item['cost_per_serving']) ?>, <?= $item['prep_time'] + $item['cook_time'] ?> min</span>
                                <form method="post" class="cell-remove">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="remove_item">
                                    <input type="hidden" name="plan_id" value="<?= (int) $plan['meal_plan_id'] ?>">
                                    <input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>">
                                    <button type="submit" aria-label="Remove <?= e($item['title']) ?> from <?= $dayName ?> <?= $meal ?>">Remove</button>
                                </form>
                            <?php else: ?>
                                <form method="post" class="cell-add">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="set_item">
                                    <input type="hidden" name="plan_id" value="<?= (int) $plan['meal_plan_id'] ?>">
                                    <input type="hidden" name="day" value="<?= $dayNum ?>">
                                    <input type="hidden" name="meal_type" value="<?= $meal ?>">
                                    <label class="visually-hidden" for="r-<?= $dayNum . $meal ?>"><?= $dayName ?> <?= $meal ?></label>
                                    <select name="recipe_id" id="r-<?= $dayNum . $meal ?>" data-autosubmit-select>
                                        <option value="">+ Add a recipe</option>
                                        <?php foreach ($recipes as $r): ?>
                                            <option value="<?= (int) $r['recipe_id'] ?>"><?= e($r['title']) ?> (<?= money($r['cost_per_serving']) ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                    <noscript><button type="submit" class="btn btn-ghost">Add</button></noscript>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="plan-tools">
            <form method="post" data-confirm="Remove every meal from this plan?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="clear_plan">
                <input type="hidden" name="plan_id" value="<?= (int) $plan['meal_plan_id'] ?>">
                <button class="btn btn-ghost" type="submit">Clear all meals</button>
            </form>
            <form method="post" data-confirm="Delete this plan? This cannot be undone.">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_plan">
                <input type="hidden" name="plan_id" value="<?= (int) $plan['meal_plan_id'] ?>">
                <button class="btn btn-danger" type="submit">Delete plan</button>
            </form>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <p>You have no meal plans yet. Create one below, then choose a recipe for each meal.</p>
        </div>
    <?php endif; ?>

    <details class="new-plan"<?= $plan ? '' : ' open' ?>>
        <summary>Start a new plan</summary>
        <form method="post" class="form form-inline" data-validate novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_plan">
            <label>Plan name
                <input type="text" name="plan_name" required maxlength="80" placeholder="Exam week" data-error="Give the plan a name.">
            </label>
            <label>Week starting (Monday)
                <input type="date" name="start_date" required value="<?= date('Y-m-d', strtotime('monday this week')) ?>" data-error="Choose a start date.">
            </label>
            <button class="btn btn-primary" type="submit">Create plan</button>
        </form>
    </details>
</section>
<?php require 'includes/footer.php'; ?>
