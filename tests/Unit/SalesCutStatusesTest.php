<?php

namespace Tests\Unit;

use App\Services\SalesCutCalculatorService;
use App\Services\SalesCutService;
use Tests\TestCase;

class SalesCutStatusesTest extends TestCase
{
    public function test_sales_contract_statuses_match_report(): void
    {
        $calcSales = SalesCutCalculatorService::salesContractStatuses();
        $svcSales = SalesCutService::salesContractStatuses();

        $this->assertSame(['vigente'], $calcSales);
        $this->assertSame(['vigente'], $svcSales);
    }

    public function test_payment_contract_statuses_include_pending_approval(): void
    {
        $calcPayments = SalesCutCalculatorService::paymentContractStatuses();
        $svcPayments = SalesCutService::paymentContractStatuses();

        $this->assertContains('pendiente_aprobacion', $calcPayments);
        $this->assertContains('pendiente_aprobacion', $svcPayments);
    }
}
