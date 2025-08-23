<?php
// Güvenli PDF görüntüleme scripti
session_start();

// Hata raporlamayı kapat (production için)
error_reporting(0);
ini_set('display_errors', 0);

try {
    // PDF dosya yolunu al
    $pdfPath = $_GET['file'] ?? '';
    
    if (empty($pdfPath)) {
        throw new Exception('PDF dosyası belirtilmedi');
    }
    
    // Güvenlik kontrolü - sadece uploads/pdfs/ klasöründeki dosyalar
    if (strpos($pdfPath, 'uploads/pdfs/') !== 0) {
        throw new Exception('Geçersiz dosya yolu');
    }
    
    // Dosya mevcutsa
    if (!file_exists($pdfPath)) {
        throw new Exception('PDF dosyası bulunamadı');
    }
    
    // PDF olduğunu kontrol et
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $pdfPath);
    finfo_close($finfo);
    
    if ($mimeType !== 'application/pdf') {
        throw new Exception('Geçersiz dosya tipi');
    }
    
    // Güvenlik başlıkları
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($pdfPath) . '"');
    header('Cache-Control: private, max-age=0, no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Content-Length: ' . filesize($pdfPath));
    
    // Dosyayı çıktı olarak gönder
    readfile($pdfPath);
    exit();
    
} catch (Exception $e) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo "PDF görüntülenemiyor: " . htmlspecialchars($e->getMessage());
    exit();
}
?>
