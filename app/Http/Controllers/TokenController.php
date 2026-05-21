<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TokenController extends Controller
{
    public function index()
    {
        $usuario = auth()->user();
        $tokens  = $usuario->tokens()->get();

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

        $usuario = auth()->user();
        $token   = $usuario->createToken($request->nombre)->plainTextToken;

        return redirect()->back()->with('token_creado', $token);
    }

    public function destroy($id)
    {
        $usuario = auth()->user();
        $usuario->tokens()->where('id', $id)->delete();

        return redirect()->back()->with('success', 'Token eliminado correctamente.');
    }
}
