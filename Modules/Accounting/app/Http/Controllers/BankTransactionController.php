<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Accounting\Models\BankTransaction;

class BankTransactionController extends Controller
{
    public function index()
    {
        return BankTransaction::with('account', 'journalEntry')->paginate(15);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'bank_account_id'  => 'required|exists:bank_accounts,bank_account_id',
            'journal_entry_id' => 'nullable|exists:journal_entries,journal_entry_id',
            'date'             => 'required|date',
            'amount'           => 'required|numeric',
            'currency'         => 'required|string|size:3',
            'reference'        => 'nullable|string|max:80',
        ]);

        return BankTransaction::create($data);
    }

    public function show(BankTransaction $bankTransaction)
    {
        return $bankTransaction->load('account', 'journalEntry');
    }

    public function update(Request $request, BankTransaction $bankTransaction)
    {
        $data = $request->validate([
            'journal_entry_id' => 'nullable|exists:journal_entries,journal_entry_id',
            'date'             => 'sometimes|date',
            'amount'           => 'sometimes|numeric',
            'currency'         => 'sometimes|string|size:3',
            'reference'        => 'nullable|string|max:80',
        ]);

        $bankTransaction->update($data);
        return $bankTransaction->load('account', 'journalEntry');
    }

    public function destroy(BankTransaction $bankTransaction)
    {
        $bankTransaction->delete();
        return response()->noContent();
    }
}
