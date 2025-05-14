<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Sales\Models\PaymentSchedule;

class PaymentScheduleController extends Controller
{
    public function index()
    {
        return PaymentSchedule::with('contract', 'payments')->paginate(15);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'contract_id' => 'required|exists:contracts,contract_id',
            'due_date'    => 'required|date',
            'amount'      => 'required|numeric',
            'status'      => 'required|in:pendiente,pagado,vencido',
        ]);

        return PaymentSchedule::create($data);
    }

    public function show(PaymentSchedule $schedule)
    {
        return $schedule->load('contract', 'payments');
    }

    public function update(Request $request, PaymentSchedule $schedule)
    {
        $data = $request->validate([
            'due_date' => 'sometimes|date',
            'amount'   => 'sometimes|numeric',
            'status'   => 'sometimes|in:pendiente,pagado,vencido',
        ]);

        $schedule->update($data);
        return $schedule->load('contract', 'payments');
    }

    public function destroy(PaymentSchedule $schedule)
    {
        $schedule->delete();
        return response()->noContent();
    }
}