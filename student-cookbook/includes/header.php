<?php
/** Page header + navigation. Set $pageTitle and $activePage before including. */
$pageTitle  = $pageTitle  ?? 'Student Cookbook';
$activePage = $activePage ?? '';
$navItems = [
    'home'     => ['index.php', 'Home'],
    'recipes'  => ['recipes.php', 'Recipes'],
    'planner'  => ['planner.php', 'Meal planner'],
    'shopping' => ['shopping-list.php', 'Shopping list'],
    'mine'     => ['my-recipes.php', 'My recipes'],
];
?>
<!DOCTYPE html>
<html lang="en-AU">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Cheap, quick recipes, weekly meal plans and automatic shopping lists for students.">
    <title><?= e($pageTitle) ?> | Student Cookbook</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Atkinson+Hyperlegible:ital,wght@0,400;0,700;1,400&family=Courier+Prime:wght@400;700&display=swap" rel="stylesheet">
       <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🥕</text></svg>">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
    <div class="wrap header-inner">
        <a class="logo" href="index.php"><span aria-hidden="true">🥕</span> Student Cookbook</a>
        <button class="nav-toggle" aria-expanded="false" aria-controls="site-nav">Menu</button>
        <nav id="site-nav" class="site-nav" aria-label="Main">
            <ul>
                <?php foreach ($navItems as $key => [$href, $label]): ?>
                    <li><a href="<?= $href ?>"<?= $activePage === $key ? ' aria-current="page"' : '' ?>><?= $label ?></a></li>
                <?php endforeach; ?>
                <?php if (is_logged_in()): ?>
                    <li><a href="profile.php"<?= $activePage === 'profile' ? ' aria-current="page"' : '' ?>>Profile</a></li>
                    <li><a class="nav-btn" href="logout.php">Sign out</a></li>
                <?php else: ?>
                    <li><a class="nav-btn" href="auth.php"<?= $activePage === 'auth' ? ' aria-current="page"' : '' ?>>Sign in</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>
<main id="main">
<?php $flashes = get_flashes(); if ($flashes): ?>
    <div class="wrap flash-stack">
        <?php foreach ($flashes as $flashItem): ?>
            <p class="flash flash-<?= e($flashItem['type']) ?>" role="status"><?= e($flashItem['message']) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
