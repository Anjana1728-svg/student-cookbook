<?php
/**
 * Shared helpers used by every page:
 * sessions, login checks, CSRF protection, flash messages and formatting.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

const DAYS       = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
const MEAL_TYPES = ['breakfast', 'lunch', 'dinner'];
const DIETS      = ['none' => 'No preference', 'vegetarian' => 'Vegetarian', 'vegan' => 'Vegan', 'gluten-free' => 'Gluten-free', 'dairy-free' => 'Dairy-free'];
const CUISINES   = ['Asian', 'Breakfast', 'Indian', 'Italian', 'Mediterranean', 'Mexican', 'Other'];

/** Escape output for HTML (prevents XSS). */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function current_user_id(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

/** Send visitors to the login page if they are not signed in. */
function require_login(): void
{
    if (!is_logged_in()) {
        flash('Sign in to use that page.', 'info');
        $back = urlencode($_SERVER['REQUEST_URI'] ?? 'index.php');
        header("Location: auth.php?next=$back");
        exit;
    }
}

function current_user(PDO $pdo): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT user_id, username, email, dietary_preference, budget, created_at FROM users WHERE user_id = ?');
    $stmt->execute([current_user_id()]);
    return $stmt->fetch() ?: null;
}

/* ---------- CSRF protection for every POST form ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function check_csrf(): void
{
    if (!hash_equals(csrf_token(), $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('This form has expired. Go back, refresh the page and try again.');
    }
}

/* ---------- One-time messages shown at the top of the next page ---------- */
function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
}

function get_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function redirect(string $url): void
{
    header("Location: $url");
    exit;
}

/* ---------- Formatting ---------- */
function money($amount): string
{
    return '$' . number_format((float) $amount, 2);
}

/** 0.200 -> 0.2, 3.000 -> 3 */
function qty($number): string
{
    $n = round((float) $number, 2);
    return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
}

function stars(float $rating): string
{
    $full = (int) round($rating);
    return '<span class="stars" aria-label="' . number_format($rating, 1) . ' out of 5">'
        . str_repeat('★', $full) . '<span class="stars-empty">' . str_repeat('★', 5 - $full) . '</span></span>';
}

/** Emoji shown on recipe cards when there is no uploaded photo. */
function cuisine_emoji(string $cuisine): string
{
    $map = ['Asian' => '🥢', 'Breakfast' => '🍳', 'Indian' => '🍛', 'Italian' => '🍝', 'Mediterranean' => '🥣', 'Mexican' => '🌮'];
    return $map[$cuisine] ?? '🍽️';
}

/** Recalculate a recipe's cost per serving from its ingredient prices. */
function update_recipe_cost(PDO $pdo, int $recipeId): void
{
    $sql = 'UPDATE recipes r SET cost_per_serving = ROUND(COALESCE((
                SELECT SUM(ri.quantity * i.typical_price)
                FROM recipe_ingredients ri JOIN ingredients i ON i.ingredient_id = ri.ingredient_id
                WHERE ri.recipe_id = r.recipe_id), 0) / r.servings, 2)
            WHERE r.recipe_id = ?';
    $pdo->prepare($sql)->execute([$recipeId]);
}

/** Render one recipe card (used on home, browse and profile pages). */
function recipe_card(array $r): string
{
    $img = $r['image']
        ? '<img src="' . e($r['image']) . '" alt="" loading="lazy">'
        : '<span class="card-emoji" aria-hidden="true">' . cuisine_emoji($r['cuisine']) . '</span>';
    $rating = isset($r['avg_rating']) && $r['avg_rating'] !== null
        ? stars((float) $r['avg_rating']) . ' <span class="muted">(' . (int) $r['review_count'] . ')</span>'
        : '<span class="muted">No ratings yet</span>';
    $diet = $r['dietary'] !== 'none' ? '<span class="tag">' . e(DIETS[$r['dietary']]) . '</span>' : '';

    return '<article class="recipe-card">
        <a class="card-link" href="recipe-detail.php?id=' . (int) $r['recipe_id'] . '">
            <div class="card-media">' . $img . '</div>
            <span class="price-tag"><strong>' . money($r['cost_per_serving']) . '</strong> a serve</span>
            <div class="card-body">
                <h3>' . e($r['title']) . '</h3>
                <p class="card-meta">' . ((int) $r['prep_time'] + (int) $r['cook_time']) . ' min, ' . e($r['difficulty']) . ', ' . e($r['cuisine']) . '</p>
                <p class="card-rating">' . $rating . '</p>
                ' . $diet . '
            </div>
        </a>
    </article>';
}

/** Base query for recipe lists with average rating. */
const RECIPE_LIST_SQL = 'SELECT r.*, AVG(rv.rating) AS avg_rating, COUNT(rv.review_id) AS review_count
    FROM recipes r LEFT JOIN reviews rv ON rv.recipe_id = r.recipe_id';
