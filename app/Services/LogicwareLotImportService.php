<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\LotFinancialTemplate;
use Modules\Inventory\Models\Manzana;
use Modules\Inventory\Models\ManzanaFinancingRule;
use Modules\Inventory\Models\StreetType;

class LogicwareLotImportService
{
    protected LogicwareApiService $logicwareApi;
    protected array $stats = [
        'total' => 0,
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0
    ];
    protected array $errors = [];
    protected array $warnings = [];

    public function __construct(LogicwareApiService $logicwareApi)
    {
        $this->logicwareApi = $logicwareApi;
    }

    public function importLotsByStage(string $projectCode, string $stageId, array $options = []): array
    {
        $options = array_merge([
            'update_existing' => false,
            'create_manzanas' => true,
            'create_templates' => true,
            'update_templates' => true,
            'update_status' => false,
            'force_refresh' => false,
        ], $options);

        DB::beginTransaction();

        try {
            $stockData = $this->logicwareApi->getStockByStage($projectCode, $stageId, (bool) $options['force_refresh']);
            $units = $stockData['data'] ?? null;
            if (!is_array($units)) {
                throw new Exception('Respuesta inválida del API de LogicWare');
            }

            $this->stats['total'] = count($units);

            foreach ($units as $index => $unit) {
                try {
                    $this->processUnit($unit, $options);
                } catch (Exception $e) {
                    $this->stats['errors']++;
                    $this->errors[] = [
                        'unit' => $unit['code'] ?? "Unidad #{$index}",
                        'error' => $e->getMessage()
                    ];
                    Log::error('[LogicwareLotImport] Error procesando unidad', [
                        'unit_code' => $unit['code'] ?? null,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            DB::commit();

            return [
                'success' => true,
                'message' => $this->buildSuccessMessage(),
                'stats' => $this->stats,
                'errors' => $this->errors,
                'warnings' => $this->warnings,
                'projectCode' => $projectCode,
                'stageId' => $stageId,
                'is_mock' => $stockData['is_mock'] ?? false
            ];
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('[LogicwareLotImport] ❌ Error en importación', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Error en la importación: ' . $e->getMessage(),
                'stats' => $this->stats,
                'errors' => $this->errors
            ];
        }
    }

    protected function processUnit(array $unit, array $options): void
    {
        if (empty($unit['code'])) {
            throw new Exception('Unidad sin código identificador');
        }

        $parsed = $this->parseUnitCode($unit['code']);
        if (!$parsed) {
            $this->warnings[] = [
                'unit' => $unit['code'],
                'warning' => 'Formato de código no reconocido, se intentará crear igual'
            ];
        }

        $manzanaName = $parsed['manzana'] ?? (string) ($unit['block'] ?? 'X');
        $manzana = $this->findOrCreateManzana($manzanaName, $options);

        $numLot = $parsed['lot_number'] ?? $unit['code'];
        $existingLot = Lot::where('num_lot', $numLot)
            ->where('manzana_id', $manzana->manzana_id)
            ->first();

        if ($existingLot) {
            if (!($options['update_existing'] ?? false)) {
                $this->stats['skipped']++;
                return;
            }

            $this->updateLot($existingLot, $unit, $options);
            $this->stats['updated']++;
            return;
        }

        $this->createLot($manzana, $unit, $parsed, $options);
        $this->stats['created']++;
    }

    protected function parseUnitCode(string $code): ?array
    {
        $code = trim(strtoupper($code));

        if (preg_match('/^([A-Z]+)-(\d+)$/', $code, $m)) {
            return ['manzana' => $m[1], 'lot_number' => (int) $m[2]];
        }

        if (preg_match('/^MZ[A-Z]?-?([A-Z]+)-(\d+)$/', $code, $m)) {
            return ['manzana' => $m[1], 'lot_number' => (int) $m[2]];
        }

        if (preg_match('/^([A-Z]\d+)-(\d+)$/', $code, $m)) {
            return ['manzana' => $m[1], 'lot_number' => (int) $m[2]];
        }

        return null;
    }

    protected function findOrCreateManzana(string $manzanaName, array $options): Manzana
    {
        $manzanaName = trim($manzanaName) === '' ? 'X' : trim($manzanaName);
        $manzana = Manzana::where('name', $manzanaName)->first();

        if ($manzana) {
            return $manzana;
        }

        if (!($options['create_manzanas'] ?? true)) {
            throw new Exception("Manzana '{$manzanaName}' no existe y la creación automática está deshabilitada");
        }

        return Manzana::create(['name' => $manzanaName]);
    }

    protected function createLot(Manzana $manzana, array $unit, ?array $parsed, array $options): Lot
    {
        $streetTypeId = $this->resolveStreetTypeId($unit);
        $numLot = $parsed['lot_number'] ?? $unit['code'];

        $lot = Lot::create([
            'manzana_id' => $manzana->manzana_id,
            'street_type_id' => $streetTypeId,
            'num_lot' => (int) $numLot,
            'area_m2' => (float) ($unit['area'] ?? 0),
            'area_construction_m2' => isset($unit['construction_area']) ? (float) $unit['construction_area'] : null,
            'total_price' => (float) ($unit['price'] ?? 0),
            'currency' => strtoupper($unit['currency'] ?? 'PEN'),
            'status' => $this->mapStatus((string) ($unit['status'] ?? 'disponible')),
            'external_id' => $unit['id'] ?? null,
            'external_code' => $unit['code'] ?? null,
            'external_sync_at' => now(),
            'external_data' => [
                'source' => 'logicware.stage_stock',
                'unit' => $unit,
            ],
        ]);

        if (!empty($unit['price']) && ($options['create_templates'] ?? true)) {
            $manzana->loadMissing('financingRule');
            $this->createOrUpdateCalculatedTemplate($lot, $unit, $manzana->financingRule);
        }

        return $lot;
    }

    protected function updateLot(Lot $lot, array $unit, array $options): void
    {
        $updates = [];

        if (isset($unit['area']) && (float) $lot->area_m2 !== (float) $unit['area']) {
            $updates['area_m2'] = (float) $unit['area'];
        }

        if (isset($unit['construction_area'])) {
            $incoming = $unit['construction_area'] === null ? null : (float) $unit['construction_area'];
            if ((string) $lot->area_construction_m2 !== (string) $incoming) {
                $updates['area_construction_m2'] = $incoming;
            }
        }

        if (isset($unit['price']) && (float) $lot->total_price !== (float) $unit['price']) {
            $updates['total_price'] = (float) $unit['price'];
        }

        if (isset($unit['currency']) && $unit['currency'] && $lot->currency !== strtoupper($unit['currency'])) {
            $updates['currency'] = strtoupper($unit['currency']);
        }

        if (($options['update_status'] ?? false) && isset($unit['status'])) {
            $newStatus = $this->mapStatus((string) $unit['status']);
            if ($lot->status !== $newStatus) {
                $updates['status'] = $newStatus;
            }
        }

        if (empty($lot->external_id) && !empty($unit['id'])) {
            $updates['external_id'] = $unit['id'];
        }

        if (empty($lot->external_code) && !empty($unit['code'])) {
            $updates['external_code'] = $unit['code'];
        }

        $updates['external_sync_at'] = now();
        $updates['external_data'] = [
            'source' => 'logicware.stage_stock',
            'unit' => $unit,
        ];

        if (!empty($updates)) {
            $lot->update($updates);
        }

        if (!empty($unit['price']) && ($options['update_templates'] ?? true)) {
            $lot->loadMissing(['manzana.financingRule']);
            $this->createOrUpdateCalculatedTemplate($lot, $unit, $lot->manzana?->financingRule);
        }
    }

    protected function createOrUpdateCalculatedTemplate(Lot $lot, array $unit, ?ManzanaFinancingRule $rule = null): void
    {
        $price = (float) ($unit['price'] ?? 0);
        if ($price <= 0) {
            return;
        }

        $downPaymentPercent = $rule?->min_down_payment_percentage !== null
            ? (float) $rule->min_down_payment_percentage
            : 20.0;
        $downPaymentPercent = max(0.0, min(100.0, $downPaymentPercent));

        $downPayment = round($price * ($downPaymentPercent / 100), 2);
        $financingAmount = $price - $downPayment;

        $installments24 = $financingAmount > 0 ? round($financingAmount / 24, 2) : 0.0;
        $installments40 = $financingAmount > 0 ? round($financingAmount / 40, 2) : 0.0;
        $installments44 = $financingAmount > 0 ? round($financingAmount / 44, 2) : 0.0;
        $installments55 = $financingAmount > 0 ? round($financingAmount / 55, 2) : 0.0;

        if ($rule) {
            if ($rule->financing_type === 'cash_only') {
                $downPayment = $price;
                $financingAmount = 0.0;
                $installments24 = 0.0;
                $installments40 = 0.0;
                $installments44 = 0.0;
                $installments55 = 0.0;
            } elseif (in_array($rule->financing_type, ['installments', 'mixed'], true) && $rule->max_installments) {
                $installments24 = 0.0;
                $installments40 = 0.0;
                $installments44 = 0.0;
                $installments55 = 0.0;

                $allowed = (int) $rule->max_installments;
                $allowedInstallment = $financingAmount > 0 ? round($financingAmount / $allowed, 2) : 0.0;

                if ($allowed === 24) $installments24 = $allowedInstallment;
                if ($allowed === 40) $installments40 = $allowedInstallment;
                if ($allowed === 44) $installments44 = $allowedInstallment;
                if ($allowed === 55) $installments55 = $allowedInstallment;
            }
        }

        $templateData = [
            'precio_lista' => $price,
            'descuento' => 0,
            'precio_venta' => $price,
            'precio_contado' => round($price * 0.95, 2),
            'cuota_inicial' => $downPayment,
            'ci_fraccionamiento' => $downPayment,
            'cuota_balon' => 0,
            'bono_bpp' => 0,
            'installments_24' => $installments24,
            'installments_40' => $installments40,
            'installments_44' => $installments44,
            'installments_55' => $installments55,
        ];

        LotFinancialTemplate::updateOrCreate(
            ['lot_id' => $lot->lot_id],
            $templateData
        );
    }

    protected function resolveStreetTypeId(array $unit): int
    {
        $name = $unit['street_type'] ?? $unit['streetType'] ?? $unit['streetTypeName'] ?? $unit['roadType'] ?? null;
        if (is_array($name)) {
            $name = $name['name'] ?? ($name['label'] ?? null);
        }

        if (!is_string($name) || trim($name) === '') {
            return (int) StreetType::firstOrCreate(['name' => 'Sin Especificar'])->street_type_id;
        }

        $normalized = mb_strtolower(trim($name));
        $normalized = str_replace(['.', ',', ';', ':', '-', '_', '/', '\\'], ' ', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if (is_string($ascii) && $ascii !== '') {
            $normalized = $ascii;
        }
        $normalized = trim($normalized);

        if ($normalized === '') {
            return (int) StreetType::firstOrCreate(['name' => 'Sin Especificar'])->street_type_id;
        }

        $mapped = null;
        if (preg_match('/^(av|avd|avda|avenida)\b/', $normalized)) $mapped = 'Avenida';
        elseif (preg_match('/^(cl|calle)\b/', $normalized)) $mapped = 'Calle';
        elseif (preg_match('/^(jr|jiron)\b/', $normalized)) $mapped = 'Jirón';
        elseif (preg_match('/^(psj|pje|pasaje)\b/', $normalized)) $mapped = 'Pasaje';

        $target = $mapped ?: implode(' ', array_map(fn($w) => $w === '' ? '' : mb_strtoupper(mb_substr($w, 0, 1)) . mb_substr($w, 1), explode(' ', $normalized)));
        if ($target === '') $target = 'Sin Especificar';

        return (int) StreetType::firstOrCreate(['name' => $target])->street_type_id;
    }

    protected function mapStatus(string $logicwareStatus): string
    {
        $normalized = strtolower(trim($logicwareStatus));

        $statusMap = [
            'disponible' => 'disponible',
            'available' => 'disponible',
            'vendido' => 'vendido',
            'sold' => 'vendido',
            'reservado' => 'reservado',
            'reserved' => 'reservado',
            'bloqueado' => 'reservado',
            'blocked' => 'reservado',
        ];

        return $statusMap[$normalized] ?? 'disponible';
    }

    protected function buildSuccessMessage(): string
    {
        $parts = [];
        if ($this->stats['created'] > 0) $parts[] = "{$this->stats['created']} lotes creados";
        if ($this->stats['updated'] > 0) $parts[] = "{$this->stats['updated']} lotes actualizados";
        if ($this->stats['skipped'] > 0) $parts[] = "{$this->stats['skipped']} lotes omitidos";
        if ($this->stats['errors'] > 0) $parts[] = "{$this->stats['errors']} errores";
        return !empty($parts) ? 'Importación completada: ' . implode(', ', $parts) : 'No se procesaron lotes';
    }

    public function getStats(): array
    {
        return $this->stats;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }
}
