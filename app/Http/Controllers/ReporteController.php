<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\Request;

/**
 * Reporte gerencial mínimo: ventas por mes comparadas con el mes anterior.
 * Solo lo ve el Admin (ver la ruta en routes/web.php).
 */
class ReporteController extends Controller
{
    // Cantidad de meses que se pueden mostrar
    private const PERIODOS = [3, 6, 12];

    public function ventas(Request $request)
    {
        $meses = (int) $request->query('meses', 6);
        if (! in_array($meses, self::PERIODOS, true)) {
            $meses = 6;
        }

        // Del más nuevo al más viejo: la primera fila es el mes actual
        $filas = Lead::ventasPorMes($meses)->reverse()->values();

        return view('reportes.ventas', [
            'filas' => $filas,
            'meses' => $meses,
            'periodos' => self::PERIODOS,
            'actual' => $filas->get(0),
            'anterior' => $filas->get(1),
        ]);
    }
}
