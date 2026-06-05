<?php
require_once '../../vendor/autoload.php';
require_once 'pdf/PdfGenerator.php';
require_once "./services/consultasDireccion.php";
require_once "./hooks/transformarDatos.php";
require_once "./hooks/transformarTabbi.php";
require_once "./hooks/transformarProgramaTNT.php";
require_once "./hooks/transformarPrograma.php";

ini_set('memory_limit', '256M');
ini_set('max_execution_time', 120); // 2 minutos

// ─────────────────────────────────────────────
// 1. PARÁMETROS
// ─────────────────────────────────────────────
$fecha    = $_GET['fecha'];
$departamento = $_GET['departamento'] ?? null; // No se usa en esta versión, pero se puede mantener para compatibilidad
$logoPath = '../../img/imglogoprosede.png';
$diasMes  = (int) date('t', strtotime($fecha));

// Orden de departamentos en el reporte general
$ordenDepartamentos = [1, 24, 25];
$nombresDepto = [
    1  => 'Cuidado Infantil',
    24 => 'Protección Femenina',
    25 => 'Incontinencia',
];

// ─────────────────────────────────────────────
// 2. OBTENER DATOS DE LA BD
// ─────────────────────────────────────────────
$direccionObj = new ReporteDiario();

// Ahora obtener datos de TODOS los departamentos de una sola vez
// Asumiendo que tu función obtenerUSTD() ahora devuelve todos los deptos
$dataDiariaCompleta = $direccionObj->obtenerUSTD($fecha); // Sin parámetro de depto
$dataTabbiCompleta  = $direccionObj->obtenerDatosTabbi($fecha); // Si TABBI también se obtiene sin filtrar por depto
$dataPlanCompleta   = $direccionObj->obtenerPlanProduccion($fecha); // Sin parámetro de depto
$dataPlanTNTCompleta  = $direccionObj->obtenerPlanproduccionTNT($fecha); // Sin parámetro de depto

// Organizar datos por departamento si es necesario
// Si ya vienen organizados como [1 => [...], 24 => [...], 25 => [...]]:
if (isset($dataDiariaCompleta[1])) {
    $datosDeptos = $dataDiariaCompleta; // Ya está organizado
} else {
    // Si vienen en array simple, organizarlos por NoDepto
    $datosDeptos = [];
    foreach ($dataDiariaCompleta as $registro) {
        $noDepto = $registro['NoDepto'];
        if (!isset($datosDeptos[$noDepto])) {
            $datosDeptos[$noDepto] = [];
        }
        $datosDeptos[$noDepto][] = $registro;
    }
}

// ─────────────────────────────────────────────
// 3. GENERAR PDF CON TODOS LOS DEPARTAMENTOS
// ─────────────────────────────────────────────
$pdf = new PdfGenerator();
$esPrimero = true;

foreach ($ordenDepartamentos as $noDepto) {
    // Verificar si este departamento tiene datos
    if (!isset($datosDeptos[$noDepto]) || empty($datosDeptos[$noDepto])) {
        continue;
    }

    // ── Transformar datos USTD para este departamento ──
    $resultado = transformarDatos($datosDeptos[$noDepto], $fecha);
    $fechas    = $resultado['fechas'];
    $tablas    = $resultado['tablas'];

    // ── Transformar datos Programa para este departamento ──
    // Si dataPlanCompleta también está organizado por depto:
    $dataPlanDepto = isset($dataPlanCompleta[$noDepto]) ? $dataPlanCompleta[$noDepto] : $dataPlanCompleta;
    
    $programa = transformarPrograma($dataPlanDepto, $fecha, $diasMes);
    $programa['nombreDepto'] = $nombresDepto[$noDepto];

    // ── Agregar al PDF ──
    if ($esPrimero) {
        // Primera sección — inicializar con header
        $pdf->iniciar($fechas, $tablas, $programa, $fecha, $logoPath);
        $esPrimero = false;
    } else {
        // Departamentos siguientes — agregar sección
        $pdf->agregarSeccion($fechas, $tablas, $programa, $nombresDepto[$noDepto]);
    }
}

// ── Finalizar y mostrar PDF ──
// ── Sección TABBI ──
if (!empty($dataTabbiCompleta)) {
    $resultadoTabbi = transformarTabbi($dataTabbiCompleta, $fecha);
    if (!empty($resultadoTabbi['tablas'])) {
        $programaTNT = [];
        if (!empty($dataPlanTNTCompleta)) {
            $programaTNT = transformarProgramaTNT($dataPlanTNTCompleta, $fecha, $diasMes);
        }
        $pdf->agregarTabbi($resultadoTabbi['fechas'], $resultadoTabbi['tablas'], $programaTNT);
    }
}

$pdf->finalizar('reporte_general.pdf');