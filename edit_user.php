<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit();
}

// Cek apakah user adalah admin
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user['is_admin']) {
  header('Location: index.php');
  exit();
}

$error = '';
$success = '';
$edit_user = [];

// Get user ID from URL
$edit_id = $_GET['id'] ?? '';
if (empty($edit_id)) {
  header('Location: users.php');
  exit();
}

// Fetch user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$edit_id]);
$edit_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$edit_user) {
  $_SESSION['flash_message'] = 'Pengguna tidak ditemukan!';
  $_SESSION['flash_type'] = 'danger';
  header('Location: users.php');
  exit();
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = $_POST['name'] ?? '';
  $email = $_POST['email'] ?? '';
  $nis = $_POST['nis'] ?? '';
  $kelas = $_POST['kelas'] ?? '';
  $is_admin = isset($_POST['is_admin']) ? 1 : 0;
  $change_password = isset($_POST['change_password']) ? true : false;
  $password = $_POST['password'] ?? '';

  // Validasi input
  if (empty($name) || empty($email)) {
    $error = 'Nama dan email harus diisi!';
  } else {
    // Cek email sudah digunakan oleh user lain
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $edit_id]);
    if ($stmt->rowCount() > 0) {
      $error = 'Email sudah terdaftar oleh pengguna lain!';
    } else {
      try {
        if ($change_password) {
          if (strlen($password) < 6) {
            $error = 'Password minimal 6 karakter!';
          } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("
              UPDATE users 
              SET name = ?, email = ?, password = ?, nis = ?, kelas = ?, is_admin = ?
              WHERE id = ?
            ");
            $stmt->execute([$name, $email, $hashed_password, $nis, $kelas, $is_admin, $edit_id]);
          }
        } else {
          $stmt = $db->prepare("
            UPDATE users 
            SET name = ?, email = ?, nis = ?, kelas = ?, is_admin = ?
            WHERE id = ?
          ");
          $stmt->execute([$name, $email, $nis, $kelas, $is_admin, $edit_id]);
        }

        if (empty($error)) {
          $_SESSION['flash_message'] = 'Data pengguna berhasil diperbarui!';
          $_SESSION['flash_type'] = 'success';
          header('Location: users.php');
          exit();
        }
      } catch (Exception $e) {
        $error = 'Gagal memperbarui pengguna: ' . $e->getMessage();
      }
    }
  }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Pengguna - Sistem Pengaduan</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  
  <style>
    :root {
      --primary: #4f46e5;
      --success: #10b981;
      --warning: #f59e0b;
      --danger: #ef4444;
      --info: #0ea5e9;
    }
    
    body {
      background-color: #f8fafc;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .sidebar {
      background: linear-gradient(135deg, var(--primary) 0%, #4338ca 100%);
      min-height: 100vh;
      position: fixed;
      left: 0;
      top: 0;
      width: 260px;
      padding: 20px;
      color: white;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    
    .sidebar .logo {
      font-size: 24px;
      font-weight: bold;
      margin-bottom: 30px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .sidebar .nav-item {
      margin-bottom: 10px;
    }
    
    .sidebar .nav-link {
      color: rgba(255,255,255,0.8);
      padding: 12px 15px;
      border-radius: 8px;
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 10px;
      transition: all 0.3s ease;
    }
    
    .sidebar .nav-link:hover,
    .sidebar .nav-link.active {
      background-color: rgba(255,255,255,0.2);
      color: white;
    }
    
    .main-content {
      margin-left: 260px;
      padding: 30px;
    }
    
    .card {
      border: none;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .card-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border: none;
      padding: 25px;
      border-radius: 12px 12px 0 0;
    }
    
    .card-header h5 {
      margin: 0;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .card-body {
      padding: 30px;
    }
    
    .form-label {
      color: #1e293b;
      font-weight: 500;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 4px;
    }
    
    .form-control,
    .form-select {
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      padding: 12px;
      font-size: 14px;
      transition: all 0.3s ease;
    }
    
    .form-control:focus,
    .form-select:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }
    
    .form-group {
      margin-bottom: 20px;
    }
    
    .form-check {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 12px;
      background-color: #f1f5f9;
      border-radius: 8px;
    }

    /* Toggle switch styles for role */
    .toggle {
      position: relative;
      width: 56px;
      height: 30px;
    }

    .toggle-input {
      position: absolute;
      opacity: 0;
      width: 0;
      height: 0;
    }

    .toggle-label {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: #e2e8f0;
      border-radius: 30px;
      cursor: pointer;
      transition: background 0.25s ease;
    }

    .toggle-label::after {
      content: '';
      position: absolute;
      width: 24px;
      height: 24px;
      left: 3px;
      top: 3px;
      background: #ffffff;
      border-radius: 50%;
      box-shadow: 0 1px 3px rgba(0,0,0,0.2);
      transition: transform 0.25s ease;
    }

    .toggle-input:checked + .toggle-label {
      background: var(--primary);
    }

    .toggle-input:checked + .toggle-label::after {
      transform: translateX(26px);
    }
    
    .form-check-input {
      width: 20px;
      height: 20px;
      margin: 0;
      cursor: pointer;
      accent-color: var(--primary);
    }
    
    .form-check-label {
      margin: 0;
      color: #1e293b;
      font-weight: 500;
      cursor: pointer;
    }
    
    .button-group {
      display: flex;
      gap: 10px;
      justify-content: flex-end;
      margin-top: 30px;
    }
    
    .btn {
      padding: 12px 24px;
      border-radius: 8px;
      font-weight: 500;
      font-size: 14px;
      border: none;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    
    .btn-primary {
      background-color: var(--primary);
      color: white;
    }
    
    .btn-primary:hover {
      background-color: #4338ca;
      color: white;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
    }
    
    .btn-secondary {
      background-color: #e2e8f0;
      color: #1e293b;
    }
    
    .btn-secondary:hover {
      background-color: #cbd5e1;
      color: #1e293b;
    }
    
    .alert {
      border: none;
      border-radius: 8px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 15px 20px;
    }
    
    .alert-danger {
      background-color: #fee2e2;
      color: #991b1b;
    }
    
    .alert-success {
      background-color: #dcfce7;
      color: #166534;
    }
    
    .row-2col {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }
    
    .section-divider {
      margin: 30px 0 20px 0;
      padding-bottom: 20px;
      border-bottom: 2px solid #e2e8f0;
    }
    
    .section-divider h6 {
      color: #1e293b;
      font-weight: 600;
      margin-bottom: 0;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .info-box {
      background-color: #eff6ff;
      border-left: 4px solid var(--info);
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 14px;
      color: #0369a1;
    }
    
    @media (max-width: 768px) {
      .sidebar {
        width: 100%;
        min-height: auto;
        position: relative;
      }
      
      .main-content {
        margin-left: 0;
        padding: 15px;
      }
      
      .row-2col {
        grid-template-columns: 1fr;
      }
      
      .button-group {
        flex-direction: column;
      }
    }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <div class="logo">
      <i class="fas fa-exclamation-circle"></i>
      Pengaduan
    </div>

    
    
    <nav class="nav flex-column">
      <div class="nav-item">
        <a href="index.php" class="nav-link">
          <i class="fas fa-home"></i>
          Dashboard
        </a>
      </div>
      <div class="nav-item">
        <a href="pengaduan.php" class="nav-link">
          <i class="fas fa-list"></i>
          Kelola Pengaduan
        </a>
      </div>
      <div class="nav-item">
        <a href="users.php" class="nav-link active">
          <i class="fas fa-users"></i>
          Kelola Pengguna
        </a>
      </div>
      <div class="nav-item">
        <a href="kategori.php" class="nav-link">
          <i class="fas fa-tags"></i>
          Kategori
        </a>
      </div>
      <div class="nav-item" style="margin-top: auto;">
        <a href="logout.php" class="nav-link">
          <i class="fas fa-sign-out-alt"></i>
          Logout
        </a>
      </div>
    </nav>
  </div>
  
  <!-- Main Content -->
  <div class="main-content">
    <a href="users.php" class="btn btn-secondary mb-4" style="border-radius: 6px; font-size: 14px;">
      <i class="fas fa-arrow-left"></i> Kembali
    </a>
    
    <div class="card">
      <div class="card-header">
        <h5>
          <i class="fas fa-user-edit"></i> Edit Pengguna
        </h5>
      </div>
      <div class="card-body">
        <?php if (!empty($error)): ?>
          <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
          </div>
        <?php endif; ?>
        
        <div class="info-box">
          <i class="fas fa-info-circle"></i> Mengedit pengguna: <strong><?php echo htmlspecialchars($edit_user['name']); ?></strong>
        </div>
        
        <form method="POST" action="">
          <!-- Row 1: Nama Lengkap -->
          <div class="form-group">
            <label for="name" class="form-label">
              <i class="fas fa-user" style="color: var(--primary);"></i> Nama Lengkap
              <span style="color: var(--danger);">*</span>
            </label>
            <input type="text" id="name" name="name" class="form-control" placeholder="Masukkan nama lengkap" value="<?php echo htmlspecialchars($edit_user['name']); ?>" required>
          </div>
          
          <!-- Row 2: Email dan Password -->
          <div class="row-2col">
            <div class="form-group">
              <label for="email" class="form-label">
                <i class="fas fa-envelope" style="color: var(--primary);"></i> Email
                <span style="color: var(--danger);">*</span>
              </label>
              <input type="email" id="email" name="email" class="form-control" placeholder="Masukkan email" value="<?php echo htmlspecialchars($edit_user['email']); ?>" required>
            </div>
            
            <div class="form-group">
              <label class="form-label">
                <i class="fas fa-lock" style="color: var(--primary);"></i> Password
              </label>
              <div class="form-check">
                <input type="checkbox" id="change_password" name="change_password" class="form-check-input" onchange="document.getElementById('password').disabled = !this.checked;">
                <label for="change_password" class="form-check-label">
                  Ubah Password
                </label>
              </div>
            </div>
          </div>
          
          <!-- Password Field (Hidden by default) -->
          <div class="form-group" id="password-group" style="display: none;">
            <label for="password" class="form-label">
              Password Baru
              <span style="color: var(--danger);">*</span>
            </label>
            <input type="password" id="password" name="password" class="form-control" placeholder="Minimal 6 karakter" disabled>
          </div>
          
          <script>
            document.getElementById('change_password').addEventListener('change', function() {
              const passwordGroup = document.getElementById('password-group');
              const passwordInput = document.getElementById('password');
              if (this.checked) {
                passwordGroup.style.display = 'block';
                passwordInput.disabled = false;
                passwordInput.required = true;
              } else {
                passwordGroup.style.display = 'none';
                passwordInput.disabled = true;
                passwordInput.required = false;
              }
            });
          </script>
          
          <!-- Row 3: NIS dan Kelas -->
          <div class="row-2col">
            <div class="form-group">
              <label for="nis" class="form-label">
                <i class="fas fa-id-card" style="color: var(--primary);"></i> NIS
              </label>
              <input type="text" id="nis" name="nis" class="form-control" placeholder="Nomor Induk Siswa" value="<?php echo htmlspecialchars($edit_user['nis'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
              <label for="kelas" class="form-label">
                <i class="fas fa-graduation-cap" style="color: var(--primary);"></i> Kelas
              </label>
              <input type="text" id="kelas" name="kelas" class="form-control" placeholder="Contoh: X RPL" value="<?php echo htmlspecialchars($edit_user['kelas'] ?? ''); ?>">
            </div>
          </div>
          
          <!-- Admin Role Section -->
          <div class="section-divider">
            <h6>
              <i class="fas fa-shield-alt"></i> Pengaturan Role
            </h6>
          </div>
          
          <div class="form-group" style="margin-bottom: 30px;">
            <div class="form-check" style="background: transparent; padding: 0;">
              <div style="display:flex; align-items:center; gap:12px; justify-content:space-between; width:100%;">
                <div style="display:flex; align-items:center; gap:8px;">
                  <label class="form-label" style="margin:0;">Jadikan sebagai Admin</label>
                  <small style="color: #64748b;">(akses penuh)</small>
                </div>
                <div class="toggle">
                  <input type="checkbox" id="is_admin" name="is_admin" class="toggle-input" <?php echo $edit_user['is_admin'] ? 'checked' : ''; ?>>
                  <label for="is_admin" class="toggle-label"></label>
                </div>
              </div>
            </div>
            <small style="color: #64748b; display: block; margin-top: 8px;">
              Admin memiliki akses penuh terhadap semua fitur manajemen sistem
            </small>
          </div>
          
          <!-- Button Group -->
          <div class="button-group">
            <a href="users.php" class="btn btn-secondary">
              <i class="fas fa-times"></i> Batal
            </a>
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save"></i> Simpan Perubahan
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
  
</body>
</html>
