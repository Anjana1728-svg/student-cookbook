<?php
/** Page 7 - My recipes: submit, edit and delete recipes you have shared. */
require_once 'includes/functions.php';
require_login();

$uid    = current_user_id();
$errors = [];

/** Load one of this user's recipes (or null). */
function own_recipe(PDO $pdo, int $id, int $uid): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM recipes WHERE recipe_id = ? AND created_by = ?');
    $stmt->execute([$id, $uid]);
    return $stmt->fetch() ?: null;
}

/** Save an uploaded photo and return its path, or null. Adds to $errors on failure. */
function save_upload(array $file, array &$errors): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'The photo did not upload. Try a smaller image.';
        return null;
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        $errors[] = 'Photos must be 2 MB or smaller.';
        return null;
    }
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime  = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($types[$mime])) {
        $errors[] = 'Photos must be JPG, PNG or WebP.';
        return null;
    }
    $name = 'uploads/' . bin2hex(random_bytes(8)) . '.' . $types[$mime];
    if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/' . $name)) {
        $errors[] = 'The photo could not be saved. Check that the uploads folder is writable.';
        return null;
    }
    return $name;
}

$editing = isset($_GET['edit']) ? own_recipe($pdo, (int) $_GET['edit'], $uid) : null;

/* ---------- Handle form actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $recipe = own_recipe($pdo, (int) $_POST['recipe_id'], $uid);
        if ($recipe) {
            $pdo->prepare('DELETE FROM recipes WHERE recipe_id = ?')->execute([$recipe['recipe_id']]);
            if ($recipe['image'] && is_file(__DIR__ . '/' . $recipe['image'])) unlink(__DIR__ . '/' . $recipe['image']);
            flash('"' . $recipe['title'] . '" deleted.', 'info');
        }
        redirect('my-recipes.php');
    }

    if ($action === 'save') {
        $editing = !empty($_POST['recipe_id']) ? own_recipe($pdo, (int) $_POST['recipe_id'], $uid) : null;

        $data = [
            'title'        => trim($_POST['title'] ?? ''),
            'description'  => trim($_POST['description'] ?? ''),
            'instructions' => trim($_POST['instructions'] ?? ''),
            'prep_time'    => (int) ($_POST['prep_time'] ?? 0),
            'cook_time'    => (int) ($_POST['cook_time'] ?? 0),
            'servings'     => (int) ($_POST['servings'] ?? 1),
            'difficulty'   => $_POST['difficulty'] ?? 'easy',
            'cuisine'      => $_POST['cuisine'] ?? 'Other',
            'dietary'      => $_POST['dietary'] ?? 'none',
            'calories'     => ($_POST['calories'] ?? '') !== '' ? (int) $_POST['calories'] : null,
            'protein_g'    => ($_POST['protein_g'] ?? '') !== '' ? (int) $_POST['protein_g'] : null,
        ];

        // Collect ingredient rows, skipping blank ones
        $rows = [];
        foreach ($_POST['ing_name'] ?? [] as $i => $name) {
            $name = trim($name);
            if ($name === '') continue;
            $rows[] = [
                'name'  => substr(ucfirst($name), 0, 80),
                'qty'   => (float) ($_POST['ing_qty'][$i] ?? 0),
                'unit'  => substr(trim($_POST['ing_unit'][$i] ?? '') ?: 'pcs', 0, 20),
                'price' => (float) ($_POST['ing_price'][$i] ?? 0),
            ];
        }

        if ($data['title'] === '' || strlen($data['title']) > 120) $errors[] = 'Give the recipe a title of up to 120 characters.';
        if ($data['description'] === '')  $errors[] = 'Add a short description.';
        if ($data['instructions'] === '') $errors[] = 'Add the method, one step per line.';
        if ($data['servings'] < 1 || $data['servings'] > 20) $errors[] = 'Servings must be between 1 and 20.';
        if ($data['prep_time'] < 0 || $data['cook_time'] < 0) $errors[] = 'Times cannot be negative.';
        if (!in_array($data['difficulty'], ['easy', 'medium', 'hard'], true)) $data['difficulty'] = 'easy';
        if (!in_array($data['cuisine'], CUISINES, true)) $data['cuisine'] = 'Other';
        if (!array_key_exists($data['dietary'], DIETS)) $data['dietary'] = 'none';
        if (!$rows) $errors[] = 'Add at least one ingredient.';
        foreach ($rows as $r) {
            if ($r['qty'] <= 0) { $errors[] = 'Every ingredient needs a quantity above zero.'; break; }
        }

        $image = $errors ? null : save_upload($_FILES['image'] ?? [], $errors);

        if (!$errors) {
            $pdo->beginTransaction();
            try {
                if ($editing) {
                    $sql = 'UPDATE recipes SET title=?, description=?, instructions=?, prep_time=?, cook_time=?, servings=?,
                            difficulty=?, cuisine=?, dietary=?, calories=?, protein_g=?' . ($image ? ', image=?' : '') . '
                            WHERE recipe_id=? AND created_by=?';
                    $params = array_values($data);
                    if ($image) $params[] = $image;
                    array_push($params, $editing['recipe_id'], $uid);
                    $pdo->prepare($sql)->execute($params);
                    $recipeId = (int) $editing['recipe_id'];
                    $pdo->prepare('DELETE FROM recipe_ingredients WHERE recipe_id = ?')->execute([$recipeId]);
                    if ($image && $editing['image'] && is_file(__DIR__ . '/' . $editing['image'])) unlink(__DIR__ . '/' . $editing['image']);
                } else {
                    $sql = 'INSERT INTO recipes (title, description, instructions, prep_time, cook_time, servings,
                            difficulty, cuisine, dietary, calories, protein_g, image, created_by)
                            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)';
                    $params = array_values($data);
                    array_push($params, $image, $uid);
                    $pdo->prepare($sql)->execute($params);
                    $recipeId = (int) $pdo->lastInsertId();
                }

                // Link ingredients, adding any new ones to the master list
                $find   = $pdo->prepare('SELECT ingredient_id FROM ingredients WHERE ingredient_name = ?');
                $insert = $pdo->prepare('INSERT INTO ingredients (ingredient_name, unit, typical_price) VALUES (?, ?, ?)');
                $link   = $pdo->prepare('INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity) VALUES (?, ?, ?)');
                foreach ($rows as $r) {
                    $find->execute([$r['name']]);
                    $ingId = $find->fetchColumn();
                    if (!$ingId) {
                        $insert->execute([$r['name'], $r['unit'], max(0, $r['price'])]);
                        $ingId = $pdo->lastInsertId();
                    }
                    $link->execute([$recipeId, $ingId, $r['qty']]);
                }
                update_recipe_cost($pdo, $recipeId);
                $pdo->commit();

                flash($editing ? 'Recipe updated.' : 'Recipe shared. Other students can now find it.');
                redirect("recipe-detail.php?id=$recipeId");
            } catch (Throwable $ex) {
                $pdo->rollBack();
                $errors[] = 'The recipe could not be saved. Please try again.';
            }
        }
        // Keep what the user typed so they do not lose it
        $editing = array_merge($editing ?? [], $data, ['recipe_id' => $editing['recipe_id'] ?? null]);
        $formRows = $rows;
    }
}

/* ---------- Data for the page ---------- */
$stmt = $pdo->prepare(RECIPE_LIST_SQL . ' WHERE r.created_by = ? GROUP BY r.recipe_id ORDER BY r.created_at DESC');
$stmt->execute([$uid]);
$mine = $stmt->fetchAll();

$allIngredients = $pdo->query('SELECT ingredient_name, unit, typical_price FROM ingredients ORDER BY ingredient_name')->fetchAll();

if (!isset($formRows)) {
    $formRows = [];
    if (!empty($editing['recipe_id'])) {
        $stmt = $pdo->prepare('SELECT i.ingredient_name AS name, ri.quantity AS qty, i.unit, i.typical_price AS price
                               FROM recipe_ingredients ri JOIN ingredients i ON i.ingredient_id = ri.ingredient_id
                               WHERE ri.recipe_id = ? ORDER BY ri.id');
        $stmt->execute([$editing['recipe_id']]);
        $formRows = $stmt->fetchAll();
    }
}
if (!$formRows) $formRows = [['name' => '', 'qty' => '', 'unit' => '', 'price' => '']];

$v = fn($key, $default = '') => e($editing[$key] ?? $default);
$isEdit = !empty($editing['recipe_id']);

$pageTitle  = 'My recipes';
$activePage = 'mine';
require 'includes/header.php';
?>
<section class="wrap section">
    <h1>My recipes</h1>

    <?php if ($mine): ?>
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th scope="col">Recipe</th><th scope="col">Per serve</th><th scope="col">Time</th><th scope="col">Rating</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach ($mine as $r): ?>
                    <tr>
                        <td><a href="recipe-detail.php?id=<?= (int) $r['recipe_id'] ?>"><?= e($r['title']) ?></a></td>
                        <td><?= money($r['cost_per_serving']) ?></td>
                        <td><?= $r['prep_time'] + $r['cook_time'] ?> min</td>
                        <td><?= $r['avg_rating'] ? stars((float) $r['avg_rating']) : '<span class="muted">None yet</span>' ?></td>
                        <td class="row-actions">
                            <a class="btn btn-ghost btn-small" href="my-recipes.php?edit=<?= (int) $r['recipe_id'] ?>#recipe-form">Edit</a>
                            <form method="post" data-confirm="Delete &quot;<?= e($r['title']) ?>&quot;? It will also be removed from any meal plans.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="recipe_id" value="<?= (int) $r['recipe_id'] ?>">
                                <button class="btn btn-danger btn-small" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="muted">You have not shared any recipes yet. Your first one goes below.</p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="form recipe-form" id="recipe-form" data-validate novalidate>
        <h2><?= $isEdit ? 'Edit "' . $v('title') . '"' : 'Share a recipe' ?></h2>
        <?php if ($errors): ?>
            <div class="form-errors" role="alert"><?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?></div>
        <?php endif; ?>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="recipe_id" value="<?= $v('recipe_id') ?>">

        <label>Title
            <input type="text" name="title" required maxlength="120" value="<?= $v('title') ?>" data-error="Give the recipe a title.">
        </label>
        <label>Short description
            <input type="text" name="description" required maxlength="255" value="<?= $v('description') ?>" placeholder="What makes it good for students?" data-error="Add a short description.">
        </label>

        <div class="form-row form-row-4">
            <label>Prep (min)<input type="number" name="prep_time" min="0" max="600" value="<?= $v('prep_time', 10) ?>"></label>
            <label>Cook (min)<input type="number" name="cook_time" min="0" max="600" value="<?= $v('cook_time', 15) ?>"></label>
            <label>Servings<input type="number" name="servings" min="1" max="20" required value="<?= $v('servings', 2) ?>" data-error="1 to 20."></label>
            <label>Difficulty
                <select name="difficulty">
                    <?php foreach (['easy', 'medium', 'hard'] as $d): ?>
                        <option value="<?= $d ?>"<?= ($editing['difficulty'] ?? 'easy') === $d ? ' selected' : '' ?>><?= ucfirst($d) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="form-row form-row-4">
            <label>Cuisine
                <select name="cuisine">
                    <?php foreach (CUISINES as $c): ?>
                        <option<?= ($editing['cuisine'] ?? '') === $c ? ' selected' : '' ?>><?= $c ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Diet
                <select name="dietary">
                    <?php foreach (DIETS as $val => $label): ?>
                        <option value="<?= $val ?>"<?= ($editing['dietary'] ?? 'none') === $val ? ' selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Calories per serve<input type="number" name="calories" min="0" max="5000" value="<?= $v('calories') ?>"></label>
            <label>Protein per serve (g)<input type="number" name="protein_g" min="0" max="500" value="<?= $v('protein_g') ?>"></label>
        </div>

        <fieldset class="ingredient-fieldset">
            <legend>Ingredients</legend>
            <p class="muted small">Pick from the list or type a new ingredient. Price per unit is only needed for new ingredients and is used to work out the cost per serve.</p>
            <div class="ingredient-rows" data-ingredient-rows>
                <?php foreach ($formRows as $row): ?>
                    <div class="ingredient-row">
                        <label>Ingredient<input type="text" name="ing_name[]" list="ingredient-list" value="<?= e($row['name']) ?>" data-ingredient-name></label>
                        <label>Quantity<input type="number" name="ing_qty[]" step="0.01" min="0" value="<?= e($row['qty'] !== '' ? qty($row['qty']) : '') ?>"></label>
                        <label>Unit<input type="text" name="ing_unit[]" value="<?= e($row['unit']) ?>" placeholder="kg, pcs, can" data-ingredient-unit></label>
                        <label>$ per unit<input type="number" name="ing_price[]" step="0.001" min="0" value="<?= e($row['price']) ?>" data-ingredient-price></label>
                        <button type="button" class="remove-row" aria-label="Remove this ingredient" data-remove-row>×</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-ghost" data-add-row>Add ingredient</button>
            <p class="cost-preview" aria-live="polite">Estimated cost per serve: <strong data-cost-preview>$0.00</strong></p>
            <datalist id="ingredient-list">
                <?php foreach ($allIngredients as $ing): ?>
                    <option value="<?= e($ing['ingredient_name']) ?>" data-unit="<?= e($ing['unit']) ?>" data-price="<?= e($ing['typical_price']) ?>"></option>
                <?php endforeach; ?>
            </datalist>
        </fieldset>

        <label>Method (one step per line)
            <textarea name="instructions" rows="7" required data-error="Add the method." placeholder="Boil the pasta.&#10;Fry the garlic.&#10;Mix together and serve."><?= $v('instructions') ?></textarea>
        </label>
        <label>Photo (optional, JPG, PNG or WebP up to 2 MB)
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-max-size="2097152">
        </label>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Save changes' : 'Share recipe' ?></button>
            <?php if ($isEdit): ?><a class="text-link" href="my-recipes.php">Cancel editing</a><?php endif; ?>
        </div>
    </form>
</section>
<?php require 'includes/footer.php'; ?>
