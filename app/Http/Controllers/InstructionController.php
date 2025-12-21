<?php

namespace App\Http\Controllers;

use App\Models\Instruction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InstructionController extends Controller
{
    public function index()
    {
        $instructions = Auth::user()->instructions()
            ->orderBy('created_at', 'desc')
            ->get();

        return view('instructions.index', compact('instructions'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'instruction' => 'required|string|max:500',
            'triggers' => 'nullable|array',
        ]);

        Instruction::create([
            'user_id' => Auth::id(),
            'instruction' => $request->instruction,
            'triggers' => $request->triggers ?? ['new_email'],
            'is_active' => true,
        ]);

        return redirect()->route('instructions.index')
            ->with('success', 'Instruction added successfully!');
    }

    public function toggle(Instruction $instruction)
    {
        if ($instruction->user_id !== Auth::id()) {
            abort(403);
        }

        $instruction->update(['is_active' => !$instruction->is_active]);

        return redirect()->route('instructions.index')
            ->with('success', 'Instruction updated!');
    }

    public function destroy(Instruction $instruction)
    {
        if ($instruction->user_id !== Auth::id()) {
            abort(403);
        }

        $instruction->delete();

        return redirect()->route('instructions.index')
            ->with('success', 'Instruction deleted!');
    }
}