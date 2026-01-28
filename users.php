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
  
  // Handle delete
  if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$delete_id]);
    header('Location: users.php');
    exit();
  }
  
  // Ambil semua users
  $stmt = $db->query("
    SELECT u.*, COUNT(p.id) as total_pengaduan
    FROM users u
    LEFT JOIN pengaduan p ON u.id = p.user_id
    GROUP BY u.id
    ORDER BY u.id DESC
  ");
  $users_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // Flash message (jika ada)
  $flash_message = $_SESSION['flash_message'] ?? '';
  $flash_type = $_SESSION['flash_type'] ?? 'info';
  if (!empty($_SESSION['flash_message'])) {
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
  }
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kelola Pengguna - Sistem Pengaduan</title>
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
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }
    
    .card-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border: none;
      border-radius: 12px 12px 0 0;
      padding: 25px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .card-header h5 {
      margin: 0;
      color: white;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .card-body {
      padding: 20px;
    }
    
    .table-responsive {
      border-radius: 8px;
    }
    
    table {
      margin-bottom: 0;
    }
    
    thead {
      background: #f1f5f9;
      color: #1e293b;
    }
    
    thead th {
      padding: 15px;
      font-weight: 600;
      border: none;
      font-size: 14px;
      background: #f1f5f9;
    }
    
    tbody tr {
      border-bottom: 1px solid #e2e8f0;
      transition: all 0.3s ease;
    }
    
    tbody tr:hover {
      background-color: #f8fafc;
    }
    
    tbody td {
      padding: 15px;
      border: none;
      color: #475569;
      font-size: 14px;
    }
    
    .badge-admin {
      background-color: #dbeafe;
      color: #0369a1;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 500;
    }
    
    .badge-user {
      background-color: #f1f5f9;
      color: #475569;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 500;
    }
    
    .btn-sm {
      padding: 6px 12px;
      font-size: 12px;
      border-radius: 6px;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    
    .btn-outline-warning {
      border: 1px solid var(--warning);
      color: var(--warning);
      background: white;
    }
    
    .btn-outline-warning:hover {
      background: #fef3c7;
      border-color: var(--warning);
      color: var(--warning);
    }
    
    .btn-outline-danger {
      border: 1px solid var(--danger);
      color: var(--danger);
      background: white;
    }
    
    .btn-outline-danger:hover {
      background: #fee2e2;
      border-color: var(--danger);
      color: var(--danger);
    }
    
    .btn-add {
      background-color: white;
      color: #667eea;
      padding: 10px 20px;
      border-radius: 8px;
      text-decoration: none;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      border: 2px solid white;
      cursor: pointer;
      font-weight: 500;
      font-size: 14px;
    }
    
    .btn-add:hover {
      background-color: #f1f5f9;
      color: #667eea;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
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
      
      .card-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
      }
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
    
    .alert-success {
      background-color: #dcfce7;
      color: #166534;
    }
    
    .alert-danger {
      background-color: #fee2e2;
      color: #991b1b;
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
    <h3 style="margin-bottom: 30px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 10px;">
      <i class="fas fa-users"></i> Kelola Pengguna
    </h3>
    
    <?php if (!empty($flash_message)): ?>
      <div class="alert alert-<?php echo htmlspecialchars($flash_type); ?>">
        <i class="fas fa-<?php echo $flash_type === 'success' ? 'check-circle' : 'info-circle'; ?>"></i>
        <?php echo htmlspecialchars($flash_message); ?>
      </div>
    <?php endif; ?>
    
    <!-- Table -->
    <div class="card">
      <div class="card-header">
        <h5>Daftar Pengguna</h5>
        <a href="tambah_user.php" class="btn-add">
          <i class="fas fa-plus"></i> Tambah Pengguna
        </a>
      </div>
      <div class="card-body">
        <?php if (!empty($users_list)): ?>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>No.</th>
                  <th>Nama</th>
                  <th>Email</th>
                  <th>NIS</th>
                  <th>Kelas</th>
                  <th>Role</th>
                  <th>Total Pengaduan</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($users_list as $index => $u): ?>
                  <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td><?php echo htmlspecialchars($u['name']); ?></td>
                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                    <td><?php echo htmlspecialchars($u['nis'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($u['kelas'] ?? '-'); ?></td>
                    <td>
                      <?php if ($u['is_admin']): ?>
                        <span class="badge-admin"><i class="fas fa-shield-alt"></i> Admin</span>
                      <?php else: ?>
                        <span class="badge-user">Pengguna</span>
                      <?php endif; ?>
                    </td>
                    <td><?php echo $u['total_pengaduan']; ?></td>
                    <td>
                      <a href="edit_user.php?id=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline-warning">
                        <i class="fas fa-edit"></i>
                      </a>
                      <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                        <a href="users.php?delete=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Yakin ingin menghapus?')">
                          <i class="fas fa-trash"></i>
                        </a>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div style="text-align: center; padding: 40px; color: #94a3b8;">
            <i class="fas fa-users" style="font-size: 48px; margin-bottom: 20px; opacity: 0.5; display: block;"></i>
            <p>Tidak ada data pengguna</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  
</body>
</html>
