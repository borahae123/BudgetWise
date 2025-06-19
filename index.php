<?php
session_start();
include 'includes/db.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$login_msg = "";
$register_msg = "";

// LOGIN LOGIC
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
  $user = $_POST['username'];
  $pass = $_POST['password'];

  $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
  $stmt->bind_param("s", $user);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();
    if (password_verify($pass, $row['password'])) {
      $_SESSION['user_id'] = $row['user_id'];  // ✅ Added
      $_SESSION['username'] = $user;
      header("Location: templates/dashboard.php");
      exit();
    } else {
      $login_msg = "❌ Incorrect password.";
    }
  } else {
    $login_msg = "❌ User not found.";
  }
}

// REGISTER LOGIC
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
  $user = $_POST['username'];
  $email = $_POST['email'];
  $pass = password_hash($_POST['password'], PASSWORD_BCRYPT);

  $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
  $stmt->bind_param("sss", $user, $email, $pass);

  if ($stmt->execute()) {
    $register_msg = "✅ Account created successfully!";
  } else {
    $register_msg = "❌ Username or email already exists.";
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>BudgetWise 💸</title>
  <style>
    :root {
      --stone: #6D6D6D;
      --lavender: #B9AEDC;
      --blue50: #1A9DD7;
      --white: #fff;
      --brown10: #9E6A44;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', sans-serif;
    }

    body {
      height: 100vh;
      background: linear-gradient(135deg, var(--stone), var(--lavender));
      display: flex;
      justify-content: center;
      align-items: center;
      overflow: hidden;
    }

    .toggle-buttons {
      position: absolute;
      top: 30px;
      left: 50%;
      transform: translateX(-50%);
      display: flex;
      gap: 1rem;
      z-index: 10;
    }

    .toggle-buttons button {
      background-color: var(--white);
      color: var(--stone);
      padding: 0.6rem 1.2rem;
      border: none;
      border-radius: 2rem;
      font-weight: 600;
      cursor: pointer;
      box-shadow: 0 4px 10px rgba(0,0,0,0.2);
      transition: 0.3s;
    }

    .toggle-buttons button.active {
      background-color: var(--brown10);
      color: white;
    }

    .form-box {
      width: 100%;
      max-width: 400px;
      background: rgba(255, 255, 255, 0.2);
      backdrop-filter: blur(10px);
      border-radius: 1.2rem;
      padding: 3rem 2rem;
      box-shadow: 0 0 30px rgba(0,0,0,0.3);
      transition: all 0.4s ease;
      position: relative;
    }

    h2 {
      color: var(--white);
      text-align: center;
      margin-bottom: 1.5rem;
    }

    label {
      display: block;
      color: #f1f1f1;
      margin-bottom: 0.4rem;
      font-size: 0.95rem;
    }

    input {
      width: 100%;
      padding: 0.75rem;
      margin-bottom: 1.2rem;
      border: none;
      border-radius: 0.5rem;
      background-color: rgba(255, 255, 255, 0.9);
      font-size: 1rem;
    }

    button.submit-btn {
      width: 100%;
      padding: 0.8rem;
      background-color: var(--blue50);
      color: white;
      border: none;
      border-radius: 0.5rem;
      font-weight: bold;
      cursor: pointer;
      transition: 0.3s;
    }

    button.submit-btn:hover {
      background-color: #147cb1;
    }

    .form-container {
      position: relative;
    }

    .form {
      display: none;
    }

    .form.active {
      display: block;
    }

    .msg {
      text-align: center;
      margin-bottom: 1rem;
      font-weight: bold;
      color: white;
    }

    @media (max-width: 500px) {
      .form-box {
        padding: 2rem 1rem;
      }
    }
  </style>
</head>
<body>

  <div class="toggle-buttons">
    <button id="loginBtn" class="active">Login</button>
    <button id="registerBtn">Register</button>
  </div>

  <div class="form-box">
    <div class="form-container">
      <!-- Login Form -->
      <div class="form active" id="loginForm">
        <h2>Login to BudgetWise 💼</h2>
        <?php if ($login_msg) echo "<p class='msg'>$login_msg</p>"; ?>
        <form method="POST">
          <label for="login-username">Username</label>
          <input type="text" id="login-username" name="username" required />

          <label for="login-password">Password</label>
          <input type="password" id="login-password" name="password" required />

          <button type="submit" class="submit-btn" name="login">Login</button>
        </form>
    
</div>

      </div>

      <!-- Register Form -->
      <div class="form" id="registerForm">
        <h2>Create Account 📝</h2>
        <?php if ($register_msg) echo "<p class='msg'>$register_msg</p>"; ?>
        <form method="POST">
          <label for="reg-username">Username</label>
          <input type="text" id="reg-username" name="username" required />

          <label for="reg-email">Email</label>
          <input type="email" id="reg-email" name="email" required />

          <label for="reg-password">Password</label>
          <input type="password" id="reg-password" name="password" required />

          <button type="submit" class="submit-btn" name="register">Register</button>
        </form>
      </div>
    </div>
  </div>

  <script>
    const loginBtn = document.getElementById('loginBtn');
    const registerBtn = document.getElementById('registerBtn');
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');

    loginBtn.addEventListener('click', () => {
      loginForm.classList.add('active');
      registerForm.classList.remove('active');
      loginBtn.classList.add('active');
      registerBtn.classList.remove('active');
    });

    registerBtn.addEventListener('click', () => {
      registerForm.classList.add('active');
      loginForm.classList.remove('active');
      registerBtn.classList.add('active');
      loginBtn.classList.remove('active');
    });
  </script>

</body>
</html>
