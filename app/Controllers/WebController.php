<?php

namespace App\Controllers;
use App\Models\BusquedasModel;

class WebController extends BaseController{

    public function index(){
        $data = ['title' => 'Clínica del Dolor y Cuidados Paliativos Hospital Ángeles Lomas'];
        $this->view('/web/index.php', $data);
    }

    public function quienesSomos(){
        $data = ['title' => '¿Quiénes somos?'];
        $this->view('/web/quienes-somos.php', $data);
    }

    public function cursos(){
        $data = ['title' => 'Cursos'];
        $this->view('/web/cursos.php', $data);
    }

    public function queEsDolor(){
        $data = ['title' => '¿Qué es el dolor?'];
        $this->view('/web/que-es-dolor.php', $data);
    }

    public function tiposDolor(){
        $data = ['title' => 'Enfermedades que causan dolor'];
        $this->view('/web/tipos-dolor.php', $data);
    }

    public function dolorAgudo(){
        $data = ['title' => 'Dolor agudo'];
        $this->view('/web/dolor-agudo.php', $data);
    }

    public function dolorCronico(){
        $data = ['title' => 'Dolor crónico'];
        $this->view('/web/dolor-cronico.php', $data);
    }

    public function dolorPerioperatorio(){
        $data = ['title' => 'Dolor perioperatorio'];
        $this->view('/web/dolor-perioperatorio.php', $data);
    }

    public function evaluacionDolor(){
        $data = ['title' => 'Evaluación del dolor'];
        $this->view('/web/evaluacion-dolor.php', $data);
    }

    public function cuidadores(){
        $data = ['title' => 'Cuidadores'];
        $this->view('/web/cuidadores.php', $data);
    }

    public function inyeccionEpidural(){
        $data = ['title' => 'Inyección epidural'];
        $this->view('/web/inyeccion-epidural.php', $data);
    }

    public function neuroestimulacion(){
        $data = ['title' => 'Control del Dolor con Estimulación de la Médula Espinal o Neuroestimulación Espinal (NEE)'];
        $this->view('/web/neuroestimulacion.php', $data);
    }

    public function comoIniciaTratamientoOpioides(){
        $data = ['title' => '¿Cómo se inicia un tratamiento con opioides?'];
        $this->view('/web/como-se-inicia-un-tratamiento-con-opioides.php', $data);
    }

    public function dolorCancer(){
        $data = ['title' => 'Dolor por cáncer'];
        $this->view('/web/dolor-cancer.php', $data);
    }

    public function dolorCancerFrecuencia(){
        $data = ['title' => 'Frecuencia'];
        $this->view('/web/dolor-cancer-frecuencia.php', $data);
    }

    public function dolorCancerCausas(){
        $data = ['title' => 'Causas'];
        $this->view('/web/dolor-cancer-causas.php', $data);
    }

    public function dolorCancerPreguntasMedicoDecidirTratamiento(){
        $data = ['title' => 'Preguntas de su médico para decidir el tratamiento'];
        $this->view('/web/dolor-cancer-preguntas-medico-decidir-tratamiento.php', $data);
    }

    public function seguimientoTratamientoDolor(){
        $data = ['title' => 'Seguimiento del tratamiento del dolor'];
        $this->view('/web/seguimiento-tratamiento-dolor.php', $data);
    }

    public function dolorCancerCincoPrioridades(){
        $data = ['title' => 'Cinco prioridades para el cuidado de un paciente agónico'];
        $this->view('/web/dolor-cancer-cinco-prioridades.php', $data);
    }

    public function dolorCancerEndoscopiaPaliativa(){
        $data = ['title' => 'Endoscopia paliativa'];
        $this->view('/web/dolor-cancer-endoscopia-paliativa.php', $data);
    }

    public function neuralgiaPostherpetica(){
        $data = ['title' => 'Endoscopia paliativa'];
        $this->view('/web/neuralgia-postherpetica.php', $data);
    }

        public function buscarWeb(){

            $query = $_GET['query'] ?? '';
            $model = new BusquedasModel();
            echo $model->getPaginaWeb($query);
            
        }

        public function estrenimiento(){
        $data = ['title' => 'Estreñimiento'];
        $this->view('/web/estrenimiento.php', $data);
        }
    
}