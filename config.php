<?php 
  // mulai session untuk otentikasi
  if (session_status() === PHP_SESSION_NONE) session_start();
  $host = "localhost"; // server mysql yang digunakan
    $dbname = "tes_ukk";
    $user = "root";
    $pass = "";
    
    // menyiapkan objek PDO untuk konektivitas ke database
    try {
      $db = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
      // set the PDO error mode to exception
      $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
      echo "Connection failed: " . $e->getMessage();
    }