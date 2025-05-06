<?php
namespace App\Helpers;
class CalculadoraEdad {
    /**
     * Calcula la edad a partir de la fecha de nacimiento.
     *
     * @param string $fechaNacimiento Fecha en formato 'YYYY-MM-DD'
     * @return int Edad de la persona
     */
    public static function calcularEdad($fechaNacimiento) {
        // Convierte la fecha de nacimiento a timestamp
        $nacimiento = strtotime($fechaNacimiento);
        $hoy = strtotime(date("Y-m-d"));

        // Calcula la diferencia en años
        $edad = date("Y", $hoy) - date("Y", $nacimiento);

        // Ajusta si aún no ha cumplido años en el año actual
        if (date("md", $hoy) < date("md", $nacimiento)) {
            $edad--;
        }

        return $edad;
    }
}

