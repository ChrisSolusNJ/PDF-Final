<?php
/**
 * transformarDatos()
 *
 * Convierte el array plano de la BD en estructura agrupada.
 * El SP debe regresar todos los registros del mes actual.
 * - Columnas D1-D7: USTD solo de los últimos 7 días
 * - AcumuladoUSTD: último registro del mes (fecha + turno más alto)
 *
 * @param array  $datos  Array plano de registros del SP
 * @param string $fecha  Fecha fin del período ('2026-05-10')
 * @return array
 */
function transformarDatos(array $datos, string $fecha): array
{
    // ── Configuración de máquinas por idCategoria ───────────────────────────
    // Define el orden y nombre a mostrar por categoría
    // Si la máquina no tiene datos ese día aparece igual con celdas vacías
    $configuracionMaquinas = [
        1 => [ // Pañal Infantil
            62 => 'PE10',
            63 => 'MP21',
            61 => 'MP23',
            60 => 'MP24',
            65 => 'MP25',
        ],
        2 => [ // Calzón Entrenador
            64 => 'MP22',
        ],
        3 => [], // SOAR
        4 => [ // Conteos Bajos
            101 => 'PX',
            138 => 'N1',
            139 => 'N2',
        ],
        5 => [
            136 => 'W. Reclaim',
        ], // Waste Reclaim
        6 => [ // Pañal Abierto
            81  => 'PA01',
        ],
        7 => [ // Predoblado
            81  => 'PA01',
            82  => 'PA02',
            83  => 'PA03',
        ],
        8 => [ // Ropa Interior
            84  => 'PA04',
            97  => 'PA05',
        ],
        9 => [ // Toalla
            69  => 'MP03',
            70  => 'MP09',
            76  => 'MP12',
            73  => 'MP13',
            74  => 'MP14',
            137 => 'MP16',
        ],
        10 => [ // Panty
            75  => 'MP08',
            72  => 'MP11',
            77  => 'MP15',
        ],
        11 => [ // Lactancia
            68  => 'MP01',
        ],
        12 => [],
        13 => [],
        14 => [ // TABBI
            85  => 'TABBI',
        ],
        15 => [
            87 => 'SPOOLER1',
            89 => 'SPOOLER2',
        ], // Spooler
    ];
    // Rango visible en columnas (últimos 7 días)
    $fechaInicioRango = date('Y-m-d', strtotime($fecha . ' -6 days'));
    // Inicio del mes para AcumuladoUSTD
    $inicioMes        = date('Y-m-01', strtotime($fecha));

    // Fechas del encabezado — solo las del período de 7 días
    $fechas      = obtenerFechasPeriodo($datos, $fechaInicioRango);
    $ultimaFecha = !empty($fechas) ? max($fechas) : $fecha;

    $agrupado = [];

    // ── Acumulador global de turnos por NoMaquina (ignora categoría) ──
    // Sirve para calcular %TP y %Merma correctamente cuando una máquina
    // aparece en múltiples categorías (ej: PA01 en Predoblado y Pañal Abierto)
    $turnosPorMaquina = [];

    foreach ($datos as $row) {
        $cat      = $row['Categoria']     ?? 'Sin categoría';
        $maq      = $row['NombreMaquina'] ?? 'Sin máquina';
        $noMaq    = $row['NoMaquina'];
        $prod     = $row['Producto']      ?? 'Sin producto';
        $clave    = $row['Clave'];
        $etapa    = $row['Etapa']         ?? 'Sin etapa';
        $descripcion = $row['Descripcion'] ?? 'Sin descripción';
        $rowFecha = $row['Fecha'];
        $turno    = $row['Turno'];

        if (!isset($agrupado[$cat])) {
            $agrupado[$cat] = [];
            $agrupado[$cat]['_idCategoria'] = $row['idCategoria'] ?? null;
        }
        if (!isset($agrupado[$cat][$maq])) {
            $agrupado[$cat][$maq] = [
                'NoMaquina' => $noMaq,
                'productos' => [],
                '_turnos'   => [],
            ];
        }
        if (!isset($agrupado[$cat][$maq]['productos'][$prod]))
            $agrupado[$cat][$maq]['productos'][$prod] = ['claves' => []];

        if (!isset($agrupado[$cat][$maq]['productos'][$prod]['claves'][$clave])) {
            $agrupado[$cat][$maq]['productos'][$prod]['claves'][$clave] = [
                'etapa'        => $clave . ' - ' . $descripcion,
                'dias'         => array_fill_keys($fechas, null),
                'acum'         => 0,
                '_ultimaFecha' => '',
                '_ultimoTurno' => 0,
            ];
        }

        // ── USTD por día: solo si está dentro de los últimos 7 días ──
        if ($rowFecha >= $fechaInicioRango) {
            $agrupado[$cat][$maq]['productos'][$prod]['claves'][$clave]['dias'][$rowFecha] =
                ($agrupado[$cat][$maq]['productos'][$prod]['claves'][$clave]['dias'][$rowFecha] ?? 0)
                + $row['USTD'];
        }

        // ── AcumuladoUSTD: último registro del mes ──
        $reg = $agrupado[$cat][$maq]['productos'][$prod]['claves'][$clave];
        if ($rowFecha > $reg['_ultimaFecha'] ||
           ($rowFecha === $reg['_ultimaFecha'] && $turno > $reg['_ultimoTurno'])) {
            $acum = $rowFecha >= $inicioMes ? $row['AcumuladoUSTD'] : 0;
            $agrupado[$cat][$maq]['productos'][$prod]['claves'][$clave]['acum']         = $acum;
            $agrupado[$cat][$maq]['productos'][$prod]['claves'][$clave]['_ultimaFecha'] = $rowFecha;
            $agrupado[$cat][$maq]['productos'][$prod]['claves'][$clave]['_ultimoTurno'] = $turno;
        }

        // ── Un registro por turno/día para %TP y %Merma (por categoría, para compatibilidad) ──
        $keyTurno = $rowFecha . '_' . $turno;
        if (!isset($agrupado[$cat][$maq]['_turnos'][$keyTurno])) {
            $agrupado[$cat][$maq]['_turnos'][$keyTurno] = [
                'Fecha'           => $rowFecha,
                'Turno'           => $turno,
                'TiempoAbajo'     => $row['TiempoAbajo'],
                'HorasTrabajadas' => (float)$row['HorasTrabajadas'],
                'Cortes'          => $row['Cortes'],
                'Rechazos'        => $row['Rechazos'],
            ];
        }

        // ── Acumular en el acumulador global por NoMaquina ──
        $noMaq = $row['NoMaquina'];
        if (!isset($turnosPorMaquina[$noMaq])) {
            $turnosPorMaquina[$noMaq] = [];
        }
        
        // ignorar registros sin categoría (basura)
        if ($row['Categoria'] === null) {
            continue;
        }

        $keyTurnoGlobal = $rowFecha . '_' . $turno;

        if (!isset($turnosPorMaquina[$noMaq][$keyTurnoGlobal])) {

            $turnosPorMaquina[$noMaq][$keyTurnoGlobal] = [
                'Fecha'           => $rowFecha,
                'Turno'           => $turno,
                'TiempoAbajo'     => $row['TiempoAbajo'],
                'HorasTrabajadas' => (float)$row['HorasTrabajadas'],
                'Cortes'          => $row['Cortes'],
                'Rechazos'        => $row['Rechazos'],
                'TotalPiezas'      => $row['TotalPiezas'],
            ];
        }
    }

    // ── Reordenar máquinas y agregar vacías según configuración ─────────────
    foreach ($agrupado as $cat => &$maquinas) {
        $idCategoria = $maquinas['_idCategoria'] ?? null;
        unset($maquinas['_idCategoria']);

        if ($idCategoria !== null && isset($configuracionMaquinas[$idCategoria])) {
            $orden = $configuracionMaquinas[$idCategoria];
            $maquinasOrdenadas = [];

            foreach ($orden as $noMaq => $nombreMostrar) {
                // Buscar si esta máquina tiene datos
                $encontrada = null;
                foreach ($maquinas as $nombreMaq => $data) {
                    if (isset($data['NoMaquina']) && $data['NoMaquina'] == $noMaq) {
                        $encontrada = $nombreMaq;
                        break;
                    }
                }

                if ($encontrada !== null) {
                    // Tiene datos — usar sus datos pero con el nombre correcto
                    $maquinasOrdenadas[$nombreMostrar] = $maquinas[$encontrada];
                    $maquinasOrdenadas[$nombreMostrar]['NoMaquina'] = $noMaq;
                } else {
                    // Sin datos — insertar vacía
                    $maquinasOrdenadas[$nombreMostrar] = [
                        'NoMaquina' => $noMaq,
                        'productos' => [],
                        '_turnos'   => [],
                    ];
                }
            }
            $maquinas = $maquinasOrdenadas;
        }
    }
    unset($maquinas);

    // ── Calcular %TP, %Merma, Prom y Totales ──
    foreach ($agrupado as $cat => &$maquinas) {
        $totalOp = inicializarTotal($fechas);

        foreach ($maquinas as $maq => &$data) {
            $turnos = $data['_turnos'];

            // Usar turnos globales de la máquina para %TP y %Merma
            // Esto asegura que máquinas en múltiples categorías tengan el mismo valor
            $noMaqActual   = $data['NoMaquina'] ?? null;
            $turnosGlobales = ($noMaqActual !== null && isset($turnosPorMaquina[$noMaqActual]))
                ? $turnosPorMaquina[$noMaqActual]
                : $turnos; // fallback a turnos locales si no hay globales

            // %TP y %Merma Día (última fecha del período) — sobre 24 horas fijas
            $turnosDia         = array_filter($turnosGlobales, fn($t) => $t['Fecha'] === $ultimaFecha);
            $data['tp_dia']    = calcularTPDia($turnosDia);
            $data['merma_dia'] = calcularMerma($turnosDia);

            // %TP y %Merma Acum (mes completo)
            $turnosAcum         = array_filter($turnosGlobales, fn($t) => $t['Fecha'] >= $inicioMes);
            $data['tp_acum']    = calcularTPAcum($turnosAcum);
            $data['merma_acum'] = calcularMerma($turnosAcum);

            // Días trabajados = suma HorasTrabajadas del mes / 24
            $turnosAcumLocal = array_filter($turnos, fn($t) => $t['Fecha'] >= $inicioMes);
            $totalHorasMes  = array_sum(array_column(array_values($turnosAcumLocal), 'HorasTrabajadas'));
            $diasTrabajados = $totalHorasMes > 0 ? round($totalHorasMes / 24, 2) : 0;

            $totalMaq = inicializarTotal($fechas);

            foreach ($data['productos'] as &$producto) {
                foreach ($producto['claves'] as &$info) {
                    // Prom = AcumuladoUSTD / días trabajados en el mes
                    $info['prom'] = $diasTrabajados > 0 ? round($info['acum'] / $diasTrabajados, 1) : 0;

                    foreach ($fechas as $f) {
                        $totalMaq['dias'][$f] += $info['dias'][$f] ?? 0;
                    }
                    $totalMaq['acum'] += $info['acum'] ?? 0;

                    unset($info['_ultimaFecha'], $info['_ultimoTurno']);
                }
                unset($info);

                // ── Filtrar claves sin ningún dato en los 7 días ──
                $producto['claves'] = array_filter(
                    $producto['claves'],
                    fn($info) => array_sum(array_map(fn($v) => (float)($v ?? 0), $info['dias'])) > 0
                );
            }
            unset($producto);

            // ── Eliminar productos que quedaron sin claves ──
            $data['productos'] = array_filter(
                $data['productos'],
                fn($p) => !empty($p['claves'])
            );

            // Prom total máquina = AcumuladoUSTD total / días trabajados
            $totalMaq['prom'] = $diasTrabajados > 0
                ? round($totalMaq['acum'] / $diasTrabajados, 1)
                : 0;

            $data['total_maquina']    = $totalMaq;
            $data['_diasTrabajados']  = $diasTrabajados;
            unset($data['_turnos']);

            foreach ($fechas as $f) {
                $totalOp['dias'][$f] += $totalMaq['dias'][$f];
            }
            $totalOp['acum']         += $totalMaq['acum'];
            $totalOp['_diasTotales'] += $diasTrabajados;
        }
        unset($data);

        // Prom total operación = AcumuladoUSTD total / suma días trabajados de todas las máquinas
        $totalOp['prom'] = $totalOp['_diasTotales'] > 0
            ? round($totalOp['acum'] / $totalOp['_diasTotales'], 1)
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
        $totalOp['tp_dia']    = calcularTPDia($turnosCatDia);
        $totalOp['tp_acum']   = calcularTPAcum($turnosCatAcum);
        $totalOp['merma_dia'] = calcularMerma($turnosCatDia);
        $totalOp['merma_acum']= calcularMerma($turnosCatAcum);

        $maquinas['_total_operacion'] = $totalOp;
    }
    unset($maquinas);

    // ── Filtrar máquinas sin productos y categorías vacías ──
    foreach ($agrupado as $cat => &$maquinas) {
        foreach ($maquinas as $maq => &$data) {
            if ($maq === '_total_operacion') continue;
            // Eliminar máquinas que no tienen ningún producto con claves
            if (empty($data['productos'])) {
                unset($maquinas[$maq]);
            }
        }
        unset($data);

        // Eliminar categoría "Sin categoría" si todas sus máquinas quedaron vacías
        // o si la categoría es literalmente "Sin categoría"
        $maquinasSinTotal = array_filter(
            $maquinas,
            fn($k) => $k !== '_total_operacion',
            ARRAY_FILTER_USE_KEY
        );
        if ($cat === 'Sin categoría' && empty($maquinasSinTotal)) {
            unset($agrupado[$cat]);
        }
    }
    unset($maquinas);

    // ── Eliminar completamente la categoría "Sin categoría" ──
    // (máquinas con Categoria=NULL del SP no deben aparecer en el reporte)
    unset($agrupado['Sin categoría']);

    return [
        'fechas' => $fechas,
        'tablas' => $agrupado,
    ];
}

// ── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Extrae solo las fechas dentro del período de 7 días
 */
function obtenerFechasPeriodo(array $datos, string $fechaInicio): array
{
    $fechas = array_unique(array_filter(
        array_column($datos, 'Fecha'),
        fn($f) => $f >= $fechaInicio
    ));
    sort($fechas);
    return array_values($fechas);
}

function inicializarTotal(array $fechas): array
{
    return ['dias' => array_fill_keys($fechas, 0), 'acum' => 0, 'prom' => 0, '_diasTotales' => 0];
}

/**
 * %TP Día = TiempoAbajo total / 60 / 24 (día siempre es 24 horas)
 */

function calcularTPDia(array $turnos): string
{
    // eliminar duplicados por Fecha + Turno
    $unicos = [];

    foreach ($turnos as $t) {
        $key = $t['Fecha'] . '_' . $t['Turno'];
        if (!isset($unicos[$key])) {
            $unicos[$key] = $t['TiempoAbajo'];
        }
    }

    $totalTP = array_sum($unicos);

    if (empty($unicos)) return '—';

    return number_format(($totalTP / 60 / 24) * 100, 2) . '%';
}


/**
 * %TP Acum = TiempoAbajo total / 60 / (días trabajados * 24)
 */

function calcularTPAcum(array $turnos): string
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

    $totalTP  = array_sum($unicos);
    $totalHrs = array_sum($horas);

    if ($totalHrs <= 0) return '—';

    $diasTrabajados = $totalHrs / 24;

    return number_format((($totalTP / 60) / ($diasTrabajados * 24)) * 100, 2) . '%';
}


/**
 * @deprecated Usar calcularTPDia o calcularTPAcum
 */
function calcularTP(array $turnos): string
{
    return calcularTPAcum($turnos);
}

function calcularMerma(array $turnos): string
{
    $totalPiezas = array_sum(array_column($turnos, 'TotalPiezas'));
    $totalCortes   = array_sum(array_column($turnos, 'Cortes'));
    if ($totalCortes <= 0) return '—';
    return number_format((1 - ($totalPiezas / $totalCortes)) * 100, 2) . '%';
}