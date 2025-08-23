<?php
// Geliştirilmiş güvenli PDF görüntüleme scripti
session_start();

// Hata raporlamayı kapat (production için)
error_reporting(0);
ini_set('display_errors', 0);

// Debug log fonksiyonu
function debugLog($message, $data = null) {
    $logMessage = "[" . date('Y-m-d H:i:s') . "] VIEW-PDF: " . $message;
    if ($data) {
        $logMessage .= " | " . json_encode($data);
    }
    error_log($logMessage . PHP_EOL, 3, 'view_pdf_debug.log');
}

try {
    // CORS başlıkları
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    // PDF dosya yolunu al
    $pdfPath = $_GET['file'] ?? '';
    
    debugLog("PDF görüntüleme isteği", [
        'file_param' => $pdfPath,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
        'referer' => $_SERVER['HTTP_REFERER'] ?? 'N/A'
    ]);
    
    if (empty($pdfPath)) {
        throw new Exception('PDF dosyası belirtilmedi');
    }
    
    // Güvenlik kontrolü - sadece uploads/pdfs/ klasöründeki dosyalar
    if (strpos($pdfPath, 'uploads/pdfs/') !== 0) {
        debugLog("Güvenlik ihlali", ['invalid_path' => $pdfPath]);
        throw new Exception('Geçersiz dosya yolu');
    }
    
    // Dosya mevcutsa
    if (!file_exists($pdfPath)) {
        debugLog("Dosya bulunamadı", ['path' => $pdfPath]);
        throw new Exception('PDF dosyası bulunamadı');
    }
    
    // Dosya boyutunu kontrol et
    $fileSize = filesize($pdfPath);
    if ($fileSize === false || $fileSize === 0) {
        debugLog("Dosya boyutu sorunu", ['path' => $pdfPath, 'size' => $fileSize]);
        throw new Exception('PDF dosyası okunamıyor');
    }
    
    // PDF olduğunu kontrol et (varsa)
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mimeType = finfo_file($finfo, $pdfPath);
            finfo_close($finfo);
            
            if ($mimeType !== 'application/pdf') {
                debugLog("Geçersiz dosya tipi", ['detected_type' => $mimeType]);
                throw new Exception('Geçersiz dosya tipi');
            }
        }
    }
    
    debugLog("PDF görüntüleme başarılı", [
        'file' => basename($pdfPath),
        'size' => $fileSize,
        'size_mb' => round($fileSize / (1024 * 1024), 2)
    ]);
    
    // Güvenlik ve önbellekleme başlıkları
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($pdfPath) . '"');
    header('Content-Length: ' . $fileSize);
    
    // Güvenlik başlıkları
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    
    // Önbellekleme kontrolü
    header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Dosyayı parçalar halinde gönder (büyük dosyalar için)
    $handle = fopen($pdfPath, 'rb');
    if ($handle === false) {
        throw new Exception('Dosya açılamadı');
    }
    
    // Dosyayı 8KB parçalar halinde gönder
    while (!feof($handle)) {
        $chunk = fread($handle, 8192);
        if ($chunk === false) {
            break;
        }
        echo $chunk;
        
        // Output buffer'ı temizle
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }
    
    fclose($handle);
    exit();
    
} catch (Exception $e) {
    debugLog("Hata", ['error' => $e->getMessage()]);
    
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    
    echo '<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Görüntülenemiyor</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
        .error-container { background: white; padding: 30px; border-radius: 10px; display: inline-block; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .error-icon { font-size: 48px; color: #e74c3c; margin-bottom: 20px; }
        .error-title { color: #2c3e50; margin-bottom: 10px; }
        .error-message { color: #7f8c8d; margin-bottom: 20px; }
        .back-button { background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; }
        .back-button:hover { background: #2980b9; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">📄❌</div>
        <h2 class="error-title">PDF Görüntülenemiyor</h2>
        <p class="error-message">' . htmlspecialchars($e->getMessage()) . '</p>
        <a href="javascript:history.back()" class="back-button">← Geri Dön</a>
    </div>
</body>
</html>';
    exit();
}
?>
