<?php
/** Page 4 - Recipe detail: ingredients, method, reviews, save and add to plan. */
require_once 'includes/functions.php';

$id   = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT r.*, u.username AS author FROM recipes r LEFT JOIN users u ON u.user_id = r.created_by WHERE r.recipe_id = ?');
$stmt->execute([$id]);
$recipe = $stmt->fetch();

if (!$recipe) {
    http_response_code(404);
    $pageTitle = 'Recipe not found';
    require 'includes/header.php';
    echo '<section class="wrap narrow section empty-state"><h1>Recipe not found</h1><p>It may have been deleted. <a href="recipes.php">Browse all recipes</a>.</p></section>';
    require 'includes/footer.php';
    exit;
}

$uid = current_user_id();

/* ---------- Handle form actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $pdo->prepare('INSERT IGNORE INTO saved_recipes (user_id, recipe_id) VALUES (?, ?)')->execute([$uid, $id]);
        flash('Saved to your favourites.');
    } elseif ($action === 'unsave') {
        $pdo->prepare('DELETE FROM saved_recipes WHERE user_id = ? AND recipe_id = ?')->execute([$uid, $id]);
        flash('Removed from your favourites.', 'info');
    } elseif ($action === 'review') {
        $rating  = (int) ($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        if ($rating < 1 || $rating > 5) {
            flash('Choose a rating from 1 to 5 stars.', 'error');
        } elseif (strlen($comment) > 1000) {
            flash('Keep your review under 1000 characters.', 'error');
        } else {
            // One review per person: posting again updates the old one
            $pdo->prepare('INSERT INTO reviews (recipe_id, user_id, rating, comment) VALUES (?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), created_at = NOW()')
                ->execute([$id, $uid, $rating, $comment]);
            flash('Review posted. Thanks for rating this recipe.');
        }
    } elseif ($action === 'add_to_plan') {
        $planId = $_POST['plan_id'] ?? '';
        $day    = (int) ($_POST['day'] ?? 0);
        $meal   = $_POST['meal_type'] ?? '';

        if (!isset(DAYS[$day]) || !in_array($meal, MEAL_TYPES, true)) {
            flash('Choose a day and a meal.', 'error');
        } else {
            if ($planId === 'new') {
                $monday = date('Y-m-d', strtotime('monday this week'));
                $pdo->prepare('INSERT INTO meal_plans (user_id, plan_name, start_date, end_date) VALUES (?, ?, ?, DATE_ADD(?, INTERVAL 6 DAY))')
                    ->execute([$uid, 'Week of ' . date('j M', strtotime($monday)), $monday, $monday]);
                $planId = (int) $pdo->lastInsertId();
            } else {
                // Make sure the plan belongs to this user
                $check = $pdo->prepare('SELECT meal_plan_id FROM meal_plans WHERE meal_plan_id = ? AND user_id = ?');
                $check->execute([(int) $planId, $uid]);
                $planId = (int) $check->fetchColumn();
            }
            if ($planId) {
                $pdo->prepare('INSERT INTO meal_plan_items (meal_plan_id, recipe_id, day_of_week, meal_type) VALUES (?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE recipe_id = VALUES(recipe_id)')
                    ->execute([$planId, $id, $day, $meal]);
                flash('Added to ' . DAYS[$day] . ' ' . $meal . '.');
                redirect("planner.php?plan=$planId");
            }
            flash('That meal plan could not be found.', 'error');
        }
    }
    redirect("recipe-detail.php?id=$id");
}

/* ---------- Load data for the page ---------- */
$stmt = $pdo->prepare('SELECT i.ingredient_name, i.unit, i.typical_price, ri.quantity
                       FROM recipe_ingredients ri JOIN ingredients i ON i.ingredient_id = ri.ingredient_id
                       WHERE ri.recipe_id = ? ORDER BY ri.id');
$stmt->execute([$id]);
$ingredients = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT rv.*, u.username FROM reviews rv JOIN users u ON u.user_id = rv.user_id
                       WHERE rv.recipe_id = ? ORDER BY rv.created_at DESC');
$stmt->execute([$id]);
$reviews   = $stmt->fetchAll();
$avg       = $reviews ? array_sum(array_column($reviews, 'rating')) / count($reviews) : 0;
$myReview  = null;
foreach ($reviews as $rv) {
    if ((int) $rv['user_id'] === $uid) $myReview = $rv;
}

$isSaved = false;
$plans   = [];
if ($uid) {
    $stmt = $pdo->prepare('SELECT 1 FROM saved_recipes WHERE user_id = ? AND recipe_id = ?');
    $stmt->execute([$uid, $id]);
    $isSaved = (bool) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT meal_plan_id, plan_name FROM meal_plans WHERE user_id = ? ORDER BY start_date DESC');
    $stmt->execute([$uid]);
    $plans = $stmt->fetchAll();
}

$steps     = array_filter(array_map('trim', explode("\n", $recipe['instructions'])));
$totalCost = $recipe['cost_per_serving'] * $recipe['servings'];

$pageTitle  = $recipe['title'];
$activePage = 'recipes';
require 'includes/header.php';
?>
<article class="wrap section recipe-detail">
    <p class="breadcrumb"><a href="recipes.php">Recipes</a> / <?= e($recipe['cuisine']) ?></p>

    <header class="detail-head">
        <div class="detail-media">
            <?php if ($recipe['image']): ?>
                <img src="<?= e($recipe['image']) ?>" alt="<?= e($recipe['title']) ?>">
            <?php else: ?>
                <span class="card-emoji" aria-hidden="true"><?= cuisine_emoji($recipe['cuisine']) ?></span>
            <?php endif; ?>
        </div>
        <div class="detail-intro">
            <h1><?= e($recipe['title']) ?></h1>
            <p class="lead"><?= e($recipe['description']) ?></p>
            <p><?= $reviews ? stars($avg) . ' ' . number_format($avg, 1) . ' from ' . count($reviews) . ' review' . (count($reviews) === 1 ? '' : 's') : '<span class="muted">No ratings yet</span>' ?></p>
            <dl class="fact-strip">
                <div><dt>Per serve</dt><dd><?= money($recipe['cost_per_serving']) ?></dd></div>
                <div><dt>Prep</dt><dd><?= (int) $recipe['prep_time'] ?> min</dd></div>
                <div><dt>Cook</dt><dd><?= (int) $recipe['cook_time'] ?> min</dd></div>
                <div><dt>Serves</dt><dd><?= (int) $recipe['servings'] ?></dd></div>
                <div><dt>Level</dt><dd><?= ucfirst(e($recipe['difficulty'])) ?></dd></div>
            </dl>
            <p class="muted small">
                <?= $recipe['dietary'] !== 'none' ? e(DIETS[$recipe['dietary']]) . '. ' : '' ?>
                <?= $recipe['calories'] ? (int) $recipe['calories'] . ' kcal and ' . (int) $recipe['protein_g'] . ' g protein per serve. ' : '' ?>
                <?= $recipe['author'] ? 'Shared by ' . e($recipe['author']) . '.' : '' ?>
            </p>

            <?php if ($uid): ?>
                <div class="detail-actions">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="<?= $isSaved ? 'unsave' : 'save' ?>">
                        <button class="btn btn-ghost" type="submit"><?= $isSaved ? '♥ Saved' : '♡ Save recipe' ?></button>
                    </form>
                    <form method="post" class="add-plan-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_to_plan">
                        <label class="visually-hidden" for="plan_id">Meal plan</label>
                        <select name="plan_id" id="plan_id">
                            <?php foreach ($plans as $p): ?>
                                <option value="<?= (int) $p['meal_plan_id'] ?>"><?= e($p['plan_name']) ?></option>
                            <?php endforeach; ?>
                            <option value="new">New plan for this week</option>
                        </select>
                        <label class="visually-hidden" for="day">Day</label>
                        <select name="day" id="day">
                            <?php foreach (DAYS as $n => $d): ?><option value="<?= $n ?>"><?= $d ?></option><?php endforeach; ?>
                        </select>
                        <label class="visually-hidden" for="meal_type">Meal</label>
                        <select name="meal_type" id="meal_type">
                            <?php foreach (MEAL_TYPES as $m): ?><option value="<?= $m ?>"<?= $m === 'dinner' ? ' selected' : '' ?>><?= ucfirst($m) ?></option><?php endforeach; ?>
                        </select>
                        <button class="btn btn-primary" type="submit">Add to meal plan</button>
                    </form>
                </div>
            <?php else: ?>
                <p><a class="btn btn-primary" href="auth.php?next=<?= urlencode("recipe-detail.php?id=$id") ?>">Sign in to save or plan this recipe</a></p>
            <?php endif; ?>
        </div>
    </header>

    <div class="detail-body">
        <section class="ingredients-panel">
            <h2>Ingredients</h2>
            <p class="muted small">For <?= (int) $recipe['servings'] ?> serve<?= $recipe['servings'] > 1 ? 's' : '' ?>. Tick them off as you go.</p>
            <ul class="check-list">
                <?php foreach ($ingredients as $i => $ing): ?>
                    <li>
                        <input type="checkbox" id="ing<?= $i ?>">
                        <label for="ing<?= $i ?>"><?= qty($ing['quantity']) ?> <?= e($ing['unit']) ?> <?= e(strtolower($ing['ingredient_name'])) ?></label>
                        <span class="line-cost"><?= money($ing['quantity'] * $ing['typical_price']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="total-line"><span>Whole recipe</span> <strong><?= money($totalCost) ?></strong></p>
        </section>

        <section class="method">
            <h2>Method</h2>
            <ol class="method-steps">
                <?php foreach ($steps as $step): ?><li><?= e($step) ?></li><?php endforeach; ?>
            </ol>
        </section>
    </div>

    <section class="reviews" id="reviews">
        <h2>Reviews</h2>
        <?php if ($uid): ?>
            <form method="post" class="form review-form" data-validate novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="review">
                <fieldset class="star-input">
                    <legend><?= $myReview ? 'Update your rating' : 'Your rating' ?></legend>
                    <?php for ($s = 5; $s >= 1; $s--): ?>
                        <input type="radio" name="rating" id="star<?= $s ?>" value="<?= $s ?>" required
                               <?= $myReview && (int) $myReview['rating'] === $s ? 'checked' : '' ?> data-error="Pick a star rating.">
                        <label for="star<?= $s ?>" title="<?= $s ?> star<?= $s > 1 ? 's' : '' ?>">★</label>
                    <?php endfor; ?>
                </fieldset>
                <label>Comment (optional)
                    <textarea name="comment" rows="3" maxlength="1000" placeholder="How did it turn out? Any tips?"><?= e($myReview['comment'] ?? '') ?></textarea>
                </label>
                <button type="submit" class="btn btn-primary"><?= $myReview ? 'Update review' : 'Post review' ?></button>
            </form>
        <?php else: ?>
            <p><a href="auth.php?next=<?= urlencode("recipe-detail.php?id=$id") ?>">Sign in</a> to rate this recipe.</p>
        <?php endif; ?>

        <?php if ($reviews): ?>
            <ul class="review-list">
                <?php foreach ($reviews as $rv): ?>
                    <li>
                        <p><?= stars($rv['rating']) ?> <strong><?= e($rv['username']) ?></strong>
                            <span class="muted small"><?= date('j M Y', strtotime($rv['created_at'])) ?></span></p>
                        <?php if ($rv['comment']): ?><p><?= nl2br(e($rv['comment'])) ?></p><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="muted">No reviews yet. Cook it and be the first to rate it.</p>
        <?php endif; ?>
    </section>
</article>
<?php require 'includes/footer.php'; ?>
