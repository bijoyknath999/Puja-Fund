<?php
session_start();
include 'db.php';
include 'lang.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($user = $res->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            unset($user['password']);
            $_SESSION['user'] = $user;
            header('Location: index.php');
            exit;
        }
    }
    $error = $t['invalid_credentials'];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <title><?php echo $t['page_title_login']; ?></title>
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      position: relative;
      overflow-x: hidden;
    }

    /* Animated background particles */
    body::before {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-image: 
        radial-gradient(circle at 20% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 40% 40%, rgba(255, 255, 255, 0.05) 0%, transparent 50%);
      animation: float 20s ease-in-out infinite;
      z-index: -1;
    }

    @keyframes float {
      0%, 100% { transform: translateY(0px) rotate(0deg); }
      33% { transform: translateY(-20px) rotate(1deg); }
      66% { transform: translateY(10px) rotate(-1deg); }
    }

    .login-container {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem 1rem;
    }

    .login-card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border: none;
      border-radius: 16px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
      padding: 3rem;
      width: 100%;
      max-width: 420px;
      position: relative;
      animation: slideUp 0.8s ease-out;
      transition: all 0.3s ease;
    }

    .login-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
    }

    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .login-header {
      text-align: center;
      margin-bottom: 2.5rem;
    }

    .logo-icon {
      width: 80px;
      height: 80px;
      background: linear-gradient(135deg, #667eea, #764ba2);
      border-radius: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.5rem;
      box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
      animation: pulse 2s ease-in-out infinite;
    }

    @keyframes pulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.05); }
    }

    .logo-icon i {
      font-size: 2.5rem;
      color: white;
      text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    }

    .login-title {
      font-size: 2rem;
      font-weight: 700;
      color: #495057;
      margin-bottom: 0.5rem;
    }

    .login-subtitle {
      color: #6c757d;
      font-size: 1rem;
      font-weight: 400;
    }

    .form-group {
      margin-bottom: 1.5rem;
      position: relative;
    }

    .form-label {
      display: block;
      color: #495057;
      font-weight: 600;
      margin-bottom: 0.5rem;
      font-size: 0.9rem;
    }

    .form-control {
      width: 100%;
      padding: 0.75rem 1rem;
      background: #fff;
      border: 1px solid #dee2e6;
      border-radius: 8px;
      color: #495057;
      font-size: 1rem;
      transition: all 0.3s ease;
    }

    .form-control:focus {
      outline: none;
      border-color: #667eea;
      background: #fff;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .form-control::placeholder {
      color: #6c757d;
    }

    .password-toggle {
      position: absolute;
      right: 1rem;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: #6c757d;
      cursor: pointer;
      padding: 0.5rem;
      border-radius: 8px;
      transition: all 0.3s ease;
    }

    .password-toggle:hover {
      color: #495057;
      background: #f8f9fa;
    }

    .btn-login {
      width: 100%;
      padding: 0.75rem 2rem;
      background: linear-gradient(135deg, #667eea, #764ba2);
      border: none;
      border-radius: 8px;
      color: white;
      font-weight: 600;
      font-size: 1rem;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
      position: relative;
      overflow: hidden;
    }

    .btn-login::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: left 0.5s;
    }

    .btn-login:hover::before {
      left: 100%;
    }

    .btn-login:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 30px rgba(102, 126, 234, 0.4);
    }

    .btn-login:active {
      transform: translateY(0);
    }

    .alert {
      background: #f8d7da;
      border: 1px solid #f5c6cb;
      border-radius: 8px;
      padding: 1rem;
      margin-bottom: 1.5rem;
      color: #721c24;
      animation: shake 0.5s ease-in-out;
    }

    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      25% { transform: translateX(-5px); }
      75% { transform: translateX(5px); }
    }


    .footer-info {
      text-align: center;
      margin-top: 2rem;
      color: rgba(255, 255, 255, 0.9);
      font-size: 0.9rem;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
    }

    .loading-spinner {
      display: inline-block;
      width: 16px;
      height: 16px;
      border: 2px solid rgba(255, 255, 255, 0.3);
      border-radius: 50%;
      border-top-color: white;
      animation: spin 1s ease-in-out infinite;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    .language-switcher {
      position: absolute;
      top: 20px;
      right: 20px;
      z-index: 1001;
      display: flex;
      align-items: center;
    }

    .lang-btn {
      background: rgba(255, 255, 255, 0.1);
      border: 1px solid rgba(255, 255, 255, 0.2);
      color: white;
      padding: 0.4rem 0.8rem;
      border-radius: 15px;
      text-decoration: none;
      font-size: 0.8rem;
      font-weight: 500;
      transition: all 0.3s ease;
      margin: 0 0.2rem;
      backdrop-filter: blur(10px);
    }

    .lang-btn:hover {
      background: rgba(255, 255, 255, 0.2);
      color: white;
      transform: translateY(-1px);
    }

    .lang-btn.active {
      background: rgba(255, 255, 255, 0.3);
      border-color: rgba(255, 255, 255, 0.4);
    }

    .bangla-text {
      font-family: 'SolaimanLipi', 'Kalpurush', 'Nikosh', Arial, sans-serif;
    }

    @media (max-width: 768px) {
      .language-switcher {
        top: 10px;
        right: 10px;
      }
      
      .lang-btn {
        padding: 0.3rem 0.6rem;
        font-size: 0.75rem;
        margin: 0 0.1rem;
      }
    }
  </style>
</head>
<body>
  <!-- Language Switcher -->
  <div class="language-switcher">
    <a href="?lang=en" class="lang-btn <?php echo $lang === 'en' ? 'active' : ''; ?>">English</a>
    <a href="?lang=bn" class="lang-btn <?php echo $lang === 'bn' ? 'active' : ''; ?>">বাংলা</a>
  </div>

  <div class="login-container">
    <div class="login-card">
      <!-- Header -->
      <div class="login-header">
        <div class="logo-icon">
          <i class="bi bi-flower1"></i>
        </div>
        <h1 class="login-title <?php echo getLangClass($lang); ?>"><?php echo $t['app_name']; ?></h1>
        <p class="login-subtitle <?php echo getLangClass($lang); ?>"><?php echo $t['app_subtitle']; ?></p>
      </div>

      <!-- Error Message -->
      <?php if (!empty($error)): ?>
      <div class="alert">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <span class="<?php echo $lang === 'bn' ? 'bangla-text' : ''; ?>"><?php echo htmlspecialchars($error); ?></span>
      </div>
      <?php endif; ?>

      <!-- Login Form -->
      <form method="POST" id="loginForm">
        <div class="form-group">
          <label for="email" class="form-label <?php echo getLangClass($lang); ?>">
            <i class="bi bi-envelope me-2"></i>
            <?php echo $t['email_label']; ?>
          </label>
          <input name="email" id="email" type="email" class="form-control" placeholder="<?php echo $t['email_placeholder']; ?>" required>
        </div>
        
        <div class="form-group">
          <label for="password" class="form-label <?php echo getLangClass($lang); ?>">
            <i class="bi bi-lock me-2"></i>
            <?php echo $t['password_label']; ?>
          </label>
          <div style="position: relative;">
            <input type="password" class="form-control" id="password" name="password" placeholder="<?php echo $t['password_placeholder']; ?>" required autocomplete="current-password">
            <button type="button" class="password-toggle" id="togglePassword">
              <i class="bi bi-eye" id="toggleIcon"></i>
            </button>
          </div>
        </div>
        
        <button type="submit" class="btn-login <?php echo getLangClass($lang); ?>" id="loginBtn">
          <i class="bi bi-box-arrow-in-right me-2"></i>
          <?php echo $t['signin_btn']; ?>
        </button>
      </form>

    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Password toggle functionality
      const togglePassword = document.getElementById('togglePassword');
      const passwordInput = document.getElementById('password');
      const toggleIcon = document.getElementById('toggleIcon');
      
      togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        
        if (type === 'text') {
          toggleIcon.classList.remove('bi-eye');
          toggleIcon.classList.add('bi-eye-slash');
        } else {
          toggleIcon.classList.remove('bi-eye-slash');
          toggleIcon.classList.add('bi-eye');
        }
      });
      
      // Form submission with loading state
      const loginForm = document.getElementById('loginForm');
      const loginBtn = document.getElementById('loginBtn');
      
      loginForm.addEventListener('submit', function(e) {
        // Show loading state
        const originalText = loginBtn.innerHTML;
        const loadingText = '<?php echo $t['signing_in']; ?>';
        loginBtn.innerHTML = '<span class="loading-spinner me-2"></span>' + loadingText;
        loginBtn.disabled = true;
        
        // Re-enable button after 3 seconds in case of error
        setTimeout(() => {
          loginBtn.innerHTML = originalText;
          loginBtn.disabled = false;
        }, 3000);
      });
      
      // Auto-focus email field
      document.getElementById('email').focus();
    });
  </script>
</body>
</html>
