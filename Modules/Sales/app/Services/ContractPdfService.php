<?php

namespace Modules\Sales\Services;

use Illuminate\Support\Facades\Storage;
use Modules\Sales\Models\Contract;
use Spatie\Browsershot\Browsershot;

class ContractPdfService
{
    protected function previewHtml(Contract $contract): string
    {
        return '<h1>Contract Preview</h1>';
    }

    protected function finalHtml(Contract $contract): string
    {
        return '<h1>Contract Final</h1>';
    }

    public function preview(Contract $contract): string
    {
        $path = 'contracts/previews/' . $contract->contract_id . '.pdf';
        Browsershot::html($this->previewHtml($contract))
            ->savePdf(Storage::path($path));

        return Storage::path($path);
    }

    public function finalize(Contract $contract): string
    {
        $path = 'contracts/' . $contract->contract_id . '.pdf';
        Browsershot::html($this->finalHtml($contract))
            ->savePdf(Storage::path($path));

        return Storage::path($path);
    }

}
