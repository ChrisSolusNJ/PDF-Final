<?php
require_once '../../vendor/tecnickcom/tcpdf/tcpdf.php';

class PdfGenerator
{
    private TCPDF $pdf;

    // ── Dimensiones página Legal horizontal (355.6 × 215.9 mm) ──
    private float $pageW    = 355.6;
    private float $pageH    = 215.9;
    private float $marginL  = 8.0;
    private float $marginR  = 8.0;
    private float $marginT  = 22.0;
    private float $marginB  = 8.0;

    // ── Ancho útil y proporciones columnas ──
    private float $usableW;
    private float $colLeftW;   // 66%
    private float $colRightW;  // 34%
    private float $colSepW = 2.0;

    // ── Colores ──
    private array $cHeader1  = [73,  100, 114]; // #496472
    private array $cHeader2  = [121, 154, 172]; // #799AAC
    private array $cMaq      = [184, 205, 214]; // #B8CDD6
    private array $cProd     = [237, 242, 244]; // #EDF2F4
    private array $cTotalMaq = [208, 228, 236]; // #D0E4EC
    private array $cTotalProd= [219, 234, 240]; // #dbeaf0
    private array $cWhite    = [255, 255, 255];
    private array $cAlt      = [247, 250, 251]; // #F7FAFB
    private array $cText     = [26,  26,  26];  // #1A1A1A
    private array $cAccent   = [73,  100, 114]; // #496472 texto énfasis
    private array $cBorder   = [159, 186, 197]; // #9FBAC5
    private array $cMorado   = [123, 45,  139]; // #7B2D8B
    private array $cVerde    = [28,  105, 58];  // #1C693A
    private array $cAmarillo = [184, 134, 11];  // #B8860B
    private array $cRojo     = [154, 28,  28];  // #9A1C1C

    // ── Fuentes y tamaños ──
    private string $font    = 'helvetica';
    private float  $fsBase  = 5.5;  // tamaño base celdas
    private float  $fsSmall = 5.0;  // claves/etapas
    private float  $fsTh    = 6.0;  // encabezados tabla
    private float  $fsTitle = 8.0; // título operación
    private float  $fsDepto = 10.0; // título departamento

    // ── Alturas de fila ──
    private float $rowH     = 4.2;  // fila normal
    private float $rowHTh   = 4.0;  // fila encabezado

    public function __construct()
    {
        $this->pdf = new TCPDF('L', 'mm', 'A3', true, 'UTF-8', false);

        $this->pdf->SetCreator('Prosede');
        $this->pdf->SetAuthor('Reporte Diario');
        $this->pdf->SetTitle('Reporte Diario');

        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);

        $this->pdf->SetMargins($this->marginL, $this->marginT, $this->marginR);
        $this->pdf->SetAutoPageBreak(false, $this->marginB);

        $this->pdf->SetFont($this->font, '', $this->fsBase);

        $this->usableW  = $this->pageW - $this->marginL - $this->marginR;
        $this->colLeftW = round($this->usableW * 0.66, 2);
        $this->colRightW= round($this->usableW - $this->colLeftW - $this->colSepW, 2);
    }

    // ══════════════════════════════════════════════════════════════
    // API PÚBLICA
    // ══════════════════════════════════════════════════════════════

    public function iniciar(array $fechas, array $tablas, array $programa, string $fecha, string $logoPath = ''): void
    {
        // Página horizontal — solo producción
        $this->pdf->AddPage('L', 'LETTER');
        $this->dibujarHeader($fecha, $logoPath);
        $this->dibujarSoloProduccion($fechas, $tablas, $programa['nombreDepto'] ?? '');

        // Página vertical — solo programa (Letter)
        $this->pdf->AddPage('P', 'LETTER');
        $this->dibujarHeaderPortrait($fecha, $logoPath);
        $this->dibujarSoloPrograma($programa);
    }

    public function agregarSeccion(array $fechas, array $tablas, array $programa, string $nombreDepto): void
    {
        $programa['nombreDepto'] = $nombreDepto;

        // Página horizontal — solo producción
        $this->pdf->AddPage('L', 'LETTER');
        $this->dibujarHeader('', '');
        $this->dibujarSoloProduccion($fechas, $tablas, $nombreDepto);

        // Página vertical — solo programa (Letter)
        $this->pdf->AddPage('P', 'LETTER');
        $this->dibujarHeaderPortrait('', '');
        $this->dibujarSoloPrograma($programa);
    }

    public function finalizar(string $nombreArchivo = 'reporte.pdf'): void
    {
        $this->pdf->Output($nombreArchivo, 'I');
    }

    // ══════════════════════════════════════════════════════════════
    // HEADER
    // ══════════════════════════════════════════════════════════════

    private function dibujarHeader(string $fecha, string $logoPath): void
    {
        $y = $this->marginT - 14;
        $x = $this->marginL;
        $w = $this->usableW;

        // Logo
        if ($logoPath && file_exists($logoPath)) {
            $this->pdf->Image($logoPath, $x, $y, 0, 8, '', '', '', false, 300);
        }

        // Título central
        $this->pdf->SetFont($this->font, 'B', 11);
        $this->setTextColor($this->cHeader1);
        $this->pdf->SetXY($x+100, $y);
        $this->pdf->Cell(70, 8, 'Reporte Diario', 0, 0, 'C');

        // Fecha derecha
        if ($fecha) {
            $this->pdf->SetFont($this->font, '', 8);
            $this->setTextColor([119, 119, 119]);
            $this->pdf->SetXY($x+190, $y);
            $this->pdf->Cell(70, 8, $this->formatFecha($fecha), 0, 0, 'R');
        }

        // Línea separadora
        $lineY = $y + 9;
        $this->setDrawColor($this->cHeader1);
        $this->pdf->SetLineWidth(0.5);
        $this->pdf->Line($x, $lineY,268, $lineY);

        $this->pdf->SetLineWidth(0.2);
    }

    // ══════════════════════════════════════════════════════════════
    // HEADER PORTRAIT
    // ══════════════════════════════════════════════════════════════

    private function dibujarHeaderPortrait(string $fecha, string $logoPath): void
    {
        // En portrait A3: 297mm ancho, 420mm alto
        $y = $this->marginT - 14;
        // Letter portrait: ancho útil
        $w = 215.9 - $this->marginL - $this->marginR;

        if ($logoPath && file_exists($logoPath)) {
            $this->pdf->Image($logoPath, $this->marginL, $y, 0, 8, '', '', '', false, 300);
        }

        $this->pdf->SetFont($this->font, 'B', 11);
        $this->setTextColor($this->cHeader1);
        $this->pdf->SetXY($this->marginL, $y);
        $this->pdf->Cell($w, 8, 'Reporte Diario', 0, 0, 'C');

        if ($fecha) {
            $this->pdf->SetFont($this->font, '', 8);
            $this->setTextColor([119, 119, 119]);
            $this->pdf->SetXY($this->marginL, $y);
            $this->pdf->Cell($w, 8, $this->formatFecha($fecha), 0, 0, 'R');
        }

        $lineY = $y + 9;
        $this->setDrawColor($this->cHeader1);
        $this->pdf->SetLineWidth(0.5);
        $this->pdf->Line($this->marginL, $lineY, $this->marginL + $w, $lineY);
        $this->pdf->SetLineWidth(0.2);
    }

    // ══════════════════════════════════════════════════════════════
    // PRODUCCIÓN — página horizontal completa
    // ══════════════════════════════════════════════════════════════

    private function dibujarSoloProduccion(array $fechas, array $tablas, string $nombreDepto): void
    {
        $x      = $this->marginL;
        $yStart = $this->marginT;
        $yMax   = $this->pageH - $this->marginB;

        // Título departamento
        $this->pdf->SetFont($this->font, 'B', $this->fsDepto);
        $this->setTextColor($this->cHeader1);
        $this->pdf->SetXY($x, $yStart);
        $this->pdf->Cell($this->usableW, 6, $nombreDepto, 0, 0, 'L');
        $this->setDrawColor($this->cHeader1);
        $this->pdf->SetLineWidth(0.5);
        $tW = $this->pdf->GetStringWidth($nombreDepto) + 2;
        $this->pdf->Line($x, $yStart + 6.5, $x + $tW, $yStart + 6.5);
        $this->pdf->SetLineWidth(0.2);

        $y      = $yStart + 9;
        $pagina = $this->pdf->getPage();

        foreach ($tablas as $categoria => $maquinas) {
            $alturaEstimada = $this->estimarAlturaTabla($maquinas);
            if ($y + $alturaEstimada > $yMax) {
                $this->pdf->AddPage('L', 'LETTER');
                $this->dibujarHeader('', '');
                $y = $this->marginT;
                $pagina++;
            }
            $y = $this->dibujarTablaUSTD($fechas, $categoria, $maquinas, $x, $y, $yMax, $pagina, $this->usableW);
            $pagina = $this->pdf->getPage();
            $y += 3;
        }
    }

    // ══════════════════════════════════════════════════════════════
    // PROGRAMA — página vertical completa
    // ══════════════════════════════════════════════════════════════

    private function dibujarSoloPrograma(array $programa, int $decimales = 0): void
    {
        // Letter portrait: 215.9mm × 279.4mm
        $wPortrait = 215.9 - $this->marginL - $this->marginR;
        $yStart    = $this->marginT;
        $yMax      = 279.4 - $this->marginB;

        $nombreDepto = $programa['nombreDepto'] ?? '';

        // Título departamento
        $this->pdf->SetFont($this->font, 'B', $this->fsDepto);
        $this->setTextColor($this->cHeader1);
        $this->pdf->SetXY($this->marginL, $yStart);
        $this->pdf->Cell($wPortrait, 6, $nombreDepto, 0, 0, 'L');
        $this->setDrawColor($this->cHeader1);
        $this->pdf->SetLineWidth(0.5);
        $tW = $this->pdf->GetStringWidth($nombreDepto) + 2;
        $this->pdf->Line($this->marginL, $yStart + 6.5, $this->marginL + $tW, $yStart + 6.5);
        $this->pdf->SetLineWidth(0.2);

        $y           = $yStart + 9;
        $esperadoPct = (float)$programa['esperadoPct'];

        // Proporciones columnas para portrait — más ancho para TIPO
        $wTipo   = round($wPortrait * 0.44, 2);
        $wConfig = round($wPortrait * 0.07, 2);
        $wProd   = round($wPortrait * 0.16, 2);
        $wProg   = round($wPortrait * 0.16, 2);
        $wAva    = round($wPortrait - $wTipo - $wConfig - $wProd - $wProg, 2);

        // Nota día
        $this->pdf->SetFont($this->font, '', 7.0);
        $this->setTextColor([119, 119, 119]);
        $this->pdf->SetXY($this->marginL, $y);
        $this->pdf->Cell($wPortrait, 4, 'Día ' . $programa['diaActual'] . ' de ' . $programa['diasMes'] . '  ·  % esperado: ' . $programa['esperadoPct'] . '%', 0, 0, 'L');
        $y += 4.5;

        // Leyenda
        $y = $this->dibujarLeyenda($this->marginL, $y, $wPortrait);

        // Encabezado tabla
        $this->pdf->SetFont($this->font, 'B', $this->fsTh);
        $this->setFillColor($this->cHeader1);
        $this->setTextColor($this->cWhite);
        $cx = $this->marginL;
        foreach ([[$wTipo, 'TIPO'], [$wConfig, 'NO CONFIG'], [$wProd, 'PROD ACUM'], [$wProg, 'PROG'], [$wAva, '% AVANCE']] as [$wC, $lbl]) {
            $this->pdf->SetXY($cx, $y);
            $this->pdf->Cell($wC, $this->rowHTh, $lbl, $this->border(), 0, 'C', true);
            $cx += $wC;
        }
        $y += $this->rowHTh;

        $primeraCat = true;

        foreach ($programa['grupos'] as $idCat => $cat) {
            if (!is_array($cat) || !isset($cat['_prods'])) continue;

            if (!$primeraCat) $y += 1.5;
            $primeraCat = false;

            $filaIdx = 0;

            foreach ($cat['_prods'] as $nombreProd => $etapas) {
                if (!is_array($etapas)) continue;

                $prodUstd = 0; $prodPlan = 0;
                foreach ($etapas as $info) {
                    if (!is_array($info)) continue;
                    $prodUstd += (float)($info['ustd'] ?? 0);
                    $prodPlan += (float)($info['plan'] ?? 0);
                }
                $prodAvance = $prodPlan > 0 ? round(($prodUstd / $prodPlan) * 100, 1) : 0;
                $prodColor  = $this->calcularColor($prodAvance, $esperadoPct);

                // Verificar espacio — saltar a nueva página portrait
                if ($y + $this->rowH > $yMax) {
                    $this->pdf->AddPage('P', 'LETTER');
                    $this->dibujarHeaderPortrait('', '');
                    $y = $this->marginT;
                }

                $this->pdf->SetFont($this->font, 'B', $this->fsSmall);
                $this->setFillColor($this->cProd);
                $this->setTextColor($this->cAccent);
                $this->pdf->SetXY($this->marginL, $y);
                $this->pdf->Cell($wPortrait, $this->rowH, '  ' . $nombreProd, $this->border(), 0, 'L', true);
                $y += $this->rowH;

                foreach ($etapas as $etapa => $info) {
                    if (!is_array($info)) continue;

                    if ($y + $this->rowH > $yMax) {
                        $this->pdf->AddPage('P', 'LETTER');
                        $this->dibujarHeaderPortrait('', '');
                        $y = $this->marginT;
                    }

                    $bg = $filaIdx % 2 === 0 ? $this->cWhite : $this->cAlt;
                    $filaIdx++;

                    $this->pdf->SetFont($this->font, '', $this->fsSmall);
                    $this->setFillColor($bg);
                    $this->setTextColor($this->cText);
                    $cx = $this->marginL;
                    $this->pdf->SetXY($cx, $y);
                    $this->pdf->Cell($wTipo, $this->rowH, '    ' . $info['etapa'], $this->border(), 0, 'L', true); $cx += $wTipo;
                    $this->pdf->SetXY($cx, $y);
                    $this->pdf->Cell($wConfig, $this->rowH, $info['config'] ?? '', $this->border(), 0, 'C', true); $cx += $wConfig;
                    $this->pdf->SetXY($cx, $y);
                    $this->pdf->Cell($wProd, $this->rowH, $this->fmtDec((float)$info['ustd'], $decimales), $this->border(), 0, 'C', true); $cx += $wProd;
                    $this->pdf->SetXY($cx, $y);
                    $this->pdf->Cell($wProg, $this->rowH, $this->fmtDec((float)$info['plan'], $decimales), $this->border(), 0, 'C', true); $cx += $wProg;
                    $this->dibujarCeldaAvance($cx, $y, $wAva, $info['avancePct'] . '%', $info['color']);
                    $y += $this->rowH;
                }

                // Total producto
                $countEtapas = count(array_filter($etapas, 'is_array'));
                if ($countEtapas > 1) {
                    if ($y + $this->rowH > $yMax) {
                        $this->pdf->AddPage('P', 'LETTER');
                        $this->dibujarHeaderPortrait('', '');
                        $y = $this->marginT;
                    }
                    $this->pdf->SetFont($this->font, 'B', $this->fsSmall);
                    $this->setFillColor($this->cTotalProd);
                    $this->setTextColor($this->cAccent);
                    $cx = $this->marginL;
                    $this->pdf->SetXY($cx, $y);
                    $this->pdf->Cell($wTipo, $this->rowH, '  Total ' . $nombreProd, $this->border('T'), 0, 'L', true); $cx += $wTipo;
                    $this->pdf->SetXY($cx, $y);
                    $this->pdf->Cell($wConfig, $this->rowH, '', $this->border('T'), 0, 'C', true); $cx += $wConfig;
                    $this->pdf->SetXY($cx, $y);
                    $this->pdf->Cell($wProd, $this->rowH, $this->fmtDec($prodUstd, $decimales), $this->border('T'), 0, 'C', true); $cx += $wProd;
                    $this->pdf->SetXY($cx, $y);
                    $this->pdf->Cell($wProg, $this->rowH, $this->fmtDec($prodPlan, $decimales), $this->border('T'), 0, 'C', true); $cx += $wProg;
                    $this->dibujarCeldaAvance($cx, $y, $wAva, $prodAvance . '%', $prodColor, true);
                    $y += $this->rowH;
                }
            }

            // Total categoría
            $total = $cat['_total'];
            if ($y + $this->rowH > $yMax) {
                $this->pdf->AddPage('P', 'LETTER');
                $this->dibujarHeaderPortrait('', '');
                $y = $this->marginT;
            }
            $nombreCat = strtoupper($cat['_nombre'] ?? 'GENERAL');
            $this->pdf->SetFont($this->font, 'B', $this->fsSmall);
            $this->setFillColor($this->cTotalMaq);
            $this->setTextColor($this->cAccent);
            $cx = $this->marginL;
            $this->pdf->SetXY($cx, $y);
            $this->pdf->Cell($wTipo, $this->rowH, '  TOTAL ' . $nombreCat, $this->border('T'), 0, 'L', true); $cx += $wTipo;
            $this->pdf->SetXY($cx, $y);
            $this->pdf->Cell($wConfig, $this->rowH, '', $this->border('T'), 0, 'C', true); $cx += $wConfig;
            $this->setTextColor($this->cText);
            $this->pdf->SetXY($cx, $y);
            $this->pdf->Cell($wProd, $this->rowH, $this->fmtDec((float)$total['ustd'], $decimales), $this->border('T'), 0, 'C', true); $cx += $wProd;
            $this->pdf->SetXY($cx, $y);
            $this->pdf->Cell($wProg, $this->rowH, $this->fmtDec((float)$total['plan'], $decimales), $this->border('T'), 0, 'C', true); $cx += $wProg;
            $this->dibujarCeldaAvance($cx, $y, $wAva, $total['avancePct'] . '%', $total['color'], true);
            $y += $this->rowH;
        }

        // Total general
        $y += 1.5;
        if ($y + $this->rowH > $yMax) {
            $this->pdf->AddPage('P', 'LETTER');
            $this->dibujarHeaderPortrait('', '');
            $y = $this->marginT;
        }
        $tg          = $programa['totalGeneral'];
        $nombreDepto = strtoupper($programa['nombreDepto'] ?? 'GENERAL');
        $this->pdf->SetFont($this->font, 'B', $this->fsSmall);
        $this->setFillColor($this->cHeader1);
        $this->setTextColor($this->cWhite);
        $cx = $this->marginL;
        $this->pdf->SetXY($cx, $y);
        $this->pdf->Cell($wTipo, $this->rowH, '  TOTAL ' . $nombreDepto, $this->border('T2'), 0, 'L', true); $cx += $wTipo;
        $this->pdf->SetXY($cx, $y);
        $this->pdf->Cell($wConfig, $this->rowH, '', $this->border('T2'), 0, 'C', true); $cx += $wConfig;
        $this->pdf->SetXY($cx, $y);
        $this->pdf->Cell($wProd, $this->rowH, $this->fmtDec((float)$tg['ustd'], $decimales), $this->border('T2'), 0, 'C', true); $cx += $wProd;
        $this->pdf->SetXY($cx, $y);
        $this->pdf->Cell($wProg, $this->rowH, $this->fmtDec((float)$tg['plan'], $decimales), $this->border('T2'), 0, 'C', true); $cx += $wProg;
        $this->dibujarCeldaAvance($cx, $y, $wAva, $tg['avancePct'] . '%', $tg['color'], true);
    }

    // ══════════════════════════════════════════════════════════════
    // DEPARTAMENTO — orquesta columnas independientes (legacy, mantenido por compatibilidad)
    // ══════════════════════════════════════════════════════════════

    private function dibujarDepartamento(array $fechas, array $tablas, array $programa): void
    {
        $xLeft  = $this->marginL;
        $xRight = $this->marginL + $this->colLeftW + $this->colSepW;
        $yStart = $this->marginT;
        $yMax   = $this->pageH - $this->marginB;

        $nombreDepto = $programa['nombreDepto'] ?? '';

        // ── Título departamento ──
        $this->pdf->SetFont($this->font, 'B', $this->fsDepto);
        $this->setTextColor($this->cHeader1);
        $this->pdf->SetXY($xLeft, $yStart);
        $this->pdf->Cell($this->colLeftW, 6, $nombreDepto, 0, 0, 'L');

        // Línea bajo título
        $this->setDrawColor($this->cHeader1);
        $this->pdf->SetLineWidth(0.5);
        $titleTextW = $this->pdf->GetStringWidth($nombreDepto) + 2;
        $this->pdf->Line($xLeft, $yStart + 6.5, $xLeft + $titleTextW, $yStart + 6.5);
        $this->pdf->SetLineWidth(0.2);

        $yContent = $yStart + 9;

        // Guardar página inicial del departamento
        $paginaInicioDepto = $this->pdf->getPage();

        // ── Dibujar programa primero (columna derecha) ──
        // Puede crear páginas nuevas si su contenido es largo
        $this->dibujarPrograma($programa, $xRight, $yContent, $yMax);
        $paginaTrasProg = $this->pdf->getPage();

        // ── Volver a la página inicial para dibujar producción ──
        $this->pdf->setPage($paginaInicioDepto);

        // ── Dibujar producción (columna izquierda) ──
        $yCursorLeft = $yContent;
        $paginaActual = $paginaInicioDepto;


        foreach ($tablas as $categoria => $maquinas) {

            $alturaEstimada = $this->estimarAlturaTabla($maquinas);

            // 🔑 VALIDACIÓN CLAVE
            if ($yCursorLeft + $alturaEstimada > $yMax) {
                $this->pdf->AddPage();
                $this->dibujarHeader('', '');
                $yCursorLeft = $this->marginT;
                $paginaActual++;
            }

            $yCursorLeft = $this->dibujarTablaUSTD(
                $fechas,
                $categoria,
                $maquinas,
                $xLeft,
                $yCursorLeft,
                $yMax,
                $paginaActual
            );

            $paginaActual = $this->pdf->getPage();
            $yCursorLeft += 3;
        }


        // ── Dejar el puntero en la última página usada ──
        $ultimaPagina = max($this->pdf->getPage(), $paginaTrasProg);
        $this->pdf->setPage($ultimaPagina);

        // ── Línea separadora — solo en la página inicial del depto ──
        $xSep = $xLeft + $this->colLeftW + ($this->colSepW / 2);
        $this->pdf->setPage($paginaInicioDepto);
        $this->setDrawColor([184, 205, 214]);
        $this->pdf->SetLineWidth(0.3);
        $this->pdf->Line($xSep, $yContent, $xSep, $yMax);
        $this->pdf->SetLineWidth(0.2);
        $this->pdf->setPage($ultimaPagina);
    }

    // ══════════════════════════════════════════════════════════════
    // TABLA USTD
    // ══════════════════════════════════════════════════════════════

    private function dibujarTablaUSTD(
        array $fechas, string $categoria, array $maquinas,
        float $x, float $y, float $yMax, int $pagina,
        float $anchoDisponible = 0
    ): float {
        if ($anchoDisponible <= 0) $anchoDisponible = $this->colLeftW;
        $this->pdf->setPage($pagina);

        // ── Título operación ──
        if ($y + 5 > $yMax) {
            $pagina++;
            $this->pdf->AddPage('L', 'LETTER');
            $this->dibujarHeader('', '');
            $y = $this->marginT;
        }

        $this->pdf->SetFont($this->font, 'B', $this->fsTitle);
        $this->setTextColor($this->cHeader1);
        $this->pdf->SetXY($x, $y);
        $this->pdf->Cell($anchoDisponible, 5, $categoria, 0, 0, 'L');

        // Línea bajo título operación
        $this->setDrawColor($this->cHeader1);
        $this->pdf->SetLineWidth(0.4);
        $tW = $this->pdf->GetStringWidth($categoria) + 2;
        $this->pdf->Line($x, $y + 5.5, $x + $tW, $y + 5.5);
        $this->pdf->SetLineWidth(0.2);

        $y += 7;

        // ── Definir anchos de columnas ──
        $nFechas  = count($fechas);
        $wMaq     = 11.0;
        $wPres    = 50.0;
        $wFecha   = round(($anchoDisponible * 0.40) / max($nFechas, 1), 2);
        $wAcum    = 10.0;
        $wProm    = 10.0;
        $wTP      = 8.5;
        $wMerma   = 8.5;

        // Ajustar wFecha si hay muchas fechas
        $totalFechasW = $wFecha * $nFechas;
        $totalFijo    = $wMaq + $wPres + $wAcum + $wProm + ($wTP * 2) + ($wMerma * 2);
        if ($totalFijo + $totalFechasW > $anchoDisponible) {
            $wFecha = round(($anchoDisponible - $totalFijo) / max($nFechas, 1), 2);
        }

        // ── Encabezados ──
        $y = $this->dibujarEncabezadoUSTD($fechas, $x, $y, $yMax, $pagina,
            $wMaq, $wPres, $wFecha, $wAcum, $wProm, $wTP, $wMerma);
        $pagina = $this->pdf->getPage();

        // ── Filas de máquinas ──
        $totalOp = $maquinas['_total_operacion'];
        unset($maquinas['_total_operacion']);

        foreach ($maquinas as $nombreMaq => $dataMaq) {
            [$y, $pagina] = $this->dibujarMaquina(
                $fechas, $nombreMaq, $dataMaq, $x, $y, $yMax, $pagina,
                $wMaq, $wPres, $wFecha, $wAcum, $wProm, $wTP, $wMerma
            );
        }

        // ── Total operación ──
        [$y, $pagina] = $this->verificarEspacio($y, $this->rowH, $yMax, $pagina, $x);
        $this->dibujarFilaTotalOp($fechas, $categoria, $totalOp, $x, $y,
            $wMaq, $wPres, $wFecha, $wAcum, $wProm, $wTP, $wMerma);
        $y += $this->rowH;

        return $y;
    }

    private function dibujarEncabezadoUSTD(
        array $fechas, float $x, float $y, float $yMax, int $pagina,
        float $wMaq, float $wPres, float $wFecha,
        float $wAcum, float $wProm, float $wTP, float $wMerma
    ): float {
        [$y, $pagina] = $this->verificarEspacio($y, $this->rowHTh * 2, $yMax, $pagina, $x);
        $this->pdf->setPage($pagina);

        $nF = count($fechas);

        // Fila 1 encabezados agrupados
        $this->pdf->SetFont($this->font, 'B', $this->fsTh);
        $this->setFillColor($this->cHeader1);
        $this->pdf->SetDrawColor(159, 186, 197); // Color RGB
        $this->setTextColor($this->cWhite);

        $cx = $x;
        $this->pdf->SetXY($cx, $y);
        $this->pdf->Cell($wMaq,  $this->rowHTh, 'Máquina',     $this->border(), 0, 'C', true);
        $cx += $wMaq;
        $this->pdf->SetXY($cx, $y);
        $this->pdf->Cell($wPres+13, $this->rowHTh, 'Presentación', $this->border(), 0, 'C', true);
        $cx += $wPres;

        $wFechasTotal = $wFecha * $nF;
        $this->pdf->SetXY($cx, $y);
        $this->pdf->SetDrawColor(159, 186, 197); // Color RGB
        $this->pdf->SetXY($cx+13, $y);
        $this->pdf->Cell($wFechasTotal - 28, $this->rowHTh, 'USTD Días Anteriores', 1, 0, 'C', true);
        $cx += $wFechasTotal;

        $this->pdf->SetLineWidth(0.2);
        $this->pdf->SetDrawColor(159, 186, 197); // Color RGB
        $this->pdf->SetXY($cx-15, $y);
        $this->pdf->Cell($wAcum + $wProm + 6, $this->rowHTh, 'USTD', $this->border(), 0, 'C', true);
        $cx += $wAcum + $wProm;

        $this->pdf->SetDrawColor(159, 186, 197); // Color RGB
        $this->pdf->SetXY($cx-9, $y);
        $this->pdf->Cell($wAcum + $wProm + 6, $this->rowHTh, '% TP', $this->border(), 0, 'C', true);
        $cx += $wTP * 2;

        $this->pdf->SetDrawColor(159, 186, 197); // Color RGB
        $this->pdf->SetXY($cx, $y);
        $this->pdf->Cell($wAcum + $wProm + 6, $this->rowHTh, '% Merma', $this->border(), 0, 'C', true);

        $y += $this->rowHTh;

        // Fila 2: fechas individuales + Acum/Prom/Día/Acum
        $this->setFillColor($this->cHeader2);
        $cx = $x + $wMaq + $wPres;

        foreach ($fechas as $f) {
            $this->pdf->SetXY($cx + 13, $y);
            $this->pdf->Cell($wFecha-4, $this->rowHTh, $this->formatFechaDia($f), $this->border(), 0, 'C', true);
            $cx += $wFecha-4;
        }

        $this->setFillColor($this->cHeader2);
        $this->pdf->SetXY(8, $y);
        $this->pdf->Cell(74, $this->rowHTh, '', $this->border(), 0, 'C', true);
        foreach (['Acum', 'Prom', 'Día', 'Acum', 'Día', 'Acum'] as $lbl) {
            $w = in_array($lbl, ['Acum', 'Prom']) ? ($lbl === 'Acum' ? $wAcum : $wAcum) : ($lbl === 'Día' ? $wAcum : $wAcum);
            // alternar entre TP y Merma
            $this->pdf->SetXY($cx + 13, $y);
            $this->pdf->Cell($wAcum+3, $this->rowHTh, $lbl, $this->border(), 0, 'C', true);
            $cx += $wAcum + 3;
        }

        $y += $this->rowHTh;
        return $y;
    }

    private function dibujarMaquina(
        array $fechas, string $nombreMaq, array $dataMaq,
        float $x, float $y, float $yMax, int $pagina,
        float $wMaq, float $wPres, float $wFecha,
        float $wAcum, float $wProm, float $wTP, float $wMerma
    ): array {
        $sinDatos = empty($dataMaq['productos']);

        if ($sinDatos) {
            [$y, $pagina] = $this->verificarEspacio($y, $this->rowH, $yMax, $pagina, $x);
            $this->dibujarFilaMaquinaSinDatos($fechas, $nombreMaq, $x, $y, $wMaq, $wPres, $wFecha, $wAcum, $wProm, $wTP, $wMerma);
            $y += $this->rowH;
        } else {
            // Contar filas totales para el rowspan simulado
            $totalFilas = 0;
            foreach ($dataMaq['productos'] as $prod) {
                $totalFilas += 1 + count($prod['claves']);
            }
            $alturaTotal     = $totalFilas * $this->rowH;
            $alturaNecesaria = $alturaTotal + $this->rowH; // +1 para la fila Total máquina

            // Si la máquina completa no cabe en el espacio restante, saltar a página nueva
            // antes de empezar a dibujarla (evita que yMaqStart quede en una página y las
            // filas terminen en otra, generando celdas con altura incorrecta).
            $espacioRestante = $yMax - $y;
            if ($alturaNecesaria > $espacioRestante && $y > $this->marginT + 20) {
                $this->pdf->AddPage('L', 'LETTER');
                $this->dibujarHeader('', '');
                $pagina++;
                $y = $this->marginT;
                $this->pdf->setPage($pagina);
            }

            $yMaqStart  = $y;
            $primerFila = true;
            $filaIdx    = 0;

            foreach ($dataMaq['productos'] as $nombreProd => $producto) {
                // Fila producto — verificar espacio y detectar salto de página
                $paginaAntesProd = $pagina;
                [$y, $pagina] = $this->verificarEspacio($y, $this->rowH, $yMax, $pagina, $x);

                if ($primerFila) {
                    // Celda máquina — se dibuja después cuando sepamos la altura total
                    $yMaqStart = $y;
                } elseif ($pagina !== $paginaAntesProd) {
                    // Hubo salto de página en medio de la máquina:
                    // cerrar la celda de máquina parcial en la página anterior
                    $alturaParcial = $yMax - $yMaqStart;
                    if ($alturaParcial > 0) {
                        $this->pdf->setPage($paginaAntesProd);
                        $this->pdf->SetFont($this->font, 'B', $this->fsSmall);
                        $this->setFillColor($this->cMaq);
                        $this->setTextColor($this->cText);
                        $this->pdf->SetXY($x, $yMaqStart);
                        $this->pdf->Cell($wMaq, $alturaParcial, $nombreMaq, $this->border(), 0, 'C', true);
                        $this->pdf->setPage($pagina);
                    }
                    // Reiniciar yMaqStart en la nueva página
                    $yMaqStart = $y;
                }

                $cx = $x + $wMaq;
                $wProdSpan = $wPres + ($wFecha * count($fechas));
                $this->pdf->SetFont($this->font, 'B', $this->fsSmall);
                $this->setFillColor($this->cProd);
                $this->setTextColor($this->cAccent);
                $this->pdf->SetXY($cx, $y);
                $this->pdf->Cell($wProdSpan-15, $this->rowH, '  ' . $nombreProd, $this->border(), 0, 'L', true);

                if ($primerFila) {
                    $primerFila = false;
                }

                $y += $this->rowH;

                // Filas de claves
                foreach ($producto['claves'] as $clave => $info) {
                    $paginaAntesClave = $pagina;
                    [$y, $pagina] = $this->verificarEspacio($y, $this->rowH, $yMax, $pagina, $x);

                    // Si hubo salto dentro de las claves, cerrar celda parcial y reiniciar
                    if ($pagina !== $paginaAntesClave) {
                        $alturaParcial = $yMax - $yMaqStart;
                        if ($alturaParcial > 0) {
                            $this->pdf->setPage($paginaAntesClave);
                            $this->pdf->SetFont($this->font, 'B', $this->fsSmall);
                            $this->setFillColor($this->cMaq);
                            $this->setTextColor($this->cText);
                            $this->pdf->SetXY($x, $yMaqStart);
                            $this->pdf->Cell($wMaq, $alturaParcial, $nombreMaq, $this->border(), 0, 'C', true);
                            $this->pdf->setPage($pagina);
                        }
                        $yMaqStart = $y;
                    }

                    $bg = $filaIdx % 2 === 0 ? $this->cWhite : $this->cAlt;
                    $filaIdx++;

                    $cx = $x + $wMaq;
                    $this->pdf->SetFont($this->font, '', $this->fsSmall);
                    $this->setFillColor($bg);
                    $this->setTextColor($this->cText);
                    $this->pdf->SetXY($cx, $y);
                    $this->pdf->Cell($wPres + 13, $this->rowH, '  ' . $info['etapa'], $this->border(), 0, 'L', true);
                    $cx += $wPres;

                    foreach ($fechas as $f) {
                        $v = $info['dias'][$f] ?? null;
                        $txt = ($v === null || $v == 0) ? '—' : $this->fmtNum($v);
                        $this->setTextColor(($v === null || $v == 0) ? [187, 187, 187] : $this->cText);
                        $this->pdf->SetXY($cx+13, $y);
                        $this->pdf->Cell($wFecha-4, $this->rowH, $txt, $this->border(), 0, 'C', true);
                        $cx += $wFecha-4;
                    }

                    $y += $this->rowH;
                }

                // ── Filtrar claves sin datos en los 7 días ──
                $producto['claves'] = array_filter(
                    $producto['claves'],
                    fn($info) => array_sum(array_map(fn($v) => (float)($v ?? 0), $info['dias'])) > 0
                );
            }

            // ── Eliminar productos sin claves ──
            $dataMaq['productos'] = array_filter(
                $dataMaq['productos'],
                fn($p) => !empty($p['claves'])
            );

            // Dibujar celda máquina (toda la altura acumulada)
            $alturaMaq = $y - $yMaqStart;
            $this->pdf->SetFont($this->font, 'B', $this->fsSmall);
            $this->setFillColor($this->cMaq);
            $this->setTextColor($this->cText);
            $this->pdf->SetXY($x, $yMaqStart);
            $this->pdf->Cell($wMaq, $alturaMaq, $nombreMaq, $this->border(), 0, 'C', true);

            // ── Indicadores centrados verticalmente ──
            $yCentrado = $yMaqStart + ($alturaMaq - $this->rowH) / 2;
            $cx2 = $x + $wMaq + $wPres + ($wFecha * count($fechas));
            foreach ([
                [$wAcum + 3, $this->fmtNum($dataMaq['total_maquina']['acum'])],
                [$wAcum + 3, $this->fmtNum($dataMaq['total_maquina']['prom'])],
                [$wAcum + 3, $dataMaq['tp_dia']],
                [$wAcum + 3, $dataMaq['tp_acum']],
                [$wAcum + 3, $dataMaq['merma_dia']],
                [$wAcum + 3, $dataMaq['merma_acum']],
            ] as [$wC, $val]) {
                $this->setFillColor($this->cProd);
                $this->setTextColor($this->cText);
                $this->pdf->SetXY($cx2-15, $yMaqStart);
                $this->pdf->Cell($wC, $alturaMaq, '', $this->border(), 0, 'C', true);
                $this->pdf->SetFont($this->font, 'B', $this->fsSmall);
                $this->pdf->SetXY($cx2 - 15, $yCentrado);
                $this->pdf->Cell($wC, $this->rowH, $val, 0, 0, 'C');
                $cx2 += $wC;
            }
        }

        // Total máquina
        [$y, $pagina] = $this->verificarEspacio($y, $this->rowH, $yMax, $pagina, $x);
        $this->dibujarFilaTotalMaq($fechas, $nombreMaq, $dataMaq, $x, $y, $wMaq, $wPres, $wFecha, $wAcum, $wProm, $wTP, $wMerma);
        $y += $this->rowH;

        return [$y, $pagina];
    }

    private function dibujarFilaMaquinaSinDatos(
        array $fechas, string $nombreMaq, float $x, float $y,
        float $wMaq, float $wPres, float $wFecha, float $wAcum, float $wProm, float $wTP, float $wMerma
    ): void {
        $this->pdf->SetFont($this->font, 'B', $this->fsSmall);
        $this->setFillColor($this->cMaq);
        $this->setTextColor($this->cText);
        $this->pdf->SetXY($x, $y);
        $this->pdf->Cell($wMaq, $this->rowH, $nombreMaq, $this->border(), 0, 'C', true);

        $cx = $x + $wMaq;
        $this->setFillColor($this->cWhite);
        $this->setTextColor([187, 187, 187]);
        $this->pdf->SetFont($this->font, '', $this->fsSmall);
        $this->pdf->SetXY($cx, $y);
        $this->pdf->Cell($wPres + 20, $this->rowH, '—', $this->border(), 0, 'C', true);
        $cx += $wPres;

        foreach ($fechas as $f) {
            $this->pdf->SetXY($cx + 20, $y);
            $this->pdf->Cell($wFecha, $this->rowH, '—', $this->border(), 0, 'C', true);
            $cx += $wFecha;
        }

        foreach ([$wAcum, $wAcum, $wAcum, $wAcum, $wAcum, $wAcum] as $wC) {
            $this->pdf->SetXY($cx+20, $y);
            $this->pdf->Cell($wC + 3, $this->rowH, '', $this->border(), 0, 'C', true);
            $cx += $wC + 3;
        }
    }

    private function dibujarFilaTotalMaq(
        array $fechas, string $nombreMaq, array $dataMaq, float $x, float $y,
        float $wMaq, float $wPres, float $wFecha, float $wAcum, float $wProm, float $wTP, float $wMerma
    ): void {
        $this->pdf->SetFont($this->font, 'B', $this->fsSmall);
        $this->setFillColor($this->cTotalMaq);
        $this->setTextColor($this->cAccent);
        

        $this->pdf->SetXY($x, $y);
        $this->pdf->SetDrawColor(159, 186, 197); // Color RGB
        $this->pdf->Cell($wMaq + $wPres + 13, $this->rowH, '  Total ' . $nombreMaq, $this->border(), 0, 'L', true);

        $cx = $x + $wMaq + $wPres;
        $this->setTextColor($this->cText);

        foreach ($fechas as $f) {
            $v = $dataMaq['total_maquina']['dias'][$f] ?? 0;
            $txt = $v > 0 ? $this->fmtNum($v) : '—';
            $this->setTextColor($v > 0 ? $this->cText : [187, 187, 187]);
            $this->pdf->SetXY($cx+13, $y);
            $this->pdf->SetDrawColor(159, 186, 197); // Color RGB
            $this->pdf->Cell($wFecha - 4, $this->rowH, $txt, $this->border(), 0, 'C', true);
            $cx += $wFecha - 4;
        }

        $this->setTextColor($this->cText);
        $this->pdf->SetXY($cx+13, $y);
        $this->pdf->SetDrawColor(159, 186, 197); // Color RGB
        $this->pdf->Cell($wAcum + 68, $this->rowH, '', $this->border(), 0, 'C', true);
        // foreach ([$wAcum, $wAcum, $wAcum, $wAcum, $wAcum, $wAcum] as $wC) {
            
            
        //     $cx += $wC;
        // }
    }

    private function dibujarFilaTotalOp(
        array $fechas, string $categoria, array $totalOp, float $x, float $y,
        float $wMaq, float $wPres, float $wFecha, float $wAcum, float $wProm, float $wTP, float $wMerma
    ): void {
        $this->pdf->SetFont($this->font, 'B', $this->fsSmall);
        $this->setFillColor($this->cHeader1);
        $this->setTextColor($this->cWhite);

        $this->pdf->SetXY($x, $y);
        $this->pdf->Cell($wMaq + $wPres + 13, $this->rowH, '  Total ' . $categoria, $this->border('T2'), 0, 'L', true);

        $cx = $x + $wMaq + $wPres;

        foreach ($fechas as $f) {
            $v = $totalOp['dias'][$f] ?? 0;
            $txt = $v > 0 ? $this->fmtNum($v) : '—';
            $this->setTextColor($v > 0 ? $this->cWhite : [159, 186, 197]);
            $this->pdf->SetXY($cx + 13, $y);
            $this->pdf->Cell($wFecha-4, $this->rowH, $txt, $this->border('T2'), 0, 'C', true);
            $cx += $wFecha-4;
        }

        $this->setTextColor($this->cWhite);
        $this->pdf->SetXY($cx + 13, $y);
        $this->pdf->Cell($wAcum + 3, $this->rowH, $this->fmtNum($totalOp['acum']), $this->border('T2'), 0, 'C', true);
        $cx += $wAcum + 3;
        $this->pdf->SetXY($cx + 13, $y);
        $this->pdf->Cell($wProm + 3, $this->rowH, $this->fmtNum($totalOp['prom']), $this->border('T2'), 0, 'C', true);
        $cx += $wProm + 3;

        foreach ([
            $totalOp['tp_dia']     ?? '-',
            $totalOp['tp_acum']    ?? '-',
            $totalOp['merma_dia']  ?? '-',
            $totalOp['merma_acum'] ?? '-',
        ] as $val) {
            $this->pdf->SetXY($cx + 13, $y);
            $this->pdf->Cell($wAcum + 3, $this->rowH, $val, $this->border('T2'), 0, 'C', true);
            $cx += $wAcum + 3;
        }
    }

    // ══════════════════════════════════════════════════════════════
    // TABLA PROGRAMA
    // ══════════════════════════════════════════════════════════════

    private function dibujarPrograma(array $programa, float $x, float $y, float $yMax, int $decimales = 0): void
    {
        $w       = $this->colRightW;
        $wTipo   = round($w * 0.38, 2);
        $wConfig = round($w * 0.08, 2);
        $wProd   = round($w * 0.18, 2);
        $wProg   = round($w * 0.18, 2);
        $wAva    = round($w - $wTipo - $wConfig - $wProd - $wProg, 2);

        // Página donde inició este departamento — el programa siempre dibuja en col derecha
        $paginaDepto = $this->pdf->getPage();

        // Helper: verificar espacio en col derecha y saltar si es necesario
        // Al saltar, vamos a una página NUEVA pero posicionamos en col derecha
        $saltarPaginaPrograma = function() use (&$y, &$x, $yMax, $paginaDepto) {
            // Insertar página nueva después de la página actual del programa
            $this->pdf->AddPage();
            $this->dibujarHeader('', '');
            $y = $this->marginT;
            // x sigue siendo la columna derecha
        };

        // Nota día
        $this->pdf->SetFont($this->font, '', 7.0);
        $this->setTextColor([119, 119, 119]);
        $this->pdf->SetXY($x, $y);
        $this->pdf->Cell($w, 4, 'Día ' . $programa['diaActual'] . ' de ' . $programa['diasMes'] . '  ·  % esperado: ' . $programa['esperadoPct'] . '%', 0, 0, 'L');
        $y += 4.5;

        // Leyenda
        $y = $this->dibujarLeyenda($x, $y, $w);

        // Encabezado tabla programa
        $this->pdf->SetFont($this->font, 'B', $this->fsTh);
        $this->setFillColor($this->cHeader1);
        $this->setTextColor($this->cWhite);
        $cx = $x;
        foreach ([[$wTipo, 'TIPO'], [$wConfig, 'NO CONFIG'], [$wProd, 'PROD ACUM'], [$wProg, 'PROG'], [$wAva, '% AVANCE']] as [$wC, $lbl]) {
            $this->pdf->SetXY($cx, $y);
            $this->pdf->Cell($wC, $this->rowHTh, $lbl, $this->border(), 0, 'C', true);
            $cx += $wC;
        }
        $y += $this->rowHTh;

        $esperadoPct = (float)$programa['esperadoPct'];
        $primeraCat  = true;

        foreach ($programa['grupos'] as $idCat => $cat) {
            if (!is_array($cat) || !isset($cat['_prods'])) continue;

            if (!$primeraCat) $y += 1.5;
            $primeraCat = false;

            $filaIdx = 0;

            foreach ($cat['_prods'] as $nombreProd => $etapas) {
                if (!is_array($etapas)) continue;

                $prodUstd = 0; $prodPlan = 0;
                foreach ($etapas as $info) {
                    if (!is_array($info)) continue;
                    $prodUstd += (float)($info['ustd'] ?? 0);
                    $prodPlan += (float)($info['plan'] ?? 0);
                }
                $prodAvance = $prodPlan > 0 ? round(($prodUstd / $prodPlan) * 100, 1) : 0;
                $prodColor  = $this->calcularColor($prodAvance, $esperadoPct);

                // Saltar si no cabe fila producto
                if ($y + $this->rowH > $yMax) $saltarPaginaPrograma();

                $this->pdf->SetFont($this->font, 'B', $this->fsSmall);
                $this->setFillColor($this->cProd);
                $this->setTextColor($this->cAccent);
                $this->pdf->SetXY($x, $y);
                $this->pdf->Cell($w, $this->rowH, '  ' . $nombreProd, $this->border(), 0, 'L', true);
                $y += $this->rowH;

                foreach ($etapas as $etapa => $info) {
                    if (!is_array($info)) continue;

                    if ($y + $this->rowH > $yMax) $saltarPaginaPrograma();

                    $bg = $filaIdx % 2 === 0 ? $this->cWhite : $this->cAlt;
                    $filaIdx++;

                    $this->pdf->SetFont($this->font, '', $this->fsSmall);
                    $this->setFillColor($bg);
                    $this->setTextColor($this->cText);
                    $cx = $x;
                    $this->pdf->SetXY($cx, $y);
                    $this->pdf->Cell($wTipo, $this->rowH, '    ' . $info['etapa'], $this->border(), 0, 'L', true);
                    $cx += $wTipo;
                    $this->pdf->SetXY($cx, $y);
                    $this->pdf->Cell($wConfig, $this->rowH, $info['config'] ?? '', $this->border(), 0, 'C', true);
                    $cx += $wConfig;
                    $this->pdf->SetXY($cx, $y);
                    $this->pdf->Cell($wProd, $this->rowH, $this->fmtDec((float)$info['ustd'], $decimales), $this->border(), 0, 'C', true);
                    $cx += $wProd;
                    $this->pdf->SetXY($cx, $y);
                    $this->pdf->Cell($wProg, $this->rowH, $this->fmtDec((float)$info['plan'], $decimales), $this->border(), 0, 'C', true);
                    $cx += $wProg;
                    $this->dibujarCeldaAvance($cx, $y, $wAva, $info['avancePct'] . '%', $info['color']);
                    $y += $this->rowH;
                }

                // Total producto
                $countEtapas = count(array_filter($etapas, 'is_array'));
                if ($countEtapas > 1) {
                    if ($y + $this->rowH > $yMax) $saltarPaginaPrograma();
                    $this->pdf->SetFont($this->font, 'B', $this->fsSmall);
                    $this->setFillColor($this->cTotalProd);
                    $this->setTextColor($this->cAccent);
                    $cx = $x;
                    $this->pdf->SetXY($cx, $y);
                    $this->pdf->Cell($wTipo, $this->rowH, '  Total ' . $nombreProd, $this->border('T'), 0, 'L', true);
                    $cx += $wTipo;
                    $this->pdf->SetXY($cx, $y);
                    $this->pdf->Cell($wConfig, $this->rowH, '', $this->border('T'), 0, 'C', true);
                    $cx += $wConfig;
                    $this->pdf->SetXY($cx, $y);
                    $this->pdf->Cell($wProd, $this->rowH, $this->fmtDec($prodUstd, $decimales), $this->border('T'), 0, 'C', true);
                    $cx += $wProd;
                    $this->pdf->SetXY($cx, $y);
                    $this->pdf->Cell($wProg, $this->rowH, $this->fmtDec($prodPlan, $decimales), $this->border('T'), 0, 'C', true);
                    $cx += $wProg;
                    $this->dibujarCeldaAvance($cx, $y, $wAva, $prodAvance . '%', $prodColor, true);
                    $y += $this->rowH;
                }
            }

            // Total categoría
            $total = $cat['_total'];
            if ($y + $this->rowH > $yMax) $saltarPaginaPrograma();
            $nombreCat = strtoupper($cat['_nombre'] ?? 'GENERAL');
            $this->pdf->SetFont($this->font, 'B', $this->fsSmall);
            $this->setFillColor($this->cTotalMaq);
            $this->setTextColor($this->cAccent);
            $cx = $x;
            $this->pdf->SetXY($cx, $y);
            $this->pdf->Cell($wTipo, $this->rowH, '  TOTAL ' . $nombreCat, $this->border('T'), 0, 'L', true);
            $cx += $wTipo;
            $this->pdf->SetXY($cx, $y);
            $this->pdf->Cell($wConfig, $this->rowH, '', $this->border('T'), 0, 'C', true);
            $cx += $wConfig;
            $this->setTextColor($this->cText);
            $this->pdf->SetXY($cx, $y);
            $this->pdf->Cell($wProd, $this->rowH, $this->fmtDec((float)$total['ustd'], $decimales), $this->border('T'), 0, 'C', true);
            $cx += $wProd;
            $this->pdf->SetXY($cx, $y);
            $this->pdf->Cell($wProg, $this->rowH, $this->fmtDec((float)$total['plan'], $decimales), $this->border('T'), 0, 'C', true);
            $cx += $wProg;
            $this->dibujarCeldaAvance($cx, $y, $wAva, $total['avancePct'] . '%', $total['color'], true);
            $y += $this->rowH;
        }

        // Total general
        $y += 1.5;
        $tg = $programa['totalGeneral'];
        $nombreDepto = strtoupper($programa['nombreDepto'] ?? 'GENERAL');
        if ($y + $this->rowH > $yMax) $saltarPaginaPrograma();
        $this->pdf->SetFont($this->font, 'B', $this->fsSmall);
        $this->setFillColor($this->cHeader1);
        $this->setTextColor($this->cWhite);
        $cx = $x;
        $this->pdf->SetXY($cx, $y);
        $this->pdf->Cell($wTipo, $this->rowH, '  TOTAL ' . $nombreDepto, $this->border('T2'), 0, 'L', true);
        $cx += $wTipo;
        $this->pdf->SetXY($cx, $y);
        $this->pdf->Cell($wConfig, $this->rowH, '', $this->border('T2'), 0, 'C', true);
        $cx += $wConfig;
        $this->pdf->SetXY($cx, $y);
        $this->pdf->Cell($wProd, $this->rowH, $this->fmtDec((float)$tg['ustd'], $decimales), $this->border('T2'), 0, 'C', true);
        $cx += $wProd;
        $this->pdf->SetXY($cx, $y);
        $this->pdf->Cell($wProg, $this->rowH, $this->fmtDec((float)$tg['plan'], $decimales), $this->border('T2'), 0, 'C', true);
        $cx += $wProg;
        $this->dibujarCeldaAvance($cx, $y, $wAva, $tg['avancePct'] . '%', $tg['color'], true);
    }

    private function fmtDec(float $val, int $dec): string
    {
        if ($val === 0.0) return $dec > 0 ? number_format(0, $dec, '.', ',') : '—';
        return number_format($val, $dec, '.', ',');
    }

    private function dibujarLeyenda(float $x, float $y, float $w): float
    {
        $items = [
            [$this->cMorado,   'Por encima'],
            [$this->cVerde,    'En meta'],
            [$this->cAmarillo, 'Liger. bajo'],
            [$this->cRojo,     'Muy bajo'],
        ];

        $this->pdf->SetFont($this->font, '', 6.5);
        $xc = $x;
        foreach ($items as [$color, $lbl]) {
            // cuadrito de color
            $this->setFillColor($color);
            $this->pdf->Rect($xc, $y + 0.8, 2.5, 2.5, 'F');
            $xc += 3.2;
            $this->setTextColor([85, 85, 85]);
            $this->pdf->SetXY($xc, $y);
            $this->pdf->Cell(($w / 4) - 3.2, 4, $lbl, 0, 0, 'L');
            $xc += ($w / 4) - 3.2;
        }
        return $y + 5;
    }

    private function dibujarCeldaAvance(float $x, float $y, float $w, string $txt, string $color, bool $bold = false): void
    {
        $bg = match($color) {
            'morado'   => $this->cMorado,
            'verde'    => $this->cVerde,
            'amarillo' => $this->cAmarillo,
            default    => $this->cRojo,
        };
        $this->setFillColor($bg);
        $this->setTextColor($this->cWhite);
        $this->pdf->SetFont($this->font, $bold ? 'B' : '', $this->fsSmall);
        $this->pdf->SetXY($x, $y);
        $this->pdf->Cell($w, $this->rowH, $txt, $this->border(), 0, 'C', true);
    }

    // ══════════════════════════════════════════════════════════════
    // TABBI
    // ══════════════════════════════════════════════════════════════

    public function agregarTabbi(array $fechas, array $tablas, array $programa = []): void
    {
        // Página horizontal — producción Tabbi
        $this->pdf->AddPage('L', 'LETTER');
        $this->dibujarHeader('', '');

        $xLeft  = $this->marginL;
        $yStart = $this->marginT;
        $yMax   = 297.0 - $this->marginB; // A3 landscape: alto = 297mm

        // Título departamento
        $this->pdf->SetFont($this->font, 'B', $this->fsDepto);
        $this->setTextColor($this->cHeader1);
        $this->pdf->SetXY($xLeft, $yStart);
        $this->pdf->Cell($this->usableW, 6, 'TELAS NO TEJIDAS', 0, 0, 'L');
        $this->setDrawColor($this->cHeader1);
        $this->pdf->SetLineWidth(0.5);
        $tW = $this->pdf->GetStringWidth('TABBI') + 2;
        $this->pdf->Line($xLeft, $yStart + 6.5, $xLeft + $tW, $yStart + 6.5);
        $this->pdf->SetLineWidth(0.2);

        $yContent = $yStart + 9;
        $pagina   = $this->pdf->getPage();
        $y        = $yContent;

        foreach ($tablas as $categoria => $maquinas) {
            $y = $this->dibujarTablaTabbi($fechas, $categoria, $maquinas, $xLeft, $y, $yMax, $pagina);
            $pagina = $this->pdf->getPage();
            $y += 3;
        }

        // Página vertical — programa Tabbi (Letter)
        if (!empty($programa)) {
            $this->pdf->AddPage('P', 'LETTER');
            $this->dibujarHeaderPortrait('', '');
            $this->dibujarSoloPrograma($programa, 3);
        }
    }

    private function dibujarTablaTabbi(
        array $fechas, string $categoria, array $maquinas,
        float $x, float $y, float $yMax, int $pagina
    ): float {
        // Ahora ocupa el ancho completo de la página (ya no comparte con col derecha)
        $wTotal = $this->usableW;

        $nFechas  = count($fechas);
        $wMaq     = 11.0;
        $wPres    = 50.0;
        $wFecha   = round(($this->usableW * 0.40) / max($nFechas, 1), 2);
        $wAcum    = 10.0;
        $wProm    = 10.0;
        $wTP      = 8.5;
        $wMerma   = 8.5;

        // Ajustar wFecha si es necesario
        $totalFijo = $wMaq + $wPres + $wAcum + $wProm + ($wTP * 2) + ($wMerma * 2);
        $disponible = $wTotal - $totalFijo;
        if ($disponible < $wFecha * $nFechas) {
            $wFecha = round($disponible / max($nFechas, 1), 2);
        }

        // Título operación
        if ($y + 5 > $yMax) {
            $this->pdf->AddPage('L', 'A3'); $pagina++;
            $this->dibujarHeader('', '');
            $y = $this->marginT;
        }
        $this->pdf->SetFont($this->font, 'B', $this->fsTitle);
        $this->setTextColor($this->cHeader1);
        $this->pdf->SetXY($x, $y);
        $this->pdf->Cell($wTotal, 5, $categoria, 0, 0, 'L');
        $this->setDrawColor($this->cHeader1);
        $this->pdf->SetLineWidth(0.4);
        $tW = $this->pdf->GetStringWidth($categoria) + 2;
        $this->pdf->Line($x, $y + 5.5, $x + $tW, $y + 5.5);
        $this->pdf->SetLineWidth(0.2);
        $y += 7;

        // Encabezados
        [$y, $pagina] = $this->verificarEspacio($y, $this->rowHTh * 2, $yMax, $pagina, $x);
        $this->pdf->SetFont($this->font, 'B', $this->fsTh);

        // Fila 1
        $this->setFillColor($this->cHeader1);
        $this->setTextColor($this->cWhite);
        $cx = $x;
        $this->pdf->SetDrawColor(159, 186, 197); // Color RGB
        $this->pdf->SetXY($cx, $y);
        $this->pdf->Cell($wMaq, $this->rowHTh, 'Máquina', $this->border(), 0, 'C', true); $cx += $wMaq;
        $this->pdf->SetXY($cx, $y);
        $this->pdf->Cell($wPres + 13, $this->rowHTh, 'Presentación', $this->border(), 0, 'C', true); $cx += $wPres;
        $this->pdf->SetXY($cx+13, $y);
        $this->pdf->Cell(($wFecha * $nFechas) - 24, $this->rowHTh, 'MM² Días Anteriores', $this->border(), 0, 'C', true); $cx += $wFecha * $nFechas;
        $this->pdf->SetXY($cx-11, $y);
        $this->pdf->Cell($wAcum + $wProm + 6, $this->rowHTh, 'MM²', $this->border(), 0, 'C', true); $cx += $wAcum + $wProm;
        $this->pdf->SetXY($cx-5, $y);
        $this->pdf->Cell($wAcum + $wProm + 6, $this->rowHTh, '% TP', $this->border(), 0, 'C', true); $cx += $wTP * 2;
        $this->pdf->SetXY($cx+4, $y);
        $this->pdf->Cell($wAcum + $wProm  + 6 , $this->rowHTh, '% Merma', $this->border(), 0, 'C', true);
        $y += $this->rowHTh;

        // Fila 2
        $this->setFillColor($this->cHeader2);
        $cx = $x + $wMaq + $wPres;

        foreach ($fechas as $f) {
            $this->pdf->SetXY($cx + 13, $y);
            $this->pdf->Cell($wFecha-4, $this->rowHTh, $this->formatFechaDia($f), $this->border(), 0, 'C', true);
            $cx += $wFecha-4;
        }

        $this->setFillColor($this->cHeader2);
        $this->pdf->SetXY(8, $y);
        $this->pdf->Cell(74, $this->rowHTh, '', $this->border(), 0, 'C', true);
        foreach ([[$wAcum,'Acum'],[$wProm,'Prom'],[$wTP,'Día'],[$wTP,'Acum'],[$wMerma,'Día'],[$wMerma,'Acum']] as [$wC,$lbl]) {
            $this->pdf->SetXY($cx + 13, $y);
            $this->pdf->Cell($wAcum+3, $this->rowHTh, $lbl, $this->border(), 0, 'C', true);
            $cx += $wAcum+3;
        }
        $y += $this->rowHTh;

        // Filas de máquinas
        $totalOp = $maquinas['_total_operacion'] ?? null;
        unset($maquinas['_total_operacion']);

        foreach ($maquinas as $nombreMaq => $dataMaq) {
            $sinDatos = empty($dataMaq['productos']);

            if ($sinDatos) {
                [$y, $pagina] = $this->verificarEspacio($y, $this->rowH, $yMax, $pagina, $x);
                $this->pdf->SetFont($this->font, 'B', $this->fsSmall);
                $this->setFillColor($this->cMaq);
                $this->setTextColor($this->cText);
                $this->pdf->SetXY($x, $y);
                $this->pdf->Cell($wMaq, $this->rowH, $nombreMaq, $this->border(), 0, 'C', true);
                $cx = $x + $wMaq;
                $this->setFillColor($this->cWhite);
                $this->setTextColor([187,187,187]);
                $this->pdf->SetXY($cx, $y);
                $this->pdf->Cell($wPres, $this->rowH, '—', $this->border(), 0, 'C', true); $cx += $wPres;
                foreach ($fechas as $f) {
                    $this->pdf->SetXY($cx, $y);
                    $this->pdf->Cell($wFecha, $this->rowH, '—', $this->border(), 0, 'C', true); $cx += $wFecha;
                }
                foreach ([$wAcum,$wProm,$wTP,$wTP,$wMerma,$wMerma] as $wC) {
                    $this->pdf->SetXY($cx, $y);
                    $this->pdf->Cell($wC, $this->rowH, '', $this->border(), 0, 'C', true); $cx += $wC;
                }
                $y += $this->rowH;
                continue;
            }

            // Calcular altura total de esta máquina para decidir si cabe
            $totalFilasTabbi = 0;
            foreach ($dataMaq['productos'] as $prod) {
                $totalFilasTabbi += 1 + count($prod['claves']);
            }
            $alturaNecesariaTabbi = ($totalFilasTabbi + 1) * $this->rowH; // +1 para Total máquina
            $espacioRestanteTabbi = $yMax - $y;
            if ($alturaNecesariaTabbi > $espacioRestanteTabbi && $y > $this->marginT + 20) {
                $this->pdf->AddPage('L', 'LETTER');
                $this->dibujarHeader('', '');
                $pagina++;
                $y = $this->marginT;
                $this->pdf->setPage($pagina);
            }

            $yMaqStart  = $y;
            $primerFila = true;
            $filaIdx    = 0;

            foreach ($dataMaq['productos'] as $nombreProd => $producto) {
                $paginaAntesProdTabbi = $pagina;
                [$y, $pagina] = $this->verificarEspacio($y, $this->rowH, $yMax, $pagina, $x);

                if ($primerFila) {
                    $yMaqStart = $y;
                } elseif ($pagina !== $paginaAntesProdTabbi) {
                    $alturaParcialTabbi = $yMax - $yMaqStart;
                    if ($alturaParcialTabbi > 0) {
                        $this->pdf->setPage($paginaAntesProdTabbi);
                        $this->pdf->SetFont($this->font, 'B', $this->fsSmall);
                        $this->setFillColor($this->cMaq);
                        $this->setTextColor($this->cText);
                        $this->pdf->SetXY($x, $yMaqStart);
                        $this->pdf->Cell($wMaq, $alturaParcialTabbi, $nombreMaq, $this->border(), 0, 'C', true);
                        $this->pdf->setPage($pagina);
                    }
                    $yMaqStart = $y;
                }

                // Fila producto
                $cx = $x + $wMaq;
                $this->pdf->SetFont($this->font, 'B', $this->fsSmall);
                $this->setFillColor($this->cProd);
                $this->setTextColor($this->cAccent);
                $this->pdf->SetXY($cx, $y);
                $this->pdf->Cell(($wPres + ($wFecha * $nFechas))-11, $this->rowH, '  ' . $nombreProd, $this->border(), 0, 'L', true);
                $primerFila = false;
                $y += $this->rowH;

                // Filas de claves
                foreach ($producto['claves'] as $clave => $info) {
                    $paginaAntesClaveTNT = $pagina;
                    [$y, $pagina] = $this->verificarEspacio($y, $this->rowH, $yMax, $pagina, $x);

                    if ($pagina !== $paginaAntesClaveTNT) {
                        $alturaParcialTabbi = $yMax - $yMaqStart;
                        if ($alturaParcialTabbi > 0) {
                            $this->pdf->setPage($paginaAntesClaveTNT);
                            $this->pdf->SetFont($this->font, 'B', $this->fsSmall);
                            $this->setFillColor($this->cMaq);
                            $this->setTextColor($this->cText);
                            $this->pdf->SetXY($x, $yMaqStart);
                            $this->pdf->Cell($wMaq, $alturaParcialTabbi, $nombreMaq, $this->border(), 0, 'C', true);
                            $this->pdf->setPage($pagina);
                        }
                        $yMaqStart = $y;
                    }
                    $bg = $filaIdx % 2 === 0 ? $this->cWhite : $this->cAlt;
                    $filaIdx++;

                    $cx = $x + $wMaq;
                    $this->pdf->SetFont($this->font, '', $this->fsSmall);
                    $this->setFillColor($bg);
                    $this->setTextColor($this->cText);
                    $this->pdf->SetXY($cx, $y);
                    $this->pdf->Cell($wPres + 13, $this->rowH, '  ' . $info['etapa'], $this->border(), 0, 'L', true); $cx += $wPres + 20;

                    foreach ($fechas as $f) {
                        $v   = $info['dias'][$f] ?? null;
                        $txt = ($v === null || $v == 0) ? '—' : number_format((float)$v, 3, '.', ',');
                        $this->setTextColor(($v === null || $v == 0) ? [187,187,187] : $this->cText);
                        $this->pdf->SetXY($cx-7, $y);
                        $this->pdf->Cell($wFecha-4, $this->rowH, $txt, $this->border(), 0, 'C', true); 
                        $cx += $wFecha-4;
                    }
                    $y += $this->rowH;
                }
            }

            // Celda máquina con altura total + indicadores centrados
            $alturaMaq = $y - $yMaqStart;
            $this->pdf->SetFont($this->font, 'B', $this->fsSmall);
            $this->setFillColor($this->cMaq);
            $this->setTextColor($this->cText);
            $this->pdf->SetXY($x, $yMaqStart);
            $this->pdf->Cell($wMaq, $alturaMaq, $nombreMaq, $this->border(), 0, 'C', true);

            // Indicadores centrados verticalmente
            $yCentrado = $yMaqStart + ($alturaMaq - $this->rowH) / 2;
            $cx2 = $x + $wMaq + $wPres + ($wFecha * $nFechas);
            foreach ([
                [$wAcum + 3, number_format((float)($dataMaq['total_maquina']['acum'] ?? 0), 3, '.', ',')],
                [$wAcum + 3, number_format((float)($dataMaq['total_maquina']['prom'] ?? 0), 3, '.', ',')],
                [$wAcum + 3,   $dataMaq['tp_dia']],
                [$wAcum + 3,   $dataMaq['tp_acum']],
                [$wAcum + 3,$dataMaq['merma_dia']],
                [$wAcum + 3,$dataMaq['merma_acum']],
            ] as [$wC, $val]) {
                $this->setFillColor($this->cProd);
                $this->setTextColor($this->cText);
                $this->pdf->SetXY($cx2 - 11, $yMaqStart);
                $this->pdf->Cell($wC, $alturaMaq, '', $this->border(), 0, 'C', true);
                $this->pdf->SetFont($this->font, 'B', $this->fsSmall);
                $this->pdf->SetXY($cx2 - 11, $yCentrado);
                $this->pdf->Cell($wC, $this->rowH, $val, 0, 0, 'C');
                $cx2 += $wC;
            }

            // Total máquina
            [$y, $pagina] = $this->verificarEspacio($y, $this->rowH, $yMax, $pagina, $x);
            $this->pdf->SetFont($this->font, 'B', $this->fsSmall);
            $this->setFillColor($this->cTotalMaq);
            $this->setTextColor($this->cAccent);
            $this->pdf->SetXY($x, $y);
            $this->pdf->SetDrawColor(159, 186, 197); // Color RGB
            $this->pdf->Cell($wMaq + $wPres + 13, $this->rowH, '  Total ' . $nombreMaq, $this->border(''), 0, 'L', true);
            $cx = $x + $wMaq + $wPres;
            $this->setTextColor($this->cText);
            foreach ($fechas as $f) {
                $v   = $dataMaq['total_maquina']['dias'][$f] ?? 0;
                $txt = $v > 0 ? number_format((float)$v, 3, '.', ',') : '—';
                $this->setTextColor($v > 0 ? $this->cText : [187,187,187]);
                $this->pdf->SetXY($cx + 13, $y);
                $this->pdf->SetDrawColor(159, 186, 197); // Color RGB
                $this->pdf->Cell($wFecha - 4, $this->rowH, $txt, $this->border(''), 0, 'C', true); 
                $cx += $wFecha - 4;
            }
            $this->setTextColor($this->cText);
            foreach ([$wAcum,$wAcum,$wAcum,$wAcum,$wAcum,$wAcum] as $wC) {
                $this->pdf->SetXY($cx + 13, $y);
                $this->pdf->SetDrawColor(159, 186, 197); // Color RGB
                $this->pdf->Cell($wC + 3, $this->rowH, '', $this->border(''), 0, 'C', true); 
                $cx += $wC + 3;
            }
            $y += $this->rowH;
        }

        // Total operación
        if ($totalOp) {
            [$y, $pagina] = $this->verificarEspacio($y, $this->rowH, $yMax, $pagina, $x);
            $this->pdf->SetFont($this->font, 'B', $this->fsSmall);
            $this->setFillColor($this->cHeader1);
            $this->setTextColor($this->cWhite);
            $this->pdf->SetXY($x, $y);
            $this->pdf->Cell($wMaq + $wPres + 13, $this->rowH, '  Total ' . $categoria, $this->border('T2'), 0, 'L', true);
            $cx = $x + $wMaq + $wPres;
            foreach ($fechas as $f) {
                $v   = $totalOp['dias'][$f] ?? 0;
                $txt = $v > 0 ? number_format((float)$v, 3, '.', ',') : '—';
                $this->setTextColor($v > 0 ? $this->cWhite : [159,186,197]);
                $this->pdf->SetXY($cx + 13, $y);
                $this->pdf->Cell($wFecha - 4, $this->rowH, $txt, $this->border('T2'), 0, 'C', true); 
                $cx += $wFecha - 4;
            }
            $this->setTextColor($this->cWhite);
            $this->pdf->SetXY($cx + 13, $y);
            $this->pdf->Cell($wAcum + 3, $this->rowH, number_format((float)$totalOp['acum'], 3, '.', ','), $this->border('T2'), 0, 'C', true); $cx += $wAcum + 3;
            $this->pdf->SetXY($cx + 13, $y);
            $this->pdf->Cell($wProm + 3, $this->rowH, number_format((float)$totalOp['prom'], 3, '.', ','), $this->border('T2'), 0, 'C', true); $cx += $wProm+ 3;
            foreach ([
                $totalOp['tp_dia']     ?? '-',
                $totalOp['tp_acum']    ?? '-',
                $totalOp['merma_dia']  ?? '-',
                $totalOp['merma_acum'] ?? '-',
            ] as $val) {
                $this->pdf->SetXY($cx + 13, $y);
                $this->pdf->Cell($wAcum + 3, $this->rowH, $val, $this->border('T2'), 0, 'C', true);
                $cx += $wAcum + 3;
            }
            $y += $this->rowH;
        }

        return $y;
    }

    // ══════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════

    
    private function estimarAlturaTabla(array $maquinas): float
    {
        $filas = 2; // encabezados

        foreach ($maquinas as $maq) {
            if ($maq === null || $maq === [] || !is_array($maq)) continue;

            if (isset($maq['productos'])) {

                foreach ($maq['productos'] as $prod) {
                    $filas += 1; // fila producto

                    if (isset($prod['claves'])) {
                        $filas += count($prod['claves']); // filas claves
                    }
                }

                $filas += 1; // total máquina
            }
        }

        $filas += 1; // total operación

        return $filas * $this->rowH;
    }


    private function verificarEspacio(float $y, float $h, float $yMax, int $pagina, float $xLeft): array
    {
        if ($y + $h > $yMax) {
            // Detectar formato por el valor de yMax:
            //   Portrait Letter : yMax ≈ 279.4 - 8 = 271.4
            //   A3 landscape    : yMax ≈ 297.0 - 8 = 289.0  (TABBI)
            //   Legal landscape : yMax ≈ 215.9 - 8 = 207.9  (Producción)
            if (abs($yMax - (279.4 - $this->marginB)) < 2) {
                $this->pdf->AddPage('P', 'LETTER');
                $this->dibujarHeaderPortrait('', '');
            } elseif (abs($yMax - (297.0 - $this->marginB)) < 2) {
                $this->pdf->AddPage('L', 'A3');
                $this->dibujarHeader('', '');
            } else {
                // Legal landscape (producción)
                $this->pdf->AddPage('L', 'LEGAL');
                $this->dibujarHeader('', '');
            }
            $pagina++;
            $y = $this->marginT;
            $this->pdf->setPage($pagina);
        }
        return [$y, $pagina];
    }

    private function border(string $tipo = ''): string
    {
        return match($tipo) {
            'T'  => 'T',
            'T2' => 'T',
            ''   => '1',
            default => '1',
        };
    }

    private function setFillColor(array $c): void
    {
        $this->pdf->SetFillColor($c[0], $c[1], $c[2]);
    }

    private function setTextColor(array $c): void
    {
        $this->pdf->SetTextColor($c[0], $c[1], $c[2]);
    }

    private function setDrawColor(array $c): void
    {
        $this->pdf->SetDrawColor($c[0], $c[1], $c[2]);
    }

    private function fmtNum($val): string
    {
        if ($val === null || $val === 0 || $val === '') return '—';
        return number_format((float)$val, 0, '.', ',');
    }

    private function formatFecha(string $fecha): string
    {
        $meses = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',
                  6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',
                  10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
        $ts = strtotime($fecha);
        return date('d', $ts) . '-' . $meses[(int)date('n', $ts)] . '-' . date('Y', $ts);
    }

    private function formatFechaDia(string $fecha): string
    {
        $p = explode('-', $fecha);
        return $p[2] . '/' . $p[1];
    }

    private function calcularColor(float $avancePct, float $esperadoPct): string
    {
        if ($esperadoPct <= 0) return 'rojo';
        $ratio = ($avancePct / $esperadoPct) * 100;
        if ($ratio > 100)  return 'morado';
        if ($ratio >= 90)  return 'verde';
        if ($ratio >= 70)  return 'amarillo';
        return 'rojo';
    }
}