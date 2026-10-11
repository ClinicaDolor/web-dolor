<?php

namespace App\Controllers;

use App\Middleware\AuthMiddleware;

class DownloadController{



    public function download($ruta, $archivo){
        (new AuthMiddleware('clinica'))->authPermisos();

        $storagePath = realpath($_SERVER['DOCUMENT_ROOT'] . '/public/storage');
        $relativeDirectory = trim($ruta, '/\\');
        $candidatePath = $storagePath
            ? $storagePath . DIRECTORY_SEPARATOR . $relativeDirectory . DIRECTORY_SEPARATOR . basename($archivo)
            : false;
        $filePath = $candidatePath ? realpath($candidatePath) : false;
        $storagePrefix = $storagePath
            ? rtrim($storagePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            : '';

        if (!$filePath || !is_file($filePath) || strncmp($filePath, $storagePrefix, strlen($storagePrefix)) !== 0) {
            http_response_code(404);
            exit('Archivo no encontrado.');
        }
        
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
