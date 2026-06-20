<?php

namespace App\Http\Controllers;

use App\Models\CustomInstruction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InstructionController extends Controller
{
    /** Affiche la page des instructions personnalisées. */
    public function index()
    {
        return Inertia::render('Settings/Instructions', [
            'instruction' => auth()->user()->customInstruction,
        ]);
    }

    /** Sauvegarde les instructions personnalisées. */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'about_user' => 'nullable|string|max:2000',
            'behavior' => 'nullable|string|max:2000',
        ]);

        // updateOrCreate = update si existe, sinon create
        CustomInstruction::updateOrCreate(
            ['user_id' => auth()->id()],
            $validated
        );

        return redirect()->back()->with('success', 'Instructions sauvegardees !');
    }
}
