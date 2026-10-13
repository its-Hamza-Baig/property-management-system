<?php

namespace App\Http\Controllers;

use App\Models\Investor;
use App\Models\Property;
use App\Services\Audit\AuditLogger;
use App\Services\Entitlements\EntitlementManager;
use App\Services\Reports\ReportRegistry;
use App\Support\Permissions;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportRegistry $reports,
        private readonly EntitlementManager $entitlements,
        private readonly AuditLogger $audit,
    ) {
    }

    public function index(): View
    {
        $this->authorize(Permissions::REPORTS_VIEW);

        return view('reports.index', ['reports' => $this->reports->all()]);
    }

    public function show(Request $request, string $report): View
    {
        $this->authorize(Permissions::REPORTS_VIEW);
        abort_unless($this->reports->exists($report), 404);

        $filters = $this->filters($request);
        $data = $this->reports->build($report, $filters);

        return view('reports.show', [
            'key' => $report,
            'definition' => $this->reports->definition($report),
            'columns' => $data['columns'],
            'rows' => $data['rows'],
            'totals' => $data['totals'],
            'filters' => $filters,
            'properties' => Property::orderBy('reference')->get(),
            'investors' => Investor::orderBy('name')->get(),
            'canExport' => $request->user()->hasPermission(Permissions::REPORTS_EXPORT)
                && $this->entitlements->allows(\App\Support\Entitlements::EXPORTS_ENABLED),
        ]);
    }

    /**
     * Exports go through the same permission and entitlement checks as the
     * screen, and every export is written to the audit log.
     */
    public function export(Request $request, string $report, string $format): StreamedResponse|Response
    {
        $this->authorize(Permissions::REPORTS_EXPORT);
        $this->entitlements->enforceExports();
        abort_unless($this->reports->exists($report), 404);

        $filters = $this->filters($request);
        $data = $this->reports->build($report, $filters);
        $definition = $this->reports->definition($report);

        $this->audit->log(
            action: 'report.exported',
            description: $definition['title'].' as '.strtoupper($format),
            newValues: $filters,
        );

        $filename = str($definition['title'])->slug().'-'.now()->format('Y-m-d');

        return $format === 'pdf'
            ? $this->pdf($definition, $data, $filters, $filename)
            : $this->csv($definition, $data, $filename);
    }

    private function csv(array $definition, array $data, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($definition, $data) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [$definition['title']]);
            fputcsv($out, ['Generated', now()->format('d M Y H:i')]);
            fputcsv($out, []);
            fputcsv($out, array_column($data['columns'], 'label'));

            foreach ($data['rows'] as $row) {
                fputcsv($out, array_map(
                    fn ($column) => $row[$column['key']] ?? '',
                    $data['columns'],
                ));
            }

            if ($data['totals'] !== []) {
                fputcsv($out, []);
                fputcsv($out, array_map(function ($column) use ($data) {
                    if ($column === reset($data['columns'])) {
                        return 'Total';
                    }

                    return $data['totals'][$column['key']] ?? '';
                }, $data['columns']));
            }

            fclose($out);
        }, $filename.'.csv', ['Content-Type' => 'text/csv']);
    }

    private function pdf(array $definition, array $data, array $filters, string $filename): Response
    {
        $pdf = Pdf::loadView('reports.pdf', [
            'definition' => $definition,
            'columns' => $data['columns'],
            'rows' => $data['rows'],
            'totals' => $data['totals'],
            'filters' => $filters,
            'tenant' => request()->user()->tenant,
        ])->setPaper('a4', count($data['columns']) > 6 ? 'landscape' : 'portrait');

        return $pdf->download($filename.'.pdf');
    }

    /** @return array{from?:string,to?:string,property_id?:int,investor_id?:int,status?:string} */
    private function filters(Request $request): array
    {
        return array_filter([
            'from' => $request->string('from')->toString() ?: null,
            'to' => $request->string('to')->toString() ?: null,
            'property_id' => $request->integer('property_id') ?: null,
            'investor_id' => $request->integer('investor_id') ?: null,
            'status' => $request->string('status')->toString() ?: null,
        ], fn ($value) => $value !== null);
    }
}
