<?php
include 'auth.php';
include 'db.php';
include 'lang.php';
include 'year_helper.php';

$lang = getCurrentLanguage();
$t = getTranslations($lang);

// Check if user is manager
if ($_SESSION['user']['role'] != 'manager') {
    header('Location: index.php');
    exit();
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $newYear = intval($_POST['active_year'] ?? 0);
    if ($newYear >= 2000 && $newYear <= 2100) {
        setActiveYear($conn, $newYear);
        $message = $t['settings_updated'];
        $messageType = 'success';
    } else {
        $message = 'Please enter a valid year.';
        $messageType = 'error';
    }
}

$activeYear = getActiveYear($conn);
$availableYears = getAvailableYears($conn, $activeYear);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $t['page_title_settings']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/app.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .language-switcher {
            display: flex;
            align-items: center;
            margin-right: 1rem;
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
        .navbar-brand {
            font-weight: 600;
            color: white !important;
        }
        .nav-link {
            color: rgba(255, 255, 255, 0.8) !important;
            transition: color 0.3s ease;
        }
        .nav-link:hover, .nav-link.active {
            color: white !important;
        }
        .dropdown-toggle::after {
            border-top-color: rgba(255, 255, 255, 0.8);
        }
        .card {
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-radius: 12px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
        }
        .active-year-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-size: 1.75rem;
            font-weight: 700;
        }
    </style>
</head>
<body>
<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="index.php">
      <i class="bi bi-gem me-2 fs-4"></i>
      <span class="<?php echo getLangClass($lang); ?>"><?php echo $t['app_name']; ?></span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link <?php echo getLangClass($lang); ?>" href="index.php"><i class="bi bi-house me-1"></i><?php echo $t['dashboard']; ?></a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php echo getLangClass($lang); ?>" href="transactions.php"><i class="bi bi-list-ul me-1"></i><?php echo $t['transactions']; ?></a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php echo getLangClass($lang); ?>" href="users.php"><i class="bi bi-people me-1"></i><?php echo $t['users']; ?></a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php echo getLangClass($lang); ?>" href="approve_transfers.php">
            <i class="bi bi-check-circle me-1"></i><?php echo $t['approve_transfers']; ?>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php echo getLangClass($lang); ?>" href="report.php"><i class="bi bi-file-earmark-text me-1"></i><?php echo $t['reports']; ?></a>
        </li>
        <li class="nav-item">
          <a class="nav-link active fw-semibold <?php echo getLangClass($lang); ?>" href="settings.php"><i class="bi bi-gear me-1"></i><?php echo $t['settings']; ?></a>
        </li>
      </ul>
      <ul class="navbar-nav">
        <!-- Language Switcher -->
        <li class="nav-item">
          <?php echo getLanguageSwitcher($lang); ?>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle me-1"></i>
            <?php echo htmlspecialchars($_SESSION['user']['name']); ?>
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i><?php echo $t['profile']; ?></a></li>
            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i><?php echo $t['logout']; ?></a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- Main Content -->
<div class="container py-4">
  <div class="row mb-4">
    <div class="col-12">
      <div class="card">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="me-3">
              <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary" style="width: 50px; height: 50px;">
                <i class="bi bi-gear-fill text-white" style="font-size: 1.2rem;"></i>
              </div>
            </div>
            <div>
              <h4 class="mb-1 fw-semibold <?php echo getLangClass($lang); ?>"><?php echo $t['settings']; ?></h4>
              <p class="text-muted mb-0 <?php echo getLangClass($lang); ?>"><?php echo $t['active_year_desc']; ?></p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php if ($message): ?>
  <div class="row mb-4">
    <div class="col-12">
      <div class="alert alert-<?php echo $messageType == 'success' ? 'success' : 'danger'; ?>">
        <?php echo htmlspecialchars($message); ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="row mb-4">
    <div class="col-12 text-center">
      <div class="active-year-badge">
        <i class="bi bi-calendar-event"></i>
        <?php echo $activeYear; ?>
      </div>
      <p class="text-muted mt-2 <?php echo getLangClass($lang); ?>"><?php echo $t['active_year']; ?></p>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="fw-semibold mb-3 <?php echo getLangClass($lang); ?>"><?php echo $t['change_active_year']; ?></h5>
          <p class="text-muted small <?php echo getLangClass($lang); ?>">Switch back to a year that already has data to make it the active year again.</p>
          <form method="POST">
            <div class="mb-3">
              <select class="form-select" name="active_year">
                <?php foreach ($availableYears as $year): ?>
                  <option value="<?php echo $year; ?>" <?php echo $year == $activeYear ? 'selected' : ''; ?>><?php echo $year; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="submit" class="btn btn-primary <?php echo getLangClass($lang); ?>">
              <i class="bi bi-check2 me-2"></i><?php echo $t['save_settings']; ?>
            </button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="fw-semibold mb-3 <?php echo getLangClass($lang); ?>"><?php echo $t['start_new_year']; ?></h5>
          <p class="text-muted small <?php echo getLangClass($lang); ?>"><?php echo $t['start_new_year_desc']; ?></p>
          <form method="POST">
            <div class="mb-3">
              <input type="number" class="form-control" name="active_year" min="2000" max="2100" placeholder="<?php echo $t['new_year_placeholder']; ?>" required>
            </div>
            <button type="submit" class="btn btn-outline-primary <?php echo getLangClass($lang); ?>">
              <i class="bi bi-plus-circle me-2"></i><?php echo $t['start_new_year']; ?>
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
