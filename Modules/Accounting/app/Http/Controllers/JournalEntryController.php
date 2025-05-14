<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Accounting\Models\JournalEntry;

class JournalEntryController extends Controller
{
    public function index()
    {
        return JournalEntry::with('lines', 'poster')->paginate(15);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date'        => 'required|date',
            'description' => 'nullable|string|max:255',
            'posted_by'   => 'nullable|exists:users,user_id',
            'status'      => 'required|in:draft,posted',
        ]);

        return JournalEntry::create($data);
    }

    public function show(JournalEntry $journalEntry)
    {
        return $journalEntry->load('lines', 'poster');
    }

    public function update(Request $request, JournalEntry $journalEntry)
    {
        $data = $request->validate([
            'date'        => 'sometimes|date',
            'description' => 'nullable|string|max:255',
            'posted_by'   => 'nullable|exists:users,user_id',
            'status'      => 'sometimes|in:draft,posted',
        ]);

        $journalEntry->update($data);
        return $journalEntry->load('lines', 'poster');
    }

    public function destroy(JournalEntry $journalEntry)
    {
        $journalEntry->delete();
        return response()->noContent();
    }
}
