<?php
namespace App\Services;

/** Deterministic, traceable clinical prose built only from already-read data. */
final class ClinicalNarrativeService
{
    /** Classifies one persisted response without treating zero as empty. */
    public function classifyResponse($value): string
    {
        $text = trim(strip_tags((string)($value ?? '')));
        if ($text === '') return 'no_response';
        $normalized = mb_strtolower($text);
        if (in_array($normalized, ['n/a', 'na', 'no aplica', 'no aplicable'], true)) return 'not_applicable';
        if (is_numeric($text)) return 'numeric';
        if ($this->negative($text)) return 'negation';
        if ($this->positive($text)) return 'affirmation';
        return 'text';
    }

    public function build(array $detail, array $supplement): array
    {
        $modules = $detail['modules'] ?? [];
        $background = ['positive' => [], 'negative' => [], 'pending' => []];
        foreach (['M02', 'M03', 'M05'] as $key) {
            foreach (($modules[$key]['records'] ?? []) as $record) {
                $fields = $this->fieldMap($record);
                $ref = (string)($record['ref'] ?? '');
                if ($key === 'M02') {
                    $disease = $this->meaningful($fields['Enfermedad'] ?? null);
                    $response = $fields['Respuesta documentada'] ?? null;
                    $responseClass = $this->classifyResponse($response);
                    $relative = $this->meaningful($fields['Parentesco especificado'] ?? null);
                    if (!$disease) continue;
                    if (in_array($responseClass, ['no_response', 'not_applicable'], true)) continue;
                    if ($this->negative($response)) {
                        $background['negative'][] = $this->item('Se registró respuesta negativa respecto al antecedente familiar de ' . $disease . '.', $ref);
                    } elseif ($this->positive($response)) {
                        $suffix = $relative ? ' en ' . $relative : ', sin parentesco especificado';
                        $background['positive'][] = $this->item('Se registra antecedente familiar de ' . $disease . $suffix . '.', $ref);
                    } elseif ($this->meaningful($response)) {
                        $background['positive'][] = $this->item('Se registra antecedente familiar de ' . $disease . '; respuesta documentada: ' . $response . '.', $ref);
                    }
                    continue;
                }
                if ($key === 'M05') {
                    $disease = $this->meaningful($fields['Enfermedad'] ?? null);
                    $response = $fields['Respuesta'] ?? null;
                    $responseClass = $this->classifyResponse($response);
                    if ($disease && !in_array($responseClass, ['no_response', 'not_applicable'], true)) {
                        if ($this->negative($response)) $background['negative'][] = $this->item('Se registró respuesta negativa respecto al antecedente de ' . $disease . '.', $ref);
                        elseif ($this->positive($response)) $background['positive'][] = $this->item('Se registra antecedente patológico de ' . $disease . '.', $ref);
                        elseif ($value = $this->meaningful($response)) $background['positive'][] = $this->item('Se documenta antecedente de ' . $disease . '; respuesta registrada: ' . $value . '.', $ref);
                        continue;
                    }
                }
                $question = $this->meaningful($fields['Pregunta'] ?? null);
                $response = $fields['Respuesta'] ?? null;
                $responseClass = $this->classifyResponse($response);
                if (!$question || in_array($responseClass, ['no_response', 'not_applicable'], true)) continue;
                $label = $question;
                $responseValue = $this->meaningful($response);
                if ($this->negative($response)) $background['negative'][] = $this->item('Se registró respuesta negativa respecto a ' . $label . '.', $ref);
                elseif ($this->positive($response)) $background['positive'][] = $this->item('Se registra respuesta afirmativa respecto a ' . $label . '.', $ref);
                elseif ($responseValue) $background['positive'][] = $this->item('Se documenta respecto a ' . $label . ': ' . $responseValue . '.', $ref);
            }
        }

        $reason = $this->fieldValue($modules['M01'] ?? [], 'Motivo de atención');
        $reasonRefs = $this->fieldRefs($modules['M01'] ?? [], 'Motivo de atención');
        $pain = $this->pain($modules['M09'] ?? []);
        $currentMedication = $this->medications($modules['M06'] ?? []);
        $painTreatments = $this->questionnaireStatements($modules['M07'] ?? [], 'tratamiento para el dolor');
        $procedures = $this->procedures($modules['M08'] ?? []);
        $followup = $this->followup($supplement['notes']['records'] ?? []);
        $prescriptions = $this->prescriptions($supplement['prescriptions']['records'] ?? []);
        $cofepris = $this->cofepris($supplement['cofepris']['records'] ?? []);

        $generalParts = [];
        if ($reason) $generalParts[] = 'El motivo de atención registrado es ' . $reason . '.';
        if ($background['positive']) $generalParts[] = $background['positive'][0]['text'];
        if ($pain['summary']) $generalParts[] = $pain['summary'];
        if ($currentMedication) $generalParts[] = $currentMedication[0]['text'];
        if ($followup) $generalParts[] = $followup[0]['text'];
        if (!$generalParts) $generalParts[] = 'No se dispone de información clínica suficiente para una síntesis ejecutiva más amplia en las fuentes consultadas.';

        return [
            'schema_version' => 'clinical-narrative.v1',
            'executive' => [
                'general' => ['text' => implode(' ', $generalParts), 'references' => array_values(array_unique(array_merge($reasonRefs, $this->refs(array_merge($background['positive'], $pain['items'], $currentMedication, $followup)))))],
                'background' => $background,
                'pain' => $pain,
                'treatments' => [
                    'current_medication' => $currentMedication,
                    'pain_treatments' => $painTreatments,
                    'procedures' => $procedures,
                    'historical_prescriptions' => $prescriptions,
                    'cofepris' => $cofepris,
                ],
                'followup' => $followup,
            ],
            'pending' => $this->pending($modules, $background['pending'], $pain),
        ];
    }

    private function pain(array $module): array
    {
        $items = [];
        $missing = [];
        foreach (($module['records'] ?? []) as $record) {
            $fields = $this->fieldMap($record);
            $parts = [];
            foreach (['dolor' => 'dolor', 'tiempo_dolor' => 'tiempo de evolución', 'descripcion' => 'características y localización', 'incremento' => 'factores relacionados'] as $key => $label) {
                $value = $this->meaningful($fields[$this->painLabel($key)] ?? ($fields[$key] ?? null));
                if ($value) $parts[] = $label . ': ' . $value;
                else $missing[] = $label;
            }
            if ($parts) $items[] = $this->item('La evaluación del dolor documenta ' . implode('; ', $parts) . '.', (string)($record['ref'] ?? ''));
        }
        $missing = array_values(array_unique($missing));
        if (!$items && !empty($module['records'])) $summary = 'La evaluación del dolor presenta información incompleta; faltan ' . implode(', ', $missing) . '.';
        else $summary = $items[0]['text'] ?? 'No se dispone de una evaluación del dolor registrada.';
        if ($missing && $items) $summary .= ' La evaluación del dolor presenta información incompleta; faltan ' . implode(', ', $missing) . '.';
        return ['summary' => $summary, 'items' => $items, 'missing' => $missing];
    }

    private function medications(array $module): array
    {
        $items = [];
        foreach (($module['records'] ?? []) as $record) {
            $fields = $this->fieldMap($record);
            $medicine = $this->meaningful($fields['Medicamento'] ?? null);
            if (!$medicine) continue;
            $extra = [];
            foreach (['Dosis', 'Tiempo de uso'] as $label) if ($value = $this->meaningful($fields[$label] ?? null)) $extra[] = mb_strtolower($label) . ': ' . $value;
            $items[] = $this->item('Se documenta medicación actual: ' . $medicine . ($extra ? ' (' . implode('; ', $extra) . ')' : '') . '.', (string)($record['ref'] ?? ''));
        }
        return $items;
    }

    private function questionnaireStatements(array $module, string $context): array
    {
        $items = [];
        foreach (($module['records'] ?? []) as $record) {
            $fields = $this->fieldMap($record);
            $question = $this->meaningful($fields['Pregunta'] ?? null);
            $response = $fields['Respuesta'] ?? null;
            $responseClass = $this->classifyResponse($response);
            $responseValue = $this->meaningful($response);
            if (!$question || in_array($responseClass, ['no_response', 'not_applicable'], true)) continue;
            $label = $question ?: $context;
            if ($this->negative($response)) $text = 'Se registró respuesta negativa respecto a ' . $label . '.';
            elseif ($this->positive($response)) $text = 'Se registra respuesta afirmativa respecto a ' . $label . '.';
            elseif ($responseValue) $text = 'Se documenta respecto a ' . $label . ': ' . $responseValue . '.';
            else continue;
            $items[] = $this->item($text, (string)($record['ref'] ?? ''));
        }
        return $items;
    }

    private function procedures(array $module): array
    {
        $items = [];
        foreach (($module['records'] ?? []) as $record) {
            $fields = $this->fieldMap($record);
            $name = $this->meaningful($fields['Procedimiento o tratamiento'] ?? null);
            if (!$name) continue;
            if (($record['meta']['source_kind'] ?? null) === 'tratamiento_cuestionario') {
                $response = $fields['Utilizó'] ?? null;
                $class = $this->classifyResponse($response);
                if (in_array($class, ['no_response', 'not_applicable'], true)) continue;
                if ($this->negative($response)) $text = 'Se registró que no utilizó el tratamiento ' . $name . '.';
                elseif ($this->positive($response)) $text = 'Se documenta que utilizó el tratamiento ' . $name . '.';
                else $text = 'Se documenta respuesta sobre el tratamiento ' . $name . ': ' . $this->meaningful($response) . '.';
                $items[] = $this->item($text, (string)($record['ref'] ?? ''));
                continue;
            }
            $items[] = $this->item('Se documenta procedimiento realizado: ' . $name . '.', (string)($record['ref'] ?? ''));
        }
        return $items;
    }

    private function followup(array $records): array
    {
        $items = [];
        foreach ($records as $record) {
            $text = $this->meaningful($record['contenido'] ?? null);
            $date = $record['fecha_hora'] ?? null;
            $prefix = $date ? 'Nota subsecuente del ' . $this->date($date) . ': ' : 'Nota subsecuente: ';
            $items[] = $this->item($prefix . ($text ?: 'seguimiento documentado.') , 'NOTA:R' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT));
        }
        return $items;
    }

    private function prescriptions(array $records): array
    {
        $items = [];
        foreach ($records as $index => $record) {
            $medicine = $this->meaningful($record['medicamento'] ?? null);
            $text = 'Se registra una prescripción histórica' . ($medicine ? ' de ' . $medicine : '') . '.';
            $items[] = $this->item($text, 'RECETA:R' . str_pad((string)($index + 1), 3, '0', STR_PAD_LEFT));
        }
        return $items;
    }

    private function cofepris(array $records): array
    {
        $items = [];
        foreach ($records as $index => $record) {
            $medicine = $this->meaningful($record['medicamento'] ?? null);
            $items[] = $this->item('Se registra documentación COFEPRIS' . ($medicine ? ' para ' . $medicine : '') . '.', 'COFEPRIS:R' . str_pad((string)($index + 1), 3, '0', STR_PAD_LEFT));
        }
        return $items;
    }

    private function pending(array $modules, array $backgroundPending, array $pain): array
    {
        $items = [];
        $labels = ['M01' => 'Identificación clínicamente pertinente', 'M02' => 'Antecedentes familiares', 'M03' => 'Antecedentes personales no patológicos', 'M04' => 'Antecedentes quirúrgicos', 'M05' => 'Antecedentes personales patológicos', 'M06' => 'Medicación actual', 'M07' => 'Tratamientos farmacológicos para el dolor', 'M08' => 'Procedimientos para el dolor', 'M09' => 'Evaluación del dolor'];
        foreach ($modules as $key => $module) {
            $status = $module['status'] ?? null;
            if ($status === 'absent') {
                $text = $key === 'M06' ? 'No se encontraron registros de medicación actual en este módulo.' : ($key === 'M09' ? 'No se encontró una evaluación del dolor registrada.' : 'No se encontraron registros de ' . mb_strtolower($labels[$key] ?? $key) . ' en este módulo.');
                $items[] = ['category' => 'Información no registrada', 'module' => $key, 'text' => $text, 'references' => []];
            } elseif ($status === 'incomplete') {
                $text = $key === 'M09' ? 'La evaluación del dolor presenta información incompleta.' : 'La información de ' . mb_strtolower($labels[$key] ?? $key) . ' está incompleta.';
                $items[] = ['category' => 'Información incompleta', 'module' => $key, 'text' => $text, 'references' => []];
            } elseif ($status === 'contradictory') $items[] = ['category' => 'Inconsistencia documental', 'module' => $key, 'text' => 'Se identifican valores que requieren comprobación en ' . mb_strtolower($labels[$key] ?? $key) . '.', 'references' => []];
        }
        foreach ($backgroundPending as $item) $items[] = ['category' => 'Revisión médica', 'module' => 'M02', 'text' => $item['text'], 'references' => $item['references']];
        if ($pain['missing'] && ($modules['M09']['status'] ?? null) === 'documented') $items[] = ['category' => 'Información incompleta', 'module' => 'M09', 'text' => 'La evaluación del dolor presenta campos sin registrar.', 'references' => $this->refs($pain['items'])];
        return $items;
    }

    private function fieldMap(array $record): array
    {
        $map = [];
        foreach (($record['groups'] ?? []) as $group) foreach (($group['fields'] ?? []) as $field) $map[(string)($field['label'] ?? '')] = $field['value'] ?? null;
        return $map;
    }
    private function fieldValue(array $module, string $label): ?string { foreach (($module['records'] ?? []) as $record) { $value = $this->meaningful($this->fieldMap($record)[$label] ?? null); if ($value) return $value; } return null; }
    private function fieldRefs(array $module, string $label): array { $refs = []; foreach (($module['records'] ?? []) as $record) { if ($this->meaningful($this->fieldMap($record)[$label] ?? null)) $refs[] = (string)($record['ref'] ?? ''); } return array_values(array_filter($refs)); }
    private function item(string $text, string $ref): array { return ['text' => $text, 'references' => $ref !== '' ? [$ref] : []]; }
    private function refs(array $items): array { return array_values(array_unique(array_merge(...array_map(fn($item) => $item['references'] ?? [], $items ?: [[]])))); }
    private function meaningful($value): ?string { $text = trim(strip_tags((string)($value ?? ''))); return $text === '' || in_array(mb_strtolower($text), ['no registrado', 'no registrada', 'no especificado', 'no especificada', 'no aplica', 'no aplicable'], true) ? null : mb_substr($text, 0, 2000); }
    private function positive($value): bool { return in_array(mb_strtolower(trim((string)$value)), ['sí', 'si', 'afirmativo', 'afirmativa', 'positivo', 'positiva', 'yes'], true); }
    private function negative($value): bool { return in_array(mb_strtolower(trim((string)$value)), ['no', 'negativo', 'negativa'], true); }
    private function date($value): string { try { return (new \DateTime((string)$value))->format('d/m/Y H:i'); } catch (\Throwable) { return 'fecha no registrada'; } }
    private function painLabel(string $key): string { return ['dolor' => 'Presencia de dolor documentada', 'tiempo_dolor' => 'Tiempo de evolución', 'descripcion' => 'Características y localización', 'incremento' => 'Factores relacionados'][$key] ?? $key; }
}
