<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function visitsMonthly(Request $request)
    {
        $month = $request->query('month'); // expected format YYYY-MM

        if (!is_string($month) || !preg_match('/^\d{4}-\d{2}$/', $month)) {
            $start = now()->startOfMonth();
        } else {
            try {
                $start = Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfMonth();
            } catch (\Exception $e) {
                $start = now()->startOfMonth();
            }
        }

        $end = (clone $start)->endOfMonth();

        // Base scope: visits that started within the month
        $base = DB::table('patient_visits as v')
            ->whereNotNull('v.start_time')
            ->whereBetween('v.start_time', [$start, $end]);

        // Totals
        $totalVisits = (clone $base)->count();

        // By day
        $byDayRows = (clone $base)
            ->selectRaw('DATE(v.start_time) as day, COUNT(*) as count')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        // By hour (0-23)
        $byHourRows = (clone $base)
            ->selectRaw('HOUR(v.start_time) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        // By visit type (infer appointment vs walk-in using correlated subquery similar to controller logic)
        $byVisitTypeRows = (clone $base)
            ->selectRaw(
                "CASE WHEN EXISTS (\n" .
                "  SELECT 1 FROM appointments a\n" .
                "  WHERE a.patient_id = v.patient_id\n" .
                "    AND a.service_id = v.service_id\n" .
                "    AND a.date = v.visit_date\n" .
                "    AND a.status IN ('approved','completed')\n" .
                ") THEN 'appointment' ELSE 'walkin' END as visit_type, COUNT(*) as count"
            )
            ->groupBy('visit_type')
            ->orderBy('visit_type')
            ->get();

        // By service
        $byServiceRows = (clone $base)
            ->leftJoin('services as s', 's.id', '=', 'v.service_id')
            ->selectRaw('v.service_id, COALESCE(s.name, \"(Unspecified)\") as service_name, COUNT(*) as count')
            ->groupBy('v.service_id', 's.name')
            ->orderByDesc('count')
            ->get();

        return response()->json([
            'month' => $start->format('Y-m'),
            'totals' => [
                'visits' => $totalVisits,
            ],
            'by_day' => $byDayRows,
            'by_hour' => $byHourRows,
            'by_visit_type' => $byVisitTypeRows,
            'by_service' => $byServiceRows,
        ]);
    }
}

