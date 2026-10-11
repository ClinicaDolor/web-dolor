<?php

namespace App\Services;

use App\Config\Database;
use PDO;

/**
 * Consultas agregadas de sólo lectura para el Centro de indicadores Doctor.
 * Las tablas incluidas en las definiciones son constantes del código y cada
 * dominio clínico se relaciona con pc_paciente antes de contar registros.
 */
final class ClinicaIndicadoresService
{
    private PDO $bd;

    /** @var array<string, array{table:string, alias:string, date:string}> */
    private const SOURCES = [
        'notas' => ['table' => 'nota_subsecuente', 'alias' => 'n', 'date' => 'fecha_hora'],
        'laboratorios' => ['table' => 'laboratorio', 'alias' => 'l', 'date' => 'fecha_hora'],
        'recetas' => ['table' => 'receta_medica', 'alias' => 'r', 'date' => 'fecha_hora'],
        'cofepris' => ['table' => 'cofepris', 'alias' => 'c', 'date' => 'fecha_hora'],
    ];

    public function __construct(?PDO $bd = null)
    {
        $this->bd = $bd ?? Database::getInstance();
    }

    /**
     * Años presentes en fuentes con fecha verificable y relación de clínica.
     * No se generan años sintéticos ni se consultan recetas externas/folios.
     */
    public function getAvailableYears(int $idClinica): array
    {
        $idClinica = $this->validateClinicId($idClinica);
        $queries = [
            'SELECT YEAR(p.fecha_alta) AS anio FROM pc_paciente p
             WHERE p.id_clinica = :id_clinica' . $this->validDateCondition('p', 'fecha_alta') . '
             GROUP BY YEAR(p.fecha_alta)',
            'SELECT YEAR(n.fecha_hora) AS anio FROM nota_subsecuente n
             INNER JOIN pc_paciente p ON p.id = n.id_paciente
             WHERE p.id_clinica = :id_clinica' . $this->validDateCondition('n', 'fecha_hora') . '
             GROUP BY YEAR(n.fecha_hora)',
            'SELECT YEAR(l.fecha_hora) AS anio FROM laboratorio l
             INNER JOIN pc_paciente p ON p.id = l.id_paciente
             WHERE p.id_clinica = :id_clinica' . $this->validDateCondition('l', 'fecha_hora') . '
             GROUP BY YEAR(l.fecha_hora)',
            'SELECT YEAR(r.fecha_hora) AS anio FROM receta_medica r
             INNER JOIN pc_paciente p ON p.id = r.id_paciente
             WHERE p.id_clinica = :id_clinica' . $this->validDateCondition('r', 'fecha_hora') . '
             GROUP BY YEAR(r.fecha_hora)',
            'SELECT YEAR(c.fecha_hora) AS anio FROM cofepris c
             INNER JOIN pc_paciente p ON p.id = c.id_paciente
             WHERE p.id_clinica = :id_clinica' . $this->validDateCondition('c', 'fecha_hora') . '
             GROUP BY YEAR(c.fecha_hora)',
        ];

        $years = [];
        foreach ($queries as $sql) {
            $stmt = $this->bd->prepare($sql);
            $stmt->execute([':id_clinica' => $idClinica]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $year) {
                $year = (int) $year;
                if ($year >= 1900 && $year <= 2200) {
                    $years[$year] = true;
                }
            }
        }

        $years = array_keys($years);
        rsort($years, SORT_NUMERIC);

        return array_values($years);
    }

    /**
     * Construye el conjunto de datos del dashboard sin volver a calcular el
     * promedio de Historia Clínica: ese valor llega del cálculo canónico de
     * ClinicaModel::getDashboardSummary().
     */
    public function getDashboardData(int $idClinica, ?int $year, array $dashboardSummary): array
    {
        $idClinica = $this->validateClinicId($idClinica);
        if ($year !== null && ($year < 1900 || $year > 2200)) {
            throw new \InvalidArgumentException('Invalid indicator year.');
        }

        return [
            'current' => [
                'total_pacientes' => (int) ($dashboardSummary['total_pacientes'] ?? 0),
                'promedio_historia_clinica' => $dashboardSummary['promedio_historia_clinica'] ?? null,
            ],
            'period' => $this->getPeriodMetrics(
                $idClinica,
                $year,
                (int) ($dashboardSummary['total_pacientes'] ?? 0)
            ),
            'patients_by_year' => $this->getPatientsByYear($idClinica),
            'activity_series' => $this->getActivitySeries($idClinica, $year),
            'module_finalizations' => $this->getModuleFinalizations($idClinica),
            'data_quality' => $this->getDataQuality($idClinica),
            'selected_year' => $year,
        ];
    }

    private function getPeriodMetrics(int $idClinica, ?int $year, int $totalPatients): array
    {
        $params = [':id_clinica' => $idClinica];
        $condition = $this->periodCondition('p', 'fecha_alta', $year, $params, 'patients');
        if ($year !== null) {
            $condition .= $this->validDateCondition('p', 'fecha_alta');
        }
        $newPatients = $this->scalarInt(
            'SELECT COUNT(*) FROM pc_paciente p WHERE p.id_clinica = :id_clinica' . $condition,
            $params
        );

        $metrics = [
            'altas_pacientes' => $newPatients,
            'notas' => 0,
            'pacientes_con_notas' => 0,
            'laboratorios' => 0,
            'pacientes_con_laboratorios' => 0,
            'recetas' => 0,
            'pacientes_con_recetas' => 0,
            'cofepris' => 0,
            'pacientes_con_cofepris' => 0,
        ];

        foreach (self::SOURCES as $key => $source) {
            $params = [':id_clinica' => $idClinica];
            $condition = $this->periodCondition($source['alias'], $source['date'], $year, $params, $key);
            if ($year !== null) {
                $condition .= $this->validDateCondition($source['alias'], $source['date']);
            }
            $sql = sprintf(
                'SELECT COUNT(*) AS total, COUNT(DISTINCT p.id) AS pacientes
                 FROM %s %s
                 INNER JOIN pc_paciente p ON p.id = %s.id_paciente
                 WHERE p.id_clinica = :id_clinica%s',
                $source['table'],
                $source['alias'],
                $source['alias'],
                $condition
            );
            $stmt = $this->bd->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                throw new \RuntimeException('Indicator aggregate unavailable.');
            }

            $metrics[$key] = (int) $row['total'];
            $metrics['pacientes_con_' . $key] = (int) $row['pacientes'];
        }

        $metrics['actividad_total'] = $metrics['notas']
            + $metrics['laboratorios']
            + $metrics['recetas']
            + $metrics['cofepris'];
        $metrics['promedio_notas_por_paciente'] = $totalPatients > 0
            ? round($metrics['notas'] / $totalPatients, 2)
            : null;

        return $metrics;
    }

    private function getPatientsByYear(int $idClinica): array
    {
        $stmt = $this->bd->prepare(
            'SELECT YEAR(p.fecha_alta) AS anio, COUNT(*) AS total
             FROM pc_paciente p
             WHERE p.id_clinica = :id_clinica' . $this->validDateCondition('p', 'fecha_alta') . '
             GROUP BY YEAR(p.fecha_alta)
             ORDER BY anio'
        );
        $stmt->execute([':id_clinica' => $idClinica]);

        $rows = array_map(static function (array $row): array {
            return ['label' => (string) (int) $row['anio'], 'value' => (int) $row['total']];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));

        $invalidDates = $this->scalarInt(
            'SELECT COUNT(*) FROM pc_paciente p
             WHERE p.id_clinica = :id_clinica' . $this->invalidDateCondition('p', 'fecha_alta'),
            [':id_clinica' => $idClinica]
        );
        if ($invalidDates > 0) {
            $rows[] = [
                'label' => 'Sin fecha válida',
                'value' => $invalidDates,
                'is_invalid_date' => true,
            ];
        }

        return $rows;
    }

    private function getActivitySeries(int $idClinica, ?int $year): array
    {
        $buckets = [];
        foreach (self::SOURCES as $key => $source) {
            $params = [':id_clinica' => $idClinica];
            $condition = $this->periodCondition($source['alias'], $source['date'], $year, $params, 'series_' . $key);
            $condition .= $this->validDateCondition($source['alias'], $source['date']);
            $bucketExpression = $year === null
                ? 'YEAR(' . $source['alias'] . '.' . $source['date'] . ')'
                : 'MONTH(' . $source['alias'] . '.' . $source['date'] . ')';
            $sql = sprintf(
                'SELECT %s AS bucket, COUNT(*) AS total
                 FROM %s %s
                 INNER JOIN pc_paciente p ON p.id = %s.id_paciente
                 WHERE p.id_clinica = :id_clinica%s
                 GROUP BY %s
                 ORDER BY bucket',
                $bucketExpression,
                $source['table'],
                $source['alias'],
                $source['alias'],
                $condition,
                $bucketExpression
            );
            $stmt = $this->bd->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $bucket = (string) (int) $row['bucket'];
                $buckets[$bucket] ??= [
                    'label' => $year === null ? $bucket : $this->monthLabel((int) $bucket),
                    'notes' => 0,
                    'laboratorios' => 0,
                    'recetas' => 0,
                    'cofepris' => 0,
                ];
                $seriesKey = $key === 'notas' ? 'notes' : $key;
                $buckets[$bucket][$seriesKey] = (int) $row['total'];
            }
        }

        if ($year !== null) {
            $series = [];
            for ($month = 1; $month <= 12; $month++) {
                $bucket = (string) $month;
                $series[] = $buckets[$bucket] ?? [
                    'label' => $this->monthLabel($month),
                    'notes' => 0,
                    'laboratorios' => 0,
                    'recetas' => 0,
                    'cofepris' => 0,
                ];
            }
            return $series;
        }

        ksort($buckets, SORT_NUMERIC);
        $series = array_values($buckets);

        $invalidBucket = [
            'label' => 'Sin fecha válida',
            'is_invalid_date' => true,
            'notes' => 0,
            'laboratorios' => 0,
            'recetas' => 0,
            'cofepris' => 0,
        ];
        foreach (self::SOURCES as $key => $source) {
            $sql = sprintf(
                'SELECT COUNT(*)
                 FROM %s %s
                 INNER JOIN pc_paciente p ON p.id = %s.id_paciente
                 WHERE p.id_clinica = :id_clinica%s',
                $source['table'],
                $source['alias'],
                $source['alias'],
                $this->invalidDateCondition($source['alias'], $source['date'])
            );
            $count = $this->scalarInt($sql, [':id_clinica' => $idClinica]);
            $seriesKey = $key === 'notas' ? 'notes' : $key;
            $invalidBucket[$seriesKey] = $count;
        }

        if (($invalidBucket['notes'] + $invalidBucket['laboratorios'] + $invalidBucket['recetas'] + $invalidBucket['cofepris']) > 0) {
            $series[] = $invalidBucket;
        }

        return $series;
    }

    private function getModuleFinalizations(int $idClinica): array
    {
        $stmt = $this->bd->prepare(
            'SELECT m.id, m.nombre,
                    COUNT(DISTINCT p.id) AS pacientes_finalizados,
                    COUNT(p.id) AS finalizaciones,
                    MAX(CASE WHEN p.id IS NOT NULL THEN f.fecha_hora END) AS ultima_fecha
             FROM historia_clinica_modulos m
             LEFT JOIN pac_historia_clinica_finalizar f ON f.id_modulo = m.id
             LEFT JOIN pc_paciente p ON p.id = f.id_paciente AND p.id_clinica = :id_clinica
             GROUP BY m.id, m.nombre
             ORDER BY m.id'
        );
        $stmt->execute([':id_clinica' => $idClinica]);

        return array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'nombre' => (string) $row['nombre'],
                'pacientes_finalizados' => (int) $row['pacientes_finalizados'],
                'finalizaciones' => (int) $row['finalizaciones'],
                'ultima_fecha' => $row['ultima_fecha'] ?: null,
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function getDataQuality(int $idClinica): array
    {
        $quality = [];
        $sources = [
            'Pacientes' => ['table' => 'pc_paciente', 'alias' => 'p', 'date' => 'fecha_alta', 'join' => false],
            'Notas subsecuentes' => ['table' => 'nota_subsecuente', 'alias' => 'n', 'date' => 'fecha_hora', 'join' => true],
            'Laboratorios' => ['table' => 'laboratorio', 'alias' => 'l', 'date' => 'fecha_hora', 'join' => true],
            'Recetas internas' => ['table' => 'receta_medica', 'alias' => 'r', 'date' => 'fecha_hora', 'join' => true],
            'Registros COFEPRIS' => ['table' => 'cofepris', 'alias' => 'c', 'date' => 'fecha_hora', 'join' => true],
        ];

        foreach ($sources as $label => $source) {
            $join = $source['join']
                ? ' INNER JOIN pc_paciente p ON p.id = ' . $source['alias'] . '.id_paciente'
                : '';
            $clinic = $source['join'] ? 'p.id_clinica' : 'p.id_clinica';
            $sql = sprintf(
                'SELECT COUNT(*) FROM %s %s%s
                 WHERE %s = :id_clinica%s',
                $source['table'],
                $source['alias'],
                $join,
                $clinic,
                $this->invalidDateCondition($source['alias'], $source['date'])
            );
            $quality[] = [
                'label' => $label,
                'invalid_dates' => $this->scalarInt($sql, [':id_clinica' => $idClinica]),
            ];
        }

        return $quality;
    }

    private function periodCondition(string $alias, string $field, ?int $year, array &$params, string $key): string
    {
        if ($year === null) {
            return '';
        }

        $startKey = ':' . $key . '_start';
        $endKey = ':' . $key . '_end';
        $params[$startKey] = sprintf('%04d-01-01 00:00:00', $year);
        $params[$endKey] = sprintf('%04d-01-01 00:00:00', $year + 1);

        return ' AND ' . $alias . '.' . $field . ' >= ' . $startKey
            . ' AND ' . $alias . '.' . $field . ' < ' . $endKey;
    }

    private function validDateCondition(string $alias, string $field): string
    {
        return sprintf(
            " AND %1\$s.%2\$s IS NOT NULL
             AND TRIM(CAST(%1\$s.%2\$s AS CHAR)) <> ''
             AND CAST(%1\$s.%2\$s AS CHAR) NOT LIKE '0000-00-00%%'
             AND YEAR(%1\$s.%2\$s) BETWEEN 1900 AND 2200",
            $alias,
            $field
        );
    }

    private function invalidDateCondition(string $alias, string $field): string
    {
        return sprintf(
            " AND (%1\$s.%2\$s IS NULL
             OR TRIM(CAST(%1\$s.%2\$s AS CHAR)) = ''
             OR CAST(%1\$s.%2\$s AS CHAR) LIKE '0000-00-00%%'
             OR YEAR(%1\$s.%2\$s) IS NULL
             OR YEAR(%1\$s.%2\$s) < 1900
             OR YEAR(%1\$s.%2\$s) > 2200)",
            $alias,
            $field
        );
    }

    private function scalarInt(string $sql, array $params): int
    {
        $stmt = $this->bd->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        if ($value === false) {
            throw new \RuntimeException('Indicator aggregate unavailable.');
        }

        return (int) $value;
    }

    private function validateClinicId(int $idClinica): int
    {
        if ($idClinica < 1) {
            throw new \InvalidArgumentException('Invalid clinic context.');
        }

        return $idClinica;
    }

    private function monthLabel(int $month): string
    {
        return [
            1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
        ][$month] ?? (string) $month;
    }
}
