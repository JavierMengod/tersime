<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TokenController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $tokens = $user->tokens()->get();

        return view('usuarios.tokens', compact('tokens'));
    }

    public function apiDocs()
    {
        return view('usuarios.api-docs');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
        ]);

        $user = auth()->user();
        $token = $user->createToken($request->nombre)->plainTextToken;

        return redirect()->back()->with('token_creado', $token);
    }

    public function destroy($id)
    {
        $user = auth()->user();
        $user->tokens()->where('id', $id)->delete();

        return redirect()->back()->with('success', 'Token eliminado correctamente.');
    }
}
