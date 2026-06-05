<?php
/**
 * Template: tabla_ustd.php — fragmento puro
 * Variables: $fechas, $tablas
 *
 * Acum y Prom de Producción Mes hacen rowspan por máquina
 * junto con %TP y %Merma
 */

if (!function_exists('fmtNum')) {
    function fmtNum($val): string {
        if ($val === null || $val === 0 || $val === '') return '—';
        return number_format((float)$val, 0, '.', ',');
    }
}
if (!function_exists('fmtFecha')) {
    function fmtFecha($fecha): string {
        $p = explode('-', $fecha);
        return $p[2] . '/' . $p[1];
    }
}
if (!function_exists('contarFilasMaquina')) {
    function contarFilasMaquina(array $productos): int {
        $total = 0;
        foreach ($productos as $producto) {
            $total += 1;
            $total += count($producto['claves']);
        }
        return $total;
    }
}

$tdBase = 'padding:2px 3px; border:0.5px solid #9FBAC5; text-align:center; vertical-align:middle; font-size:8px;';
$tdLeft = 'padding:2px 5px; border:0.5px solid #9FBAC5; text-align:left; vertical-align:middle; font-size:8px;';
?>

<?php foreach ($tablas as $categoria => $maquinas):
    $totalOp = $maquinas['_total_operacion'];
    unset($maquinas['_total_operacion']);
?>

  <p class="op-titulo"><?= htmlspecialchars($categoria) ?></p>

  <table class="ustd-table">
    <thead>
      <tr>
        <th class="th-g1" style="width:6%;" rowspan="2">Máquina</th>
        <th class="th-g1" style="width:14%;" rowspan="2">Presentación</th>
        <th class="th-g1" colspan="<?= count($fechas) ?>">USTD Días Anteriores</th>
        <th class="th-g1" colspan="2">Producción Mes (USTD)</th>
        <th class="th-g1" colspan="2">% TP</th>
        <th class="th-g1" colspan="2">% Merma</th>
      </tr>
      <tr>
        <?php foreach ($fechas as $f): ?>
          <th class="th-g2" style="width:<?= round(38 / count($fechas), 1) ?>%;"><?= fmtFecha($f) ?></th>
        <?php endforeach; ?>
        <th class="th-g2" style="width:4%;">Acum</th>
        <th class="th-g2" style="width:4%;">Prom</th>
        <th class="th-g2" style="width:4%;">Día</th>
        <th class="th-g2" style="width:4%;">Acum</th>
        <th class="th-g2" style="width:4%;">Día</th>
        <th class="th-g2" style="width:4%;">Acum</th>
      </tr>
    </thead>
    <tbody>

    <?php foreach ($maquinas as $nombreMaq => $dataMaq):
        $totalFilas  = contarFilasMaquina($dataMaq['productos']);
        $sinDatos    = empty($dataMaq['productos']);
        $primeraFila = true;
        $filasClave  = 0;
    ?>

      <?php if ($sinDatos): ?>
        <!-- Máquina sin datos: fila nombre + fila total vacía -->
        <tr>
          <td style="<?= $tdBase ?> background-color:#B8CDD6; font-weight:bold; font-size:8.5px;"><?= htmlspecialchars($nombreMaq) ?></td>
          <td style="<?= $tdLeft ?> background-color:#FFFFFF; color:#BBBBBB;">—</td>
          <?php foreach ($fechas as $f): ?>
            <td style="<?= $tdBase ?>"><span style="color:#BBBBBB;">—</span></td>
          <?php endforeach; ?>
          <td colspan="6" style="<?= $tdBase ?>"></td>
        </tr>

      <?php else: ?>

        <?php foreach ($dataMaq['productos'] as $nombreProd => $producto): ?>

          <!-- Fila separadora: Producto -->
          <tr>
            <?php if ($primeraFila): ?>
              <td rowspan="<?= $totalFilas ?>" style="<?= $tdBase ?> background-color:#B8CDD6; font-weight:bold; font-size:8.5px;"><?= htmlspecialchars($nombreMaq) ?></td>
            <?php endif; ?>
            <td colspan="<?= count($fechas) + 1 ?>" style="<?= $tdLeft ?> background-color:#EDF2F4; font-weight:bold; color:#496472; border-left:1.5px solid #799AAC;"><?= htmlspecialchars($nombreProd) ?></td>
            <?php if ($primeraFila): ?>
              <td rowspan="<?= $totalFilas ?>" style="<?= $tdBase ?> background-color:#EDF2F4; font-weight:bold;"><?= fmtNum($dataMaq['total_maquina']['acum']) ?></td>
              <td rowspan="<?= $totalFilas ?>" style="<?= $tdBase ?> background-color:#EDF2F4; font-weight:bold;"><?= fmtNum($dataMaq['total_maquina']['prom']) ?></td>
              <td rowspan="<?= $totalFilas ?>" style="<?= $tdBase ?> background-color:#EDF2F4; font-weight:bold;"><?= $dataMaq['tp_dia'] ?></td>
              <td rowspan="<?= $totalFilas ?>" style="<?= $tdBase ?> background-color:#EDF2F4; font-weight:bold;"><?= $dataMaq['tp_acum'] ?></td>
              <td rowspan="<?= $totalFilas ?>" style="<?= $tdBase ?> background-color:#EDF2F4; font-weight:bold;"><?= $dataMaq['merma_dia'] ?></td>
              <td rowspan="<?= $totalFilas ?>" style="<?= $tdBase ?> background-color:#EDF2F4; font-weight:bold;"><?= $dataMaq['merma_acum'] ?></td>
              <?php $primeraFila = false; ?>
            <?php endif; ?>
          </tr>

          <!-- Filas de claves -->
          <?php foreach ($producto['claves'] as $clave => $info):
              $bgFila = $filasClave % 2 === 0 ? '#FFFFFF' : '#F7FAFB';
              $filasClave++;
          ?>
            <tr>
              <td style="<?= $tdLeft ?> background-color:<?= $bgFila ?>; padding-left:12px;"><?= htmlspecialchars($info['etapa']) ?></td>
              <?php foreach ($fechas as $f): ?>
                <?php $v = $info['dias'][$f] ?? null; ?>
                <td style="<?= $tdBase ?> background-color:<?= $bgFila ?>;">
                  <?= ($v === null || $v == 0) ? '<span style="color:#BBBBBB;">—</span>' : fmtNum($v) ?>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>

        <?php endforeach; ?>

      <?php endif; ?>

      <!-- Total Máquina -->
      <tr>
        <td colspan="2" style="<?= $tdLeft ?> background-color:#D0E4EC; font-weight:bold; color:#496472; border-top:1px solid #496472;">Total <?= htmlspecialchars($nombreMaq) ?></td>
        <?php foreach ($fechas as $f): ?>
          <td style="<?= $tdBase ?> background-color:#D0E4EC; font-weight:bold; border-top:1px solid #496472;">
            <?php $v = $dataMaq['total_maquina']['dias'][$f] ?? 0; ?>
            <?= $v > 0 ? fmtNum($v) : '<span style="color:#BBBBBB;">—</span>' ?>
          </td>
        <?php endforeach; ?>
        <td colspan="6" style="<?= $tdBase ?> background-color:#D0E4EC; border-top:1px solid #496472;"></td>
      </tr>

    <?php endforeach; ?>

    <!-- Total Operación -->
    <tr>
      <td colspan="2" style="<?= $tdLeft ?> background-color:#496472; color:#FFFFFF; font-weight:bold; border-top:1.5px solid #2C3E50;">Total <?= htmlspecialchars($categoria) ?></td>
      <?php foreach ($fechas as $f): ?>
        <td style="<?= $tdBase ?> background-color:#496472; color:#FFFFFF; font-weight:bold; border-top:1.5px solid #2C3E50;">
          <?= $totalOp['dias'][$f] > 0 ? fmtNum($totalOp['dias'][$f]) : '<span style="color:#9FBAC5;">—</span>' ?>
        </td>
      <?php endforeach; ?>
      <td style="<?= $tdBase ?> background-color:#496472; color:#FFFFFF; font-weight:bold; border-top:1.5px solid #2C3E50;"><?= fmtNum($totalOp['acum']) ?></td>
      <td style="<?= $tdBase ?> background-color:#496472; color:#FFFFFF; font-weight:bold; border-top:1.5px solid #2C3E50;"><?= fmtNum($totalOp['prom']) ?></td>
      <td colspan="4" style="<?= $tdBase ?> background-color:#496472; border-top:1.5px solid #2C3E50;"></td>
    </tr>

    </tbody>
  </table>

<?php endforeach; ?>