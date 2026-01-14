<?php

namespace Modules\Inventory\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Inventory\Services\ManzanaFinancingRuleImportService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManzanaFinancingRuleImportController extends Controller
{
    public function __construct(private ManzanaFinancingRuleImportService $service)
    {}

    public function downloadTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $data = $this->service->buildTemplate();
        $sheet->fromArray($data, null, 'A1');

        foreach (range('A', 'E') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'plantilla_reglas_manzanas.xlsx';

        return new StreamedResponse(
            function () use ($writer) {
                $writer->save('php://output');
            },
            200,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                'Cache-Control' => 'max-age=0',
            ]
        );
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
            'create_missing_manzanas' => 'sometimes|boolean',
        ]);

        $result = $this->service->import(
            $request->file('file'),
            (bool) $request->boolean('create_missing_manzanas')
        );

        return response()->json([
            'success' => true,
            'message' => 'Archivo procesado exitosamente',
            'data' => $result,
        ]);
    }
}
