<?php

namespace App\Controllers;

class DownloadController{



    public function download($ruta, $archivo){
        $uploadPath = $_SERVER['DOCUMENT_ROOT'] . '/public/storage/';

        $filePath = $uploadPath  . $ruta . '/' . basename($archivo);
        
        // Obtener tipo MIME
        $fileMime = mime_content_type($filePath);
        
        // Configurar cabeceras para la descarga
        header("Content-Description: File Transfer");
        header("Content-Type: " . $fileMime);
        header("Content-Disposition: attachment; filename=\"" . basename($filePath) . "\"");
        header("Expires: 0");
        header("Cache-Control: must-revalidate");
        header("Pragma: public");
        header("Content-Length: " . filesize($filePath));
        
        // Leer el archivo y enviarlo al output buffer
        readfile($filePath);
        exit;
    }
}