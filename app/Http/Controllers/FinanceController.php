<?php

namespace App\Http\Controllers;

use App\Services\FinanceService;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function __invoke(Request $request, FinanceService $finance)
    {
        $from = $request->input('from');
        $to = $request->input('to');

        return view('finance.summary', [
            'summary' => $finance->summary($from, $to),
            'trend' => $finance->monthlyTrend(),
            'from' => $from,
            'to' => $to,
        ]);
    }
}
