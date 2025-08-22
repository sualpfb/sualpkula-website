<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Basit güvenlik kontrolü
$expected_token = "GUVENLIK_TOKENI_12345";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Sadece POST isteği kabul edilir']);
    exit;
}

// Token kontrolü (basit)
if (!isset($_POST['token']) || $_POST['token'] !== $expected_token) {
    echo json_encode(['error' => 'Geçersiz güvenlik tokenı']);
    exit;
}

// Dosya kontrolü
if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'Dosya seçilmedi veya hata oluştu']);
    exit;
}

// Klasör yoksa oluştur
$upload_dir = 'uploads/pdfs/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Benzersiz dosya adı
$original_name = $_FILES['pdf_file']['name'];
$file_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $original_name);
$file_path = $upload_dir . $file_name;

// Dosyayı yükle
if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $file_path)) {
    echo json_encode([
        'success' => true,
        'file_path' => $file_path,
        'file_name' => $file_name,
        'original_name' => $original_name
    ]);
} else {
    echo json_encode(['error' => 'Dosya yüklenemedi']);
}
?>
