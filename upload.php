<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://siteniz.com'); // Sitenizin domaini
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Güvenlik önlemleri
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Sadece POST isteği kabul edilir']);
    exit;
}

// Basit token kontrolü (admin.html'deki token ile aynı olmalı)
$expected_token = "GUVENLIK_TOKENI_12345"; // Bu token'i admin.html'de de kullanacağız
if (!isset($_POST['token']) || $_POST['token'] !== $expected_token) {
    http_response_code(403);
    echo json_encode(['error' => 'Geçersiz güvenlik tokenı']);
    exit;
}

// Dosya kontrolü
if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Dosya yüklenirken hata oluştu']);
    exit;
}

// Dosya tipi kontrolü
$allowed_types = ['application/pdf'];
if (!in_array($_FILES['pdf_file']['type'], $allowed_types)) {
    http_response_code(400);
    echo json_encode(['error' => 'Sadece PDF dosyaları yüklenebilir']);
    exit;
}

// Dosya boyutu kontrolü (100MB)
$max_size = 100 * 1024 * 1024;
if ($_FILES['pdf_file']['size'] > $max_size) {
    http_response_code(400);
    echo json_encode(['error' => 'Dosya boyutu 100MB\'dan büyük olamaz']);
    exit;
}

// Klasör yoksa oluştur
$upload_dir = 'uploads/pdfs/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Benzersiz dosya adı oluştur
$original_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['pdf_file']['name']);
$file_name = time() . '_' . $original_name;
$file_path = $upload_dir . $file_name;

// Dosyayı yükle
if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $file_path)) {
    // Başarılı yanıt
    echo json_encode([
        'success' => true,
        'file_path' => $file_path,
        'file_name' => $file_name,
        'original_name' => $original_name
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Dosya yüklenirken hata oluştu']);
}
// Dosya silme işlemi
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['file_path'])) {
    if (!isset($_POST['token']) || $_POST['token'] !== $expected_token) {
        http_response_code(403);
        echo json_encode(['error' => 'Geçersiz güvenlik tokenı']);
        exit;
    }
    
    $file_path = $_POST['file_path'];
    
    // Güvenlik kontrolü - sadece uploads klasöründeki dosyalar silinebilir
    if (strpos($file_path, 'uploads/') !== 0 || !file_exists($file_path)) {
        http_response_code(400);
        echo json_encode(['error' => 'Geçersiz dosya yolu']);
        exit;
    }
    
    if (unlink($file_path)) {
        echo json_encode(['success' => true, 'message' => 'Dosya silindi']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Dosya silinirken hata oluştu']);
    }
    exit;
}
?>

