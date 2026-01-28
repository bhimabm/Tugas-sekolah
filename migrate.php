<?php
// File untuk memastikan database schema sudah benar
try {
    $db = new PDO('mysql:host=localhost;dbname=pengaduan_db;charset=utf8mb4', 'root', '');
    
    // Pastikan kolom author_id ada di tabel feedback
    $check = $db->prepare("SELECT COUNT(*) as cnt FROM information_schema.COLUMNS 
                          WHERE TABLE_SCHEMA = 'pengaduan_db' 
                          AND TABLE_NAME = 'feedback' 
                          AND COLUMN_NAME = 'author_id'");
    $check->execute();
    $exists = $check->fetch(PDO::FETCH_ASSOC)['cnt'] > 0;
    
    if (!$exists) {
        echo "Menambahkan kolom author_id ke tabel feedback...<br>";
        $db->exec("ALTER TABLE feedback ADD COLUMN author_id INT DEFAULT NULL AFTER tanggal");
        echo "✓ Kolom author_id berhasil ditambahkan<br>";
    } else {
        echo "✓ Kolom author_id sudah ada<br>";
    }
    
    echo "✓ Database schema sudah valid<br>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
