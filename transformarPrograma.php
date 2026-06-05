<?php
/**
 * transformarPrograma()
 *
 * Agrupa: idCategoria → Producto → EtapaNombre
 */
function transformarPrograma(array $datos, string $fecha, int $diasMes): array
{
    $diaActual   = (int) date('j', strtotime($fecha));
    $esperadoPct = $diasMes > 0 ? ($diaActual / $diasMes) * 100 : 0;

    // ── 1. PlanProduccion vigente por clave (mayor configuracion) ─────────────
    $planPorClave = [];
    foreach ($datos as $row) {
        $clave  = trim($row['clave'] ?? '');
        $config = (int)($row['configuracion'] ?? 0);
        $plan   = (float)($row['PlanProduccion'] ?? 0);
        if (!isset($planPorClave[$clave]) || $config > $planPorClave[$clave]['maxConfig']) {
            $planPorClave[$clave] = ['plan' => $plan, 'maxConfig' => $config];
        }
    }

    // ── 2. Agrupar idCategoria → nombreCat → Producto → Etapa ────────────────
    $agrupado = [];

    foreach ($datos as $row) {
        $idCat   = $row['idCategoria']  ?? 0;
        $cat     = $row['Categoria']   ?? $row['NombreDepto'] ?? 'Sin categoría'; // nombre real de la operación
        $prod    = $row['Producto']     ?? 'Sin producto';
        $etapa   = $row['EtapaNombre']  ?? 'Sin etapa';
        $clave   = trim($row['clave']   ?? '');
        $ustd    = (float)($row['USTDAcc'] ?? 0);
        $config  = $row['configuracion'] ?? '';

        if (!isset($agrupado[$idCat])) {
            $agrupado[$idCat] = [
                '_nombre' => $cat,
                '_prods'  => [],
            ];
        }
        if (!isset($agrupado[$idCat]['_prods'][$prod])) {
            $agrupado[$idCat]['_prods'][$prod] = [];
        }
        if (!isset($agrupado[$idCat]['_prods'][$prod][$etapa])) {
            $agrupado[$idCat]['_prods'][$prod][$etapa] = [
                'etapa'    => $etapa,
                'config'   => $config,
                'ustd'     => 0,
                'plan'     => 0,
                '_claves'  => [],
            ];
        }

        // Sumar USTD
        $agrupado[$idCat]['_prods'][$prod][$etapa]['ustd'] += $ustd;

        // Sumar plan solo una vez por clave
        if (!in_array($clave, $agrupado[$idCat]['_prods'][$prod][$etapa]['_claves'])) {
            $agrupado[$idCat]['_prods'][$prod][$etapa]['plan'] += $planPorClave[$clave]['plan'];
            $agrupado[$idCat]['_prods'][$prod][$etapa]['_claves'][] = $clave;
        }
    }

    // ── 3. Ordenar etapas por talla ───────────────────────────────────────────
    $ordenEtapas = [
        'recién nacido' => 0, 'recien nacido' => 0, 'e1' => 0,
        'chico'         => 1, 'e2' => 1,
        'mediano'       => 2, 'e3' => 2,
        'grande'        => 3, 'e4' => 3,
        'jumbo'         => 4, 'e5' => 4,
        'extra jumbo'   => 5, 'e6' => 5,
        'extra extra jumbo' => 6, 'e7' => 6,
    ];

    $getPrioridad = function(string $etapa) use ($ordenEtapas): int {
        $lower = mb_strtolower(trim($etapa));
        // Coincidencia exacta primero
        if (isset($ordenEtapas[$lower])) return $ordenEtapas[$lower];
        // Buscar por contenido — de más largo a más corto para evitar que
        // "jumbo" matchee antes que "extra jumbo" o "extra extra jumbo"
        $keys = array_keys($ordenEtapas);
        usort($keys, fn($a, $b) => strlen($b) <=> strlen($a));
        foreach ($keys as $key) {
            if (str_contains($lower, $key)) return $ordenEtapas[$key];
        }
        return 99; // sin match va al final
    };

    foreach ($agrupado as &$cat) {
        foreach ($cat['_prods'] as &$etapas) {
            uksort($etapas, fn($a, $b) => $getPrioridad($a) <=> $getPrioridad($b));
        }
        unset($etapas);
    }
    unset($cat);

    // ── 4. Calcular % avance, colores y totales ───────────────────────────────
    $totalGenUstd = 0;
    $totalGenPlan = 0;

    foreach ($agrupado as $idCat => &$cat) {
        $totalCatUstd = 0;
        $totalCatPlan = 0;

        foreach ($cat['_prods'] as $prod => &$etapas) {
            foreach ($etapas as $etapa => &$info) {
                unset($info['_claves']);
                $info['avancePct'] = $info['plan'] > 0
                    ? round(($info['ustd'] / $info['plan']) * 100, 1)
                    : 0;
                $info['color'] = calcularColor($info['avancePct'], $esperadoPct);
                $totalCatUstd += $info['ustd'];
                $totalCatPlan += $info['plan'];
            }
            unset($info);
        }
        unset($etapas);

        $avanceCat = $totalCatPlan > 0
            ? round(($totalCatUstd / $totalCatPlan) * 100, 1)
            : 0;

        $cat['_total'] = [
            'ustd'      => $totalCatUstd,
            'plan'      => $totalCatPlan,
            'avancePct' => $avanceCat,
            'color'     => calcularColor($avanceCat, $esperadoPct),
        ];

        $totalGenUstd += $totalCatUstd;
        $totalGenPlan += $totalCatPlan;
    }
    unset($cat);

    $avanceGen = $totalGenPlan > 0
        ? round(($totalGenUstd / $totalGenPlan) * 100, 1)
        : 0;

    return [
        'grupos'       => $agrupado,
        'totalGeneral' => [
            'ustd'      => $totalGenUstd,
            'plan'      => $totalGenPlan,
            'avancePct' => $avanceGen,
            'color'     => calcularColor($avanceGen, $esperadoPct),
        ],
        'esperadoPct'  => round($esperadoPct, 1),
        'diaActual'    => $diaActual,
        'diasMes'      => $diasMes,
    ];
}

function calcularColor(float $avancePct, float $esperadoPct): string
{
    if ($esperadoPct <= 0) return 'rojo';
    $ratio = ($avancePct / $esperadoPct) * 100;
    if ($ratio > 100)  return 'morado';
    if ($ratio >= 90)  return 'verde';
    if ($ratio >= 70)  return 'amarillo';
    return 'rojo';
}