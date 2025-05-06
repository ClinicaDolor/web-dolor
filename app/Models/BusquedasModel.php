<?php
namespace App\Models;
use App\Config\Database;
class BusquedasModel{

    private $bd;
    public function __construct(){
        $this->bd = Database::getInstance();
    }

    public function getPacientes($query){

        $sql = "SELECT id, nombre_completo AS nombre FROM pc_paciente WHERE nombre_completo LIKE :query LIMIT 20";
        $stmt = $this->bd->prepare($sql);
        $stmt->execute(['query' => "%{$query}%"]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);

    }

    public function getPaginaWeb($query){

        $sql = "SELECT * FROM dcp_buscador WHERE titulo LIKE :query LIMIT 20";
        $stmt = $this->bd->prepare($sql);
        $stmt->execute(['query' => "%{$query}%"]);
        $registros = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $contenido = '';
        $contenido .= '<div class="bg-white fs-4 fw-light" style="border-bottom: 4px solid #129f68">';
        $contenido .= '<div class="text-end">';
        $contenido .= '<span class="badge rounded-pill bg-danger m-2 fw-light" style="cursor: pointer;" onclick="Limpiar()">X</span>';
        $contenido .= '</div>';

        $contenido .= '<ul class="list-group list-group-flush">';
        if (count($registros) > 0) {
            foreach ($registros as $data):
                $contenido .= '<a class="text-secondary" href="'.SERVIDOR.$data['url'].'" style="text-decoration: none;"><li class="list-group-item list-group-item-action">'.$data['titulo'].'</li></a>';
            endforeach;  
        }else{
        $contenido .= '<li class="list-group-item text-center text-secondary"><small>No se encontró información</small></li>';
        }
        $contenido .= '</ul>';
        $contenido .= '</div>';

        return $contenido;

    }


}