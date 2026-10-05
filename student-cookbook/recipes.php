<?php
/** Page 3 - Recipe browser with search and filters. */
require_once 'includes/functions.php';

$f = [
    'q'          => trim($_GET['q'] ?? ''),
    'cuisine'    => $_GET['cuisine'] ?? '',
    'max_time'   => (int) ($_GET['max_time'] ?? 0),
    'max_cost'   => (float) ($_GET['max_cost'] ?? 0),
    'difficulty' => $_GET['difficulty'] ?? '',
    'dietary'    => $_GET['dietary'] ?? '',
    'sort'       => $_GET['sort'] ?? 'cost',
];

// Build the WHERE clause from whichever filters were chosen
$where  = [];
$params = [];

if ($f['q'] !== '') {
    // Match title, description or any ingredient name
    $where[]  = '(r.title LIKE ? OR r.description LIKE ? OR r.recipe_id IN (
                    SELECT ri.recipe_id FROM recipe_ingredients ri
                    JOIN ingredients i ON i.ingredient_id = ri.ingredient_id WHERE i.ingredient_name LIKE ?))';
    $like     = '%' . $f['q'] . '%';
    array_push($params, $like, $like, $like);
}
if (in_array($f['cuisine'], CUISINES, true)) {
    $where[]  = 'r.cuisine = ?';
    $params[] = $f['cuisine'];
}
if ($f['max_time'] > 0) {
    $where[]  = '(r.prep_time + r.cook_time) <= ?';
    $params[] = $f['max_time'];
}
if ($f['max_cost'] > 0) {
    $where[]  = 'r.cost_per_serving <= ?';
    $params[] = $f['max_cost'];
}
if (in_array($f['difficulty'], ['easy', 'medium', 'hard'], true)) {
    $where[]  = 'r.difficulty = ?';
    $params[] = $f['difficulty'];
}
if (array_key_exists($f['dietary'], DIETS) && $f['dietary'] !== 'none') {
    // Vegan food also suits vegetarians
    if ($f['dietary'] === 'vegetarian') {
        $where[] = "r.dietary IN ('vegetarian', 'vegan')";
    } else {
        $where[]  = 'r.dietary = ?';
        $params[] = $f['dietary'];
    }
}

$sorts = [
    'cost'   => 'r.cost_per_serving ASC',
    'time'   => '(r.prep_time + r.cook_time) ASC',
    'rating' => 'avg_rating DESC',
    'newest' => 'r.created_at DESC',
];
$orderBy = $sorts[$f['sort']] ?? $sorts['cost'];

$sql = RECIPE_LIST_SQL . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . " GROUP BY r.recipe_id ORDER BY $orderBy, r.title";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$recipes = $stmt->fetchAll();

$user = current_user($pdo);
$hasFilters = $f['q'] !== '' || $f['cuisine'] || $f['max_time'] || $f['max_cost'] || $f['difficulty'] || $f['dietary'];

$pageTitle  = 'Recipes';
$activePage = 'recipes';
require 'includes/header.php';
?>
<section class="wrap section">
    <h1>Recipes</h1>
    <p class="lead">Every price is an estimate per serve, worked out from typical supermarket prices.</p>

    <form class="filter-bar" method="get" data-autosubmit>
        <label class="filter-search">Search
            <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Recipe or ingredient">
        </label>
        <label>Cuisine
            <select name="cuisine">
                <option value="">Any</option>
                <?php foreach (CUISINES as $c): ?>
                    <option<?= $f['cuisine'] === $c ? ' selected' : '' ?>><?= $c ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Total time
            <select name="max_time">
                <option value="0">Any</option>
                <?php foreach ([10, 20, 30, 45] as $t): ?>
                    <option value="<?= $t ?>"<?= $f['max_time'] === $t ? ' selected' : '' ?>>Up to <?= $t ?> min</option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Cost per serve
            <select name="max_cost">
                <option value="0">Any</option>
                <?php foreach ([1, 2, 3, 5] as $c): ?>
                    <option value="<?= $c ?>"<?= $f['max_cost'] == $c ? ' selected' : '' ?>>Up to <?= money($c) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Difficulty
            <select name="difficulty">
                <option value="">Any</option>
                <?php foreach (['easy', 'medium', 'hard'] as $d): ?>
                    <option value="<?= $d ?>"<?= $f['difficulty'] === $d ? ' selected' : '' ?>><?= ucfirst($d) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Diet
            <select name="dietary">
                <option value="">Any</option>
                <?php foreach (DIETS as $val => $label): if ($val === 'none') continue; ?>
                    <option value="<?= $val ?>"<?= $f['dietary'] === $val ? ' selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Sort by
            <select name="sort">
                <option value="cost"<?= $f['sort'] === 'cost' ? ' selected' : '' ?>>Cheapest</option>
                <option value="time"<?= $f['sort'] === 'time' ? ' selected' : '' ?>>Quickest</option>
                <option value="rating"<?= $f['sort'] === 'rating' ? ' selected' : '' ?>>Top rated</option>
                <option value="newest"<?= $f['sort'] === 'newest' ? ' selected' : '' ?>>Newest</option>
            </select>
        </label>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Apply filters</button>
            <?php if ($hasFilters): ?><a href="recipes.php" class="text-link">Clear</a><?php endif; ?>
        </div>
    </form>

    <?php if ($user && $user['dietary_preference'] !== 'none' && !$f['dietary']): ?>
        <p class="hint">Your profile says <?= e(strtolower(DIETS[$user['dietary_preference']])) ?>.
            <a href="recipes.php?dietary=<?= e($user['dietary_preference']) ?>">Show only matching recipes</a></p>
    <?php endif; ?>

    <p class="result-count"><?= count($recipes) ?> recipe<?= count($recipes) === 1 ? '' : 's' ?> found</p>

    <?php if ($recipes): ?>
        <div class="recipe-grid">
            <?php foreach ($recipes as $r) echo recipe_card($r); ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <p>No recipes match those filters. Try a higher price or a longer cooking time, or <a href="recipes.php">clear the filters</a>.</p>
            <?php if (is_logged_in()): ?><p>Know a good one? <a href="my-recipes.php">Add your own recipe</a>.</p><?php endif; ?>
        </div>
    <?php endif; ?>
</section>
<?php require 'includes/footer.php'; ?>
