<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Accounting\Models\ChartOfAccount;

class ChartOfAccountController extends Controller
{
    public function index()
    {
        return ChartOfAccount::all();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|unique:chart_of_accounts,code',
            'name' => 'required|string|max:120',
            'type' => 'required|in:activo,pasivo,patrimonio,ingreso,gasto,orden',
        ]);

        return ChartOfAccount::create($data);
    }

    public function show(ChartOfAccount $chartOfAccount)
    {
        return $chartOfAccount->load('entries');
    }

    public function update(Request $request, ChartOfAccount $chartOfAccount)
    {
        $data = $request->validate([
            'code' => 'required|string|unique:chart_of_accounts,code,' . $chartOfAccount->account_id . ',account_id',
            'name' => 'required|string|max:120',
            'type' => 'required|in:activo,pasivo,patrimonio,ingreso,gasto,orden',
        ]);

        $chartOfAccount->update($data);
        return $chartOfAccount;
    }

    public function destroy(ChartOfAccount $chartOfAccount)
    {
        $chartOfAccount->delete();
        return response()->noContent();
    }
}
