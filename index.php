<?php
  require_once 'config.php';
  
  // Redirect to login jika belum login
  if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
  }
  
  // Ambil data user
  $user_id = $_SESSION['user_id'];
  $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
  $stmt->execute([$user_id]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  // Batasi akses dashboard hanya untuk admin
  if (!$user['is_admin']) {
    header('Location: riwayat_pengaduan.php');
    exit();
  }

  // Flash message dari aksi lain (mis. buat pengaduan)
  $flash_message = $_SESSION['flash_message'] ?? '';
  $flash_type = $_SESSION['flash_type'] ?? 'info';
  if (!empty($_SESSION['flash_message'])) {
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
  }
  
  // Hitung statistik
  $stats = [];
  
  // Total pengaduan
  $stmt = $db->query("SELECT COUNT(*) as total FROM pengaduan");
  $stats['total_pengaduan'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
  
  // Pengaduan menunggu
  $stmt = $db->query("SELECT COUNT(*) as total FROM pengaduan WHERE status = 'Menunggu'");
  $stats['menunggu'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
  
  // Pengaduan proses
  $stmt = $db->query("SELECT COUNT(*) as total FROM pengaduan WHERE status = 'Proses'");
  $stats['proses'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
  
  // Pengaduan selesai
  $stmt = $db->query("SELECT COUNT(*) as total FROM pengaduan WHERE status = 'Selesai'");
  $stats['selesai'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
  
  // Total users
  $stmt = $db->query("SELECT COUNT(*) as total FROM users");
  $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
  
  // Ambil data pengaduan terbaru (limit 5)
  $stmt = $db->query("
    SELECT p.id, p.tanggal, p.lokasi, p.status, p.keterangan, u.name, k.nama as kategori
    FROM pengaduan p
    JOIN users u ON p.user_id = u.id
    JOIN kategori k ON p.kategori_id = k.id
    ORDER BY p.tanggal DESC
    LIMIT 5
  ");
  $pengaduan_terbaru = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // Ambil statistik kategori
  $stmt = $db->query("
    SELECT k.nama, COUNT(p.id) as jumlah
    FROM kategori k
    LEFT JOIN pengaduan p ON k.id = p.kategori_id
    GROUP BY k.id, k.nama
  ");
  $kategori_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // Tambah Kategori
  if (isset($_POST['add_category'])) {
    $category_name = trim($_POST['category_name']);
    
    // Cek apakah kategori sudah ada
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM kategori WHERE nama = ?");
    $stmt->execute([$category_name]);
    $kategori_exist = $stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;
    
    if (!$kategori_exist) {
      // Insert kategori baru
      $stmt = $db->prepare("INSERT INTO kategori (nama) VALUES (?)");
      $stmt->execute([$category_name]);
      header('Location: kategori.php');
      exit();
    } else {
      $error_message = "Kategori sudah ada.";
    }
  }
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard - Sistem Pengaduan</title>
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
    
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
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
    
    .sidebar .nav-link i {
      width: 20px;
      text-align: center;
    }
    
    .main-content {
      margin-left: 260px;
      padding: 30px;
    }
    
    .top-navbar {
      background: white;
      padding: 15px 30px;
      border-radius: 10px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
    }
    
    .top-navbar .user-info {
      display: flex;
      align-items: center;
      gap: 15px;
    }
    
    .top-navbar .user-info .avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), var(--info));
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: bold;
    }
    
    .stat-card {
      background: white;
      border-radius: 12px;
      padding: 25px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      border-left: 4px solid var(--primary);
      transition: all 0.3s ease;
      cursor: pointer;
    }
    
    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 15px rgba(0,0,0,0.2);
    }
    
    .stat-card.success {
      border-left-color: var(--success);
    }
    
    .stat-card.warning {
      border-left-color: var(--warning);
    }
    
    .stat-card.danger {
      border-left-color: var(--danger);
    }
    
    .stat-card.info {
      border-left-color: var(--info);
    }
    
    .stat-card .icon {
      font-size: 28px;
      margin-bottom: 15px;
    }
    
    .stat-card .value {
      font-size: 32px;
      font-weight: bold;
      color: #1e293b;
      margin-bottom: 5px;
    }
    
    .stat-card .label {
      color: #64748b;
      font-size: 14px;
    }
    
    .card {
      border: none;
      border-radius: 12px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      transition: all 0.3s ease;
    }
    
    .card:hover {
      box-shadow: 0 8px 15px rgba(0,0,0,0.1);
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
    
    .chart-container {
      position: relative;
      height: 300px;
    }
    
    .btn-custom {
      padding: 10px 20px;
      border-radius: 8px;
      font-weight: 500;
      text-decoration: none;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
    
    .btn-primary-custom {
      background-color: var(--primary);
      color: white;
    }
    
    .btn-primary-custom:hover {
      background-color: #4338ca;
      color: white;
    }
    
    .section-title {
      font-size: 20px;
      font-weight: 600;
      color: #1e293b;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
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
    }
    
    @media (max-width: 768px) {
      .sidebar {
        width: 100%;
        min-height: auto;
        position: relative;
        padding: 15px;
      }
      
      .main-content {
        margin-left: 0;
        padding: 15px;
      }
      
      .top-navbar {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
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
        <a href="index.php" class="nav-link active">
          <i class="fas fa-home"></i>
          Dashboard
        </a>
      </div>
      <?php if ($user['is_admin']): ?>
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
          <a href="kategori.php" class="nav-link">
            <i class="fas fa-tags"></i>
            Kategori
          </a>
        </div>
      <?php else: ?>
        <div class="nav-item">
          <a href="buat_pengaduan.php" class="nav-link">
            <i class="fas fa-plus"></i>
            Buat Pengaduan
          </a>
        </div>
        <div class="nav-item">
          <a href="riwayat_pengaduan.php" class="nav-link">
            <i class="fas fa-history"></i>
            Riwayat Pengaduan
          </a>
        </div>
      <?php endif; ?>
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
    <!-- Top Navbar -->
    <div class="top-navbar">
      <div>
        <h5>Selamat datang, <strong><?php echo htmlspecialchars($user['name']); ?></strong></h5>
        <small style="color: #64748b;">
          <i class="far fa-calendar"></i> 
          <?php echo date('l, d F Y'); ?>
        </small>
      </div>
      <div class="user-info">
        <div class="avatar">
          <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
        </div>
        <div>
          <strong><?php echo htmlspecialchars($user['name']); ?></strong><br>
          <small style="color: #64748b;">
            <?php echo $user['is_admin'] ? 'Administrator' : 'Pengguna'; ?>
          </small>
        </div>
      </div>
    </div>
    
    <?php if (!empty($flash_message)): ?>
      <div class="alert alert-<?php echo htmlspecialchars($flash_type); ?> mt-3" role="alert">
        <?php echo htmlspecialchars($flash_message); ?>
      </div>
    <?php endif; ?>

    <!-- Statistics Section -->
    <div class="section-title">
      <i class="fas fa-chart-bar"></i> Statistik Pengaduan
    </div>
    
    <div class="row mb-4">
      <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-card">
          <div class="icon" style="color: var(--primary);">
            <i class="fas fa-inbox"></i>
          </div>
          <div class="value"><?php echo $stats['total_pengaduan']; ?></div>
          <div class="label">Total Pengaduan</div>
        </div>
      </div>
      
      <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-card warning">
          <div class="icon" style="color: var(--warning);">
            <i class="fas fa-clock"></i>
          </div>
          <div class="value"><?php echo $stats['menunggu']; ?></div>
          <div class="label">Menunggu Konfirmasi</div>
        </div>
      </div>
      
      <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-card info">
          <div class="icon" style="color: var(--info);">
            <i class="fas fa-spinner"></i>
          </div>
          <div class="value"><?php echo $stats['proses']; ?></div>
          <div class="label">Dalam Proses</div>
        </div>
      </div>
      
      <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-card success">
          <div class="icon" style="color: var(--success);">
            <i class="fas fa-check-circle"></i>
          </div>
          <div class="value"><?php echo $stats['selesai']; ?></div>
          <div class="label">Selesai</div>
        </div>
      </div>
    </div>
    
    <!-- Charts Section -->
    <div class="row mb-4">
      <div class="col-lg-6">
        <div class="card">
          <div class="card-header">
            <h5>Status Pengaduan</h5>
          </div>
          <div class="card-body">
            <div class="chart-container">
              <canvas id="statusChart"></canvas>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-lg-6">
        <div class="card">
          <div class="card-header">
            <h5>Pengaduan per Kategori</h5>
          </div>
          <div class="card-body">
            <div class="chart-container">
              <canvas id="kategoriChart"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Recent Complaints Section -->
    <div class="section-title">
      <i class="fas fa-list"></i> Pengaduan Terbaru
    </div>
    
    <div class="card mb-4">
      <div class="card-body">
        <?php if (!empty($pengaduan_terbaru)): ?>
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
                <?php foreach ($pengaduan_terbaru as $index => $item): ?>
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
                      <a href="detail_pengaduan.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-eye"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>Belum ada data pengaduan</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
    
    <!-- Kategori Stats -->
    <div class="section-title">
      <i class="fas fa-tags"></i> Statistik per Kategori
    </div>
    
    <div class="row">
      <?php foreach ($kategori_stats as $kat): ?>
        <div class="col-lg-3 col-md-6 mb-3">
          <div class="card">
            <div class="card-body text-center">
              <h6 class="card-title"><?php echo htmlspecialchars($kat['nama']); ?></h6>
              <div style="font-size: 28px; font-weight: bold; color: var(--primary);">
                <?php echo $kat['jumlah']; ?>
              </div>
              <small style="color: #64748b;">pengaduan</small>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    
  
  
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
  <script>
    // Status Chart
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
      new Chart(statusCtx, {
        type: 'doughnut',
        data: {
          labels: ['Menunggu', 'Proses', 'Selesai'],
          datasets: [{
            data: [
              <?php echo $stats['menunggu']; ?>,
              <?php echo $stats['proses']; ?>,
              <?php echo $stats['selesai']; ?>
            ],
            backgroundColor: [
              '#f59e0b',
              '#0ea5e9',
              '#10b981'
            ],
            borderColor: 'white',
            borderWidth: 2
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                padding: 20,
                font: { size: 12 }
              }
            }
          }
        }
      });
    }
    
    // Kategori Chart
    const kategoriCtx = document.getElementById('kategoriChart');
    if (kategoriCtx) {
      new Chart(kategoriCtx, {
        type: 'bar',
        data: {
          labels: [
            <?php 
              foreach ($kategori_stats as $kat) {
                echo "'" . htmlspecialchars($kat['nama']) . "',";
              }
            ?>
          ],
          datasets: [{
            label: 'Jumlah Pengaduan',
            data: [
              <?php 
                foreach ($kategori_stats as $kat) {
                  echo $kat['jumlah'] . ",";
                }
              ?>
            ],
            backgroundColor: '#4f46e5',
            borderColor: '#4338ca',
            borderWidth: 1,
            borderRadius: 8
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          indexAxis: 'y',
          plugins: {
            legend: {
              display: false
            }
          },
          scales: {
            x: {
              beginAtZero: true,
              ticks: {
                stepSize: 1
              }
            }
          }
        }
      });
    }
  </script>
</body>
</html>
