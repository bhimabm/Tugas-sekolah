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

// Ambil daftar kategori
$stmt = $db->query("SELECT id, nama FROM kategori ORDER BY nama ASC");
$kategori_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$message = '';
$message_type = '';
$foto_path = '';

// Buat folder uploads jika belum ada
if (!is_dir('uploads')) {
  mkdir('uploads', 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_pengaduan'])) {
  $kategori_id = intval($_POST['kategori_id'] ?? 0);
  $lokasi = trim($_POST['lokasi'] ?? '');
  $keterangan = trim($_POST['keterangan'] ?? '');

  if ($kategori_id <= 0) {
    $message = 'Pilih kategori yang valid.';
    $message_type = 'danger';
  } elseif (empty($lokasi)) {
    $message = 'Lokasi tidak boleh kosong.';
    $message_type = 'danger';
  } else {
    // Handle file upload
    $foto_path = '';
    if (!empty($_FILES['foto']['name'])) {
      $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
      $file_ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
      $max_size = 5 * 1024 * 1024; // 5MB

      if (!in_array($file_ext, $allowed_ext)) {
        $message = 'Format file tidak didukung. Gunakan JPG, PNG, atau GIF.';
        $message_type = 'danger';
      } elseif ($_FILES['foto']['size'] > $max_size) {
        $message = 'Ukuran file terlalu besar. Maksimal 5MB.';
        $message_type = 'danger';
      } elseif ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        $message = 'Gagal upload file.';
        $message_type = 'danger';
      } else {
        // Generate unique filename
        $filename = uniqid() . '.' . $file_ext;
        $upload_path = 'uploads/' . $filename;

        if (move_uploaded_file($_FILES['foto']['tmp_name'], $upload_path)) {
          $foto_path = $upload_path;
        } else {
          $message = 'Gagal menyimpan file.';
          $message_type = 'danger';
        }
      }
    }

    // Insert ke database jika tidak ada error
    if (empty($message)) {
      try {
        $tanggal = date('Y-m-d H:i:s');
        $status = 'Menunggu';
        $stmt = $db->prepare("INSERT INTO pengaduan (user_id, kategori_id, lokasi, keterangan, foto, tanggal, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $kategori_id, $lokasi, $keterangan, $foto_path, $tanggal, $status]);
        
        $_SESSION['flash_message'] = 'Pengaduan berhasil dibuat.';
        $_SESSION['flash_type'] = 'success';
        header('Location: riwayat_pengaduan.php');
        exit();
      } catch (Exception $e) {
        // Delete uploaded file jika ada error saat insert
        if (!empty($foto_path) && file_exists($foto_path)) {
          unlink($foto_path);
        }
        $message = 'Gagal mengirim pengaduan: ' . $e->getMessage();
        $message_type = 'danger';
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
  <title>Buat Pengaduan - Sistem Pengaduan</title>
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

    .page-header {
      background: white;
      padding: 20px 30px;
      border-radius: 10px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      margin-bottom: 30px;
    }

    .page-header h4 {
      margin: 0;
      color: #1e293b;
      font-weight: 600;
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
      padding: 30px;
    }

    .form-group {
      margin-bottom: 24px;
    }

    .form-group label {
      display: block;
      margin-bottom: 10px;
      color: #1e293b;
      font-weight: 600;
      font-size: 14px;
    }

    .form-control,
    .form-select {
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 10px 14px;
      font-size: 14px;
      transition: all 0.3s ease;
    }

    .form-control:focus,
    .form-select:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
      outline: none;
    }

    .form-control::placeholder {
      color: #cbd5e1;
    }

    .file-upload-area {
      border: 2px dashed #e2e8f0;
      border-radius: 8px;
      padding: 30px;
      text-align: center;
      cursor: pointer;
      transition: all 0.3s ease;
      background-color: #f8fafc;
    }

    .file-upload-area:hover {
      border-color: var(--primary);
      background-color: rgba(79, 70, 229, 0.05);
    }

    .file-upload-area.drag-over {
      border-color: var(--primary);
      background-color: rgba(79, 70, 229, 0.1);
    }

    .file-upload-area i {
      font-size: 48px;
      color: var(--primary);
      margin-bottom: 12px;
      display: block;
    }

    .file-upload-area p {
      margin: 0;
      color: #475569;
      font-size: 14px;
    }

    .file-upload-area small {
      display: block;
      color: #94a3b8;
      margin-top: 8px;
    }

    .preview-img {
      max-width: 200px;
      max-height: 200px;
      border-radius: 8px;
      margin-top: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .btn-group {
      display: flex;
      gap: 12px;
      justify-content: flex-end;
      padding-top: 20px;
      border-top: 1px solid #e2e8f0;
    }

    .btn-primary {
      background-color: var(--primary);
      border-color: var(--primary);
      padding: 10px 24px;
      font-weight: 600;
      border-radius: 8px;
      transition: all 0.3s ease;
    }

    .btn-primary:hover {
      background-color: #4338ca;
      border-color: #4338ca;
    }

    .btn-secondary {
      background-color: #e2e8f0;
      border-color: #e2e8f0;
      color: #475569;
      padding: 10px 24px;
      font-weight: 600;
      border-radius: 8px;
      transition: all 0.3s ease;
      text-decoration: none;
    }

    .btn-secondary:hover {
      background-color: #cbd5e1;
      border-color: #cbd5e1;
      color: #1e293b;
    }

    .alert {
      border-radius: 8px;
      border: none;
      margin-bottom: 20px;
    }

    #foto-input {
      display: none;
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

      .btn-group {
        flex-direction: column-reverse;
      }

      .btn-group .btn {
        width: 100%;
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
        <a href="riwayat_pengaduan.php" class="nav-link">
          <i class="fas fa-history"></i>
          Riwayat Pengaduan
        </a>
      </div>
      <div class="nav-item">
        <a href="buat_pengaduan.php" class="nav-link active">
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
    <!-- Page Header -->
    <div class="page-header">
      <h4><i class="fas fa-plus-circle"></i> Buat Pengaduan Baru</h4>
    </div>

    <?php if (!empty($message)): ?>
      <div class="alert alert-<?php echo $message_type; ?>" role="alert">
        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo htmlspecialchars($message); ?>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header">
        <h5>Form Pengaduan</h5>
      </div>
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data" id="pengaduan-form">
          <!-- Kategori -->
          <div class="form-group">
            <label for="kategori_id">
              <i class="fas fa-list"></i> Kategori <span style="color: var(--danger);">*</span>
            </label>
            <select id="kategori_id" name="kategori_id" class="form-select" required>
              <option value="">-- Pilih Kategori --</option>
              <?php foreach ($kategori_list as $kat): ?>
                <option value="<?php echo $kat['id']; ?>"><?php echo htmlspecialchars($kat['nama']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Lokasi -->
          <div class="form-group">
            <label for="lokasi">
              <i class="fas fa-map-marker-alt"></i> Lokasi <span style="color: var(--danger);">*</span>
            </label>
            <input type="text" id="lokasi" name="lokasi" class="form-control" placeholder="Contoh: Ruang kelas 12 RPL" required>
          </div>

          <!-- Keterangan -->
          <div class="form-group">
            <label for="keterangan">
              <i class="fas fa-align-left"></i> Keterangan
            </label>
            <textarea id="keterangan" name="keterangan" class="form-control" rows="5" placeholder="Jelaskan masalah secara detail..."></textarea>
          </div>

          <!-- File Upload -->
          <div class="form-group">
            <label>
              <i class="fas fa-image"></i> Foto Pengaduan
            </label>
            <input type="file" id="foto-input" name="foto" accept="image/jpeg,image/png,image/gif" onchange="previewImage(this)">
            <div class="file-upload-area" id="upload-area" onclick="document.getElementById('foto-input').click();">
              <i class="fas fa-cloud-upload-alt"></i>
              <p>Klik di sini atau drag-drop gambar</p>
              <small>JPG, PNG, GIF (Maksimal 5MB)</small>
            </div>
            <img id="preview" class="preview-img" style="display: none;">
          </div>

          <!-- Buttons -->
          <div class="btn-group">
            <a href="riwayat_pengaduan.php" class="btn btn-secondary">
              <i class="fas fa-times"></i> Batal
            </a>
            <button type="submit" name="submit_pengaduan" class="btn btn-primary">
              <i class="fas fa-paper-plane"></i> Kirim Pengaduan
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
  <script>
    // Drag and drop
    const uploadArea = document.getElementById('upload-area');
    const fileInput = document.getElementById('foto-input');

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
      uploadArea.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
      e.preventDefault();
      e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
      uploadArea.addEventListener(eventName, () => uploadArea.classList.add('drag-over'), false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
      uploadArea.addEventListener(eventName, () => uploadArea.classList.remove('drag-over'), false);
    });

    uploadArea.addEventListener('drop', (e) => {
      const dt = e.dataTransfer;
      const files = dt.files;
      fileInput.files = files;
      previewImage(fileInput);
    }, false);

    // Preview image
    function previewImage(input) {
      const preview = document.getElementById('preview');
      if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = (e) => {
          preview.src = e.target.result;
          preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
      }
    }
  </script>
</body>
</html>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
  
</body>
</html>
