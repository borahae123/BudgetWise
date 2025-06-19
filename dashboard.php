<?php
include('../includes/db.php');
session_start();

if (!isset($_SESSION['user_id'])) {
    die("User not logged in.");
}

$user_id = $_SESSION['user_id'];
$currentMonth = date('Y-m');

// Fetch budget
$budget = 0;
$budgetQuery = "SELECT amount FROM budgets WHERE user_id = $user_id AND month_year = '$currentMonth'";
$budgetResult = $conn->query($budgetQuery);

if ($budgetResult->num_rows > 0) {
    $budget = $budgetResult->fetch_assoc()['amount'];
} else {
    // No budget row yet, so insert a ₹0 row for this month
    $conn->query("INSERT INTO budgets (user_id, amount, month_year) VALUES ($user_id, 0, '$currentMonth')");
    $budget = 0;
}


// Total spent this month
$spentQuery = "SELECT IFNULL(SUM(amount), 0) AS total FROM transactions WHERE user_id = $user_id AND type = 'expense' AND txn_date LIKE '$currentMonth%'";
$totalSpent = $conn->query($spentQuery)->fetch_assoc()['total'];
$remaining = $budget - $totalSpent;

// Adjust Budget
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_budget'])) {
    $amount = floatval($_POST['budget_amount']);
    $action = $_POST['adjust_type'];

    // Log to budget_transactions
    $conn->query("INSERT INTO budget_transactions (user_id, amount, action, note) VALUES ($user_id, $amount, '$action', 'Manual budget update')");

    if ($action === 'add') {
        $conn->query("UPDATE budgets SET amount = amount + $amount WHERE user_id = $user_id AND month_year = '$currentMonth'");
        $conn->query("INSERT INTO notifications (user_id, message) VALUES ($user_id, 'Budget increased by ₹$amount')");
    } elseif ($action === 'subtract') {
        $conn->query("UPDATE budgets SET amount = amount - $amount WHERE user_id = $user_id AND month_year = '$currentMonth'");
        $conn->query("INSERT INTO notifications (user_id, message) VALUES ($user_id, 'Budget reduced by ₹$amount')");
    }

    // 🔁 Redirect to avoid re-submission on refresh
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}
// Reset Budget
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_budget'])) {
    $conn->query("DELETE FROM budgets WHERE user_id = $user_id AND month_year = '$currentMonth'");
    $conn->query("DELETE FROM budget_transactions WHERE user_id = $user_id");
    $conn->query("INSERT INTO budgets (user_id, amount, month_year) VALUES ($user_id, 0, '$currentMonth')");
    $conn->query("INSERT INTO notifications (user_id, message) VALUES ($user_id, 'Budget reset for $currentMonth')");

    $budget = 0;
    $totalSpent = 0;
    $remaining = 0;
}

// Add Goal
if (isset($_POST['goal_title'])) {
    $title = $conn->real_escape_string($_POST['goal_title']);
    $target = floatval($_POST['goal_target']);
    $conn->query("INSERT INTO goals(user_id, title, target_amount, saved_amount) VALUES($user_id, '$title', $target, 0)");
    $conn->query("INSERT INTO notifications (user_id, message) VALUES ($user_id, 'New goal \"$title\" added with target ₹$target')");
}

// Save to Goal
if (isset($_POST['save_to_goal'])) {
    $gid = intval($_POST['goal_id']);
    $amount = floatval($_POST['amount']);
    $source = $_POST['source'];

    $goal = $conn->query("SELECT title, saved_amount FROM goals WHERE goal_id = $gid AND user_id = $user_id")->fetch_assoc();
    $newSaved = $goal['saved_amount'] + $amount;

    if ($source === 'budget' && $amount <= $remaining) {
        $conn->query("UPDATE budgets SET amount = amount - $amount WHERE user_id = $user_id AND month_year = '$currentMonth'");
    }

    $conn->query("UPDATE goals SET saved_amount = $newSaved WHERE goal_id = $gid AND user_id = $user_id");
    $goalTitle = $conn->real_escape_string($goal['title']);
    $conn->query("INSERT INTO notifications (user_id, message) VALUES ($user_id, 'Saved ₹$amount to goal \"$goalTitle\"')");

    $budget = $conn->query($budgetQuery)->fetch_assoc()['amount'];
    $totalSpent = $conn->query($spentQuery)->fetch_assoc()['total'];
    $remaining = $budget - $totalSpent;
}

// Handle custom category
if (isset($_POST['add_expense']) && isset($_POST['custom_category']) && !empty(trim($_POST['custom_category']))) {
    $customCategory = $conn->real_escape_string(trim($_POST['custom_category']));
    $conn->query("INSERT INTO categories (user_id, name, is_fixed) VALUES ($user_id, '$customCategory', 0)");
    $_POST['category_id'] = $conn->insert_id;
}

// Add Expense
if (isset($_POST['add_expense'])) {
    $amount = floatval($_POST['expense_amount']);
    $category = intval($_POST['category_id']);
    $payment = intval($_POST['payment_method_id']);
    $note = $conn->real_escape_string($_POST['note']);
    $mood = $conn->real_escape_string($_POST['mood']);
    $txn_date = date('Y-m-d');

    $image_url = '';
    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === 0) {
        $filename = basename($_FILES['receipt']['name']);
        $target = "../uploads/" . $filename;
        move_uploaded_file($_FILES['receipt']['tmp_name'], $target);
        $image_url = $target;
    }

    $conn->query("INSERT INTO transactions 
        (user_id, amount, txn_date, category_id, payment_method_id, note, type, mood, image_url) 
        VALUES 
        ($user_id, $amount, '$txn_date', $category, $payment, '$note', 'expense', '$mood', '$image_url')");

    $conn->query("INSERT INTO notifications (user_id, message) VALUES ($user_id, 'Expense of ₹$amount added.')");

    $totalSpent = $conn->query($spentQuery)->fetch_assoc()['total'];
    $remaining = $budget - $totalSpent;
}

// Top 3 categories
$topCatQuery = "SELECT c.name, SUM(t.amount) AS total FROM transactions t JOIN categories c ON t.category_id = c.category_id WHERE t.user_id = $user_id AND t.type = 'expense' AND t.txn_date LIKE '$currentMonth%' GROUP BY c.name ORDER BY total DESC LIMIT 3";
$topCats = $conn->query($topCatQuery);

// Other fetches
$goals = $conn->query("SELECT * FROM goals WHERE user_id = $user_id");
$notifications = $conn->query("SELECT * FROM notifications WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 5");
?>


<!-- HTML output handled in the rest of your code above, assumed unchanged -->


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BudgetWise Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.5.1/dist/confetti.browser.min.js"></script>
    <style>
        body { font-family: sans-serif; background: #f4f4f4; margin: 0; }
        .topnav {
            background-color: #7e57c2; /* ₹100 note */
            color: white;
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .topnav a { color: white; margin-left: 15px; text-decoration: none; font-weight: bold; }

        .container { padding: 25px; }

        .card {
            background: white;
            border-radius: 10px;
            margin-bottom: 20px;
            padding: 20px;
            box-shadow: 0 0 8px rgba(0,0,0,0.1);
        }

        input, select {
            padding: 12px;
            margin: 8px 4px;
            border: 1px solid #ccc;
            border-radius: 8px;
            width: 220px;
        }

        .btn {
            background-color: #388e3c; /* ₹500 note */
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 5px;
            cursor: pointer;
        }

        .progress {
            height: 14px;
            background-color: #eee;
            border-radius: 7px;
            overflow: hidden;
        }

        .progress .bar {
            height: 100%;
            background: #ef6c00; /* ₹200 note */
        }

        .noti {
            background-color: #ffebee;
            border-left: 5px solid #d50000; /* ₹10 note */
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
        }

        .top3cat li:nth-child(1) { color: #00bfa5; } /* ₹20 note */
        .top3cat li:nth-child(2) { color: #ffc107; } /* ₹200 note */
        .top3cat li:nth-child(3) { color: #8bc34a; } /* ₹500 note */
    </style>

</head>
<body>

<div class="topnav">
    BudgetWise ₹
    <div>
        <a href="#summary">Summary</a>
        <a href="#budget">Budget</a>
        <a href="#goals">Goals</a>
        <a href="/Budgetwise/templates/expenses.php">Expenses</a>


    </div>
</div>


<div class="card" id="analytics">


    </div>

<div class="container">
    <div class="card" id="summary">
        <h2>Total Spent: ₹<?= number_format($totalSpent, 2) ?></h2>
        <h2>Remaining Budget: ₹<?= number_format($remaining, 2) ?></h2>
    </div>

    <div class="card">
        <h3>Notifications 🔔</h3>
        <?php while ($notif = $notifications->fetch_assoc()): ?>
            <div class="noti"><?= htmlspecialchars($notif['message']) ?> <small style="float:right;">(<?= date('d M, H:i', strtotime($notif['created_at'])) ?>)</small></div>
        <?php endwhile; ?>
    </div>

<div class="card" id="add-expense">
    <h3>Add Expense</h3>
    <form method="post" enctype="multipart/form-data">
        <input type="number" name="expense_amount" step="0.01" placeholder="₹ Amount" required>

        <select name="category_id" id="category-dropdown" required>
            <option value="">Select Category</option>
            <?php
            $cats = $conn->query("SELECT * FROM categories WHERE user_id = $user_id OR is_fixed = 1");
            while ($c = $cats->fetch_assoc()) {
                echo "<option value='{$c['category_id']}'>" . htmlspecialchars($c['name']) . "</option>";
            }
            ?>
            <option value="other">Other</option>
        </select>

        <div id="custom-category-input" style="display: none;">
            <input type="text" name="custom_category" placeholder="Enter new category name">
        </div>

<select name="payment_method_id" required>
    <option value="">Payment Method</option>
    <?php
    $methods = $conn->query("SELECT * FROM payment_methods WHERE user_id = $user_id OR user_id IS NULL");
    while ($m = $methods->fetch_assoc()) {
        echo "<option value='{$m['method_id']}'>" . htmlspecialchars($m['name']) . "</option>";
    }
    ?>
</select>


        <input type="text" name="note" placeholder="Reason / Notes (e.g. Dominos 🍕)">
        <input type="text" name="mood" placeholder="Mood (optional)">
        <input type="file" name="receipt">

        <button class="btn" type="submit" name="add_expense">Add Expense</button>
    </form>
</div>



    <div class="card" id="budget">
       <form method="post">
    <input type="number" step="0.01" name="budget_amount" placeholder="₹ Amount" required>
    <select name="adjust_type">
        <option value="add">Add to Budget</option>
        <option value="subtract">Lessen Budget</option>
    </select>
    <button class="btn" type="submit" name="adjust_budget">Update</button>
</form>




    <div class="card">
        <h3>Top 3 Categories</h3>
        <ul class="top3cat">
            <?php while ($row = $topCats->fetch_assoc()): ?>
                <li><?= htmlspecialchars($row['name']) ?>: ₹<?= number_format($row['total'], 2) ?></li>
            <?php endwhile; ?>
        </ul>
    </div>

    <div class="card goal-form" id="goals">
        <h3>Add a Goal</h3>
        <form method="post">
            <input type="text" name="goal_title" placeholder="Goal Title" required>
            <input type="number" step="0.01" name="goal_target" placeholder="Target ₹" required>
            <button class="btn" type="submit">Create Goal</button>
        </form>
    </div>

    <div class="card">
        <h3>Your Goals</h3>
        <?php while ($goal = $goals->fetch_assoc()):
            $progress = $goal['target_amount'] > 0 ? ($goal['saved_amount'] / $goal['target_amount']) * 100 : 0;
        ?>
            <div class="card" style="border-left: 6px solid #ec407a;"> <!-- ₹2000 pink -->
                <h4><?= htmlspecialchars($goal['title']) ?></h4>
                <p>Saved: ₹<?= number_format($goal['saved_amount'], 2) ?> / ₹<?= number_format($goal['target_amount'], 2) ?> (<?= round($progress) ?>%)</p>
                <div class="progress"><div class="bar" style="width:<?= $progress ?>%"></div></div>
                <form method="post">
                    <input type="hidden" name="goal_id" value="<?= $goal['goal_id'] ?>">
                    <input type="number" step="0.01" name="amount" max="<?= $remaining ?>" placeholder="Add ₹" required>
                    <select name="source" required>
                        <option value="budget">From Budget</option>
                        <option value="other">Other</option>
                    </select>
                    <button class="btn" type="submit" name="save_to_goal">Save</button>
                </form>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<?php if ($remaining > 0): ?>
<script> confetti({ particleCount: 100, spread: 60, origin: { y: 0.6 } }); </script>
<?php endif; ?>

<?php if (isset($_POST['reset_budget'])): ?>
<script> confetti({ particleCount: 200, spread: 90, origin: { y: 0.6 } }); </script>
<?php endif; ?>

<!-- 🧾 Budget Adjustment History Section -->
<div class="card">
    <h3>Budget Adjustment History 🧾</h3>
    <ul>
        <?php
        $history = $conn->query("SELECT amount, action, created_at FROM budget_transactions WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 10");
        while ($row = $history->fetch_assoc()):
        ?>
            <li>
                <?= ucfirst($row['action']) ?> ₹<?= number_format($row['amount'], 2) ?>
                <small style="float:right;"><?= date('d M, H:i', strtotime($row['created_at'])) ?></small>
            </li>
        <?php endwhile; ?>
    </ul>
</div>


<script>
document.getElementById('category-dropdown').addEventListener('change', function () {
    const customInput = document.getElementById('custom-category-input');
    if (this.value === 'other') {
        customInput.style.display = 'block';
    } else {
        customInput.style.display = 'none';
    }
});


</script>
</body>
</html>
