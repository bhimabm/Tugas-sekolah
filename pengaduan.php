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
    $stmt = $db->prepare("DELETE FROM pengaduan WHERE id = ?");
    $stmt->execute([$delete_id]);
    header('Location: pengaduan.php');
    exit();
  }
  
  // Handle filter
  $filter_status = $_GET['status'] ?? '';
  $search = $_GET['search'] ?? '';
  
  // Build query
  $query = "
    SELECT p.id, p.tanggal, p.lokasi, p.status, p.keterangan, u.name, k.nama as kategori
    FROM pengaduan p
    JOIN users u ON p.user_id = u.id
    JOIN kategori k ON p.kategori_id = k.id
    WHERE 1=1
  ";
  
  $params = [];
  
  if ($filter_status) {
    $query .= " AND p.status = ?";
    $params[] = $filter_status;
  }
  
  if ($search) {
    $query .= " AND (u.name LIKE ? OR p.lokasi LIKE ? OR p.keterangan LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
  }
  
  $query .= " ORDER BY p.tanggal DESC";
  
  $stmt = $db->prepare($query);
  $stmt->execute($params);
  $pengaduan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kelola Pengaduan - Sistem Pengaduan</title>
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
    }
    
    .card-header h5 {
      margin: 0;
      color: #1e293b;
      font-weight: 600;
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
    }
    
    tbody td {
      padding: 15px;
      border-top: 1px solid #e2e8f0;
      color: #475569;
      font-size: 14px;
    }
    
    .status-badge {
      display: inline-block;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 500;
    }
    
    .status-menunggu {
      background-color: #fef3c7;
      color: #b45309;
    }
    
    .status-proses {
      background-color: #dbeafe;
      color: #0369a1;
    }
    
    .status-selesai {
      background-color: #dcfce7;
      color: #166534;
    }
    
    .filter-section {
      background: white;
      padding: 20px;
      border-radius: 12px;
      margin-bottom: 20px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .form-control, .form-select {
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 10px 12px;
    }
    
    .form-control:focus, .form-select:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }
    
    .btn-sm {
      padding: 5px 10px;
      font-size: 12px;
      border-radius: 6px;
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
        <a href="pengaduan.php" class="nav-link active">
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
    <h3 style="margin-bottom: 30px; font-weight: 600; color: #1e293b;">
      <i class="fas fa-list"></i> Kelola Pengaduan
    </h3>
    
    <!-- Filter Section -->
    <div class="filter-section">
      <form method="GET" class="row g-3">
        <div class="col-md-6">
          <input 
            type="text" 
            class="form-control" 
            name="search" 
            placeholder="Cari nama, lokasi, atau keterangan..." 
            value="<?php echo htmlspecialchars($search); ?>"
          >
        </div>
        <div class="col-md-3">
          <select name="status" class="form-select">
            <option value="">Semua Status</option>
            <option value="Menunggu" <?php echo $filter_status === 'Menunggu' ? 'selected' : ''; ?>>Menunggu</option>
            <option value="Proses" <?php echo $filter_status === 'Proses' ? 'selected' : ''; ?>>Proses</option>
            <option value="Selesai" <?php echo $filter_status === 'Selesai' ? 'selected' : ''; ?>>Selesai</option>
          </select>
        </div>
        <div class="col-md-3">
          <button type="submit" class="btn btn-primary w-100">
            <i class="fas fa-search"></i> Cari
          </button>
        </div>
      </form>
    </div>
    
    <!-- Table -->
    <div class="card">
      <div class="card-header">
        <h5>Daftar Pengaduan</h5>
      </div>
      <div class="card-body">
        <?php if (!empty($pengaduan_list)): ?>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>No.</th>
                  <th>Tanggal</th>
                  <th>Pelapor</th>
                  <th>Kategori</th>
                  <th>Lokasi</th>
                  <th>Keterangan</th>
                  <th>Status</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($pengaduan_list as $index => $item): ?>
                  <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td><?php echo date('d M Y H:i', strtotime($item['tanggal'])); ?></td>
                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                    <td><?php echo htmlspecialchars($item['kategori']); ?></td>
                    <td><?php echo htmlspecialchars($item['lokasi']); ?></td>
                    <td>
                      <span title="<?php echo htmlspecialchars($item['keterangan']); ?>">
                        <?php echo substr(htmlspecialchars($item['keterangan']), 0, 30) . '...'; ?>
                      </span>
                    </td>
                    <td>
                      <span class="status-badge status-<?php echo strtolower($item['status']); ?>">
                        <?php echo $item['status']; ?>
                      </span>
                    </td>
                    <td>
                      <div class="btn-group" role="group">
                        <a href="detail_pengaduan.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-primary" title="Lihat Detail" data-bs-toggle="tooltip">
                          <i class="fas fa-eye"></i> Detail
                        </a>
                        <a href="pengaduan.php?delete=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-danger" title="Hapus" data-bs-toggle="tooltip" onclick="return confirm('Yakin ingin menghapus pengaduan ini?')">
                          <i class="fas fa-trash"></i>
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div style="text-align: center; padding: 40px; color: #94a3b8;">
            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 20px; opacity: 0.5; display: block;"></i>
            <p>Tidak ada data pengaduan</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
  <script>
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    })
  </script>
</body>
</html>
