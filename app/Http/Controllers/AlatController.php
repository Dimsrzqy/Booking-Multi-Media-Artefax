<?php

namespace App\Http\Controllers;

use App\Models\Alat;
use Illuminate\Http\Request;

class AlatController extends Controller
{
    public function index()
    {
        $alats = Alat::all();
        return view('admin.alat.index', compact('alats'));
    }
    public function create()
    {
        return view('admin.alat.create');
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'stok' => ['required', 'integer', 'min:0'],
            'harga_sewa' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:aktif,nonaktif'],
        ]);

        Alat::create($validated);

        return redirect()->route('alat.index')->with('success', 'Alat berhasil ditambahkan.');
    }
    public function show(Alat $alat)
    {
        return view('admin.alat.show', compact('alat'));
    }
    public function edit(Alat $alat)
    {
        return view('admin.alat.edit', compact('alat'));
    }
    public function update(Request $request, Alat $alat)
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'stok' => ['required', 'integer', 'min:0'],
            'harga_sewa' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:aktif,nonaktif'],
        ]);

        $alat->update($validated);

        return redirect()->route('alat.index')->with('success', 'Alat berhasil diubah.');
    }
    public function destroy(Alat $alat)
    {
        $alat->delete();

        return redirect()->route('alat.index')->with('success', 'Alat berhasil dihapus.');
    }
}
