<?php
// Geliştirilmiş hata ayıklamalı upload.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');

// Hata raporlamayı aç
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Debug log fonksiyonu
function debugLog($message, $data = null) {
    $logMessage = "[" . date('Y-m-d H:i:s') . "] DEBUG: " . $message;
    if ($data) {
        $logMessage .= " | Data: " . json_encode($data);
    }
    error_log($logMessage . PHP_EOL, 3, 'upload_debug.log');
    return $logMessage;
}

// Güvenlik tokeni
define('UPLOAD_TOKEN', 'GUVENLIK_TOKENI_12345');

// Maximum dosya boyutu (100MB)
define('MAX_FILE_SIZE', 100 * 1024 * 1024);

debugLog("Script başlatıldı", [
    'method' => $_SERVER['REQUEST_METHOD'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'none',
    'post_data_count' => count($_POST),
    'files_count' => count($_FILES)
]);

// Preflight istekleri için
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    debugLog("OPTIONS isteği işlendi");
    http_response_code(200);
    exit();
}

// POST kontrolü
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debugLog("Geçersiz method", $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Sadece POST istekleri kabul edilir. Mevcut: ' . $_SERVER['REQUEST_METHOD'],
        'debug_info' => [
            'server_method' => $_SERVER['REQUEST_METHOD'],
            'allowed_methods' => ['POST'],
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
    exit();
}

// JSON inputunu işle (eğer varsa)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    if ($data) {
        $_POST = array_merge($_POST, $data);
    }
}

try {
    debugLog("POST verileri alındı", [
        'token_exists' => isset($_POST['token']),
        'action' => $_POST['action'] ?? 'upload',
        'post_keys' => array_keys($_POST),
        'files_keys' => array_keys($_FILES)
    ]);

    // Token kontrolü
    $token = $_POST['token'] ?? '';
    if ($token !== UPLOAD_TOKEN) {
        throw new Exception('Geçersiz güvenlik tokeni: ' . $token);
    }

    $action = $_POST['action'] ?? 'upload';
    
    if ($action === 'delete') {
        handleDeleteRequest();
    } else {
        handleUploadRequest();
    }

} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    debugLog("Hata yakalandı", $errorMessage);
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $errorMessage,
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'none',
            'post_data' => $_POST,
            'files_info' => array_map(function($file) {
                return [
                    'name' => $file['name'] ?? 'N/A',
                    'size' => $file['size'] ?? 0,
                    'error' => $file['error'] ?? 'N/A',
                    'type' => $file['type'] ?? 'N/A'
                ];
            }, $_FILES),
            'server_info' => [
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'max_file_uploads' => ini_get('max_file_uploads')
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
    exit();
}

function handleUploadRequest() {
    debugLog("Upload işlemi başlatılıyor");
    
    // Form verilerini al
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $visibility = trim($_POST['visibility'] ?? '');

    debugLog("Form verileri", [
        'title' => $title,
        'category' => $category,
        'visibility' => $visibility
    ]);

    // Validasyon
    if (empty($title) || empty($category) || empty($visibility)) {
        throw new Exception('Başlık, kategori ve görünürlük alanları zorunludur');
    }

    // Dosya kontrolü
    if (!isset($_FILES['pdf_file'])) {
        debugLog("pdf_file bulunamadı", $_FILES);
        throw new Exception('PDF dosyası gönderilmedi');
    }

    $file = $_FILES['pdf_file'];
    debugLog("Dosya bilgileri", [
        'name' => $file['name'],
        'size' => $file['size'],
        'error' => $file['error'],
        'type' => $file['type'],
        'tmp_name' => $file['tmp_name']
    ]);

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = 'Dosya yükleme hatası: ';
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
                $errorMsg .= 'Dosya boyutu php.ini limitini aşıyor (' . ini_get('upload_max_filesize') . ')';
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $errorMsg .= 'Dosya boyutu form limitini aşıyor';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errorMsg .= 'Dosya kısmen yüklendi';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMsg .= 'Dosya seçilmedi';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $errorMsg .= 'Geçici klasör bulunamadı';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $errorMsg .= 'Dosya diske yazılamadı';
                break;
            case UPLOAD_ERR_EXTENSION:
                $errorMsg .= 'PHP uzantısı dosya yüklemeyi durdurdu';
                break;
            default:
                $errorMsg .= 'Bilinmeyen hata kodu: ' . $file['error'];
        }
        throw new Exception($errorMsg);
    }

    // Dosya boyutu kontrolü
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception('Dosya boyutu 100MB\'dan büyük olamaz. Mevcut boyut: ' . round($file['size']/(1024*1024), 2) . 'MB');
    }

    // MIME type kontrolü
    if (!function_exists('finfo_open')) {
        debugLog("finfo_open fonksiyonu bulunamadı, tip kontrolü atlanıyor");
    } else {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            debugLog("MIME type kontrolü", ['detected' => $mimeType]);
            
            if ($mimeType !== 'application/pdf') {
                throw new Exception('Sadece PDF dosyaları kabul edilir. Algılanan tip: ' . $mimeType);
            }
        }
    }

    // Dosya uzantısı kontrolü
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($fileExtension !== 'pdf') {
        throw new Exception('Dosya uzantısı .pdf olmalıdır. Mevcut: ' . $fileExtension);
    }

    // Upload klasörünü oluştur
    $uploadDir = 'uploads/pdfs/';
    debugLog("Upload klasörü kontrol ediliyor", ['dir' => $uploadDir]);
    
    if (!is_dir($uploadDir)) {
        debugLog("Upload klasörü oluşturuluyor");
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Upload klasörü oluşturulamadı: ' . $uploadDir);
        }
    }

    // Klasör izinlerini kontrol et
    if (!is_writable($uploadDir)) {
        throw new Exception('Upload klasörüne yazma izni yok: ' . $uploadDir);
    }

    // Benzersiz dosya adı oluştur
    $originalName = basename($file['name']);
    $fileName = time() . '_' . uniqid() . '.pdf';
    $filePath = $uploadDir . $fileName;

    debugLog("Dosya taşınıyor", [
        'from' => $file['tmp_name'],
        'to' => $filePath
    ]);

    // Dosyayı taşı
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        $error = error_get_last();
        debugLog("move_uploaded_file hatası", $error);
        throw new Exception('Dosya sunucuya yüklenemedi. Hata: ' . ($error['message'] ?? 'Bilinmeyen hata'));
    }

    // Dosya izinlerini ayarla
    chmod($filePath, 0644);

    debugLog("Upload başarılı", ['file_path' => $filePath]);

    // Başarılı yanıt
    echo json_encode([
        'success' => true,
        'message' => 'PDF başarıyla yüklendi',
        'data' => [
            'file_name' => $fileName,
            'file_path' => $filePath,
            'original_name' => $originalName,
            'file_size' => $file['size'],
            'upload_date' => date('Y-m-d H:i:s'),
            'file_size_mb' => round($file['size'] / (1024 * 1024), 2)
        ]
    ]);
}

function handleDeleteRequest() {
    debugLog("Delete işlemi başlatılıyor");
    
    $filePath = $_POST['file_path'] ?? '';
    
    if (empty($filePath)) {
        throw new Exception('Silinecek dosya yolu belirtilmedi');
    }

    // Güvenlik kontrolü - sadece uploads klasörü içindeki dosyalar
    if (strpos($filePath, 'uploads/pdfs/') !== 0) {
        throw new Exception('Geçersiz dosya yolu: ' . $filePath);
    }

    debugLog("Dosya siliniyor", ['file_path' => $filePath]);

    // Dosya mevcutsa sil
    if (file_exists($filePath)) {
        if (unlink($filePath)) {
            echo json_encode([
                'success' => true,
                'message' => 'Dosya başarıyla silindi'
            ]);
        } else {
            throw new Exception('Dosya silinemedi: ' . $filePath);
        }
    } else {
        // Dosya zaten yoksa, başarılı kabul et
        echo json_encode([
            'success' => true,
            'message' => 'Dosya zaten mevcut değil'
        ]);
    }
}
?>
