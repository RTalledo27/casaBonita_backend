<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Sales\Models\Payment;

class PaymentController extends Controller
{
    public function index()
    {
        return Payment::with('schedule', 'journalEntry')->paginate(15);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'schedule_id'      => 'required|exists:payment_schedules,schedule_id',
            'journal_entry_id' => 'nullable|exists:journal_entries,journal_entry_id',
            'payment_date'     => 'required|date',
            'amount'           => 'required|numeric',
            'method'           => 'required|in:transferencia,efectivo,tarjeta',
            'reference'        => 'nullable|string|max:60',
        ]);

        return Payment::create($data);
    }

    public function show(Payment $payment)
    {
        return $payment->load('schedule', 'journalEntry');
    }

    public function update(Request $request, Payment $payment)
    {
        $data = $request->validate([
            'payment_date'     => 'sometimes|date',
            'amount'           => 'sometimes|numeric',
            'method'           => 'sometimes|in:transferencia,efectivo,tarjeta',
            'reference'        => 'nullable|string|max:60',
        ]);

        $payment->update($data);
        return $payment->load('schedule', 'journalEntry');
    }

    public function destroy(Payment $payment)
    {
        $payment->delete();
        return response()->noContent();
    }
}
