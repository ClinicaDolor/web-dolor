<?php
namespace App\Controllers;

class BaseController {
    protected function view($viewName, $data = []) {
        // Extraer los datos para que estén disponibles en la vista
        extract($data);

        // Cargar la vista
        require __DIR__ . '/../views/' . $viewName;
    }
}