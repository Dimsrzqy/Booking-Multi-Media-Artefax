<?php

namespace App\Http\Controllers;

use App\Models\Jasa;
use Illuminate\Http\Request;

class JasaController extends Controller
{
    public function index()
    {
        $jasas = Jasa::all();
        return view('admin.jasa.index', compact('jasas'));
    }

    public function create()
    {
        return view('admin.jasa.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'harga' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:aktif,nonaktif'],
        ]);

        Jasa::create($validated);

        return redirect()->route('jasa.index')->with('success', 'Jasa berhasil ditambahkan.');
    }

    public function show(Jasa $jasa)
    {
        return view('admin.jasa.show', compact('jasa'));
    }

    public function edit(Jasa $jasa)
    {
        return view('admin.jasa.edit', compact('jasa'));
    }

    public function update(Request $request, Jasa $jasa)
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'harga' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:aktif,nonaktif'],
        ]);

        $jasa->update($validated);

        return redirect()->route('jasa.index')->with('success', 'Jasa berhasil diubah.');
    }

    public function destroy(Jasa $jasa)
    {
        $jasa->delete();

        return redirect()->route('jasa.index')->with('success', 'Jasa berhasil dihapus.');
    }
}
