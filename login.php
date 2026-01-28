<?php
  require_once 'config.php';
  
  if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
  }
  
  $error = '';
  
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
      $error = 'Email dan password tidak boleh kosong!';
    } else {
      $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
      $stmt->execute([$email]);
      $user = $stmt->fetch(PDO::FETCH_ASSOC);
      
      if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        header('Location: index.php');
        exit();
      } else {
        $error = 'Email atau password salah!';
      }
    }
  }
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Sistem Pengaduan</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --primary: #4f46e5;
    }
    
    body {
      background: linear-gradient(135deg, var(--primary) 0%, #4338ca 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .login-container {
      width: 100%;
      max-width: 400px;
      padding: 20px;
    }
    
    .login-card {
      background: white;
      border-radius: 15px;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
      padding: 40px;
    }
    
    .login-header {
      text-align: center;
      margin-bottom: 30px;
    }
    
    .login-header .logo {
      font-size: 48px;
      color: var(--primary);
      margin-bottom: 15px;
    }
    
    .login-header h1 {
      font-size: 24px;
      font-weight: 600;
      color: #1e293b;
      margin-bottom: 5px;
    }
    
    .login-header p {
      color: #64748b;
      font-size: 14px;
    }
    
    .form-control {
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 12px 15px;
      margin-bottom: 15px;
      font-size: 14px;
      transition: all 0.3s ease;
    }
    
    .form-control:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }
    
    .form-control::placeholder {
      color: #94a3b8;
    }
    
    .btn-login {
      background-color: var(--primary);
      border: none;
      border-radius: 8px;
      padding: 12px;
      font-weight: 600;
      color: white;
      width: 100%;
      margin-top: 10px;
      cursor: pointer;
      transition: all 0.3s ease;
    }
    
    .btn-login:hover {
      background-color: #4338ca;
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(79, 70, 229, 0.3);
    }
    
    .alert {
      border-radius: 8px;
      border: none;
      margin-bottom: 20px;
    }
    
    .alert-danger {
      background-color: #fee2e2;
      color: #991b1b;
    }
    
    .demo-accounts strong {
      display: block;
      margin-bottom: 8px;
    }
    
    .demo-accounts p {
      margin-bottom: 5px;
    }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="login-card">
      <div class="login-header">
        <div class="logo">
          <i class="fas fa-exclamation-circle"></i>
        </div>
        <h1>Sistem Pengaduan</h1>
        <p>Silakan login untuk melanjutkan</p>
      </div>
      
      <?php if ($error): ?>
        <div class="alert alert-danger">
          <i class="fas fa-times-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>
      
      <form method="POST">
        <input 
          type="email" 
          class="form-control" 
          name="email" 
          placeholder="Email" 
          required
          autofocus
        >
        
        <input 
          type="password" 
          class="form-control" 
          name="password" 
          placeholder="Password" 
          required
        >
        
        <button type="submit" class="btn-login">
          <i class="fas fa-sign-in-alt"></i> Login
        </button>
      </form>
      
    </div>
  </div>
</body>
</html>
