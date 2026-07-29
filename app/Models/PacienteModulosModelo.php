<?php
namespace App\Models;
use App\Config\Database;

class PacienteModulosModelo{
    private $bd;

    public function __construct(){
    $this->bd = Database::getInstance();
    
    }

    public function agregarComentariosModulo($data){

    $sql = "INSERT INTO pac_historia_clinica_comentario (
    id_modulo,
    id_paciente,
    comentario
    ) VALUES (
    :id_modulo,
    :id_paciente,
    :comentario
    )";
    
    $stmt = $this->bd->prepare($sql);                    
        
    $datos = [
    ':id_modulo' => $data['idModulo'],
    ':id_paciente' => $data['idPaciente'],
    ':comentario' => $data['comentarioModulos']
    ];
    
    if ($stmt->execute($datos)) {
    return array('resultado' => 200,'mensaje' => '¡Se agrego el comentario correctamente!');
    
    } else {
    return array('resultado' => 401,'mensaje' => '¡Error al agregar el comentario la lista!');
    }
    
    }

    public function eliminarComentariosModulo($data){

    $sql = "DELETE FROM pac_historia_clinica_comentario WHERE id = :id_comentario";
    $stmt = $this->bd->prepare($sql);                    
            
    $datos = [
    ':id_comentario' => $data['idComentario']
    ];
        
    if ($stmt->execute($datos)) {
    return array('resultado' => 200,'mensaje' => '¡Se elimino el comentario correctamente!');
        
    } else {
    return array('resultado' => 401,'mensaje' => '¡Error al eliminar el comentario de la lista!');
    }
        
    }


    //---------- VALIDACION STATUS DE MODULO ----------

    public function statusModulo($idPaciente, $idModulo){
    $stmt = $this->bd->query("SELECT id, comentario FROM pac_historia_clinica_comentario WHERE id_modulo = '".$idModulo."' AND id_paciente = '".$idPaciente."' ORDER BY id DESC LIMIT 1");
    $comentarios = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    return $comentarios;
    }
    
    public function comentariosModulos($idPaciente, $idModulo){
    $stmt = $this->bd->query("SELECT comentario FROM pac_historia_clinica_comentario WHERE id_modulo = '".$idModulo."' AND id_paciente = '".$idPaciente."' ORDER BY id DESC LIMIT 1");
    $comentarios = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    $moduloComentario = "Sin comentarios";

    foreach ($comentarios as $comentario): 
    $moduloComentario = $comentario['comentario'];
    $num++;
    endforeach;

    $result = '<strong>Si tiene alguna otra información o comentarios que desee compartir, por favor, indíquelo:</strong>
    <br> '.$moduloComentario.'';

    return $result;
    }

    //---------- COMENTATIOS DE LOS MODULOS ----------
    public function mostrarComentariosModulo($idPaciente, $idRol, $idModulo) {
    $result = '';
    
    $comentarios = $this->statusModulo($idPaciente, $idModulo);
        
    if ($idRol == "Doctor"){
    $moduloComentario = "Sin comentarios";
    $deshabilitar = "disabled";
    }else{


    $deshabilitar = "";
    if (!empty($comentarios)) {
    $num = 1;
    foreach ($comentarios as $comentario): 
    $moduloComentario = $comentario['comentario'];
    $num++;
    endforeach; 
    $deshabilitar = "disabled";
    }else{
    $moduloComentario = "";
    }
    }

    $result .= '
    <textarea class="form-control" id="comentarioModulos" placeholder="Ingresa aquí tu comentario..." style="height: 200px;" '.$deshabilitar.'>'.$moduloComentario.'</textarea>';
    return $result;
    }


    //---------- FINALIZAR MODULO ----------
    public function botonFinalizarModulo($idModulo, $idPaciente, $idRol){
    $result = '';

    $stmt = $this->bd->query("SELECT * FROM pac_historia_clinica_finalizar WHERE id_modulo = '".$idModulo."' AND id_paciente = '".$idPaciente."' ORDER BY id ASC");
    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    if (!empty($preguntas)) {
    $result .= '';
    }else{

    if($idModulo == 2 || $idModulo == 3){
    $result .= '<button class="btn btn-success" onclick="agregarComentario('.$idModulo.', '.$idPaciente.',\''.$idRol.'\')">Finalizar</button>';
    } else{
    $result .= '<button class="btn btn-success" onclick="finalizarModuloPAC('.$idModulo.', '.$idPaciente.')">Finalizar</button>';
    }   
    }

    return $result;
    }

    //---------- FINALIZAR MODULO ----------
    public function finalizarModulos($data){

    $sql = "INSERT INTO pac_historia_clinica_finalizar (
    id_modulo,
    id_paciente
    ) VALUES (
    :id_modulo,
    :id_paciente
    )";
        
    $stmt = $this->bd->prepare($sql);                    
            
    $datos = [
    ':id_modulo' => $data['idModulo'],
    ':id_paciente' => $data['idPaciente']
    ];
        
    if ($stmt->execute($datos)) {
    return array('resultado' => 200,'mensaje' => '¡Se finalizo el modulo correctamente!');
        
    } else {
    return array('resultado' => 401,'mensaje' => '¡Error al finalizar el modulo!');
    }
        
    }

 
    //-------------------- PORCENTAJES DE LOS MODULOS --------------------
    public function porcentajeModulo1($idPaciente){
    $result = "";

    $stmt = $this->bd->query("SELECT 
    id,
    nombres,
    ROUND((
    (
    IF(nombres IS NOT NULL AND nombres != '', 1, 0) +
    IF(apellido_paterno IS NOT NULL AND apellido_paterno != '', 1, 0) +
    IF(edad IS NOT NULL AND edad != '', 1, 0) +
    IF(sexo IS NOT NULL AND sexo != '', 1, 0) +
    IF(estado_civil IS NOT NULL AND estado_civil != '', 1, 0) +
    IF(fecha_nacimiento IS NOT NULL AND fecha_nacimiento != '', 1, 0) +
    IF(curp IS NOT NULL AND curp != '', 1, 0) +
    IF(lugar_origen IS NOT NULL AND lugar_origen != '', 1, 0) +
    IF(lugar_residencia IS NOT NULL AND lugar_residencia != '', 1, 0) +
    IF(ocupacion IS NOT NULL AND ocupacion != '', 1, 0) +
    IF(num_hijos IS NOT NULL AND num_hijos != '', 1, 0) +
    IF(edad_hijos IS NOT NULL AND edad_hijos != '', 1, 0) + 
    IF(motivo_atencion IS NOT NULL AND motivo_atencion != '', 1, 0)
    ) / 13 * 100
    ), 0) AS porcentaje_cumplimiento
    FROM pc_paciente WHERE id = '".$idPaciente."'");
    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($preguntas as $index => $pregunta) :
    $porcentajeCumpliiento = $pregunta['porcentaje_cumplimiento'];
    endforeach;
    
    return $porcentajeCumpliiento;
    }

    public function porcentajeModulo2($idPaciente){
    $result = "";

    $stmt = $this->bd->query("SELECT 
    id_paciente,
    COUNT(*) AS total_enfermedades,

    SUM(
    CASE 
    WHEN detalle = 'No' AND enfermedad IS NOT NULL AND enfermedad != ''
    THEN 1

    WHEN detalle = 'Sí' AND enfermedad = 'Diabetes Mellitus'
    AND especificar IS NOT NULL AND especificar != ''
    AND detalle IS NOT NULL AND detalle != ''
    AND enfermedad IS NOT NULL AND enfermedad != ''
    THEN 1

    WHEN detalle = 'Sí' AND enfermedad != 'Diabetes Mellitus'
    AND especificar IS NOT NULL AND especificar != ''
    AND enfermedad IS NOT NULL AND enfermedad != ''
    THEN 1

    ELSE 0
    END

    ) AS enfermedades_validas,

    ROUND((
    SUM(
    CASE 
    WHEN detalle = 'No' AND enfermedad IS NOT NULL AND enfermedad != ''
    THEN 1

    WHEN detalle = 'Sí' AND enfermedad = 'Diabetes Mellitus'
    AND especificar IS NOT NULL AND especificar != ''
    AND detalle IS NOT NULL AND detalle != ''
    AND enfermedad IS NOT NULL AND enfermedad != ''
    THEN 1

    WHEN detalle = 'Sí' AND enfermedad != 'Diabetes Mellitus'
    AND especificar IS NOT NULL AND especificar != ''
    AND enfermedad IS NOT NULL AND enfermedad != ''
    THEN 1

    ELSE 0
    END
    ) / COUNT(*) * 100
    ), 0) AS porcentaje_cumplimiento
    FROM pc_antecedentes_familiares
    WHERE id_paciente = '".$idPaciente."'");

    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    foreach ($preguntas as $index => $pregunta) :
    $porcentajeCumpliiento = $pregunta['porcentaje_cumplimiento'];
    endforeach;
    

    return $porcentajeCumpliiento;
    }

    public function porcentajeModulo3($idPaciente){
    $result = "";

    $stmt = $this->bd->query("
    SELECT 
        resumen.id_tema,
        resumen.tema,
        resumen.cumplimiento,
        resumen.cumplimiento * 100 AS porcentaje_cumplimiento_tema,

        (
            SELECT ROUND(AVG(cumplimiento) * 100)
            FROM (
                SELECT 
                    t.id AS id_tema,
                    CASE 
                        -- Si NO existe pregunta tipo 'Select', entonces todas las preguntas deben estar llenas
                        WHEN SUM(CASE WHEN p.tipo = 'Select' THEN 1 ELSE 0 END) = 0 THEN
                            CASE 
                                WHEN COUNT(*) = SUM(CASE WHEN r.respuesta IS NOT NULL AND r.respuesta != '' THEN 1 ELSE 0 END)
                                THEN 1 ELSE 0
                            END
                        
                        -- Si existe tipo 'Select' y la respuesta es 'No', cuenta como válido
                        WHEN MAX(CASE WHEN p.tipo = 'Select' THEN r.respuesta END) = 'No' THEN 1
                        
                        -- Si es 'Sí', todas deben estar llenas
                        WHEN MAX(CASE WHEN p.tipo = 'Select' THEN r.respuesta END) = 'Sí'
                            AND COUNT(*) = SUM(CASE WHEN r.respuesta IS NOT NULL AND r.respuesta != '' THEN 1 ELSE 0 END)
                        THEN 1
                        
                        ELSE 0
                    END AS cumplimiento
                FROM pac_temas_modulo_3 t
                INNER JOIN pac_preguntas_modulo_3 p ON p.id_tema = t.id
                INNER JOIN pac_respuestas_paciente_modulo_3 r ON r.id_pregunta = p.id
                WHERE r.id_paciente = '".$idPaciente."'
                GROUP BY t.id
            ) AS sub
        ) AS porcentaje_general

    FROM (
        SELECT 
            t.id AS id_tema,
            t.tema,
            CASE 
                WHEN SUM(CASE WHEN p.tipo = 'Select' THEN 1 ELSE 0 END) = 0 THEN
                    CASE 
                        WHEN COUNT(*) = SUM(CASE WHEN r.respuesta IS NOT NULL AND r.respuesta != '' THEN 1 ELSE 0 END)
                        THEN 1 ELSE 0
                    END
                WHEN MAX(CASE WHEN p.tipo = 'Select' THEN r.respuesta END) = 'No' THEN 1
                WHEN MAX(CASE WHEN p.tipo = 'Select' THEN r.respuesta END) = 'Sí'
                    AND COUNT(*) = SUM(CASE WHEN r.respuesta IS NOT NULL AND r.respuesta != '' THEN 1 ELSE 0 END)
                THEN 1
                ELSE 0
            END AS cumplimiento
        FROM pac_temas_modulo_3 t
        INNER JOIN pac_preguntas_modulo_3 p ON p.id_tema = t.id
        INNER JOIN pac_respuestas_paciente_modulo_3 r ON r.id_pregunta = p.id
        WHERE r.id_paciente = '".$idPaciente."'
        GROUP BY t.id, t.tema
    ) AS resumen;
    ");

    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    $totalTemas = count($preguntas);
    $sumaPorcentajes = 0;

    foreach ($preguntas as $pregunta) {
    $sumaPorcentajes += $pregunta['porcentaje_cumplimiento_tema'];
    }
    
    $porcentajeCumpliiento = $totalTemas > 0 ? round($sumaPorcentajes / $totalTemas, 2) : 0;

    return $porcentajeCumpliiento;

    }


    public function porcentajeModulo4($idPaciente){
    $result = "";

    $stmt = $this->bd->query("SELECT 
    id_paciente,
    COUNT(*) AS total_cirugias,

    SUM(
    CASE 
    WHEN fecha IS NOT NULL AND fecha != '0000-00-00'
    AND cirugia IS NOT NULL AND cirugia != ''
    AND observaciones IS NOT NULL AND observaciones != ''
    THEN 1
    ELSE 0
    END
    ) AS cirugias_validas,

    ROUND((
    CASE 
    WHEN COUNT(*) = 0 THEN 100
    ELSE (
    SUM(
    CASE 
    WHEN fecha IS NOT NULL AND fecha != '0000-00-00'
    AND cirugia IS NOT NULL AND cirugia != ''
    AND observaciones IS NOT NULL AND observaciones != ''
    THEN 1
    ELSE 0
    END
    ) / COUNT(*) * 100
    )
    END
    ), 0) AS porcentaje_cumplimiento
    FROM pc_antecedentes_quirurgicos
    WHERE id_paciente = '".$idPaciente."'");

    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    foreach ($preguntas as $index => $pregunta) :
    $porcentajeCumpliiento = $pregunta['porcentaje_cumplimiento'];
    endforeach;
    
    return $porcentajeCumpliiento;
    
    }

    //--------------------
    public function porcentajeModulo5($idPaciente){
    $result = "";
    $porcentajeCumpliiento = 0;

    $total1 = $this->porcentajeModulo5Seccion1($idPaciente);
    $total2 = $this->porcentajeModulo5Seccion2($idPaciente);

    $porcentajeCumpliiento = ($total1 + $total2) / 2;

    return $porcentajeCumpliiento;
   
    }

    public function porcentajeModulo5Seccion1($idPaciente){
    $result = "";
    
    $stmt = $this->bd->query("
    SELECT 
    resumen.id_tema,
    resumen.tema,
    resumen.cumplimiento,
    resumen.cumplimiento * 100 AS porcentaje_cumplimiento_tema
    FROM (
    SELECT 
    t.id AS id_tema,
    t.tema,
    CASE 
    WHEN SUM(CASE WHEN p.tipo = 'Select' THEN 1 ELSE 0 END) = 0 THEN
    CASE 
    WHEN COUNT(*) = SUM(CASE WHEN r.respuesta IS NOT NULL AND r.respuesta != '' THEN 1 ELSE 0 END)
    THEN 1 ELSE 0
    END
    WHEN MAX(CASE WHEN p.tipo = 'Select' THEN r.respuesta END) = 'No' THEN 1
    WHEN MAX(CASE WHEN p.tipo = 'Select' THEN r.respuesta END) = 'Sí'
    AND COUNT(*) = SUM(CASE WHEN r.respuesta IS NOT NULL AND r.respuesta != '' THEN 1 ELSE 0 END)
    THEN 1
    ELSE 0
    END AS cumplimiento
    FROM pac_temas_modulo_5 t
    INNER JOIN pac_preguntas_modulo_5 p ON p.id_tema = t.id
    INNER JOIN pac_respuestas_paciente_modulo_5 r ON r.id_pregunta = p.id
    WHERE r.id_paciente = '".$idPaciente."'
    GROUP BY t.id, t.tema
    ) AS resumen");
    
    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    $totalTemas = count($preguntas);
    $sumaPorcentajes = 0;
    
    foreach ($preguntas as $pregunta) {
    $sumaPorcentajes += $pregunta['porcentaje_cumplimiento_tema'];
    }
        
    $porcentajeCumpliiento = $totalTemas > 0 ? round($sumaPorcentajes / $totalTemas, 2) : 0;
    
    return $porcentajeCumpliiento;

    }

    public function porcentajeModulo5Seccion2($idPaciente){
    $result = "";
        
    $stmt = $this->bd->query("
    SELECT 
    COUNT(*) AS total_enfermedades,
    SUM(
    CASE 
    -- Si respuesta está vacía o NULL, no se cumple
    WHEN r.respuesta IS NULL OR r.respuesta = '' THEN 0

    -- Si es 'Diabetes Mellitus' o 'Enfermedad pulmonar' y respuesta es 'Si', deben estar llenos ambos campos
    WHEN e.descripcion IN ('Diabetes Mellitus', 'Enfermedad pulmonar') AND r.respuesta = 'Si'
    AND (r.year_diagnostico IS NULL OR r.year_diagnostico = ''
    OR r.tipo IS NULL OR r.tipo = '') THEN 0

    -- Si es 'Diabetes Mellitus' o 'Enfermedad pulmonar' y todos los campos están llenos
    WHEN e.descripcion IN ('Diabetes Mellitus', 'Enfermedad pulmonar') AND r.respuesta = 'Si'
    AND r.year_diagnostico IS NOT NULL AND r.year_diagnostico != ''
    AND r.tipo IS NOT NULL AND r.tipo != '' THEN 1

    -- Si respuesta es 'Si', debe tener year_diagnostico
    WHEN r.respuesta = 'Si'
    AND r.year_diagnostico IS NOT NULL AND r.year_diagnostico != '' THEN 1

    -- Si respuesta es 'No', se considera válido sin importar otros campos
    WHEN r.respuesta = 'No' THEN 1

    ELSE 0
    END
    ) AS enfermedades_validas,
    ROUND((
    CASE 
    WHEN COUNT(*) = 0 THEN 100
    ELSE SUM(
    CASE 
    WHEN r.respuesta IS NULL OR r.respuesta = '' THEN 0
    WHEN e.descripcion IN ('Diabetes Mellitus', 'Enfermedad pulmonar') AND r.respuesta = 'Si'
    AND (r.year_diagnostico IS NULL OR r.year_diagnostico = ''
    OR r.tipo IS NULL OR r.tipo = '') THEN 0
    WHEN e.descripcion IN ('Diabetes Mellitus', 'Enfermedad pulmonar') AND r.respuesta = 'Si'
    AND r.year_diagnostico IS NOT NULL AND r.year_diagnostico != ''
    AND r.tipo IS NOT NULL AND r.tipo != '' THEN 1
    WHEN r.respuesta = 'Si'
    AND r.year_diagnostico IS NOT NULL AND r.year_diagnostico != '' THEN 1
    WHEN r.respuesta = 'No' THEN 1
    ELSE 0
    END
    ) * 100 / COUNT(*)
    END
    ), 0) AS porcentaje_cumplimiento
    FROM pac_enfermedades_respuestas_modulo_5 r
    INNER JOIN pac_enfermedades_modulo_5 e ON r.id_enfermedad = e.id
    WHERE r.id_paciente = '".$idPaciente."'");

    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($preguntas as $pregunta) {
    $porcentajeCumpliiento = $pregunta['porcentaje_cumplimiento'];
    }
            
    return $porcentajeCumpliiento;

    }
    
    public function porcentajeModulo6($idPaciente){
    $result = "";

    $stmt = $this->bd->query("SELECT 
    id_paciente,
    COUNT(*) AS total_medicacion,

    SUM(
    CASE 
    WHEN medicamento IS NOT NULL AND medicamento != ''
    AND descripcion IS NOT NULL AND descripcion != ''
    AND tiempo_uso IS NOT NULL AND tiempo_uso != ''
    AND dosis IS NOT NULL AND dosis != ''
    AND medico IS NOT NULL AND medico != ''
    THEN 1
    ELSE 0
    END
    ) AS medicacion_valida,

    ROUND((
    CASE 
    WHEN COUNT(*) = 0 THEN 100
    ELSE (
    SUM(
    CASE 
    WHEN medicamento IS NOT NULL AND medicamento != ''
    AND descripcion IS NOT NULL AND descripcion != ''
    AND tiempo_uso IS NOT NULL AND tiempo_uso != ''
    AND dosis IS NOT NULL AND dosis != ''
    AND medico IS NOT NULL AND medico != ''
    THEN 1
    ELSE 0
    END
    ) / COUNT(*) * 100
    )
    END
    ), 0) AS porcentaje_cumplimiento
    FROM pac_medicacion_actual_modulo_6
    WHERE id_paciente = '".$idPaciente."'");

    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    foreach ($preguntas as $index => $pregunta) :
    $porcentajeCumpliiento = $pregunta['porcentaje_cumplimiento'];
    endforeach;
    
    return $porcentajeCumpliiento;
  
    }

    
    public function porcentajeModulo7($idPaciente){
    $result = "";

$stmt = $this->bd->query("
SELECT 
    COUNT(*) AS total_preguntas,
    SUM(
        CASE 
            WHEN r.respuesta IS NULL OR r.respuesta = '' THEN 0
            WHEN r.respuesta = 'No' THEN 1
            WHEN r.respuesta = 'Sí'
                 AND r.dosis IS NOT NULL AND r.dosis != ''
                 AND r.resultados IS NOT NULL AND r.resultados != ''
                 AND r.consumo IS NOT NULL AND r.consumo != ''
                 AND (
                     (r.resultados = 'Tuve efectos adversos' AND r.descripcion IS NOT NULL AND r.descripcion != '')
                     OR (r.resultados != 'Tuve efectos adversos')
                 )
            THEN 1
            ELSE 0
        END
    ) AS preguntas_validas,
    ROUND((
        CASE 
            WHEN COUNT(*) = 0 THEN 100
            ELSE SUM(
                CASE 
                    WHEN r.respuesta IS NULL OR r.respuesta = '' THEN 0
                    WHEN r.respuesta = 'No' THEN 1
                    WHEN r.respuesta = 'Sí'
                         AND r.dosis IS NOT NULL AND r.dosis != ''
                         AND r.resultados IS NOT NULL AND r.resultados != ''
                         AND r.consumo IS NOT NULL AND r.consumo != ''
                         AND (
                             (r.resultados = 'Tuve efectos adversos' AND r.descripcion IS NOT NULL AND r.descripcion != '')
                             OR (r.resultados != 'Tuve efectos adversos')
                         )
                    THEN 1
                    ELSE 0
                END
            ) * 100 / COUNT(*)
        END
    ), 0) AS porcentaje_cumplimiento
FROM pac_respuestas_paciente_modulo_7 r
INNER JOIN pac_preguntas_modulo_7 p ON r.id_pregunta = p.id
INNER JOIN pac_temas_modulo_7 t ON p.id_tema = t.id
WHERE r.id_paciente = '".$idPaciente."'
");


    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    foreach ($preguntas as $index => $pregunta) :
    $porcentajeCumpliiento = $pregunta['porcentaje_cumplimiento'];
    endforeach;
    
    return $porcentajeCumpliiento;
       
    }


    //-------------------- MODULO 8 --------------------
    public function porcentajeModulo8($idPaciente){
    $result = "";
    $porcentajeCumpliiento = 0;
    
    $total1 = $this->porcentajeModulo8Seccion1($idPaciente);
    $total2 = $this->porcentajeModulo8Seccion2($idPaciente);
    
    $porcentajeCumpliiento = ($total1 + $total2) / 2;
    
    return $porcentajeCumpliiento;      
    }

    public function porcentajeModulo8Seccion1($idPaciente){
    $result = "";
    
    $stmt = $this->bd->query("
    SELECT 
        COUNT(*) AS total_procedimientos,
        SUM(
            CASE 
                WHEN procedimiento IS NOT NULL AND procedimiento != ''
                AND fecha IS NOT NULL AND fecha != ''
                AND resultados IS NOT NULL AND resultados != ''
                AND (
                    (resultados = 'Tuve efectos adversos' AND descripcion IS NOT NULL AND descripcion != '')
                    OR (resultados != 'Tuve efectos adversos')
                )
                THEN 1
                ELSE 0
            END
        ) AS procedimientos_validos,
        ROUND(
            CASE 
                WHEN COUNT(*) = 0 THEN 100
                ELSE (
                    SUM(
                        CASE 
                            WHEN procedimiento IS NOT NULL AND procedimiento != ''
                            AND fecha IS NOT NULL AND fecha != ''
                            AND resultados IS NOT NULL AND resultados != ''
                            AND (
                                (resultados = 'Tuve efectos adversos' AND descripcion IS NOT NULL AND descripcion != '')
                                OR (resultados != 'Tuve efectos adversos')
                            )
                            THEN 1
                            ELSE 0
                        END
                    ) * 100.0 / COUNT(*)
                )
            END
        , 0) AS porcentaje_cumplimiento
    FROM pac_procedimientos_dolor_modulo_8
    WHERE id_paciente = '".$idPaciente."'
    ");
    
    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($preguntas as $pregunta) {
    $porcentaje_cumplimiento = $pregunta['porcentaje_cumplimiento'];
    }
    
    return $porcentaje_cumplimiento;

    }


    public function porcentajeModulo8Seccion2($idPaciente){
    $result = "";
    
    $stmt = $this->bd->query("
    SELECT 
        COUNT(*) AS total_tratamientos,
        SUM(
            CASE 
                WHEN utilizo = 'No' THEN 1
                WHEN utilizo = 'Si' 
                     AND resultado IS NOT NULL AND resultado != ''
                     AND (
                         (resultado = 'Tuve efectos adversos' AND descripcion IS NOT NULL AND descripcion != '')
                         OR (resultado != 'Tuve efectos adversos')
                     )
                THEN 1
                ELSE 0
            END
        ) AS tratamientos_validos,
        ROUND(
            CASE 
                WHEN COUNT(*) = 0 THEN 100
                ELSE (
                    SUM(
                        CASE 
                            WHEN utilizo = 'No' THEN 1
                            WHEN utilizo = 'Si' 
                                 AND resultado IS NOT NULL AND resultado != ''
                                 AND (
                                     (resultado = 'Tuve efectos adversos' AND descripcion IS NOT NULL AND descripcion != '')
                                     OR (resultado != 'Tuve efectos adversos')
                                 )
                            THEN 1
                            ELSE 0
                        END
                    ) * 100.0 / COUNT(*)
                )
            END
        , 0) AS porcentaje_cumplimiento
    FROM pac_respuestas_paciente_modulo_8 
    INNER JOIN pac_tratamientos_dolor_modulo_8 
        ON pac_respuestas_paciente_modulo_8.id_tratamiento = pac_tratamientos_dolor_modulo_8.id
    WHERE pac_respuestas_paciente_modulo_8.id_paciente = '".$idPaciente."'
    ");
    
    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    foreach ($preguntas as $pregunta) {
    $porcentaje_cumplimiento = $pregunta['porcentaje_cumplimiento'];
    }
            
    return $porcentaje_cumplimiento;
    }


    public function porcentajeModulo9($idPaciente){
    $result = "";

    $stmt = $this->bd->query("SELECT 
    id_paciente,
    COUNT(*) AS total_registros,

    SUM(
    CASE 
    WHEN dolor = 'No' THEN 1
    WHEN dolor = 'Sí'
    AND tiempo_dolor IS NOT NULL AND tiempo_dolor != ''
    AND descripcion IS NOT NULL AND descripcion != ''
    AND incremento IS NOT NULL AND incremento != ''
    THEN 1
    ELSE 0
    END
    ) AS registros_validos,

    ROUND((
    CASE 
    WHEN COUNT(*) = 0 THEN 100
    ELSE (
    SUM(
    CASE 
    WHEN dolor = 'No' THEN 1
    WHEN dolor = 'Sí' 
    AND tiempo_dolor IS NOT NULL AND tiempo_dolor != ''
    AND descripcion IS NOT NULL AND descripcion != ''
    AND incremento IS NOT NULL AND incremento != ''
    THEN 1
    ELSE 0
    END
    ) / COUNT(*) * 100
    )
    END
    ), 0) AS porcentaje_cumplimiento

    FROM pc_paciente_evaluacion_dolor
    WHERE id_paciente = '".$idPaciente."';
    ");


    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    foreach ($preguntas as $index => $pregunta) :
    $porcentajeCumpliiento = $pregunta['porcentaje_cumplimiento'];
    endforeach;
    
    return $porcentajeCumpliiento;   
    }

    public function editarContenidoEditor($data){

    $idPaciente = preg_replace('/[^0-9]/', '', $data['idPaciente'] ?? '');
    $idTema = preg_replace('/[^a-zA-Z0-9_\-]/', '', $data['idTema'] ?? '');
    $contenido = $data['contenido'] ?? '';
    $directorio = $_SERVER['DOCUMENT_ROOT'] . "/public/assets/notas-paciente/paciente_$idPaciente";
    if (!is_dir($directorio)) mkdir($directorio, 0777, true);

    $archivo = "$directorio/$idTema.html";

    if (file_put_contents($archivo, $contenido) !== false) {
    return array('resultado' => 200,'mensaje' => '¡Se actualizo la informacion correctamente!');
    } else {
    return array('resultado' => 401,'mensaje' => '¡Error al actualizar la información!');
    }

    }



    public function contenidoEditorPAC($idPaciente, $idTema){
    $resultado = "";

    $idPacienteDOC = preg_replace('/[^0-9]/', '', $idPaciente ?? '');
    $idTemaDOC = preg_replace('/[^a-zA-Z0-9_\-]/', '', $idTema ?? '');
    $nombreArchivo = $idTemaDOC . ".html";

    $rutaArchivo = $_SERVER['DOCUMENT_ROOT'] . "/public/assets/notas-paciente/paciente_$idPacienteDOC/$nombreArchivo";

    $contenidoEditor = file_exists($rutaArchivo)
    ? file_get_contents($rutaArchivo)
    : '';

    return $contenidoEditor;

    }

    }