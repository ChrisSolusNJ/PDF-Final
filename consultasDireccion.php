<?php
require_once "..\..\conexion.php";

class ReporteDiario
{

    public function obtenerUSTD($fecha)
    {
        $conexion = new ClassConexion();
        $conn = $conexion->conexion('TLX004MXDB');

        $resultado = [];

        // SP 1
        list($ok1, $data1) = $this->ejecutarSP(
            $conn,
            "EXEC dbo.sp_PRSD_ObtenerProduccionUltimos7Dias @FechaFin = ?",
            [$fecha]
        );

        if (!$ok1)
            return ["ok" => false, "error" => "SP principal", "details" => $data1];

        // SP 2
        list($ok2, $data2) = $this->ejecutarSP(
            $conn,
            "EXEC dbo.sp_PRSD_ObtenerProduccionMaquinasSinRedUltimos7Dias @FechaFin = ?",
            [$fecha]
        );

        if (!$ok2)
            return ["ok" => false, "error" => "SP adicional", "details" => $data2];

        $resultado = array_merge($data1, $data2);

        $ordenDepartamentos = [1, 24, 25, 2];
        $resultadoAgrupado = [];

        // Agrupar
        foreach ($resultado as $fila) {
            $dep = $fila['NoDepto'];

            if (!isset($resultadoAgrupado[$dep])) {
                $resultadoAgrupado[$dep] = [];
            }

            $resultadoAgrupado[$dep][] = $fila;
        }


        $resultadoOrdenado = [];

        foreach ($ordenDepartamentos as $dep) {
            if (isset($resultadoAgrupado[$dep])) {
                $resultadoOrdenado[$dep] = $resultadoAgrupado[$dep];
            }
        }

        sqlsrv_close($conn);
        // echo json_encode(["ok" => true, "data" => $resultadoOrdenado], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return $resultadoOrdenado;
    }

    public function obtenerDatosTabbi($fecha){
        $conexion = new ClassConexion();
        $conn = $conexion->conexion('TLX004MXDB');

        $sql = "EXEC dbo.sp_PRSD_ObtenerProduccionTNT_ConTiempos @FechaEvaluar = ?";
        $params = array($fecha);

        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) {
            $errors = sqlsrv_errors();
            sqlsrv_close($conn);
            return [
                "ok" => false,
                "error" => "Error ejecutando query",
                "details" => $errors
            ];
        }

        $resultado = [];
        while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $resultado[] = $this->mapearFilaTabbi($row);
        }
        sqlsrv_close($conn);
        // echo json_encode(["ok" => true, "data" => $resultado], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return $resultado;
    }

    private function ejecutarSP($conn, $sql, $params)
    {
        $data = [];

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            return [false, sqlsrv_errors()];
        }

        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $data[] = $this->mapearFilaUSTD($row);
        }

        sqlsrv_free_stmt($stmt);

        return [true, $data];
    }


    private function mapearFilaUSTD($row)
    {
        // Manejo robusto de Fecha
        if ($row["Fecha"] instanceof DateTime) {
            $fecha = $row["Fecha"]->format('Y-m-d');
        } else {
            // Asume string (ya viene formateada o compatible)
            $fecha = date('Y-m-d', strtotime($row["Fecha"]));
        }

        return [
            "Fecha" => $fecha,
            'Turno' => $row['Turno'],
            'NoDepto' => $row['NoDepto'],
            'NombreDepto' => $row['NombreDepto'],
            'NoMaquina' => $row['NoMaquina'],
            'NombreMaquina' => $row['NombreMaquina'],
            'idCategoria' => $row['idCategoria'],
            'Categoria' => $row['Categoria'],
            'idProducto' => $row['idProducto'],
            'Producto' => $row['Producto'],
            'idEtapa' => $row['idEtapa'],
            'Etapa' => $row['Etapa'],
            'Clave' => $row['Clave'],
            'Descripcion' => $row['Descripcion'],
            'Reales' => $row['Reales'],
            'USTD' => $row['USTD'],
            'AcumuladoUSTD' => $row['AcumuladoUSTD'],
            'Piezas' => $row['Piezas'],
            'TotalPiezas' => $row['TotalPiezas'],
            'Cortes' => $row['Cortes'],
            'Rechazos' => $row['Rechazos'],
            'TiempoAbajo' => $row['TiempoAbajo'],
            'HorasTrabajadas' => $row['HorasTrabajadas'],
        ];
    }

    private function mapearFilaTabbi($row)
    {
        if($row["Fecha"] instanceof DateTime) {
            $fecha = $row["Fecha"]->format('Y-m-d');
        } else {
            $fecha = date('Y-m-d', strtotime($row["Fecha"]));
        }
        
        return [
            "Fecha" => $fecha,
            'Turno' => $row['Turno'],
            'NoDepto' => $row['NoDepto'],
            'NombreDepto' => $row['NombreDepto'],
            'NoMaquina' => $row['NoMaquina'],
            'NombreMaquina' => $row['NombreMaquina'],
            'idCategoria' => $row['idCategoria'],
            'Categoria' => $row['Categoria'],
            'idProducto' => $row['idProducto'],
            'Producto' => $row['Producto'],
            'idEtapa' => $row['idEtapa'],
            'Etapa' => $row['Etapa'],
            'Clave' => $row['Clave'],
            'Descripcion' => $row['Descripcion'],
            'MetrosLineales' => $row['TotalML'],
            'MetrosCuadrados' => $row['TotalMC'],
            'Kilogramos' => $row['TotalPeso'],
            'TiempoAbajo' => $row['TiempoAbajo'],
            'HorasTrabajadas' => $row['HorasTrabajadas'],
            'KGSRechazados' => $row['KGSRechazados'],
            'MC' => $row['AcMMC'],
        ];
    }

    public function obtenerPlanProduccion($fecha)
    {
        $conexion = new ClassConexion();
        $conn = $conexion->conexion('TLX004MXDB');

        $sql = "EXEC dbo.sp_PRSD_ObtenerPlanProduccion @FechaFin = ?";
        $params = array($fecha);

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            $errors = sqlsrv_errors();
            sqlsrv_close($conn);
            return [
                "ok" => false,
                "error" => "Error ejecutando query",
                "details" => $errors
            ];
        }

        $resultado = [];

        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $resultado[] = [
                "Fecha" => $row["fecha"]->format('Y-m-d'),
                'Departamento' => $row['NoDepto'], // 🔥 clave homologada
                'NombreDepto' => $row['NombreDepto'],
                'idProducto' => $row['Producto'],
                "Producto" => $row['ProductoNombre'],
                'clave' => $row['clave'],
                'Etapa' => $row['Etapa'],
                'EtapaNombre' => $row['EtapaNombre'],
                'idCategoria' => $row['Categoria'],
                'Categoria' => $row['NombreCategoria'],
                'PlanProduccion' => $row['PlanProduccion'],
                'configuracion' => $row['configuracion'],
                'USTDAcc' => $row['STDAcumulado'],
            ];
        }

        sqlsrv_free_stmt($stmt);
        sqlsrv_close($conn);

        // ===============================
        // 🔥 AGRUPAR POR DEPARTAMENTO
        // ===============================
        $agrupado = [];

        foreach ($resultado as $fila) {
            $dep = $fila['Departamento'];

            if (!isset($agrupado[$dep])) {
                $agrupado[$dep] = [];
            }

            $agrupado[$dep][] = $fila;
        }

        // ===============================
        // 🔥 ORDEN PERSONALIZADO
        // ===============================
        $ordenDepartamentos = [1, 24, 25];

        $resultadoOrdenado = [];

        foreach ($ordenDepartamentos as $dep) {
            if (isset($agrupado[$dep])) {
                $resultadoOrdenado[$dep] = $agrupado[$dep];
            }
        }
        // echo json_encode(["ok" => true, "data" => $resultadoOrdenado], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return $resultadoOrdenado;
    }

    public function obtenerPlanproduccionTNT($fecha){
        $conexion = new ClassConexion();
        $conn = $conexion->conexion('TLX004MXDB');

        $sql = "EXEC dbo.sp_PRSD_ReporteProduccionMensualTNT @FechaFinal = ?";
        $params = array($fecha);

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            $errors = sqlsrv_errors();
            sqlsrv_close($conn);
            return [
                "ok" => false,
                "error" => "Error ejecutando query",
                "details" => $errors
            ];
        }
        $resultado = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $resultado[] = [
                "Fecha" => $row["fecha"]->format('Y-m-d'),
                'Departamento' => $row['NoDepto'], 
                'NombreDepto' => $row['NombreDepto'],
                'idProducto' => $row['Producto'],
                "Producto" => $row['ProductoNombre'],
                'clave' => $row['clave'],
                'Etapa' => $row['Etapa'],
                'EtapaNombre' => $row['EtapaNombre'],
                'idCategoria' => $row['Categoria'],
                'Categoria' => $row['NombreCategoria'],
                'PlanProduccion' => $row['PlanProduccion'],
                'configuracion' => $row['configuracion'],
                'MMCAcc' => $row['TotalMMC'],
            ];
        }

        sqlsrv_free_stmt($stmt);
        sqlsrv_close($conn);

        return $resultado;
    }
}