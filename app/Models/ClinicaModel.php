<?php
namespace App\Models;

use App\Config\Database;
use App\Core\PasswordHelper;
class ClinicaModel{

    private $bd;
    public function __construct(){
        $this->bd = Database::getInstance();
    }

    public function countPacientes($idClinica){

    $stmt = $this->bd->prepare("SELECT COUNT(*) as total FROM pc_paciente WHERE id_clinica = :id_clinica");
    $stmt->execute(['id_clinica' => $idClinica]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    return $row['total'];

    }

    public function getPacientesByClinica($idClinica): array
    {
        $stmt = $this->bd->prepare("SELECT
            id,
            fecha_alta,
            nombres,
            apellido_paterno,
            apellido_materno,
            edad,
            sexo,
            fecha_nacimiento
        FROM pc_paciente
        WHERE id_clinica = :id_clinica
        ORDER BY fecha_alta DESC, id DESC");
        $stmt->execute(['id_clinica' => $idClinica]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene los indicadores de lectura del dashboard para una sola clínica.
     * Cada registro clínico se limita mediante la relación con pc_paciente;
     * no se aceptan identificadores de clínica provenientes de la petición.
     */
    public function getDashboardSummary($idClinica): array
    {
        $idClinica = filter_var($idClinica, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($idClinica === false) {
            throw new \InvalidArgumentException('Invalid clinic context.');
        }

        $queries = [
            'total_pacientes' => "SELECT COUNT(*)
                FROM pc_paciente
                WHERE id_clinica = :id_clinica",
            'total_notas' => "SELECT COUNT(*)
                FROM nota_subsecuente n
                INNER JOIN pc_paciente p ON p.id = n.id_paciente
                WHERE p.id_clinica = :id_clinica",
            'total_laboratorios' => "SELECT COUNT(*)
                FROM laboratorio l
                INNER JOIN pc_paciente p ON p.id = l.id_paciente
                WHERE p.id_clinica = :id_clinica",
            'total_recetas' => "SELECT COUNT(*)
                FROM receta_medica r
                INNER JOIN pc_paciente p ON p.id = r.id_paciente
                WHERE p.id_clinica = :id_clinica",
            'total_cofepris' => "SELECT COUNT(*)
                FROM cofepris c
                INNER JOIN pc_paciente p ON p.id = c.id_paciente
                WHERE p.id_clinica = :id_clinica",
        ];

        $summary = [];

        foreach ($queries as $key => $sql) {
            $stmt = $this->bd->prepare($sql);
            $stmt->execute([':id_clinica' => $idClinica]);
            $summary[$key] = (int) $stmt->fetchColumn();
        }

        $summary['promedio_historia_clinica'] = $this->getAverageHistoriaClinica($idClinica);

        return $summary;
    }

    /**
     * Obtiene los conteos de navegación del expediente para un solo paciente.
     *
     * El conjunto contado coincide con los listados existentes: todos los
     * registros del módulo para el paciente, incluidos PIN expirados. La
     * relación con pc_paciente aplica además el aislamiento de clínica.
     */
    public function getPatientModuleCounts($idPaciente, $idClinica): array
    {
        $idPaciente = filter_var($idPaciente, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $idClinica = filter_var($idClinica, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        $counts = [
            'pin' => 0,
            'notas' => 0,
            'laboratorio' => 0,
            'recetas' => 0,
            'cofepris' => 0,
        ];

        if ($idPaciente === false || $idClinica === false) {
            return $counts;
        }

        $queries = [
            'pin' => "SELECT COUNT(*)
                FROM pc_paciente p
                INNER JOIN pc_paciente_acceso a ON a.paciente_id = p.id
                WHERE p.id = :id_paciente AND p.id_clinica = :id_clinica",
            'notas' => "SELECT COUNT(*)
                FROM nota_subsecuente n
                INNER JOIN pc_paciente p ON p.id = n.id_paciente
                WHERE p.id = :id_paciente AND p.id_clinica = :id_clinica",
            'laboratorio' => "SELECT COUNT(*)
                FROM laboratorio l
                INNER JOIN pc_paciente p ON p.id = l.id_paciente
                WHERE p.id = :id_paciente AND p.id_clinica = :id_clinica",
            'recetas' => "SELECT COUNT(*)
                FROM receta_medica r
                INNER JOIN pc_paciente p ON p.id = r.id_paciente
                WHERE p.id = :id_paciente AND p.id_clinica = :id_clinica",
            'cofepris' => "SELECT COUNT(*)
                FROM cofepris c
                INNER JOIN pc_paciente p ON p.id = c.id_paciente
                WHERE p.id = :id_paciente AND p.id_clinica = :id_clinica",
        ];

        foreach ($queries as $key => $sql) {
            $stmt = $this->bd->prepare($sql);
            $stmt->execute([
                ':id_paciente' => $idPaciente,
                ':id_clinica' => $idClinica,
            ]);
            $counts[$key] = (int) $stmt->fetchColumn();
        }

        return $counts;
    }

    /**
     * Promedia el avance canónico de los nueve módulos por paciente.
     * Las consultas son agregadas por módulo para evitar N+1 consultas.
     * Los valores por defecto replican la semántica actual de
     * PacienteModulosModelo::porcentajeModulo1..9().
     */
    private function getAverageHistoriaClinica(int $idClinica): ?float
    {
        $queries = [
            // Módulo 1: ficha de identificación.
            "SELECT AVG(ROUND((
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
            ) / 13 * 100, 0))
            FROM pc_paciente
            WHERE id_clinica = :id_clinica",

            // Módulo 2: antecedentes familiares; paciente sin filas equivale a 0.
            "SELECT AVG(COALESCE(m.porcentaje, 0))
            FROM pc_paciente p
            LEFT JOIN (
                SELECT id_paciente,
                    ROUND(SUM(CASE
                        WHEN detalle = 'No' AND enfermedad IS NOT NULL AND enfermedad != '' THEN 1
                        WHEN detalle = 'Sí' AND enfermedad = 'Diabetes Mellitus'
                            AND especificar IS NOT NULL AND especificar != ''
                            AND detalle IS NOT NULL AND detalle != ''
                            AND enfermedad IS NOT NULL AND enfermedad != '' THEN 1
                        WHEN detalle = 'Sí' AND enfermedad != 'Diabetes Mellitus'
                            AND especificar IS NOT NULL AND especificar != ''
                            AND enfermedad IS NOT NULL AND enfermedad != '' THEN 1
                        ELSE 0 END) / COUNT(*) * 100, 0) AS porcentaje
                FROM pc_antecedentes_familiares
                GROUP BY id_paciente
            ) m ON m.id_paciente = p.id
            WHERE p.id_clinica = :id_clinica",

            // Módulo 3: promedio de cumplimiento de temas; sin respuestas equivale a 0.
            "SELECT AVG(COALESCE(m.porcentaje, 0))
            FROM pc_paciente p
            LEFT JOIN (
                SELECT x.id_paciente, ROUND(AVG(x.cumplimiento) * 100, 2) AS porcentaje
                FROM (
                    SELECT r.id_paciente, t.id AS id_tema,
                        CASE
                            WHEN SUM(CASE WHEN q.tipo = 'Select' THEN 1 ELSE 0 END) = 0 THEN
                                CASE WHEN COUNT(*) = SUM(CASE WHEN r.respuesta IS NOT NULL AND r.respuesta != '' THEN 1 ELSE 0 END)
                                    THEN 1 ELSE 0 END
                            WHEN MAX(CASE WHEN q.tipo = 'Select' THEN r.respuesta END) = 'No' THEN 1
                            WHEN MAX(CASE WHEN q.tipo = 'Select' THEN r.respuesta END) = 'Sí'
                                AND COUNT(*) = SUM(CASE WHEN r.respuesta IS NOT NULL AND r.respuesta != '' THEN 1 ELSE 0 END)
                                THEN 1
                            ELSE 0
                        END AS cumplimiento
                    FROM pac_temas_modulo_3 t
                    INNER JOIN pac_preguntas_modulo_3 q ON q.id_tema = t.id
                    INNER JOIN pac_respuestas_paciente_modulo_3 r ON r.id_pregunta = q.id
                    GROUP BY r.id_paciente, t.id
                ) x
                GROUP BY x.id_paciente
            ) m ON m.id_paciente = p.id
            WHERE p.id_clinica = :id_clinica",

            // Módulo 4: cirugías; sin filas equivale a 100 según la lógica actual.
            "SELECT AVG(COALESCE(m.porcentaje, 100))
            FROM pc_paciente p
            LEFT JOIN (
                SELECT id_paciente,
                    ROUND(CASE WHEN COUNT(*) = 0 THEN 100 ELSE
                        SUM(CASE WHEN fecha IS NOT NULL AND fecha != '0000-00-00'
                            AND cirugia IS NOT NULL AND cirugia != ''
                            AND observaciones IS NOT NULL AND observaciones != '' THEN 1 ELSE 0 END)
                        / COUNT(*) * 100 END, 0) AS porcentaje
                FROM pc_antecedentes_quirurgicos
                GROUP BY id_paciente
            ) m ON m.id_paciente = p.id
            WHERE p.id_clinica = :id_clinica",

            // Módulo 5: promedio de antecedentes patológicos y enfermedades.
            "SELECT AVG((COALESCE(s1.porcentaje, 0) + COALESCE(s2.porcentaje, 100)) / 2)
            FROM pc_paciente p
            LEFT JOIN (
                SELECT x.id_paciente, ROUND(AVG(x.cumplimiento) * 100, 2) AS porcentaje
                FROM (
                    SELECT r.id_paciente, t.id AS id_tema,
                        CASE
                            WHEN SUM(CASE WHEN q.tipo = 'Select' THEN 1 ELSE 0 END) = 0 THEN
                                CASE WHEN COUNT(*) = SUM(CASE WHEN r.respuesta IS NOT NULL AND r.respuesta != '' THEN 1 ELSE 0 END)
                                    THEN 1 ELSE 0 END
                            WHEN MAX(CASE WHEN q.tipo = 'Select' THEN r.respuesta END) = 'No' THEN 1
                            WHEN MAX(CASE WHEN q.tipo = 'Select' THEN r.respuesta END) = 'Sí'
                                AND COUNT(*) = SUM(CASE WHEN r.respuesta IS NOT NULL AND r.respuesta != '' THEN 1 ELSE 0 END)
                                THEN 1
                            ELSE 0
                        END AS cumplimiento
                    FROM pac_temas_modulo_5 t
                    INNER JOIN pac_preguntas_modulo_5 q ON q.id_tema = t.id
                    INNER JOIN pac_respuestas_paciente_modulo_5 r ON r.id_pregunta = q.id
                    GROUP BY r.id_paciente, t.id
                ) x
                GROUP BY x.id_paciente
            ) s1 ON s1.id_paciente = p.id
            LEFT JOIN (
                SELECT r.id_paciente,
                    ROUND(CASE WHEN COUNT(*) = 0 THEN 100 ELSE SUM(CASE
                        WHEN r.respuesta IS NULL OR r.respuesta = '' THEN 0
                        WHEN e.descripcion IN ('Diabetes Mellitus', 'Enfermedad pulmonar') AND r.respuesta = 'Si'
                            AND (r.year_diagnostico IS NULL OR r.year_diagnostico = '' OR r.tipo IS NULL OR r.tipo = '') THEN 0
                        WHEN e.descripcion IN ('Diabetes Mellitus', 'Enfermedad pulmonar') AND r.respuesta = 'Si'
                            AND r.year_diagnostico IS NOT NULL AND r.year_diagnostico != ''
                            AND r.tipo IS NOT NULL AND r.tipo != '' THEN 1
                        WHEN r.respuesta = 'Si' AND r.year_diagnostico IS NOT NULL AND r.year_diagnostico != '' THEN 1
                        WHEN r.respuesta = 'No' THEN 1
                        ELSE 0 END) * 100 / COUNT(*) END, 0) AS porcentaje
                FROM pac_enfermedades_respuestas_modulo_5 r
                INNER JOIN pac_enfermedades_modulo_5 e ON e.id = r.id_enfermedad
                GROUP BY r.id_paciente
            ) s2 ON s2.id_paciente = p.id
            WHERE p.id_clinica = :id_clinica",

            // Módulo 6: medicación actual; sin filas equivale a 100 según la lógica actual.
            "SELECT AVG(COALESCE(m.porcentaje, 100))
            FROM pc_paciente p
            LEFT JOIN (
                SELECT id_paciente,
                    ROUND(CASE WHEN COUNT(*) = 0 THEN 100 ELSE SUM(CASE
                        WHEN medicamento IS NOT NULL AND medicamento != ''
                            AND descripcion IS NOT NULL AND descripcion != ''
                            AND tiempo_uso IS NOT NULL AND tiempo_uso != ''
                            AND dosis IS NOT NULL AND dosis != ''
                            AND medico IS NOT NULL AND medico != '' THEN 1 ELSE 0 END)
                        / COUNT(*) * 100 END, 0) AS porcentaje
                FROM pac_medicacion_actual_modulo_6
                GROUP BY id_paciente
            ) m ON m.id_paciente = p.id
            WHERE p.id_clinica = :id_clinica",

            // Módulo 7: medicamentos para controlar el dolor.
            "SELECT AVG(COALESCE(m.porcentaje, 100))
            FROM pc_paciente p
            LEFT JOIN (
                SELECT r.id_paciente,
                    ROUND(CASE WHEN COUNT(*) = 0 THEN 100 ELSE SUM(CASE
                        WHEN r.respuesta IS NULL OR r.respuesta = '' THEN 0
                        WHEN r.respuesta = 'No' THEN 1
                        WHEN r.respuesta = 'Sí' AND r.dosis IS NOT NULL AND r.dosis != ''
                            AND r.resultados IS NOT NULL AND r.resultados != ''
                            AND r.consumo IS NOT NULL AND r.consumo != ''
                            AND ((r.resultados = 'Tuve efectos adversos' AND r.descripcion IS NOT NULL AND r.descripcion != '')
                                OR r.resultados != 'Tuve efectos adversos') THEN 1
                        ELSE 0 END) * 100 / COUNT(*) END, 0) AS porcentaje
                FROM pac_respuestas_paciente_modulo_7 r
                INNER JOIN pac_preguntas_modulo_7 q ON q.id = r.id_pregunta
                INNER JOIN pac_temas_modulo_7 t ON t.id = q.id_tema
                GROUP BY r.id_paciente
            ) m ON m.id_paciente = p.id
            WHERE p.id_clinica = :id_clinica",

            // Módulo 8: promedio de procedimientos y tratamientos.
            "SELECT AVG((COALESCE(s1.porcentaje, 100) + COALESCE(s2.porcentaje, 100)) / 2)
            FROM pc_paciente p
            LEFT JOIN (
                SELECT id_paciente,
                    ROUND(CASE WHEN COUNT(*) = 0 THEN 100 ELSE SUM(CASE
                        WHEN procedimiento IS NOT NULL AND procedimiento != ''
                            AND fecha IS NOT NULL AND fecha != ''
                            AND resultados IS NOT NULL AND resultados != ''
                            AND ((resultados = 'Tuve efectos adversos' AND descripcion IS NOT NULL AND descripcion != '')
                                OR resultados != 'Tuve efectos adversos') THEN 1 ELSE 0 END)
                        * 100.0 / COUNT(*) END, 0) AS porcentaje
                FROM pac_procedimientos_dolor_modulo_8
                GROUP BY id_paciente
            ) s1 ON s1.id_paciente = p.id
            LEFT JOIN (
                SELECT r.id_paciente,
                    ROUND(CASE WHEN COUNT(*) = 0 THEN 100 ELSE SUM(CASE
                        WHEN r.utilizo = 'No' THEN 1
                        WHEN r.utilizo = 'Si' AND r.resultado IS NOT NULL AND r.resultado != ''
                            AND ((r.resultado = 'Tuve efectos adversos' AND r.descripcion IS NOT NULL AND r.descripcion != '')
                                OR r.resultado != 'Tuve efectos adversos') THEN 1 ELSE 0 END)
                        * 100.0 / COUNT(*) END, 0) AS porcentaje
                FROM pac_respuestas_paciente_modulo_8 r
                INNER JOIN pac_tratamientos_dolor_modulo_8 t ON t.id = r.id_tratamiento
                GROUP BY r.id_paciente
            ) s2 ON s2.id_paciente = p.id
            WHERE p.id_clinica = :id_clinica",

            // Módulo 9: evaluación del dolor; sin filas equivale a 100 según la lógica actual.
            "SELECT AVG(COALESCE(m.porcentaje, 100))
            FROM pc_paciente p
            LEFT JOIN (
                SELECT id_paciente,
                    ROUND(CASE WHEN COUNT(*) = 0 THEN 100 ELSE SUM(CASE
                        WHEN dolor = 'No' THEN 1
                        WHEN dolor = 'Sí' AND tiempo_dolor IS NOT NULL AND tiempo_dolor != ''
                            AND descripcion IS NOT NULL AND descripcion != ''
                            AND incremento IS NOT NULL AND incremento != '' THEN 1
                        ELSE 0 END) / COUNT(*) * 100 END, 0) AS porcentaje
                FROM pc_paciente_evaluacion_dolor
                GROUP BY id_paciente
            ) m ON m.id_paciente = p.id
            WHERE p.id_clinica = :id_clinica",
        ];

        $moduleValues = [];

        foreach ($queries as $sql) {
            $stmt = $this->bd->prepare($sql);
            $stmt->execute([':id_clinica' => $idClinica]);
            $value = $stmt->fetchColumn();

            if ($value !== false && $value !== null) {
                $moduleValues[] = (float) $value;
            }
        }

        if ($moduleValues === []) {
            return null;
        }

        return round(array_sum($moduleValues) / count($moduleValues), 2);
    }

    public function insertPaciente($data,$cookie){
        
        $sql = "INSERT INTO pc_paciente (
            id_clinica,

            nombres,
            apellido_paterno,
            apellido_materno,

            edad,
            sexo,
            fecha_nacimiento,
            estado_civil,
            curp,
            lugar_origen,    
            lugar_residencia,    
            ocupacion,   
            num_hijos,   
            edad_hijos,  
            medico_tratante, 
            medico_refiere,
            diagnostico_inicial,
            calle,   
            num_interior,    
            num_exterior,  
            colonia, 
            delegacion, 
            cp,  
            municipio,   
            estado,  
            distancia,   
            email,   
            telefono,    
            celular, 
            res_nombre,  
            res_telefono,    
            cuidador,    
            cuidador_telefono,   
            aseguradora, 
            informe_aseguradora, 
            hoja_membretada, 
            formato_aseguradora, 
            honorarios, 
            observaciones,
            motivo_atencion,
            quien_recomienda, 
            redes_sociales,    
            status
        ) VALUES (
            :id_clinica,

            :nombres,
            :apellido_paterno,
            :apellido_materno,

            :edad,
            :sexo,
            :fecha_nacimiento,
            :estado_civil,
            :curp,
            :lugar_origen,    
            :lugar_residencia,    
            :ocupacion,   
            :num_hijos,   
            :edad_hijos,  
            :medico_tratante, 
            :medico_refiere,
            :diagnostico_inicial,
            :calle,   
            :num_interior,    
            :num_exterior,  
            :colonia, 
            :delegacion, 
            :cp,  
            :municipio,   
            :estado,  
            :distancia,   
            :email,   
            :telefono,    
            :celular, 
            :res_nombre,  
            :res_telefono,    
            :cuidador,    
            :cuidador_telefono,   
            :aseguradora, 
            :informe_aseguradora, 
            :hoja_membretada, 
            :formato_aseguradora, 
            :honorarios, 
            :observaciones,
            :motivo_atencion,
            :quien_recomienda,   
            :redes_sociales,   
            :status
        )";

        $stmt = $this->bd->prepare($sql);                    
        
        $datos = [
        ':id_clinica' => $cookie['id_clinica'],

        ':nombres' => $data['Nombres'],
        ':apellido_paterno' => $data['ApellidoPaterno'],
        ':apellido_materno' => $data['ApellidoMaterno'],

        ':edad' => $data['Edad'],
        ':sexo' => $data['Sexo'],
        ':fecha_nacimiento' => $data['FeNacimiento'],
        ':estado_civil' => $data['EstadoCivil'],
        ':curp' => $data['CURP'],
        ':lugar_origen' => $data['LuOrigen'],    
        ':lugar_residencia' => $data['LuResidencia'],    
        ':ocupacion' => $data['Ocupacion'],   
        ':num_hijos' => $data['NumHijos'],   
        ':edad_hijos' => $data['EdadHijos'],  
        ':medico_tratante' => '', 
        ':medico_refiere' => '',
        ':diagnostico_inicial' => '',
        ':calle' => $data['DACalle'],   
        ':num_interior' => $data['DANI'],    
        ':num_exterior' => $data['DANE'],  
        ':colonia' => $data['DAColonia'], 
        ':delegacion' => $data['DADelegacion'], 
        ':cp' => $data['DACP'],  
        ':municipio' => $data['DAMunicipio'],   
        ':estado' => '',  
        ':distancia' => $data['Distancia'],   
        ':email' => $data['Email'],   
        ':telefono' => $data['Telefono'],    
        ':celular' => $data['Celular'], 
        ':res_nombre' => $data['ResNombre'],  
        ':res_telefono' => $data['ResTelefono'],    
        ':cuidador' => $data['CuiNombre'],    
        ':cuidador_telefono' => $data['CuiTelefono'],   
        ':aseguradora' => '', 
        ':informe_aseguradora' => '', 
        ':hoja_membretada' => '', 
        ':formato_aseguradora' => '', 
        ':honorarios' => '', 
        ':observaciones' => '',
        ':motivo_atencion' => $data['motivoAtencionClinica'],
        ':quien_recomienda' => $data['personaRecomienda'],
        ':redes_sociales' => $data['RedesSociales'],
        ':status' => 1
        ];
    
        if ($stmt->execute($datos)) {
            return array('resultado' => 200,'mensaje' => $this->bd->lastInsertId());

        } else {
            return array('resultado' => 401,'mensaje' => '¡Error al agregar nuevo paciente a la lista!');
        }
        

    }

    public function editPaciente($data){

    $sql = "UPDATE pc_paciente SET 
    
    nombres = :nombres,
    apellido_paterno = :apellido_paterno,
    apellido_materno = :apellido_materno,

    edad = :edad,
    sexo = :sexo,
    fecha_nacimiento = :fecha_nacimiento,
    estado_civil = :estado_civil,
    curp = :curp,
    lugar_origen = :lugar_origen,    
    lugar_residencia = :lugar_residencia,    
    ocupacion = :ocupacion,   
    num_hijos = :num_hijos,   
    edad_hijos = :edad_hijos,   
    calle = :calle,   
    num_interior = :num_interior,    
    num_exterior = :num_exterior,  
    colonia = :colonia, 
    delegacion = :delegacion, 
    cp = :cp,  
    municipio = :municipio,  
    distancia = :distancia,   
    email = :email,   
    telefono = :telefono,    
    celular = :celular, 
    res_nombre = :res_nombre,  
    res_telefono = :res_telefono,    
    cuidador = :cuidador,    
    cuidador_telefono = :cuidador_telefono,  
    motivo_atencion = :motivo_atencion,
    quien_recomienda = :quien_recomienda, 
    redes_sociales = :redes_sociales
    WHERE id = :id";
    $stmt = $this->bd->prepare($sql);

    $datos = [
        
        'nombres' => $data['Nombres'],
        'apellido_paterno' => $data['ApellidoPaterno'],
        'apellido_materno' => $data['ApellidoMaterno'],

        'edad' => $data['Edad'],
        'sexo' => $data['Sexo'],
        'fecha_nacimiento' => $data['FeNacimiento'],
        'estado_civil' => $data['EstadoCivil'],
        'curp' => $data['CURP'],
        'lugar_origen' => $data['LuOrigen'],
        'lugar_residencia' => $data['LuResidencia'],
        'ocupacion' => $data['Ocupacion'],
        'num_hijos' => $data['NumHijos'],
        'edad_hijos' => $data['EdadHijos'],
        'calle' => $data['DACalle'],
        'num_interior' => $data['DANI'],
        'num_exterior' => $data['DANE'],
        'colonia' => $data['DAColonia'],
        'delegacion' => $data['DADelegacion'],
        'cp' => $data['DACP'],
        'municipio' => $data['DAMunicipio'],
        'distancia' => $data['Distancia'],
        'email' => $data['Email'],
        'telefono' => $data['Telefono'],
        'celular' => $data['Celular'],
        'res_nombre' => $data['ResNombre'],
        'res_telefono' => $data['ResTelefono'],
        'cuidador' => $data['CuiNombre'],
        'cuidador_telefono' => $data['CuiTelefono'],
        'motivo_atencion' => $data['motivoAtencionClinica'],
        'quien_recomienda' => $data['personaRecomienda'],
        'redes_sociales' => $data['RedesSociales'],
        'id' => $data['idPaciente']
    ];

    if ($stmt->execute($datos)) {
        return array('resultado' => 200,'mensaje' => $data['idPaciente']);

    } else {
        return array('resultado' => 401,'mensaje' => '¡Error al editar la información del paciente!');
    }


    }

    public function insertPacientePin($data){

        date_default_timezone_set('America/Mexico_City');
        $fecha = date('Y-m-d');
        $hora = date('H:i:s');

        $nuevaFecha = date('Y-m-d', strtotime($fecha . ' +30 days'));
    
        $sql = "INSERT INTO pc_paciente_acceso (
            paciente_id,
            pin,
            fecha_expiracion,
            estatus
        ) VALUES (
            :paciente_id,
            :pin,
            :fecha_expiracion,
            :estatus
        )";

        $stmt = $this->bd->prepare($sql);                    
        
        $datos = [
        ':paciente_id' => $data['idPaciente'],
        ':pin' => PasswordHelper::hashPassword('cdp'.$data['idPaciente']),
        ':fecha_expiracion' => $nuevaFecha.' '.$hora,
        ':estatus' => 0
        ];
    
        if ($stmt->execute($datos)) {
            return array('resultado' => 200,'mensaje' => '¡Se creo el PIN Correctamente!');

        } else {
            return array('resultado' => 401,'mensaje' => '¡Error al agregar nuevo paciente a la lista!');
        }

    }

public function insertPacienteReceta($data){

    $sql = "INSERT INTO receta_medica (
        id_paciente,
        diagnostico,
        medicamento,
        codigo_referencia,
        ta,
        fc,
        spo2,
        temperatura
    ) VALUES (
        :id_paciente,
        :diagnostico,
        :medicamento,
        :codigo_referencia,
        :ta,
        :fc,
        :spo2,
        :temperatura
    )";

    $stmt = $this->bd->prepare($sql);

    $datos = [
        ':id_paciente'       => $data['idPaciente'],
        ':diagnostico'       => $data['diagnostico'],
        ':medicamento'       => $data['medicamento'],
        ':codigo_referencia' => $data['referencia'],
        ':ta'                => $data['ta'],
        ':fc'                => $data['fc'],
        ':spo2'              => $data['spo2'],
        ':temperatura'       => $data['temperatura']
    ];

    if ($stmt->execute($datos)) {
        return array(
            'resultado' => 200,
            'mensaje' => $this->bd->lastInsertId()
        );

    } else {
        return array(
            'resultado' => 401,
            'mensaje' => '¡Error al agregar nueva receta a la lista!'
        );
    }

}

}
