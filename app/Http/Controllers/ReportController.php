<?php

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $reports = collect(ReportService::REPORTS)
            ->filter(fn ($r) => $request->user()->can('reports.'.$r[1]))
            ->groupBy(fn ($r) => $r[1], true);

        abort_if($reports->isEmpty(), 403);

        return view('reports.index', compact('reports'));
    }

    public function show(Request $request, string $key, ReportService $service)
    {
        $def = ReportService::REPORTS[$key] ?? abort(404);
        abort_unless($request->user()->can('reports.'.$def[1]), 403);

        $report = $service->build($key, $request);
        $filename = Str::slug($report['title']).'-'.now()->format('Ymd-His');

        return match ($request->input('export')) {
            'pdf' => Pdf::loadView('reports.pdf', compact('report'))
                ->setPaper('a4', count($report['columns']) > 6 ? 'landscape' : 'portrait')
                ->download($filename.'.pdf'),
            'excel' => Excel::download(new ReportExport($report), $filename.'.xlsx'),
            default => view('reports.show', compact('report')),
        };
    }
}
