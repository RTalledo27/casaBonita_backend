<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Http\Requests\ContractApprovalActionRequest;
use Modules\Sales\Models\ContractApproval;
use Modules\Sales\Repositories\ContractApprovalRepository;
use Modules\Sales\Transformers\ContractApprovalResource;
use Modules\Services\PusherNotifier;

class ContractApprovalController extends Controller
{

    public function __construct(
        private ContractApprovalRepository $approvals,
        private PusherNotifier $pusher
    ) {
        $this->middleware('auth:sanctum');
    }




    /*   public function approve(ContractApprovalActionRequest $request, ContractApproval $approval)
    {
        $this->authorize('approve', $approval);
        $updated = $this->approvals->approve($approval, $request->validated('comments'));
        $this->pusher->notify('contract', 'approval', ['approval' => new ContractApprovalResource($updated)]);

        if ($updated->contract->status === 'vigente') {
            $this->pusher->notify('client', 'contract_finalized', [
                'contract_id' => $updated->contract->contract_id,
                'pdf_path'    => $updated->contract->pdf_path,
            ]);
        }

        return new ContractApprovalResource($updated->load(['contract', 'approver']));
    }*/

    
    /** Aprobar contrato */
    public function approve(
        ContractApprovalActionRequest $request,
        ContractApproval              $approval
    ) {
        $this->authorize('approve', $approval);

        try {
            DB::beginTransaction();

            $updated = $this->approvals->approve(
                $approval,
                $request->validated('comments')
            );

            DB::commit();

            // Notificar aprobación
            
            $this->pusher->notify('contract-channel', 'approval', [
                'approval' => (new ContractApprovalResource($updated))->toArray($request),
            ]);

            // Si el contrato quedó vigente, notificar al cliente
            if ($updated->contract->status === 'vigente') {
                $this->pusherInstance()->trigger('client-channel', 'contract_finalized', [
                    'contract_id' => $updated->contract->contract_id,
                    'pdf_path'    => $updated->contract->pdf_path,
                ]);
            }

            return new ContractApprovalResource(
                $updated->load(['contract', 'approver'])
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al aprobar contrato',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function reject(
        ContractApprovalActionRequest $request,
        ContractApproval              $approval
    ) {
        $this->authorize('reject', $approval);

        try {
            DB::beginTransaction();

            $updated = $this->approvals->reject(
                $approval,
                $request->validated('comments')
            );

            DB::commit();

            $this->pusher->notify('contract-channel', 'approval', [
                'approval' => (new ContractApprovalResource($updated))->toArray($request),
            ]);

            return new ContractApprovalResource(
                $updated->load(['contract', 'approver'])
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al rechazar contrato',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}
