<?php

namespace Modules\HumanResources\Services;

use Carbon\Carbon;

class CommissionEvaluator
{
    /**
     * Evaluate commission percentage based on sales count and term months.
     * Returns array with keys: percentage, scheme_id, rule_id or null if not applicable.
     *
     * @param int $salesCount
     * @param int $termMonths
     * @param string|null $saleType 'cash', 'financed', or null for both
     * @param string|null $asOfDate YYYY-MM-DD or null
     * @return array|null
     */
    public function evaluate(int $salesCount, int $termMonths, ?string $saleType = null, string $asOfDate = null): ?array
    {
        try {
            // If models don't exist in this installation, gracefully return null
            if (!class_exists(\Modules\HumanResources\Models\CommissionScheme::class) || !class_exists(\Modules\HumanResources\Models\CommissionRule::class)) {
                return null;
            }

            $date = $asOfDate ? Carbon::parse($asOfDate) : Carbon::now();

            // Prefer schemes that are active for the given date
            $schemeQuery = \Modules\HumanResources\Models\CommissionScheme::query();
            // If there are effective_from / effective_to columns, use them, otherwise use is_default
            if (\Schema::hasColumn((new \Modules\HumanResources\Models\CommissionScheme)->getTable(), 'effective_from')) {
                $schemeQuery->where(function ($q) use ($date) {
                    $q->whereNull('effective_from')->orWhere('effective_from', '<=', $date->toDateString());
                })->where(function ($q) use ($date) {
                    $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date->toDateString());
                });
            }

            $scheme = $schemeQuery->orderByDesc('is_default')->first();

            if (!$scheme) {
                return null;
            }

            // Find candidate rules by sales range and priority
            $ruleQuery = $scheme->rules()->where(function ($q) use ($salesCount) {
                $q->where('min_sales', '<=', $salesCount)
                  ->where(function ($qq) use ($salesCount) {
                      $qq->whereNull('max_sales')->orWhere('max_sales', '>=', $salesCount);
                  });
            });

            // Prefer to filter by explicit effective window if available; otherwise fall back to created_at
            try {
                $ruleModel = new \Modules\HumanResources\Models\CommissionRule;
                if (\Schema::hasColumn($ruleModel->getTable(), 'effective_from') && \Schema::hasColumn($ruleModel->getTable(), 'effective_to')) {
                    $ruleQuery->where(function ($q) use ($date) {
                        $q->whereNull('effective_from')->orWhere('effective_from', '<=', $date->toDateString());
                    })->where(function ($q) use ($date) {
                        $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date->toDateString());
                    });
                } elseif (\Schema::hasColumn($ruleModel->getTable(), 'created_at')) {
                    $ruleQuery->where('created_at', '<=', $date->toDateString());
                }
            } catch (\Throwable $e) {
                // ignore schema checks if unavailable
            }

            $candidates = $ruleQuery->orderByDesc('priority')->get();

            if ($candidates->isEmpty()) {
                return null;
            }

            // Determine term group legacy fallback
            $termGroup = in_array($termMonths, [12, 24, 36]) ? 'short' : 'long';

            // Prefer rules that explicitly declare term ranges (term_min_months / term_max_months).
            // If rule has explicit term range, use it; otherwise fallback to term_group matching.
            $rule = null;
            foreach ($candidates as $r) {
                // Filter by sale_type if provided
                if ($saleType && \Schema::hasColumn($r->getTable(), 'sale_type')) {
                    // Rule must be 'both' or match the sale_type
                    if ($r->sale_type && $r->sale_type !== 'both' && $r->sale_type !== $saleType) {
                        continue;
                    }
                }
                
                // If schema has term_min_months column and rule defines a range, use it
                if (\Schema::hasColumn($r->getTable(), 'term_min_months') && (!is_null($r->term_min_months) || !is_null($r->term_max_months))) {
                    $min = is_null($r->term_min_months) ? 0 : (int) $r->term_min_months;
                    $max = is_null($r->term_max_months) ? PHP_INT_MAX : (int) $r->term_max_months;
                    if ($termMonths >= $min && $termMonths <= $max) {
                        $rule = $r;
                        break;
                    }
                    continue;
                }

                // Fallback to legacy term_group behavior
                if (is_null($r->term_group) || $r->term_group === $termGroup) {
                    $rule = $r;
                    break;
                }
            }

            if (!$rule) {
                return null;
            }

            return [
                'percentage' => (float) $rule->percentage,
                'scheme_id' => $scheme->id ?? $scheme->commission_scheme_id ?? null,
                'rule_id' => $rule->id ?? $rule->commission_rule_id ?? null,
            ];
        } catch (\Throwable $e) {
            // Do not break the flow — calling code will fallback to legacy logic
            return null;
        }
    }
}
