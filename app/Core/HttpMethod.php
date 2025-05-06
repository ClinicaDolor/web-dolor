<?php
namespace App\Core;

class HttpMethod
{
    public static function jsonResponse($statusCode, $resultado, $mensaje, $token = '')
    {
        http_response_code($statusCode);
        return json_encode([
            'resultado' => $resultado,
            'mensaje' => $mensaje,
            'token' => $token
        ]);
    }
}