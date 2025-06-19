<?php
include('../includes/db.php');
session_start();

if (!isset($_SESSION['user_id'])) {
    die("User not logged in.");
}

$user_id = $_SESSION['user_id'];

// Filters
$where = "WHERE t.user_id = $user_id AND t.type = 'expense'";
if (!empty($_GET['category_id'])) {
    $cat = intval($_GET['category_id']);
    $where .= " AND t.category_id = $cat";
}
if (!empty($_GET['payment_method_id'])) {
    $pay = intval($_GET['payment_method_id']);
    $where .= " AND t.payment_method_id = $pay";
}
if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
    $from = $_GET['from_date'];
    $to = $_GET['to_date'];
    $where .= " AND t.txn_date BETWEEN '$from' AND '$to'";
}

// Fetch expenses
$sql = "SELECT t.*, c.name AS category_name, p.name AS payment_name 
        FROM transactions t 
        LEFT JOIN categories c ON t.category_id = c.category_id 
        LEFT JOIN payment_methods p ON t.payment_method_id = p.method_id
        $where ORDER BY t.txn_date DESC";
$expenses = $conn->query($sql);

// Fetch categories and payment methods for filters
$categories = $conn->query("SELECT * FROM categories WHERE user_id = $user_id OR is_fixed = 1");
$methods = $conn->query("SELECT * FROM payment_methods WHERE user_id = $user_id");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Expense History</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 8px rgba(0,0,0,0.1); margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background-color: #7e57c2; color: white; }
        .filter { margin-bottom: 10px; }
        .filter input, .filter select { padding: 8px; margin-right: 10px; }
        img.receipt-preview { height: 40px; border-radius: 4px; }
    
        .topnav {
            background-color: #7e57c2;
            color: white;
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .topnav a {
            color: white;
            margin-left: 15px;
            text-decoration: none;
            font-weight: bold;
        }

        .container {
            padding: 30px;
        }

        .back-btn {
            background-color: #ff7043;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            margin-bottom: 20px;
            display: inline-block;
        }

        .expense-card {
            background: white;
            margin-bottom: 15px;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 6px rgba(0,0,0,0.1);
        }

        .expense-card h4 {
            margin: 0;
            color: #6a1b9a;
        }

        .expense-meta {
            font-size: 14px;
            color: #666;
        }

    </style>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

</head>
<body>

<div class="topnav">
    BudgetWise ₹
    <div>
        <a href="/Budgetwise/templates/dashboard.php">Dashboard</a>
        <a href="#summary">Summary</a>
        <a href="#budget">Budget</a>
        <a href="#goals">Goals</a>
        <a href="/Budgetwise/templates/expenses.php" style="text-decoration: underline;">Expenses</a>
        <button class="btn" onclick="downloadExpensePDF()">📄 Export Expenses to PDF</button>

    </div>
</div>
    <a class="back-btn" href="/Budgetwise/templates/dashboard.php">⬅ Back to Dashboard</a>

<!-- Add this ID to the container holding your expenses -->
<div class="card" id="expense-list">
    <h2>Expense History 💸</h2>
    <form method="get" class="filter">
        <label>From: <input type="date" name="from_date" value="<?= $_GET['from_date'] ?? '' ?>"></label>
        <label>To: <input type="date" name="to_date" value="<?= $_GET['to_date'] ?? '' ?>"></label>

        <select name="category_id">
            <option value="">All Categories</option>
            <?php while($c = $categories->fetch_assoc()): ?>
                <option value="<?= $c['category_id'] ?>" <?= (($_GET['category_id'] ?? '') == $c['category_id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
            <?php endwhile; ?>
        </select>

        <select name="payment_method_id">
            <option value="">All Payment Methods</option>
            <?php while($m = $methods->fetch_assoc()): ?>
                <option value="<?= $m['method_id'] ?>" <?= (($_GET['payment_method_id'] ?? '') == $m['method_id']) ? 'selected' : '' ?>><?= htmlspecialchars($m['name']) ?></option>
            <?php endwhile; ?>
        </select>

        <button type="submit">Filter</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Amount</th>
                <th>Category</th>
                <th>Payment</th>
                <th>Mood</th>
                <th>Note</th>
                <th>Receipt</th>
            </tr>
        </thead>
        <tbody>
            <?php while($exp = $expenses->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($exp['txn_date']) ?></td>
                    <td>₹<?= number_format($exp['amount'], 2) ?></td>
                    <td><?= htmlspecialchars($exp['category_name']) ?></td>
                    <td><?= htmlspecialchars($exp['payment_name']) ?></td>
                    <td><?= htmlspecialchars($exp['mood']) ?></td>
                    <td><?= htmlspecialchars($exp['note']) ?></td>
                    <td>
                        <?php if ($exp['image_url']): ?>
                            <a href="<?= $exp['image_url'] ?>" target="_blank">
                                <img src="<?= $exp['image_url'] ?>" class="receipt-preview">
                            </a>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<script>
    async function downloadExpensePDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();

        const element = document.getElementById('expense-list');
        await html2canvas(element).then(canvas => {
            const imgData = canvas.toDataURL('image/png');
            const pdfWidth = doc.internal.pageSize.getWidth();
            const pdfHeight = (canvas.height * pdfWidth) / canvas.width;

            doc.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);
            doc.save('Expenses_BudgetWise.pdf');
        });
    }
</script>

</body>
</html>