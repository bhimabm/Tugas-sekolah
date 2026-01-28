<?php
  require_once 'config.php';
  
  if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
  }
  
  $id = $_GET['id'] ?? 0;
  
  if (!$id) {
    header('Location: index.php');
    exit();
  }
  
  // Ambil data pengaduan
  $stmt = $db->prepare("
    SELECT p.*, u.name as pelapor, u.nis, u.kelas, k.nama as kategori
    FROM pengaduan p
    JOIN users u ON p.user_id = u.id
    JOIN kategori k ON p.kategori_id = k.id
    WHERE p.id = ?
  ");
  $stmt->execute([$id]);
  $pengaduan = $stmt->fetch(PDO::FETCH_ASSOC);
  
  if (!$pengaduan) {
    header('Location: index.php');
    exit();
  }
  
  // Ambil feedback
  // Ensure feedback.author_id exists (stores admin user id who posted feedback)
  $hasCol = false;
  try {
    $colCheck = $db->prepare("SELECT COUNT(*) as cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'feedback' AND COLUMN_NAME = 'author_id'");
    $colCheck->execute();
    $hasCol = $colCheck->fetch(PDO::FETCH_ASSOC)['cnt'] > 0;
    if (!$hasCol) {
      $db->exec("ALTER TABLE feedback ADD COLUMN author_id INT DEFAULT NULL");
      $hasCol = true;
    }
  } catch (Exception $e) {
    // ignore if cannot alter; continue without author column
    $hasCol = false;
  }

  if ($hasCol) {
    $stmt = $db->prepare("
      SELECT f.*, u.name AS author_name
      FROM feedback f
      LEFT JOIN users u ON f.author_id = u.id
      WHERE f.pengaduan_id = ?
      ORDER BY f.tanggal DESC
    ");
  } else {
    $stmt = $db->prepare("
      SELECT *, NULL AS author_name
      FROM feedback
      WHERE pengaduan_id = ?
      ORDER BY tanggal DESC
    ");
  }
  $stmt->execute([$id]);
  $feedback_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
  // Ambil user info
  $user_id = $_SESSION['user_id'];
  $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
  $stmt->execute([$user_id]);
  $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
  
  // Cek hak akses: hanya pemilik pengaduan atau admin yang boleh melihat
  if (!$current_user['is_admin'] && $pengaduan['user_id'] != $user_id) {
    // Redirect ke halaman utama jika tidak berhak
    header('Location: index.php');
    exit();
  }

  // Handle tambah feedback (admin only)
  $feedback_message = '';
  $feedback_msg_type = '';
  
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_feedback']) && $current_user['is_admin']) {
    $feedback_text = trim($_POST['feedback_text'] ?? '');
    
        if (empty($feedback_text)) {
      $feedback_message = 'Feedback tidak boleh kosong.';
      $feedback_msg_type = 'danger';
    } else {
      try {
        $tanggal = date('Y-m-d H:i:s');
        $author_id = $current_user['id'] ?? $user_id;
        
        // Simple approach: always use author_id, LEFT JOIN handles if missing
        $insertSql = "INSERT INTO feedback (pengaduan_id, isi, tanggal, author_id) VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($insertSql);
        $stmt->execute([$id, $feedback_text, $tanggal, $author_id]);
        
        $feedback_message = 'Feedback berhasil dikirim.';
        $feedback_msg_type = 'success';
        
        // Refresh feedback list (include author name when available)
        $stmt = $db->prepare("
          SELECT f.id, f.pengaduan_id, f.isi, f.tanggal, f.author_id, u.name AS author_name
          FROM feedback f
          LEFT JOIN users u ON f.author_id = u.id
          WHERE f.pengaduan_id = ?
          ORDER BY f.tanggal DESC
        ");
        $stmt->execute([$id]);
        $feedback_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
      } catch (Exception $e) {
        $feedback_message = 'Gagal mengirim feedback: ' . $e->getMessage();
        $feedback_msg_type = 'danger';
      }
    }
  }
  
  // Update status jika admin
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current_user['is_admin'] && isset($_POST['status'])) {
    $new_status = $_POST['status'] ?? '';
    if (in_array($new_status, ['Menunggu', 'Proses', 'Selesai'])) {
      $stmt = $db->prepare("UPDATE pengaduan SET status = ? WHERE id = ?");
      $stmt->execute([$new_status, $id]);
      
      // Refresh data
      $stmt = $db->prepare("
        SELECT p.*, u.name as pelapor, u.nis, u.kelas, k.nama as kategori
        FROM pengaduan p
        JOIN users u ON p.user_id = u.id
        JOIN kategori k ON p.kategori_id = k.id
        WHERE p.id = ?
      ");
      $stmt->execute([$id]);
      $pengaduan = $stmt->fetch(PDO::FETCH_ASSOC);
    }
  }
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Detail Pengaduan - Sistem Pengaduan</title>
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
      margin-bottom: 20px;
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
    
    .detail-row {
      display: grid;
      grid-template-columns: 200px 1fr;
      gap: 20px;
      padding: 20px 0;
      border-bottom: 1px solid #e2e8f0;
      align-items: start;
    }
    
    .detail-row:last-child {
      border-bottom: none;
    }
    
    .detail-label {
      font-weight: 700;
      color: #64748b;
      font-size: 14px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .detail-value {
      color: #1e293b;
      font-size: 15px;
      line-height: 1.6;
      word-break: break-word;
    }
    
    .status-badge {
      display: inline-block;
      padding: 8px 15px;
      border-radius: 20px;
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
    
    .photo-container {
      max-width: 500px;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .photo-container:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
    }
    
    .photo-container img {
      width: 100%;
      height: auto;
      display: block;
    }
    
    .feedback-item {
      background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
      padding: 20px;
      border-radius: 12px;
      margin-bottom: 16px;
      border-left: 5px solid var(--primary);
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
      transition: all 0.3s ease;
    }

    .feedback-item:hover {
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
      transform: translateY(-2px);
    }
    
    .feedback-date {
      font-size: 12px;
      color: #94a3b8;
      margin-bottom: 10px;
      font-weight: 500;
    }
    
    .feedback-text {
      color: #1e293b;
      line-height: 1.7;
      font-size: 14px;
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
      border: none;
      cursor: pointer;
    }
    
    .btn-primary-custom {
      background-color: var(--primary);
      color: white;
    }
    
    .btn-primary-custom:hover {
      background-color: #4338ca;
      color: white;
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
      
      .detail-row {
        flex-direction: column;
      }
      
      .detail-label {
        width: 100%;
        margin-bottom: 5px;
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
      <?php if ($current_user['is_admin']): ?>
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
    <a href="index.php" class="btn btn-outline-secondary mb-4" style="border-radius: 6px; border-width: 1px; font-size: 14px;">
      <i class="fas fa-arrow-left"></i> Kembali
    </a>
    
    <!-- Detail Pengaduan -->
    <div class="card" style="box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08); border: none;">
      <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px 8px 0 0;">
        <h5 style="margin: 0; display: flex; align-items: center; gap: 8px;">
          <i class="fas fa-clipboard"></i> Detail Pengaduan
        </h5>
      </div>
      <div class="card-body">
        <div class="detail-row">
          <div class="detail-label"><i class="fas fa-calendar"></i> Tanggal</div>
          <div class="detail-value">
            <?php echo date('d F Y H:i', strtotime($pengaduan['tanggal'])); ?>
          </div>
        </div>
        
        <div class="detail-row">
          <div class="detail-label"><i class="fas fa-user"></i> Pelapor</div>
          <div class="detail-value">
            <strong><?php echo htmlspecialchars($pengaduan['pelapor']); ?></strong><br>
            <small style="color: #94a3b8;">NIS: <?php echo htmlspecialchars($pengaduan['nis'] ?? '-'); ?></small><br>
            <small style="color: #94a3b8;">Kelas: <?php echo htmlspecialchars($pengaduan['kelas'] ?? '-'); ?></small>
          </div>
        </div>
        
        <div class="detail-row">
          <div class="detail-label"><i class="fas fa-list"></i> Kategori</div>
          <div class="detail-value">
            <span style="display: inline-block; padding: 6px 12px; background-color: #ede9fe; color: #6d28d9; border-radius: 6px; font-weight: 500;">
              <?php echo htmlspecialchars($pengaduan['kategori']); ?>
            </span>
          </div>
        </div>
        
        <div class="detail-row">
          <div class="detail-label"><i class="fas fa-map-marker-alt"></i> Lokasi</div>
          <div class="detail-value">
            <?php echo htmlspecialchars($pengaduan['lokasi']); ?>
          </div>
        </div>
        
        <div class="detail-row">
          <div class="detail-label"><i class="fas fa-check-circle"></i> Status</div>
          <div class="detail-value">
            <?php if ($current_user['is_admin']): ?>
              <form method="POST" style="display: inline;">
                <select name="status" class="form-select form-select-sm" style="width: 200px; border-radius: 6px;" onchange="this.form.submit()">
                  <option value="Menunggu" <?php echo $pengaduan['status'] === 'Menunggu' ? 'selected' : ''; ?>>Menunggu</option>
                  <option value="Proses" <?php echo $pengaduan['status'] === 'Proses' ? 'selected' : ''; ?>>Proses</option>
                  <option value="Selesai" <?php echo $pengaduan['status'] === 'Selesai' ? 'selected' : ''; ?>>Selesai</option>
                </select>
              </form>
            <?php else: ?>
              <span class="status-badge status-<?php echo strtolower($pengaduan['status']); ?>">
                <?php echo $pengaduan['status']; ?>
              </span>
            <?php endif; ?>
          </div>
        </div>
        
        <div class="detail-row">
          <div class="detail-label"><i class="fas fa-align-left"></i> Keterangan</div>
          <div class="detail-value">
            <div style="background-color: #fafafa; padding: 12px; border-radius: 6px; border-left: 3px solid #e2e8f0;">
              <?php echo nl2br(htmlspecialchars($pengaduan['keterangan'])); ?>
            </div>
          </div>
        </div>
        
        <?php if ($pengaduan['foto']): ?>
          <div class="detail-row">
            <div class="detail-label"><i class="fas fa-image"></i> Foto</div>
            <div class="detail-value">
              <div class="photo-container">
                <img src="<?php echo htmlspecialchars($pengaduan['foto']); ?>" alt="Foto Pengaduan" style="border-radius: 8px;">
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
    
    <!-- Feedback Section -->
    <div class="card" style="box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08); border: none; margin-top: 20px;">
      <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px 8px 0 0;">
        <h5 style="margin: 0; display: flex; align-items: center; gap: 8px;">
          <i class="fas fa-comments"></i> Feedback (<?php echo count($feedback_list); ?>)
        </h5>
      </div>
      <div class="card-body">
        <!-- Feedback Form (Admin Only) -->
        <?php if ($current_user['is_admin']): ?>
          <?php if (!empty($feedback_message)): ?>
            <div class="alert alert-<?php echo $feedback_msg_type; ?>" role="alert" style="margin-bottom: 20px;">
              <i class="fas fa-<?php echo $feedback_msg_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
              <?php echo htmlspecialchars($feedback_message); ?>
            </div>
          <?php endif; ?>

          <div style="background: linear-gradient(135deg, #f0f4ff 0%, #fafbff 100%); padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #4f46e5; box-shadow: 0 2px 6px rgba(79, 70, 229, 0.1);">
            <h6 style="margin-bottom: 15px; color: #1e293b; font-weight: 600;">
              <i class="fas fa-reply"></i> Berikan Tanggapan
            </h6>
            <form method="POST">
              <div style="margin-bottom: 12px;">
                <textarea name="feedback_text" class="form-control" rows="4" placeholder="Tulis tanggapan untuk pengaduan ini..." required style="border-radius: 6px; border: 1px solid #cbd5e1; box-shadow: 0 1px 2px rgba(0,0,0,0.05);"></textarea>
              </div>
              <button type="submit" name="add_feedback" class="btn btn-primary" style="background-color: #4f46e5; border-color: #4f46e5; padding: 8px 20px; font-size: 14px; border-radius: 6px; transition: all 0.3s ease;">
                <i class="fas fa-send"></i> Kirim Tanggapan
              </button>
            </form>
          </div>
        <?php endif; ?>

        <!-- Feedback List -->
        <?php if (!empty($feedback_list)): ?>
          <div style="margin-top: 20px;">
            <?php foreach ($feedback_list as $fb): ?>
              <div class="feedback-item">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                  <div style="font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 6px;">
                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; background-color: #4f46e5; color: white; border-radius: 50%; font-size: 12px;">
                      <i class="fas fa-shield-alt"></i>
                    </span>
                    <?php echo htmlspecialchars($fb['author_name'] ?? 'Admin'); ?>
                  </div>
                  <div style="font-size: 12px; color: #94a3b8; display: flex; align-items: center; gap: 4px;">
                    <i class="fas fa-calendar-alt"></i> <?php echo date('d F Y H:i', strtotime($fb['tanggal'])); ?>
                  </div>
                </div>
                <div class="feedback-text" style="color: #334155; line-height: 1.6; font-size: 14px;">
                  <?php echo nl2br(htmlspecialchars($fb['isi'])); ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div style="text-align: center; padding: 40px; color: #94a3b8;">
            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 20px; opacity: 0.5; display: block;"></i>
            <p>Belum ada feedback untuk pengaduan ini</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
  
</body>
</html>
