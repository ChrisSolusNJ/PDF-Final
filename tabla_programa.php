<?php
/**
 * Template: tabla_programa.php
 *
 * CAMBIO 4: Agrega fila "Total {Producto}" por cada producto dentro de cada categoría,
 * acumulando sus etapas, antes de la fila "TOTAL {Categoría}".
 */
if (!function_exists('fmtN')) {
    function fmtN($val): string {
        if ($val === null || $val === '') return '—';
        return number_format((float)$val, 0, '.', ',');
    }
}
if (!function_exists('colorStyle')) {
    function colorStyle(string $color): string {
        return match($color) {
            'morado'   => 'background-color:#7B2D8B; color:#FFFFFF; font-weight:bold;',
            'verde'    => 'background-color:#1C693A; color:#FFFFFF; font-weight:bold;',
            'amarillo' => 'background-color:#B8860B; color:#FFFFFF; font-weight:bold;',
            default    => 'background-color:#9A1C1C; color:#FFFFFF; font-weight:bold;',
        };
    }
}
if (!function_exists('calcularColorLocal')) {
    function calcularColorLocal(float $avancePct, float $esperadoPct): string {
        if ($esperadoPct <= 0) return 'rojo';
        $ratio = ($avancePct / $esperadoPct) * 100;
        if ($ratio > 100)  return 'morado';
        if ($ratio >= 90)  return 'verde';
        if ($ratio >= 70)  return 'amarillo';
        return 'rojo';
    }
}

$tdB = 'padding:2px 3px; border:0.5px solid #9FBAC5; text-align:center; vertical-align:middle; font-size:8.5px;';
$tdL = 'padding:2px 5px; border:0.5px solid #9FBAC5; text-align:left; vertical-align:middle; font-size:8.5px;';
?>

<p class="prog-nota">
  Día <?= $programa['diaActual'] ?> de <?= $programa['diasMes'] ?> &nbsp;·&nbsp;
  % esperado: <?= $programa['esperadoPct'] ?>%
</p>

<!-- Leyenda -->
<table style="border:none; border-collapse:collapse; margin-bottom:5px;">
  <tr>
    <td style="border:none; padding:1px 2px;">
      <table style="border-collapse:collapse; border:none;">
        <tr>
          <td style="border:none; padding:0 2px; font-size:10px; color:#7B2D8B; line-height:1;">&#9632;</td>
          <td style="border:none; padding:0 8px 0 2px; font-size:7.5px;">Por encima</td>
          <td style="border:none; padding:0 2px; font-size:10px; color:#1C693A; line-height:1;">&#9632;</td>
          <td style="border:none; padding:0 8px 0 2px; font-size:7.5px;">En meta</td>
          <td style="border:none; padding:0 2px; font-size:10px; color:#B8860B; line-height:1;">&#9632;</td>
          <td style="border:none; padding:0 8px 0 2px; font-size:7.5px;">Ligeramente bajo</td>
          <td style="border:none; padding:0 2px; font-size:10px; color:#9A1C1C; line-height:1;">&#9632;</td>
          <td style="border:none; padding:0 0 0 2px; font-size:7.5px;">Muy por debajo</td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<table class="prog-table" style="width:100%;">
  <thead>
    <tr>
      <th class="prog-th-g1" style="width:46%;">TIPO</th>
      <th class="prog-th-g1" style="width:18%;">PROD ACUM</th>
      <th class="prog-th-g1" style="width:18%;">PROG</th>
      <th class="prog-th-g1" style="width:18%;">% AVANCE</th>
    </tr>
  </thead>
  <tbody>

  <?php
  $esperadoPct   = (float)$programa['esperadoPct'];
  $primeraCategoria = true;

  foreach ($programa['grupos'] as $idCat => $cat):
      if (!is_array($cat) || !isset($cat['_prods'])) continue;
      $total     = $cat['_total'];
      $nombreCat = strtoupper($cat['_nombre'] ?? 'GENERAL');
  ?>

    <?php if (!$primeraCategoria): ?>
      <tr><td colspan="4" style="height:4px; border:none; background:#FFFFFF;"></td></tr>
    <?php endif; $primeraCategoria = false; ?>

    <?php
    $filaIdx = 0;
    foreach ($cat['_prods'] as $nombreProd => $etapas):
        if (!is_array($etapas)) continue;

        // ── Acumular totales por producto ──
        $prodUstd = 0;
        $prodPlan = 0;
        foreach ($etapas as $etapa => $info) {
            if (!is_array($info)) continue;
            $prodUstd += (float)($info['ustd'] ?? 0);
            $prodPlan += (float)($info['plan'] ?? 0);
        }
        $prodAvance = $prodPlan > 0 ? round(($prodUstd / $prodPlan) * 100, 1) : 0;
        $prodColor  = calcularColorLocal($prodAvance, $esperadoPct);
    ?>

      <!-- Fila separadora: Producto -->
      <tr>
        <td colspan="4" style="<?= $tdL ?> background-color:#EDF2F4; font-weight:bold; color:#496472; border-left:1.5px solid #799AAC; padding-left:6px;">
          <?= htmlspecialchars($nombreProd) ?>
        </td>
      </tr>

      <!-- Filas de etapas -->
      <?php foreach ($etapas as $etapa => $info):
          if (!is_array($info)) continue;
          $bg = $filaIdx % 2 === 0 ? '#FFFFFF' : '#F7FAFB';
          $filaIdx++;
      ?>
        <tr>
          <td style="<?= $tdL ?> background-color:<?= $bg ?>; padding-left:14px;"><?= htmlspecialchars($info['etapa']) ?></td>
          <td style="<?= $tdB ?> background-color:<?= $bg ?>;"><?= fmtN($info['ustd']) ?></td>
          <td style="<?= $tdB ?> background-color:<?= $bg ?>;"><?= fmtN($info['plan']) ?></td>
          <td style="<?= $tdB ?> <?= colorStyle($info['color']) ?>"><?= $info['avancePct'] ?>%</td>
        </tr>
      <?php endforeach; ?>

      <!-- CAMBIO 4: Total por producto -->
      <?php $countEtapas = count(array_filter($etapas, 'is_array')); ?>
      <?php if ($countEtapas > 1): // Solo mostrar si hay más de una etapa ?>
        <tr class="prog-total-prod">
          <td style="<?= $tdL ?> background-color:#dbeaf0; font-weight:bold; color:#496472; padding-left:8px; border-top:0.5px solid #799AAC;">
            Total <?= htmlspecialchars($nombreProd) ?>
          </td>
          <td style="<?= $tdB ?> background-color:#dbeaf0; font-weight:bold; border-top:0.5px solid #799AAC;"><?= fmtN($prodUstd) ?></td>
          <td style="<?= $tdB ?> background-color:#dbeaf0; font-weight:bold; border-top:0.5px solid #799AAC;"><?= fmtN($prodPlan) ?></td>
          <td style="<?= $tdB ?> border-top:0.5px solid #799AAC; <?= colorStyle($prodColor) ?>"><?= $prodAvance ?>%</td>
        </tr>
      <?php endif; ?>

    <?php endforeach; ?>

    <!-- Total categoría -->
    <tr>
      <td style="<?= $tdL ?> background-color:#D0E4EC; font-weight:bold; color:#496472; border-top:1px solid #496472;">TOTAL <?= htmlspecialchars($nombreCat) ?></td>
      <td style="<?= $tdB ?> background-color:#D0E4EC; font-weight:bold; border-top:1px solid #496472;"><?= fmtN($total['ustd']) ?></td>
      <td style="<?= $tdB ?> background-color:#D0E4EC; font-weight:bold; border-top:1px solid #496472;"><?= fmtN($total['plan']) ?></td>
      <td style="<?= $tdB ?> border-top:1px solid #496472; <?= colorStyle($total['color']) ?>"><?= $total['avancePct'] ?>%</td>
    </tr>

  <?php endforeach; ?>

    <tr><td colspan="4" style="height:4px; border:none; background:#FFFFFF;"></td></tr>

    <!-- Total General -->
    <?php $tg = $programa['totalGeneral']; ?>
    <tr>
      <td style="<?= $tdL ?> background-color:#496472; color:#FFFFFF; font-weight:bold; border-top:1.5px solid #2C3E50;">TOTAL <?= htmlspecialchars(strtoupper($programa['nombreDepto'] ?? 'GENERAL')) ?></td>
      <td style="<?= $tdB ?> background-color:#496472; color:#FFFFFF; font-weight:bold; border-top:1.5px solid #2C3E50;"><?= fmtN($tg['ustd']) ?></td>
      <td style="<?= $tdB ?> background-color:#496472; color:#FFFFFF; font-weight:bold; border-top:1.5px solid #2C3E50;"><?= fmtN($tg['plan']) ?></td>
      <td style="<?= $tdB ?> border-top:1.5px solid #2C3E50; <?= colorStyle($tg['color']) ?>"><?= $tg['avancePct'] ?>%</td>
    </tr>

  </tbody>
</table>