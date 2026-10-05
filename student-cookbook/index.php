<?php
/** Page 1 - Home: search, featured recipes and how the site works. */
require_once 'includes/functions.php';

// Cheapest well-rated recipes to feature
$featured = $pdo->query(RECIPE_LIST_SQL . ' GROUP BY r.recipe_id ORDER BY r.cost_per_serving ASC LIMIT 8')->fetchAll();

// Numbers for the hero
$stats = $pdo->query('SELECT COUNT(*) AS total, MIN(cost_per_serving) AS cheapest,
                             SUM(prep_time + cook_time <= 20) AS quick FROM recipes')->fetch();

$pageTitle  = 'Home';
$activePage = 'home';
require 'includes/header.php';
?>
<section class="hero">
    <div class="wrap hero-inner">
        <div class="hero-copy">
            <h1>Dinner for about two dollars.<br>Shopping list done for you.</h1>
            <p class="lead">Find cheap, quick recipes, plan your week of meals and get one shopping list with the total cost before you leave for the shops.</p>
            <form class="hero-search" action="recipes.php" method="get" role="search">
                <label for="q" class="visually-hidden">Search recipes</label>
                <input type="search" id="q" name="q" placeholder="Try rice, pasta, eggs, curry" autocomplete="off">
                <button type="submit" class="btn btn-primary">Search recipes</button>
            </form>
            <?php if (!is_logged_in()): ?>
                <p class="hero-signup">New here? <a href="auth.php?mode=register">Create a free account</a> to plan meals and save recipes.</p>
            <?php else: ?>
                <p class="hero-signup">Welcome back. <a href="planner.php">Open your meal planner</a>.</p>
            <?php endif; ?>
        </div>
        <aside class="hero-docket" aria-label="Site at a glance">
            <p class="docket-head">Student Cookbook<br><span>today's docket</span></p>
            <dl>
                <div><dt>Recipes on the site</dt><dd><?= (int) $stats['total'] ?></dd></div>
                <div><dt>Ready in 20 min or less</dt><dd><?= (int) $stats['quick'] ?></dd></div>
                <div><dt>Cheapest serve</dt><dd><?= money($stats['cheapest']) ?></dd></div>
            </dl>
            <p class="docket-foot">Thank you for cooking at home</p>
        </aside>
    </div>
</section>

<section class="wrap section">
    <div class="section-head">
        <h2>Cheapest recipes this week</h2>
        <a href="recipes.php" class="text-link">Browse all recipes</a>
    </div>
    <div class="recipe-grid">
        <?php foreach ($featured as $r) echo recipe_card($r); ?>
    </div>
</section>

<section class="wrap section">
    <h2>How it works</h2>
    <ol class="steps">
        <li>
            <h3>Pick recipes</h3>
            <p>Filter by cost, cooking time and diet. Every recipe shows its price per serve.</p>
        </li>
        <li>
            <h3>Plan your week</h3>
            <p>Drop recipes into breakfast, lunch and dinner for each day and see the week's cost against your budget.</p>
        </li>
        <li>
            <h3>Shop once</h3>
            <p>Your shopping list adds up every ingredient across the week, so you buy what you need and nothing extra.</p>
        </li>
    </ol>
</section>
<?php require 'includes/footer.php'; ?>
