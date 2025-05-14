<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Accounting\Models\BankAccount;

class BankAccountController extends Controller
{
    public function index()
    {
        return BankAccount::all();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'bank_name'      => 'required|string|max:80',
            'currency'       => 'required|string|size:3',
            'account_number' => 'required|string|unique:bank_accounts,account_number',
        ]);

        return BankAccount::create($data);
    }

    public function show(BankAccount $bankAccount)
    {
        return $bankAccount->load('transactions');
    }

    public function update(Request $request, BankAccount $bankAccount)
    {
        $data = $request->validate([
            'bank_name'      => 'sometimes|string|max:80',
            'currency'       => 'sometimes|string|size:3',
            'account_number' => 'required|string|unique:bank_accounts,account_number,' . $bankAccount->bank_account_id . ',bank_account_id',
        ]);

        $bankAccount->update($data);
        return $bankAccount->load('transactions');
    }

    public function destroy(BankAccount $bankAccount)
    {
        $bankAccount->delete();
        return response()->noContent();
    }
}
