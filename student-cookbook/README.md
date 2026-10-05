# Student Cookbook & Meal Planner

A web information system that helps university and TAFE students find cheap, quick recipes, plan a week of meals and generate one combined shopping list with an estimated total cost.

MIT122 Interactive Web Design and Development, Assignment 2
Group: Akshay Jangid (989082), Abhishek Soni (989091), Anjana (989108)

## Built with

HTML5, CSS3 and JavaScript on the front end; PHP 8 with PDO and a MySQL database on the back end. Developed locally with XAMPP and managed in Git/GitHub.

## Pages (8)

| # | File | What it does | Tables used |
|---|------|--------------|-------------|
| 1 | `index.php` | Home: search box, cheapest recipes, how the site works | recipes, reviews |
| 2 | `auth.php` | Sign in and create an account (tabbed) | users |
| 3 | `recipes.php` | Browse and filter recipes by cuisine, time, cost, difficulty and diet | recipes, reviews, recipe_ingredients, ingredients |
| 4 | `recipe-detail.php` | Ingredients, method, cost, nutrition, reviews, save and add to plan | recipes, recipe_ingredients, ingredients, reviews, saved_recipes, meal_plans, meal_plan_items |
| 5 | `planner.php` | Weekly planner (7 days x breakfast, lunch, dinner) with budget meter | meal_plans, meal_plan_items, recipes, users |
| 6 | `shopping-list.php` | Combined shopping list with quantities and total cost, tick-off and print | meal_plan_items, recipes, recipe_ingredients, ingredients |
| 7 | `my-recipes.php` | Share, edit and delete your own recipes, with photo upload | recipes, ingredients, recipe_ingredients |
| 8 | `profile.php` | Account settings, password change, saved recipes, plan history | users, saved_recipes, meal_plans |

`logout.php` and the files in `includes/` and `config/` are helper scripts, not pages.

## Database (8 tables)

`users`, `recipes`, `ingredients`, `recipe_ingredients`, `meal_plans`, `meal_plan_items`, `reviews`, `saved_recipes`. The full schema with comments and sample data is in `database/cookbook.sql`.

The shopping list is built with one SQL query that joins `meal_plan_items -> recipes -> recipe_ingredients -> ingredients` and uses `SUM()` with `GROUP BY` to combine the same ingredient across every recipe in the week.

## Run it locally with XAMPP

1. Install XAMPP and start **Apache** and **MySQL** from the XAMPP Control Panel.
2. Copy this whole folder into `C:\xampp\htdocs\` (Windows) or `/Applications/XAMPP/htdocs/` (Mac), so you have `htdocs/student-cookbook/`.
3. Open http://localhost/phpmyadmin, click **Import**, choose `database/cookbook.sql` and click **Import** (or **Go**). This creates the `student_cookbook` database with all tables and sample data.
4. Open http://localhost/student-cookbook/ in your browser.
5. Sign in with the demo account: username **demo**, password **password123**. Or create your own account.

If your MySQL root user has a password, change `DB_PASS` in `config/db.php`.
On Mac, if photo uploads fail, make the `uploads` folder writable (`chmod 777 uploads`).

## Security features

Passwords are stored with `password_hash()` (bcrypt) and checked with `password_verify()`. Every query uses PDO prepared statements to prevent SQL injection. All output is escaped with `htmlspecialchars()` to prevent XSS. Every form that changes data carries a CSRF token. Users can only edit their own recipes and meal plans. Uploaded photos are checked by real file type and size.

## JavaScript features

Form validation with inline error messages, sign-in/register tabs, filters that apply as you change them, auto-saving planner dropdowns, confirm dialogs before deleting, add/remove ingredient rows with a live cost-per-serve preview, and shopping list ticks remembered with `localStorage`.

## Push to GitHub

```bash
cd student-cookbook
git init
git add .
git commit -m "Student Cookbook and Meal Planner - Assignment 2"
git branch -M main
git remote add origin https://github.com/YOUR-USERNAME/student-cookbook.git
git push -u origin main
```

Create the empty repository on github.com first and set it to **Public**.

## Team and roles

| Member | Role | Main responsibilities |
|---|---|---|
| Akshay Jangid | Scrum Master and Backend Lead | Sprint planning, stand-ups, PHP for accounts, meal planner and security |
| Abhishek Soni | Frontend Lead | HTML5 structure, CSS3 design, JavaScript and responsive layout |
| Anjana | Database and GitHub Lead | MySQL database, SQL queries, sample data and GitHub repository |
