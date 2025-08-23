<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');

// Hata raporlamayı aç (geliştirme için)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Güvenlik tokeni
define('UPLOAD_TOKEN', 'GUVENLIK_TOKENI_12345');

// Maximum dosya boyutu (100MB)
define('MAX_FILE_SIZE', 100 * 1024 * 1024);

// Preflight istekleri için
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// JSON inputunu işle
if ($_SERVER['CONTENT_TYPE'] === 'application/json') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    if ($data) {
        $_POST = array_merge($_POST, $data);
    }
}

try {
    // POST kontrolü
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Sadece POST istekleri kabul edilir');
    }

    // Token kontrolü
    $token = $_POST['token'] ?? '';
    if ($token !== UPLOAD_TOKEN) {
        throw new Exception('Geçersiz güvenlik tokeni');
    }

    $action = $_POST['action'] ?? 'upload';
    
    if ($action === 'delete') {
        handleDeleteRequest();
    } else {
        handleUploadRequest();
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'post_data' => $_POST,
            'files' => $_FILES
        ]
    ]);
    exit();
}

function handleUploadRequest() {
    // Form verilerini al
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $visibility = trim($_POST['visibility'] ?? '');

    // Validasyon
    if (empty($title) || empty($category) || empty($visibility)) {
        throw new Exception('Başlık, kategori ve görünürlük alanları zorunludur');
    }

    // Dosya kontrolü
    if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = 'Dosya yükleme hatası: ';
        switch ($_FILES['pdf_file']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errorMsg .= 'Dosya boyutu çok büyük';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errorMsg .= 'Dosya kısmen yüklendi';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMsg .= 'Dosya seçilmedi';
                break;
            default:
                $errorMsg .= 'Bilinmeyen hata (' . $_FILES['pdf_file']['error'] . ')';
        }
        throw new Exception($errorMsg);
    }

    $file = $_FILES['pdf_file'];
    
    // Dosya boyutu kontrolü
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception('Dosya boyutu 100MB\'dan büyük olamaz. Mevcut boyut: ' . round($file['size']/(1024*1024), 2) . 'MB');
    }

    // MIME type kontrolü
    $allowedTypes = ['application/pdf'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Sadece PDF dosyaları kabul edilir. Algılanan tip: ' . $mimeType);
    }

    // Dosya uzantısı kontrolü
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($fileExtension !== 'pdf') {
        throw new Exception('Dosya uzantısı .pdf olmalıdır');
    }

    // Upload klasörünü oluştur
    $uploadDir = 'uploads/pdfs/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Upload klasörü oluşturulamadı');
        }
    }

    // Benzersiz dosya adı oluştur
    $originalName = basename($file['name']);
    $fileName = time() . '_' . uniqid() . '.pdf';
    $filePath = $uploadDir . $fileName;

    // Dosyayı taşı
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('Dosya sunucuya yüklenemedi');
    }

    // Dosya izinlerini ayarla (güvenlik için)
    chmod($filePath, 0644);

    // Başarılı yanıt
    echo json_encode([
        'success' => true,
        'message' => 'PDF başarıyla yüklendi',
        'data' => [
            'file_name' => $fileName,
            'file_path' => $filePath,
            'original_name' => $originalName,
            'file_size' => $file['size'],
            'upload_date' => date('Y-m-d H:i:s')
        ]
    ]);
}

function handleDeleteRequest() {
    $filePath = $_POST['file_path'] ?? '';
    
    if (empty($filePath)) {
        throw new Exception('Silinecek dosya yolu belirtilmedi');
    }

    // Güvenlik kontrolü - sadece uploads klasörü içindeki dosyalar
    if (strpos($filePath, 'uploads/pdfs/') !== 0) {
        throw new Exception('Geçersiz dosya yolu');
    }

    // Dosya mevcutsa sil
    if (file_exists($filePath)) {
        if (unlink($filePath)) {
            echo json_encode([
                'success' => true,
                'message' => 'Dosya başarıyla silindi'
            ]);
        } else {
            throw new Exception('Dosya silinemedi');
        }
    } else {
        // Dosya zaten yoksa, hata vermek yerine başarılı kabul et
        echo json_encode([
            'success' => true,
            'message' => 'Dosya zaten mevcut değil'
        ]);
    }
}
