<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit();
}

$user_id = $_SESSION['user_id'];

// Ambil data user
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Hitung statistik pengaduan user
$stats = [];

// Total pengaduan user
$stmt = $db->prepare("SELECT COUNT(*) as total FROM pengaduan WHERE user_id = ?");
$stmt->execute([$user_id]);
$stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Pengaduan user status menunggu
$stmt = $db->prepare("SELECT COUNT(*) as total FROM pengaduan WHERE user_id = ? AND status = 'Menunggu'");
$stmt->execute([$user_id]);
$stats['menunggu'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Pengaduan user status proses
$stmt = $db->prepare("SELECT COUNT(*) as total FROM pengaduan WHERE user_id = ? AND status = 'Proses'");
$stmt->execute([$user_id]);
$stats['proses'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Pengaduan user status selesai
$stmt = $db->prepare("SELECT COUNT(*) as total FROM pengaduan WHERE user_id = ? AND status = 'Selesai'");
$stmt->execute([$user_id]);
$stats['selesai'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Ambil pengaduan milik user saat ini
$stmt = $db->prepare("SELECT p.id, p.tanggal, p.lokasi, p.status, p.keterangan, k.nama as kategori, p.foto
  FROM pengaduan p
  JOIN kategori k ON p.kategori_id = k.id
  WHERE p.user_id = ?
  ORDER BY p.tanggal DESC");
$stmt->execute([$user_id]);
$pengaduan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
  <title>Riwayat Pengaduan - Sistem Pengaduan</title>
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

    .main-content {
      margin-left: 260px;
      padding: 30px;
    }

    .top-navbar {
      background: linear-gradient(135deg, white 0%, #f8fafc 100%);
      padding: 20px 30px;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
      border: 1px solid #e2e8f0;
    }

    .top-navbar h5 {
      color: #1e293b;
      font-weight: 600;
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
      box-shadow: 0 1px 3px rgba(0,0,0,0.08);
      border-left: 4px solid var(--primary);
      transition: all 0.3s ease;
    }

    .stat-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 12px 20px rgba(0,0,0,0.12);
    }

    .stat-card.success {
      border-left-color: var(--success);
      background: linear-gradient(135deg, rgba(16,185,129,0.02) 0%, white 100%);
    }

    .stat-card.warning {
      border-left-color: var(--warning);
      background: linear-gradient(135deg, rgba(245,158,11,0.02) 0%, white 100%);
    }

    .stat-card.danger {
      border-left-color: var(--danger);
      background: linear-gradient(135deg, rgba(239,68,68,0.02) 0%, white 100%);
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
      margin-bottom: 20px;
    }

    .card:hover {
      box-shadow: 0 8px 15px rgba(0,0,0,0.1);
    }

    .pengaduan-card {
      background: white;
      border-radius: 12px;
      padding: 20px;
      border-left: 4px solid var(--primary);
      transition: all 0.3s ease;
      margin-bottom: 15px;
      cursor: pointer;
      box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    }

    .pengaduan-card:hover {
      box-shadow: 0 8px 20px rgba(0,0,0,0.12);
      transform: translateY(-4px);
    }

    .pengaduan-card.status-selesai {
      border-left-color: var(--success);
      background: linear-gradient(135deg, rgba(16,185,129,0.05) 0%, white 100%);
    }

    .pengaduan-card.status-proses {
      border-left-color: var(--info);
      background: linear-gradient(135deg, rgba(14,165,233,0.05) 0%, white 100%);
    }

    .pengaduan-card.status-menunggu {
      border-left-color: var(--warning);
      background: linear-gradient(135deg, rgba(245,158,11,0.05) 0%, white 100%);
    }

    .status-badge {
      display: inline-block;
      padding: 6px 12px;
      border-radius: 20px;
      font-weight: 600;
      font-size: 12px;
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
      padding: 60px 20px;
      color: #94a3b8;
    }

    .empty-state i {
      font-size: 64px;
      margin-bottom: 20px;
      opacity: 0.5;
      display: block;
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
        <a href="riwayat_pengaduan.php" class="nav-link active">
          <i class="fas fa-history"></i>
          Riwayat Pengaduan
        </a>
      </div>
      <div class="nav-item">
        <a href="buat_pengaduan.php" class="nav-link">
          <i class="fas fa-plus-circle"></i>
          Buat Pengaduan
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
          <small style="color: #64748b;">Pengguna</small>
        </div>
      </div>
    </div>

    <?php if (!empty($flash_message)): ?>
      <div class="alert alert-<?php echo htmlspecialchars($flash_type); ?>" role="alert">
        <i class="fas fa-<?php echo $flash_type === 'success' ? 'check-circle' : 'info-circle'; ?>"></i>
        <?php echo htmlspecialchars($flash_message); ?>
      </div>
    <?php endif; ?>

    <!-- Statistics Section -->
    <div class="section-title">
      <i class="fas fa-chart-bar"></i> Statistik Pengaduan Saya
    </div>

    <div class="row mb-4">
      <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-card">
          <div class="icon" style="color: var(--primary);">
            <i class="fas fa-inbox"></i>
          </div>
          <div class="value"><?php echo $stats['total']; ?></div>
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
        <div class="stat-card" style="border-left-color: var(--info);">
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

    <!-- Pengaduan Section -->
    <div class="section-title">
      <i class="fas fa-list"></i> Daftar Pengaduan Saya
    </div>

    <?php if (!empty($pengaduan_list)): ?>
      <?php foreach ($pengaduan_list as $p): ?>
        <div class="pengaduan-card status-<?php echo strtolower($p['status']); ?>">
          <div class="row align-items-start">
            <div class="col-md-8">
              <div style="margin-bottom: 10px;">
                <h6 style="color: #1e293b; margin-bottom: 8px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                  <span style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; background-color: #ede9fe; color: #6d28d9; border-radius: 6px; font-size: 12px;">
                    <i class="fas fa-tag"></i>
                  </span>
                  <?php echo htmlspecialchars($p['kategori']); ?>
                </h6>
                <p style="color: #64748b; font-size: 14px; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                  <i class="fas fa-map-marker-alt" style="color: #ef4444; width: 16px; text-align: center;"></i> <?php echo htmlspecialchars($p['lokasi']); ?>
                </p>
                <p style="color: #475569; line-height: 1.6; margin-bottom: 10px; font-size: 14px;">
                  <?php echo htmlspecialchars(substr($p['keterangan'], 0, 120)) . (strlen($p['keterangan']) > 120 ? '...' : ''); ?>
                </p>
                <small style="color: #94a3b8; display: flex; align-items: center; gap: 4px;">
                  <i class="far fa-calendar"></i> <?php echo date('d M Y H:i', strtotime($p['tanggal'])); ?>
                </small>
              </div>
            </div>
            <div class="col-md-4">
              <div style="text-align: right;">
                <div style="margin-bottom: 12px;">
                  <span class="status-badge status-<?php echo strtolower($p['status']); ?>">
                    <?php echo $p['status']; ?>
                  </span>
                </div>
                <?php if (!empty($p['foto'])): ?>
                  <img src="<?php echo htmlspecialchars($p['foto']); ?>" alt="foto" style="max-width: 100px; max-height: 70px; border-radius: 8px; margin-bottom: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.1);">
                <?php endif; ?>
                <div>
                  <a href="detail_pengaduan.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-primary" style="border-radius: 6px; font-size: 13px;">
                    <i class="fas fa-eye"></i> Lihat Detail
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="empty-state">
        <i class="fas fa-inbox"></i>
        <p style="font-size: 16px; margin-bottom: 20px;">Belum ada pengaduan yang Anda buat.</p>
        <a href="buat_pengaduan.php" class="btn btn-primary" style="border-radius: 6px;">
          <i class="fas fa-plus-circle"></i> Buat Pengaduan Sekarang
        </a>
      </div>
    <?php endif; ?>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
  
</body>
</html>
