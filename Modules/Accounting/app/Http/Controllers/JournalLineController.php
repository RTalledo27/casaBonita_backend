<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Accounting\Models\JournalLine;

class JournalLineController extends Controller
{
    public function index()
    {
        return JournalLine::with('entry', 'account', 'lot')->paginate(15);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'journal_entry_id' => 'required|exists:journal_entries,journal_entry_id',
            'account_id'       => 'required|exists:chart_of_accounts,account_id',
            'lot_id'           => 'nullable|exists:lots,lot_id',
            'debit'            => 'required|numeric|min:0',
            'credit'           => 'required|numeric|min:0',
        ]);

        return JournalLine::create($data);
    }

    public function show(JournalLine $journalLine)
    {
        return $journalLine->load('entry', 'account', 'lot');
    }

    public function update(Request $request, JournalLine $journalLine)
    {
        $data = $request->validate([
            'journal_entry_id' => 'sometimes|exists:journal_entries,journal_entry_id',
            'account_id'       => 'sometimes|exists:chart_of_accounts,account_id',
            'lot_id'           => 'nullable|exists:lots,lot_id',
            'debit'            => 'sometimes|numeric|min:0',
            'credit'           => 'sometimes|numeric|min:0',
        ]);

        $journalLine->update($data);
        return $journalLine->load('entry', 'account', 'lot');
    }

    public function destroy(JournalLine $journalLine)
    {
        $journalLine->delete();
        return response()->noContent();
    }
}
