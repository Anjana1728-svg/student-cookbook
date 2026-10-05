-- =====================================================================
-- Student Cookbook & Meal Planner - MySQL database
-- MIT122 Assignment 2
-- Import this file in phpMyAdmin (Import tab) or run:
--   mysql -u root -p < cookbook.sql
-- =====================================================================

DROP DATABASE IF EXISTS student_cookbook;
CREATE DATABASE student_cookbook CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE student_cookbook;

-- ---------------------------------------------------------------------
-- 1. users : registered student accounts
-- ---------------------------------------------------------------------
CREATE TABLE users (
    user_id            INT AUTO_INCREMENT PRIMARY KEY,
    username           VARCHAR(50)  NOT NULL UNIQUE,
    email              VARCHAR(120) NOT NULL UNIQUE,
    password_hash      VARCHAR(255) NOT NULL,
    dietary_preference ENUM('none','vegetarian','vegan','gluten-free','dairy-free') NOT NULL DEFAULT 'none',
    budget             DECIMAL(8,2) NOT NULL DEFAULT 60.00,   -- weekly grocery budget (AUD)
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. recipes : every recipe on the site (system or student-submitted)
-- ---------------------------------------------------------------------
CREATE TABLE recipes (
    recipe_id        INT AUTO_INCREMENT PRIMARY KEY,
    title            VARCHAR(120) NOT NULL,
    description      TEXT NOT NULL,
    instructions     TEXT NOT NULL,              -- one step per line
    prep_time        INT NOT NULL DEFAULT 0,     -- minutes
    cook_time        INT NOT NULL DEFAULT 0,     -- minutes
    servings         INT NOT NULL DEFAULT 1,
    cost_per_serving DECIMAL(6,2) NOT NULL DEFAULT 0.00,  -- calculated from ingredients
    difficulty       ENUM('easy','medium','hard') NOT NULL DEFAULT 'easy',
    cuisine          VARCHAR(40) NOT NULL,
    dietary          ENUM('none','vegetarian','vegan','gluten-free','dairy-free') NOT NULL DEFAULT 'none',
    calories         INT DEFAULT NULL,           -- per serving
    protein_g        INT DEFAULT NULL,           -- per serving
    image            VARCHAR(255) DEFAULT NULL,  -- path inside /uploads
    created_by       INT DEFAULT NULL,           -- NULL = site recipe
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. ingredients : master list with a typical supermarket price per unit
-- ---------------------------------------------------------------------
CREATE TABLE ingredients (
    ingredient_id   INT AUTO_INCREMENT PRIMARY KEY,
    ingredient_name VARCHAR(80) NOT NULL UNIQUE,
    unit            VARCHAR(20) NOT NULL,
    typical_price   DECIMAL(8,3) NOT NULL DEFAULT 0.000   -- AUD per unit
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. recipe_ingredients : which ingredients (and how much) a recipe needs
-- ---------------------------------------------------------------------
CREATE TABLE recipe_ingredients (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id     INT NOT NULL,
    ingredient_id INT NOT NULL,
    quantity      DECIMAL(8,3) NOT NULL,   -- for the whole recipe (all servings)
    FOREIGN KEY (recipe_id)     REFERENCES recipes(recipe_id)         ON DELETE CASCADE,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(ingredient_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. meal_plans : a student's weekly plan
-- ---------------------------------------------------------------------
CREATE TABLE meal_plans (
    meal_plan_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    plan_name    VARCHAR(80) NOT NULL,
    start_date   DATE NOT NULL,                  -- always a Monday
    end_date     DATE NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. meal_plan_items : which recipe goes on which day / meal
-- ---------------------------------------------------------------------
CREATE TABLE meal_plan_items (
    item_id      INT AUTO_INCREMENT PRIMARY KEY,
    meal_plan_id INT NOT NULL,
    recipe_id    INT NOT NULL,
    day_of_week  TINYINT NOT NULL,                          -- 1 = Monday ... 7 = Sunday
    meal_type    ENUM('breakfast','lunch','dinner') NOT NULL,
    UNIQUE KEY one_meal_per_slot (meal_plan_id, day_of_week, meal_type),
    FOREIGN KEY (meal_plan_id) REFERENCES meal_plans(meal_plan_id) ON DELETE CASCADE,
    FOREIGN KEY (recipe_id)    REFERENCES recipes(recipe_id)       ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. reviews : star ratings and comments (one per user per recipe)
-- ---------------------------------------------------------------------
CREATE TABLE reviews (
    review_id  INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id  INT NOT NULL,
    user_id    INT NOT NULL,
    rating     TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment    TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY one_review_each (recipe_id, user_id),
    FOREIGN KEY (recipe_id) REFERENCES recipes(recipe_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(user_id)     ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. saved_recipes : a student's favourites
-- ---------------------------------------------------------------------
CREATE TABLE saved_recipes (
    user_id   INT NOT NULL,
    recipe_id INT NOT NULL,
    saved_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, recipe_id),
    FOREIGN KEY (user_id)   REFERENCES users(user_id)     ON DELETE CASCADE,
    FOREIGN KEY (recipe_id) REFERENCES recipes(recipe_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- SAMPLE DATA
-- =====================================================================

-- Demo account  ->  username: demo   password: password123
INSERT INTO users (username, email, password_hash, dietary_preference, budget) VALUES
('demo', 'demo@student.edu.au', '$2y$10$cnUMAvbdR7s.vIMyfPQNruvrKMpOkMjXIs3/eMXoLupmO1wygEVe6', 'none', 60.00);

INSERT INTO ingredients (ingredient_id, ingredient_name, unit, typical_price) VALUES
(1,  'White rice',            'kg',    2.500),
(2,  'Eggs',                  'pcs',   0.500),
(3,  'Frozen mixed vegetables','kg',   4.000),
(4,  'Soy sauce',             'ml',    0.010),
(5,  'Pasta',                 'kg',    2.000),
(6,  'Canned tomatoes',       'can',   1.000),
(7,  'Garlic',                'clove', 0.100),
(8,  'Brown onion',           'pcs',   0.600),
(9,  'Olive oil',             'ml',    0.012),
(10, 'Canned chickpeas',      'can',   1.100),
(11, 'Coconut milk',          'can',   1.600),
(12, 'Curry powder',          'tbsp',  0.300),
(13, 'Rolled oats',           'kg',    2.800),
(14, 'Milk',                  'L',     1.600),
(15, 'Banana',                'pcs',   0.400),
(16, 'Honey',                 'tbsp',  0.250),
(17, 'Chicken breast',        'kg',   11.000),
(18, 'Capsicum',              'pcs',   1.500),
(19, 'Tortillas',             'pcs',   0.450),
(20, 'Canned kidney beans',   'can',   1.100),
(21, 'Cheddar cheese',        'kg',   12.000),
(22, 'Red lentils',           'kg',    4.500),
(23, 'Carrot',                'pcs',   0.350),
(24, 'Vegetable stock cube',  'pcs',   0.300),
(25, 'Bread',                 'slice', 0.150),
(26, 'Butter',                'tbsp',  0.200),
(27, 'Canned tuna',           'can',   2.000),
(28, 'Peanut butter',         'tbsp',  0.200),
(29, 'Baby spinach',          'cup',   0.600),
(30, 'Beef mince',            'kg',   12.000),
(31, 'Taco seasoning',        'packet',1.200),
(32, 'Tomato',                'pcs',   0.800),
(33, 'Ground cumin',          'tsp',   0.150);

INSERT INTO recipes (recipe_id, title, description, instructions, prep_time, cook_time, servings, difficulty, cuisine, dietary, calories, protein_g) VALUES
(1, 'Egg Fried Rice',
 'Turns leftover rice and a bag of frozen veg into dinner in twenty minutes.',
 'Heat the oil in a large pan or wok over high heat.\nFry the garlic for 30 seconds, then add the frozen vegetables and cook for 3 minutes.\nPush everything to one side, crack in the eggs and scramble them.\nAdd the cooked rice and soy sauce and stir-fry for 4 minutes until hot.\nTaste, add more soy sauce if needed, and serve.',
 10, 10, 2, 'easy', 'Asian', 'vegetarian', 480, 16),
(2, 'One-Pot Tomato Pasta',
 'Pasta, tomatoes and garlic cooked together in one pot, so there is only one thing to wash.',
 'Dice the onion and slice the garlic.\nAdd pasta, canned tomatoes, onion, garlic, oil and 700 ml of water to a large pot.\nBring to the boil, then simmer for 12 to 15 minutes, stirring often, until the pasta is cooked and the sauce is thick.\nSeason with salt and pepper and serve.',
 5, 15, 2, 'easy', 'Italian', 'vegan', 520, 15),
(3, 'Chickpea Coconut Curry',
 'A mild, creamy curry that makes four meals and tastes better the next day.',
 'Cook the rice following the packet instructions.\nFry the diced onion in a little oil for 5 minutes until soft.\nAdd garlic and curry powder and stir for 1 minute.\nAdd drained chickpeas, canned tomatoes and coconut milk.\nSimmer for 15 minutes until thickened.\nServe over the rice. Keeps 3 days in the fridge.',
 10, 25, 4, 'easy', 'Indian', 'vegan', 560, 14),
(4, 'Overnight Oats',
 'Make it before bed, eat it on the way to class. No cooking at all.',
 'Mix the oats and milk in a jar or container.\nSlice half the banana into the jar and stir in the honey.\nCover and refrigerate overnight.\nTop with the rest of the banana in the morning.',
 5, 0, 1, 'easy', 'Breakfast', 'vegetarian', 390, 12),
(5, 'Chicken Veggie Stir-Fry',
 'A high-protein weeknight dinner that is quicker than ordering takeaway.',
 'Cook the rice following the packet instructions.\nSlice the chicken into thin strips and the capsicum into strips.\nHeat the oil in a wok over high heat and cook the chicken for 5 minutes.\nAdd garlic, capsicum and frozen vegetables and cook for 4 minutes.\nStir in the soy sauce and serve over rice.',
 10, 12, 2, 'medium', 'Asian', 'dairy-free', 610, 42),
(6, 'Bean & Cheese Quesadillas',
 'Crispy, cheesy and filling. Good for lunch or a late-night study snack.',
 'Drain and roughly mash the kidney beans.\nDice the capsicum.\nSpread beans on two tortillas, top with capsicum and grated cheese, then cover with the other tortillas.\nCook in a dry pan for 2 to 3 minutes each side until golden.\nCut into wedges and serve.',
 5, 10, 2, 'easy', 'Mexican', 'vegetarian', 540, 22),
(7, 'Red Lentil Soup',
 'Cheap, warming and freezes well. One pot gives you four lunches.',
 'Dice the onion and carrots and crush the garlic.\nSoften them in a pot with a little oil for 5 minutes.\nAdd cumin, rinsed lentils, canned tomatoes, stock cubes and 1.2 litres of water.\nSimmer for 25 minutes until the lentils break down.\nBlend or mash for a smoother soup and season to taste.',
 10, 30, 4, 'easy', 'Mediterranean', 'vegan', 310, 17),
(8, 'Scrambled Eggs on Toast',
 'The classic five-minute breakfast every student should have down.',
 'Whisk the eggs with the milk and a pinch of salt.\nMelt the butter in a pan over low heat.\nAdd the eggs and stir gently until just set.\nToast the bread and pile the eggs on top.',
 2, 5, 1, 'easy', 'Breakfast', 'vegetarian', 420, 24),
(9, 'Tuna Pasta Bake',
 'A budget family-style bake that covers four dinners.',
 'Preheat the oven to 200C.\nCook the pasta for 2 minutes less than the packet says and drain.\nFry the diced onion until soft, then add canned tomatoes and drained tuna.\nMix the sauce with the pasta and tip into a baking dish.\nTop with grated cheese and bake for 15 minutes until golden.',
 10, 25, 4, 'medium', 'Italian', 'none', 590, 33),
(10, 'Peanut Butter Banana Smoothie',
 'A filling breakfast in a glass when you have no time to sit down.',
 'Add banana, peanut butter, milk, oats and honey to a blender.\nBlend until smooth.\nAdd a few ice cubes if you like it cold and blend again.',
 5, 0, 1, 'easy', 'Breakfast', 'vegetarian', 450, 16),
(11, 'Spinach & Capsicum Omelette',
 'A quick gluten-free meal that uses up veg before it goes off.',
 'Whisk the eggs with a pinch of salt and pepper.\nDice the capsicum and cook it in the oil for 2 minutes.\nAdd the spinach and let it wilt.\nPour in the eggs and cook without stirring until almost set.\nSprinkle cheese on one half, fold and serve.',
 5, 8, 1, 'easy', 'Breakfast', 'gluten-free', 380, 26),
(12, 'Beef Mince Tacos',
 'Share-house taco night for four, for less than one takeaway meal.',
 'Dice the onion and tomatoes and grate the cheese.\nBrown the mince with the onion in a pan for 8 minutes.\nStir in the taco seasoning and a splash of water and simmer for 3 minutes.\nWarm the tortillas in a dry pan.\nFill with mince, tomato and cheese.',
 10, 15, 4, 'easy', 'Mexican', 'none', 620, 36);

INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity) VALUES
-- 1 Egg fried rice
(1,1,0.2),(1,2,2),(1,3,0.2),(1,4,30),(1,7,2),(1,9,15),
-- 2 Tomato pasta
(2,5,0.2),(2,6,1),(2,7,2),(2,8,1),(2,9,15),
-- 3 Chickpea curry
(3,10,2),(3,11,1),(3,6,1),(3,8,1),(3,7,3),(3,12,2),(3,1,0.3),
-- 4 Overnight oats
(4,13,0.05),(4,14,0.15),(4,15,1),(4,16,1),
-- 5 Chicken stir-fry
(5,17,0.3),(5,18,1),(5,3,0.15),(5,4,30),(5,7,2),(5,1,0.2),(5,9,15),
-- 6 Quesadillas
(6,19,4),(6,20,1),(6,21,0.1),(6,18,1),
-- 7 Lentil soup
(7,22,0.25),(7,23,2),(7,8,1),(7,7,2),(7,24,2),(7,33,1),(7,6,1),
-- 8 Scrambled eggs
(8,2,3),(8,25,2),(8,26,1),(8,14,0.03),
-- 9 Tuna pasta bake
(9,5,0.4),(9,27,2),(9,6,1),(9,21,0.1),(9,8,1),
-- 10 Smoothie
(10,15,1),(10,28,2),(10,14,0.25),(10,13,0.03),(10,16,1),
-- 11 Omelette
(11,2,3),(11,29,1),(11,18,0.5),(11,21,0.03),(11,9,5),
-- 12 Tacos
(12,30,0.5),(12,31,1),(12,19,8),(12,21,0.1),(12,32,2),(12,8,1);

-- Work out cost per serving from the ingredient prices
UPDATE recipes r
SET cost_per_serving = ROUND((
    SELECT SUM(ri.quantity * i.typical_price)
    FROM recipe_ingredients ri
    JOIN ingredients i ON i.ingredient_id = ri.ingredient_id
    WHERE ri.recipe_id = r.recipe_id
) / r.servings, 2);

-- A few sample reviews from the demo account
INSERT INTO reviews (recipe_id, user_id, rating, comment) VALUES
(1, 1, 5, 'My go-to after a late lab. Day-old rice works best.'),
(3, 1, 4, 'Made a big batch on Sunday and had lunch sorted for three days.'),
(7, 1, 5, 'Cheapest thing I cook and it actually fills me up.');

INSERT INTO saved_recipes (user_id, recipe_id) VALUES (1, 1), (1, 3);

-- A sample meal plan for the demo account
INSERT INTO meal_plans (meal_plan_id, user_id, plan_name, start_date, end_date) VALUES
(1, 1, 'Exam week',
    DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY),                     -- Monday this week
    DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 6 DAY));

INSERT INTO meal_plan_items (meal_plan_id, recipe_id, day_of_week, meal_type) VALUES
(1, 4, 1, 'breakfast'), (1, 7, 1, 'lunch'), (1, 3, 1, 'dinner'),
(1, 8, 2, 'breakfast'), (1, 7, 2, 'lunch'), (1, 1, 2, 'dinner'),
(1, 10,3, 'breakfast'), (1, 3, 3, 'lunch'), (1, 2, 3, 'dinner');
