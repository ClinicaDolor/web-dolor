<?php
namespace App\Core;

class HttpMethod
{
    public static function jsonResponse($statusCode, $resultado, $mensaje, $token = '', array $extra = [])
    {
        http_response_code($statusCode);
        return json_encode(array_merge([
            'resultado' => $resultado,
            'mensaje' => $mensaje,
            'token' => $token
        ], $extra));
    }
}
