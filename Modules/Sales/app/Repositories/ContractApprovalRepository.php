<?php

namespace Modules\Sales\Repositories;

use Carbon\Carbon;
use Modules\Sales\Models\Contract;
use Modules\Sales\Models\ContractApproval;
use Modules\Sales\Services\ContractPdfService;

class ContractApprovalRepository
{
    public function request(Contract $contract, array $userIds): void
    {
        foreach ($userIds as $id) {
            ContractApproval::create([
                'contract_id' => $contract->contract_id,
                'user_id'     => $id,
                'status'      => 'pendiente'
            ]);
        }
    }

    public function approve(ContractApproval $approval, ?string $comments = null): ContractApproval
    {
        $approval->update([
            'status'      => 'aprobado',
            'approved_at' => Carbon::now(),
            'comments'    => $comments,
        ]);

        $contract = $approval->contract;
        $approved = $contract->approvals()->where('status', 'aprobado')->count();
        if ($approved >= 2 && $contract->status === 'pendiente_aprobacion') {
            $pdf = app(ContractPdfService::class);
            $path = $pdf->finalize($contract);
            $contract->update(['status' => 'vigente', 'pdf_path' => $path]);
        }

        return $approval;
    }

    public function reject(ContractApproval $approval, ?string $comments = null): ContractApproval
    {
        $approval->update([
            'status'      => 'rechazado',
            'approved_at' => Carbon::now(),
            'comments'    => $comments,
        ]);
        $approval->contract->update(['status' => 'cancelado']);
        return $approval;
    }

    public function pendingForUser(int $userId)
    {
        return ContractApproval::where('user_id', $userId)
            ->where('status', 'pendiente')
            ->with('contract')
            ->get();
    }
}
