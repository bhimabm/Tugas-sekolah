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
  
  // Handle tambah kategori
  $message = '';
  $message_type = '';
  
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_kategori'])) {
    $nama = trim($_POST['nama']);
    
    if (empty($nama)) {
      $message = 'Nama kategori tidak boleh kosong!';
      $message_type = 'danger';
    } else {
      try {
        $stmt = $db->prepare("INSERT INTO kategori (nama) VALUES (?)");
        $stmt->execute([$nama]);
        $message = 'Kategori berhasil ditambahkan!';
        $message_type = 'success';
      } catch (Exception $e) {
        $message = 'Gagal menambahkan kategori: ' . $e->getMessage();
        $message_type = 'danger';
      }
    }
  }
  
  // Handle delete
  if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    $stmt = $db->prepare("DELETE FROM kategori WHERE id = ?");
    $stmt->execute([$delete_id]);
    header('Location: kategori.php');
    exit();
  }
  
  // Ambil semua kategori
  $stmt = $db->query("
    SELECT k.*, COUNT(p.id) as total_pengaduan
    FROM kategori k
    LEFT JOIN pengaduan p ON k.id = p.kategori_id
    GROUP BY k.id
    ORDER BY k.nama ASC
  ");
  $kategori_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Menampilkan kategori yang ada
  $stmt = $db->query('SELECT * FROM kategori');
  $categories = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kelola Kategori - Sistem Pengaduan</title>
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
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .card-header {
      background: white;
      border-bottom: 1px solid #e2e8f0;
      padding: 20px;
      border-radius: 12px 12px 0 0;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .card-header h5 {
      margin: 0;
      color: #1e293b;
      font-weight: 600;
    }
    
    .card-body {
      padding: 20px;
    }
    
    .kategori-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
      gap: 20px;
    }
    
    .kategori-card {
      background: white;
      border-radius: 12px;
      padding: 20px;
      border: 1px solid #e2e8f0;
      transition: all 0.3s ease;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    
    .kategori-card:hover {
      box-shadow: 0 8px 15px rgba(0,0,0,0.1);
      border-color: var(--primary);
    }
    
    .kategori-card h6 {
      color: #1e293b;
      font-weight: 600;
      margin-bottom: 15px;
    }
    
    .kategori-card .count {
      font-size: 24px;
      font-weight: bold;
      color: var(--primary);
      margin-bottom: 15px;
    }
    
    .kategori-card .count-label {
      color: #64748b;
      font-size: 13px;
      margin-bottom: 15px;
    }
    
    .kategori-actions {
      display: flex;
      gap: 8px;
    }
    
    .btn-sm {
      padding: 6px 12px;
      font-size: 12px;
      border-radius: 6px;
    }
    
    .btn-add {
      background-color: var(--primary);
      color: white;
      padding: 10px 20px;
      border-radius: 8px;
      text-decoration: none;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      border: none;
      cursor: pointer;
    }
    
    .btn-add:hover {
      background-color: #4338ca;
      color: white;
    }
    
    .empty-state {
      text-align: center;
      padding: 40px;
      color: #94a3b8;
    }
    
    .empty-state i {
      font-size: 48px;
      margin-bottom: 20px;
      opacity: 0.5;
      display: block;
    }
    
    .modal-content {
      border-radius: 12px;
      border: none;
    }
    
    .modal-header {
      border-bottom: 1px solid #e2e8f0;
      padding: 20px;
    }
    
    .modal-body {
      padding: 25px;
    }
    
    .form-group {
      margin-bottom: 20px;
    }
    
    .form-group label {
      display: block;
      margin-bottom: 8px;
      color: #1e293b;
      font-weight: 500;
      font-size: 14px;
    }
    
    .form-group input,
    .form-group textarea {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid #e2e8f0;
      border-radius: 6px;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      font-size: 14px;
    }
    
    .form-group input:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }
    
    .alert {
      border-radius: 8px;
      border: none;
      margin-bottom: 20px;
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
      
      .kategori-grid {
        grid-template-columns: 1fr;
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
        <a href="users.php" class="nav-link">
          <i class="fas fa-users"></i>
          Kelola Pengguna
        </a>
      </div>
      <div class="nav-item">
        <a href="kategori.php" class="nav-link active">
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
    <h3 style="margin-bottom: 30px; font-weight: 600; color: #1e293b;">
      <i class="fas fa-tags"></i> Kelola Kategori
    </h3>
    
    <?php if (!empty($message)): ?>
      <div class="alert alert-<?php echo $message_type; ?>" role="alert">
        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo $message; ?>
      </div>
    <?php endif; ?>
    
    <!-- Card -->
    <div class="card">
      <div class="card-header">
        <h5>Daftar Kategori</h5>
        <button class="btn-add" data-bs-toggle="modal" data-bs-target="#tambahKategoriModal">
          <i class="fas fa-plus"></i> Tambah Kategori
        </button>
      </div>
      <div class="card-body">
        <?php if (!empty($kategori_list)): ?>
          <div class="kategori-grid">
            <?php foreach ($kategori_list as $k): ?>
              <div class="kategori-card">
                <div>
                  <h6><i class="fas fa-tag"></i> <?php echo htmlspecialchars($k['nama']); ?></h6>
                  <div class="count"><?php echo $k['total_pengaduan']; ?></div>
                  <div class="count-label">pengaduan</div>
                </div>
                <div class="kategori-actions">
                  <a href="edit_kategori.php?id=<?php echo $k['id']; ?>" class="btn btn-sm btn-outline-warning">
                    <i class="fas fa-edit"></i>
                  </a>
                  <a href="kategori.php?delete=<?php echo $k['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Yakin ingin menghapus?')">
                    <i class="fas fa-trash"></i>
                  </a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <i class="fas fa-tags"></i>
            <p>Tidak ada data kategori</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Modal Tambah Kategori -->
  <div class="modal fade" id="tambahKategoriModal" tabindex="-1" aria-labelledby="tambahKategoriModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="tambahKategoriModalLabel">
            <i class="fas fa-plus-circle"></i> Tambah Kategori Baru
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="POST">
          <div class="modal-body">
            <div class="form-group">
              <label for="nama">Nama Kategori <span style="color: var(--danger);">*</span></label>
              <input type="text" id="nama" name="nama" class="form-control" placeholder="Contoh: Jalan Rusak" required>
            </div>
          </div>
          <div class="modal-footer" style="border-top: 1px solid #e2e8f0; padding: 15px 20px;">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" name="add_kategori" class="btn btn-primary" style="background-color: var(--primary); border-color: var(--primary);">
              <i class="fas fa-save"></i> Simpan Kategori
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
          

</body>
</html>
