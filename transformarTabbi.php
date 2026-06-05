<?php
/**
 * transformarTabbi()
 *
 * Convierte el array plano del SP de TABBI en estructura agrupada.
 * Similar a transformarDatos() pero con métricas propias:
 * - USTD    → MetrosCuadrados (MM²)
 * - Acum    → TotalMC (acumulado MM² del mes)
 * - Cortes  → TotalML (Metros Lineales, para %TP)
 * - Rechazos→ KGSRechazados (para %Merma)
 * - Reales  → PesoTotal (KG, denominador %Merma)
 *
 * @param array  $datos  Array plano del SP
 * @param string $fecha  Fecha fin del período ('2026-05-21')
 * @return array
 */
function transformarTabbi(array $datos, string $fecha): array
{
    if (empty($datos)) {
        return ['fechas' => [], 'tablas' => []];
    }

    $fechaInicioRango = date('Y-m-d', strtotime($fecha . ' -6 days'));
    $inicioMes        = date('Y-m-01', strtotime($fecha));

    // Fechas del encabezado — solo las del período de 7 días
    $fechas      = obtenerFechasPeriodoTabbi($datos, $fechaInicioRango);
    $ultimaFecha = !empty($fechas) ? max($fechas) : $fecha;

    $agrupado = [];
    $turnosPorMaquina = [];

    foreach ($datos as $row) {
        $cat         = $row['Categoria']     ?? 'TABBI';
        $maq         = $row['NombreMaquina'] ?? 'TABBI';
        $noMaq       = $row['NoMaquina'];
        $prod        = $row['Producto']      ?? 'Sin producto';
        $clave       = $row['Clave'];
        $descripcion = $row['Descripcion']   ?? 'Sin descripción';
        $rowFecha    = $row['Fecha'];
        $turno       = $row['Turno'];

        if (!isset($agrupado[$cat])) {
            $agrupado[$cat] = [];
        }
        if (!isset($agrupado[$cat][$maq])) {
            $agrupado[$cat][$maq] = [
                'NoMaquina' => $noMaq,
                'productos' => [],
                '_turnos'   => [],
            ];
        }
        if (!isset($agrupado[$cat][$maq]['productos'][$prod])) {
            $agrupado[$cat][$maq]['productos'][$prod] = ['claves' => []];
        }
        if (!isset($agrupado[$cat][$maq]['productos'][$prod]['claves'][$clave])) {
            $agrupado[$cat][$maq]['productos'][$prod]['claves'][$clave] = [
                'etapa'        => $clave . ' - ' . $descripcion,
                'dias'         => array_fill_keys($fechas, null),
                'acum'         => 0,
                '_ultimaFecha' => '',
                '_ultimoTurno' => 0,
            ];
        }

        // MM² por día
        if ($rowFecha >= $fechaInicioRango) {
            $agrupado[$cat][$maq]['productos'][$prod]['claves'][$clave]['dias'][$rowFecha] =
                round(($agrupado[$cat][$maq]['productos'][$prod]['claves'][$clave]['dias'][$rowFecha] ?? 0)
                + (float)$row['MetrosCuadrados'], 3);
        }

        // Acumulado MM² del mes — último registro
        $reg = $agrupado[$cat][$maq]['productos'][$prod]['claves'][$clave];
        if ($rowFecha > $reg['_ultimaFecha'] ||
           ($rowFecha === $reg['_ultimaFecha'] && $turno > $reg['_ultimoTurno'])) {
            $acum = $rowFecha >= $inicioMes ? (float)($row['MC'] ?? 0) : 0;
            $agrupado[$cat][$maq]['productos'][$prod]['claves'][$clave]['acum']         = $acum;
            $agrupado[$cat][$maq]['productos'][$prod]['claves'][$clave]['_ultimaFecha'] = $rowFecha;
            $agrupado[$cat][$maq]['productos'][$prod]['claves'][$clave]['_ultimoTurno'] = $turno;
        }

        // Turnos para %TP y %Merma
        $keyTurno = $rowFecha . '_' . $turno;
        if (!isset($agrupado[$cat][$maq]['_turnos'][$keyTurno])) {
            $agrupado[$cat][$maq]['_turnos'][$keyTurno] = [
                'Fecha'            => $rowFecha,
                'Turno'            => $turno,
                'TiempoAbajo'      => (int)($row['TiempoAbajo'] ?? 0),
                'HorasTrabajadas'  => (float)($row['HorasTrabajadas'] ?? 0),
                'ML'               => (float)($row['MetrosLineales'] ?? 0),
                'MetrosCuadrados'  => (float)($row['MetrosCuadrados'] ?? 0),
                'KGSRechazados'    => (float)($row['KGSRechazados'] ?? 0),
                'PesoTotal'        => (float)($row['Kilogramos'] ?? 0),
            ];
        }

        // Acumulador global por máquina
        if (!empty($row['HorasTrabajadas'])) {
            if (!isset($turnosPorMaquina[$noMaq])) {
                $turnosPorMaquina[$noMaq] = [];
            }
            if (!isset($turnosPorMaquina[$noMaq][$keyTurno])) {
                $turnosPorMaquina[$noMaq][$keyTurno] = [
                    'Fecha'            => $rowFecha,
                    'Turno'            => $turno,
                    'TiempoAbajo'      => (int)($row['TiempoAbajo'] ?? 0),
                    'HorasTrabajadas'  => (float)($row['HorasTrabajadas'] ?? 0),
                    'ML'               => (float)($row['MetrosLineales'] ?? 0),
                    'MetrosCuadrados'  => (float)($row['MetrosCuadrados'] ?? 0),
                    'KGSRechazados'    => (float)($row['KGSRechazados'] ?? 0),
                    'PesoTotal'        => (float)($row['Kilogramos'] ?? 0),
                ];
            }
        }
    }

    // Calcular %TP, %Merma, Prom y Totales
    foreach ($agrupado as $cat => &$maquinas) {
        $totalOp = inicializarTotalTabbi($fechas);

        foreach ($maquinas as $maq => &$data) {
            $turnos = $data['_turnos'];
            $noMaqActual = $data['NoMaquina'] ?? null;
            $turnosGlobales = ($noMaqActual !== null && isset($turnosPorMaquina[$noMaqActual]))
                ? $turnosPorMaquina[$noMaqActual]
                : $turnos;

            // %TP Día y Acum
            $turnosDia  = array_filter($turnosGlobales, fn($t) => $t['Fecha'] === $ultimaFecha);
            $turnosAcum = array_filter($turnosGlobales, fn($t) => $t['Fecha'] >= $inicioMes);

            $data['tp_dia']    = calcularTPDiaTabbi($turnosDia);
            $data['tp_acum']   = calcularTPAcumTabbi($turnosAcum);
            $data['merma_dia'] = calcularMermaTabbi($turnosDia);
            $data['merma_acum']= calcularMermaTabbi($turnosAcum);

            // Días trabajados
            $turnosAcumLocal = array_filter($turnos, fn($t) => $t['Fecha'] >= $inicioMes);
            $totalHrs        = array_sum(array_column(array_values($turnosAcumLocal), 'HorasTrabajadas'));
            $diasTrabajados  = $totalHrs > 0 ? round($totalHrs / 24, 2) : 0;

            $totalMaq = inicializarTotalTabbi($fechas);

            foreach ($data['productos'] as &$producto) {
                foreach ($producto['claves'] as &$info) {
                    $info['prom'] = $diasTrabajados > 0
                        ? round($info['acum'] / $diasTrabajados, 3)
                        : 0;

                    foreach ($fechas as $f) {
                        $totalMaq['dias'][$f] = round(($totalMaq['dias'][$f] ?? 0) + ($info['dias'][$f] ?? 0), 3);
                    }
                    $totalMaq['acum'] += $info['acum'] ?? 0;

                    unset($info['_ultimaFecha'], $info['_ultimoTurno']);
                }
                unset($info);

                // Filtrar claves sin datos en los 7 días
                $producto['claves'] = array_filter(
                    $producto['claves'],
                    fn($info) => array_sum(array_map(fn($v) => (float)($v ?? 0), $info['dias'])) > 0
                );
            }
            unset($producto);

            // Eliminar productos sin claves
            $data['productos'] = array_filter(
                $data['productos'],
                fn($p) => !empty($p['claves'])
            );

            // Acum total de la máquina = suma de MetrosCuadrados del mes desde turnos
            $turnosAcumMC = array_filter($turnosGlobales, fn($t) => $t['Fecha'] >= $inicioMes);
            $totalMaq['acum'] = round(array_sum(array_column(array_values($turnosAcumMC), 'MetrosCuadrados')), 3);

            $totalMaq['prom'] = $diasTrabajados > 0
                ? round($totalMaq['acum'] / $diasTrabajados, 3)
                : 0;

            $data['total_maquina']   = $totalMaq;
            $data['_diasTrabajados'] = $diasTrabajados;
            unset($data['_turnos']);

            foreach ($fechas as $f) {
                $totalOp['dias'][$f] = round(($totalOp['dias'][$f] ?? 0) + ($totalMaq['dias'][$f] ?? 0), 3);
            }
            $totalOp['acum']         += $totalMaq['acum'];
            $totalOp['_diasTotales'] += $diasTrabajados;
        }
        unset($data);

        $totalOp['prom'] = $totalOp['_diasTotales'] > 0
            ? round($totalOp['acum'] / $totalOp['_diasTotales'], 3)
            : 0;

        // %TP y %Merma acumulados de toda la categoría
        $turnosCatDia  = [];
        $turnosCatAcum = [];
        foreach ($maquinas as $maq => $data) {
            if ($maq === '_total_operacion' || !isset($data['NoMaquina'])) continue;
            $noMaqCat   = $data['NoMaquina'];
            $turnosGlob = isset($turnosPorMaquina[$noMaqCat]) ? $turnosPorMaquina[$noMaqCat] : [];
            foreach ($turnosGlob as $key => $t) {
                if ($t['Fecha'] === $ultimaFecha) $turnosCatDia[$noMaqCat . '_' . $key]  = $t;
                if ($t['Fecha'] >= $inicioMes)    $turnosCatAcum[$noMaqCat . '_' . $key] = $t;
            }
        }
        $totalOp['tp_dia']    = calcularTPDiaTabbi($turnosCatDia);
        $totalOp['tp_acum']   = calcularTPAcumTabbi($turnosCatAcum);
        $totalOp['merma_dia'] = calcularMermaTabbi($turnosCatDia);
        $totalOp['merma_acum']= calcularMermaTabbi($turnosCatAcum);

        $maquinas['_total_operacion'] = $totalOp;
    }
    unset($maquinas);

    return [
        'fechas' => $fechas,
        'tablas' => $agrupado,
    ];
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function obtenerFechasPeriodoTabbi(array $datos, string $fechaInicio): array
{
    $fechas = array_unique(array_filter(
        array_column($datos, 'Fecha'),
        fn($f) => $f >= $fechaInicio
    ));
    sort($fechas);
    return array_values($fechas);
}

function inicializarTotalTabbi(array $fechas): array
{
    return ['dias' => array_fill_keys($fechas, 0), 'acum' => 0, 'prom' => 0, '_diasTotales' => 0];
}


function calcularTPDiaTabbi(array $turnos): string
{
    $unicos = [];

    foreach ($turnos as $t) {
        $key = $t['Fecha'] . '_' . $t['Turno'];

        if (!isset($unicos[$key])) {
            $unicos[$key] = $t['TiempoAbajo'];
        }
    }

    $totalTA = array_sum($unicos);

    if (empty($unicos)) return '—';

    return number_format(($totalTA / 60 / 24) * 100, 2) . '%';
}



function calcularTPAcumTabbi(array $turnos): string
{
    $unicos = [];
    $horas  = [];

    foreach ($turnos as $t) {
        $key = $t['Fecha'] . '_' . $t['Turno'];

        if (!isset($unicos[$key])) {
            $unicos[$key] = $t['TiempoAbajo'];
            $horas[$key]  = $t['HorasTrabajadas'];
        }
    }

    $totalTA  = array_sum($unicos);
    $totalHrs = array_sum($horas);

    if ($totalHrs <= 0) return '—';

    $dias = $totalHrs / 24;

    return number_format(($totalTA / 60 / ($dias * 24)) * 100, 2) . '%';
}


function calcularMermaTabbi(array $turnos): string
{
    $kgsRechazados = array_sum(array_column($turnos, 'KGSRechazados'));
    $pesoTotal     = array_sum(array_column($turnos, 'PesoTotal'));
    if ($pesoTotal <= 0) return '—';
    return number_format(($kgsRechazados / $pesoTotal) * 100, 2) . '%';
}