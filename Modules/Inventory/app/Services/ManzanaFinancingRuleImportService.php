<?php

namespace Modules\Inventory\Services;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\Manzana;
use Modules\Inventory\Models\ManzanaFinancingRule;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ManzanaFinancingRuleImportService
{
    public function import(UploadedFile $file, bool $createMissingManzanas = false): array
    {
        if (!class_exists('ZipArchive')) {
            throw new Exception('La extensión ZIP de PHP no está disponible.');
        }

        $spreadsheet = IOFactory::load($file->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        if (count($rows) < 2) {
            throw new Exception('El archivo debe tener headers y al menos una fila de datos.');
        }

        $headerRow = $rows[0];
        $headerMap = $this->mapHeaders($headerRow);

        foreach (['manzana', 'financiamiento'] as $required) {
            if (!isset($headerMap[$required])) {
                throw new Exception("Falta la columna requerida: {$required}");
            }
        }

        $stats = [
            'total' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];
        $errors = [];

        DB::beginTransaction();
        try {
            foreach (array_slice($rows, 1) as $index => $row) {
                $stats['total']++;
                $rowNumber = $index + 2;

                try {
                    if ($this->isEmptyRow($row)) {
                        $stats['skipped']++;
                        continue;
                    }

                    $manzanaName = $this->getCell($row, $headerMap['manzana']);
                    $financiamiento = $this->getCell($row, $headerMap['financiamiento']);

                    if ($manzanaName === '' || $financiamiento === '') {
                        $stats['skipped']++;
                        continue;
                    }

                    $manzanaName = strtoupper(trim($manzanaName));

                    $manzana = Manzana::where('name', $manzanaName)->first();
                    if (!$manzana) {
                        if (!$createMissingManzanas) {
                            throw new Exception("Manzana '{$manzanaName}' no existe");
                        }
                        $manzana = Manzana::create(['name' => $manzanaName]);
                    }

                    $parsed = $this->parseFinanciamiento($financiamiento);
                    $minDownPayment = null;
                    if (isset($headerMap['min_down_payment_percentage'])) {
                        $raw = $this->getCell($row, $headerMap['min_down_payment_percentage']);
                        if ($raw !== '' && is_numeric($raw)) {
                            $minDownPayment = (float) $raw;
                        }
                    }

                    $allowsBalloon = false;
                    if (isset($headerMap['allows_balloon_payment'])) {
                        $allowsBalloon = $this->parseBoolean($this->getCell($row, $headerMap['allows_balloon_payment']));
                    }

                    $allowsBpp = false;
                    if (isset($headerMap['allows_bpp_bonus'])) {
                        $allowsBpp = $this->parseBoolean($this->getCell($row, $headerMap['allows_bpp_bonus']));
                    }

                    $rule = ManzanaFinancingRule::updateOrCreate(
                        ['manzana_id' => $manzana->manzana_id],
                        [
                            'financing_type' => $parsed['financing_type'],
                            'max_installments' => $parsed['max_installments'],
                            'min_down_payment_percentage' => $minDownPayment,
                            'allows_balloon_payment' => $allowsBalloon,
                            'allows_bpp_bonus' => $allowsBpp,
                        ]
                    );

                    if ($rule->wasRecentlyCreated) {
                        $stats['created']++;
                    } else {
                        $stats['updated']++;
                    }
                } catch (Exception $e) {
                    $stats['errors']++;
                    $errors[] = "Fila {$rowNumber}: {$e->getMessage()}";
                }
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'stats' => $stats,
            'errors' => $errors,
        ];
    }

    public function buildTemplate(): array
    {
        $headers = [
            'MANZANA',
            'FINANCIAMIENTO',
            'CI_MIN_%',
            'CUOTA_BALON',
            'BONO_BPP',
        ];

        $exampleRows = [
            ['A', 'CONTADO', '', 'NO', 'NO'],
            ['B', '24', '20', 'NO', 'SI'],
            ['E2', '40', '15', 'SI', 'NO'],
        ];

        return [$headers, ...$exampleRows];
    }

    protected function mapHeaders(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $idx => $header) {
            $key = $this->normalizeHeader((string) $header);
            if ($key === '') continue;

            if (in_array($key, ['MANZANA', 'MZNA', 'MZ'], true)) $map['manzana'] = $idx;
            if (in_array($key, ['FINANCIAMIENTO', 'FINANCIACION', 'CUOTAS', 'PLAZO', 'TEMPLATE'], true)) $map['financiamiento'] = $idx;
            if (in_array($key, ['CI_MIN_%', 'CI_MIN', 'CI_MIN_PCT', 'MIN_DOWN_PAYMENT_PERCENTAGE'], true)) $map['min_down_payment_percentage'] = $idx;
            if (in_array($key, ['CUOTA_BALON', 'BALON', 'ALLOWS_BALLOON_PAYMENT'], true)) $map['allows_balloon_payment'] = $idx;
            if (in_array($key, ['BONO_BPP', 'BPP', 'ALLOWS_BPP_BONUS'], true)) $map['allows_bpp_bonus'] = $idx;
        }
        return $map;
    }

    protected function normalizeHeader(string $header): string
    {
        $header = trim($header);
        if ($header === '') return '';
        $header = mb_strtoupper($header);
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $header);
        if (is_string($ascii) && $ascii !== '') $header = $ascii;
        $header = preg_replace('/\s+/', '_', $header);
        $header = preg_replace('/[^A-Z0-9_%]/', '', $header);
        return $header ?? '';
    }

    protected function parseFinanciamiento(string $value): array
    {
        $raw = strtoupper(trim($value));
        $raw = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $raw) ?: $raw;
        $raw = trim($raw);

        if ($raw === 'CONTADO' || $raw === 'CASH' || $raw === 'AL CONTADO') {
            return [
                'financing_type' => 'cash_only',
                'max_installments' => null,
            ];
        }

        if ($raw === 'MIXTO') {
            return [
                'financing_type' => 'mixed',
                'max_installments' => 40,
            ];
        }

        if (is_numeric($raw)) {
            $months = (int) $raw;
            if (!in_array($months, [24, 40, 44, 55], true)) {
                throw new Exception("Plazo inválido: {$months}");
            }
            return [
                'financing_type' => 'installments',
                'max_installments' => $months,
            ];
        }

        throw new Exception("Financiamiento inválido: {$value}");
    }

    protected function parseBoolean(string $value): bool
    {
        $v = strtoupper(trim($value));
        if ($v === '1' || $v === 'SI' || $v === 'S' || $v === 'YES' || $v === 'Y' || $v === 'TRUE') return true;
        return false;
    }

    protected function getCell(array $row, int $index): string
    {
        $val = $row[$index] ?? '';
        if ($val === null) return '';
        return trim((string) $val);
    }

    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) ($cell ?? '')) !== '') return false;
        }
        return true;
    }
}

