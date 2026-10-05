<?php
/** Page 6 - Shopping list generated from a meal plan (combines every ingredient). */
require_once 'includes/functions.php';
require_login();

$uid = current_user_id();

$stmt = $pdo->prepare('SELECT * FROM meal_plans WHERE user_id = ? ORDER BY start_date DESC, meal_plan_id DESC');
$stmt->execute([$uid]);
$plans = $stmt->fetchAll();

$plan = null;
foreach ($plans as $p) {
    if (isset($_GET['plan']) && (int) $_GET['plan'] === (int) $p['meal_plan_id']) $plan = $p;
}
if (!$plan && $plans) $plan = $plans[0];

// Cooking for a share house? Scale everything up.
$people = max(1, min(10, (int) ($_GET['people'] ?? 1)));

$items = [];
$total = 0;
if ($plan) {
    // Each planned meal is one serve, so use (recipe quantity / recipe servings).
    // SUM combines the same ingredient across every recipe in the week.
    $sql = 'SELECT i.ingredient_id, i.ingredient_name, i.unit, i.typical_price,
                   SUM(ri.quantity / r.servings) * ? AS needed,
                   GROUP_CONCAT(DISTINCT r.title ORDER BY r.title SEPARATOR ", ") AS used_in
            FROM meal_plan_items mpi
            JOIN recipes r             ON r.recipe_id = mpi.recipe_id
            JOIN recipe_ingredients ri ON ri.recipe_id = r.recipe_id
            JOIN ingredients i         ON i.ingredient_id = ri.ingredient_id
            WHERE mpi.meal_plan_id = ?
            GROUP BY i.ingredient_id, i.ingredient_name, i.unit, i.typical_price
            ORDER BY i.ingredient_name';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$people, $plan['meal_plan_id']]);
    $items = $stmt->fetchAll();

    foreach ($items as &$item) {
        // Round countable things up: you cannot buy 0.7 of a can
        $countable = in_array($item['unit'], ['pcs', 'can', 'slice', 'clove', 'packet'], true);
        $item['buy']  = $countable ? ceil($item['needed'] - 0.001) : $item['needed'];
        $item['cost'] = $item['needed'] * $item['typical_price'];
        $total += $item['cost'];
    }
    unset($item);

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM meal_plan_items WHERE meal_plan_id = ?');
    $stmt->execute([$plan['meal_plan_id']]);
    $mealCount = (int) $stmt->fetchColumn();
}

$user = current_user($pdo);

$pageTitle  = 'Shopping list';
$activePage = 'shopping';
require 'includes/header.php';
?>
<section class="wrap section">
    <div class="section-head no-print">
        <h1>Shopping list</h1>
        <?php if ($plans): ?>
            <form method="get" class="plan-switch">
                <label for="plan-select">Plan</label>
                <select name="plan" id="plan-select" data-autosubmit-select>
                    <?php foreach ($plans as $p): ?>
                        <option value="<?= (int) $p['meal_plan_id'] ?>"<?= $p['meal_plan_id'] == $plan['meal_plan_id'] ? ' selected' : '' ?>><?= e($p['plan_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="people">People</label>
                <select name="people" id="people" data-autosubmit-select>
                    <?php for ($n = 1; $n <= 6; $n++): ?>
                        <option value="<?= $n ?>"<?= $people === $n ? ' selected' : '' ?>><?= $n ?></option>
                    <?php endfor; ?>
                </select>
                <noscript><button class="btn btn-ghost" type="submit">Update</button></noscript>
            </form>
        <?php endif; ?>
    </div>

    <?php if (!$plan): ?>
        <div class="empty-state">
            <p>Your shopping list is built from a meal plan. <a href="planner.php">Create a meal plan</a> first.</p>
        </div>
    <?php elseif (!$items): ?>
        <div class="empty-state">
            <p>"<?= e($plan['plan_name']) ?>" has no meals yet. <a href="planner.php?plan=<?= (int) $plan['meal_plan_id'] ?>">Add some meals</a> and the list will fill itself in.</p>
        </div>
    <?php else: ?>
        <div class="shopping-layout">
            <div class="docket" data-shopping-list="plan-<?= (int) $plan['meal_plan_id'] ?>-<?= $people ?>">
                <header class="docket-top">
                    <p class="docket-store">Student Cookbook</p>
                    <p><?= e($plan['plan_name']) ?></p>
                    <p><?= date('d/m/Y', strtotime($plan['start_date'])) ?> to <?= date('d/m/Y', strtotime($plan['end_date'])) ?></p>
                    <p><?= $mealCount ?> meals for <?= $people ?> <?= $people === 1 ? 'person' : 'people' ?></p>
                </header>
                <ul class="docket-items">
                    <?php foreach ($items as $item): ?>
                        <li>
                            <label>
                                <input type="checkbox" value="<?= (int) $item['ingredient_id'] ?>">
                                <span class="item-name"><?= e($item['ingredient_name']) ?>
                                    <small><?= qty($item['buy']) ?> <?= e($item['unit']) ?><?= $item['buy'] != $item['needed'] ? ' (recipes use ' . qty($item['needed']) . ')' : '' ?></small>
                                    <small class="used-in">For <?= e($item['used_in']) ?></small>
                                </span>
                                <span class="item-cost"><?= money($item['cost']) ?></span>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="docket-total">
                    <p><span>Items</span><span><?= count($items) ?></span></p>
                    <p><span>Still to get</span><span data-remaining><?= count($items) ?></span></p>
                    <p class="grand"><span>Estimated total</span><span><?= money($total) ?></span></p>
                </div>
                <p class="docket-foot">Prices are estimates. Pantry staples like salt and pepper are not listed.</p>
            </div>

            <aside class="shopping-side no-print">
                <h2>Before you go</h2>
                <p>Tick items off as they go in the trolley. Ticks are remembered on this device.</p>
                <?php if ($user['budget'] > 0): $b = $user['budget'] * $people; ?>
                    <p class="budget-note<?= $total > $b ? ' is-over' : '' ?>">
                        <?= $total > $b
                            ? 'This list is ' . money($total - $b) . ' over your weekly budget of ' . money($b) . '.'
                            : 'This list is ' . money($b - $total) . ' under your weekly budget of ' . money($b) . '.' ?>
                    </p>
                <?php endif; ?>
                <button class="btn btn-primary btn-block" type="button" data-print>Print list</button>
                <button class="btn btn-ghost btn-block" type="button" data-clear-ticks>Untick everything</button>
                <a class="text-link" href="planner.php?plan=<?= (int) $plan['meal_plan_id'] ?>">Edit the meal plan</a>
            </aside>
        </div>
    <?php endif; ?>
</section>
<?php require 'includes/footer.php'; ?>
