<?php

namespace App\Http\Controllers;

use App\Models\BookingDetail;
use Illuminate\Http\Request;

use App\Models\Alat;
use App\Models\Jasa;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;

class BookingDetailController extends Controller
{
    // Method untuk menambahkan item (alat/jasa) baru ke dalam suatu booking
    public function store(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => ['required', 'exists:booking,id'],
            'tipe' => ['required', 'in:alat,jasa'],
            'item_id' => ['required', 'integer'],
            'qty' => ['required', 'integer', 'min:1'],
        ]);

        $booking = Booking::findOrFail($validated['booking_id']);

        // Pastikan hanya bisa menambah item jika booking masih pending
        if ($booking->status !== 'pending') {
            return back()->withErrors(['error' => 'Tidak dapat merubah item, Booking sudah diproses.']);
        }

        DB::beginTransaction();
        try {
            $harga = 0;

            // Cari harga aktual dari database
            if ($validated['tipe'] === 'alat') {
                $alat = Alat::findOrFail($validated['item_id']);
                $harga = $alat->harga_sewa;
            } else {
                $jasa = Jasa::findOrFail($validated['item_id']);
                $harga = $jasa->harga;
            }

            $subtotal = $harga * $validated['qty'];

            // Simpan detail (item)
            BookingDetail::create([
                'booking_id' => $booking->id,
                'tipe' => $validated['tipe'],
                'item_id' => $validated['item_id'],
                'qty' => $validated['qty'],
                'harga' => $harga,
                'subtotal' => $subtotal,
            ]);

            // Selaraskan Total Harga Induk
            $booking->increment('total_harga', $subtotal);

            DB::commit();

            return back()->with('success', 'Item berhasil ditambahkan ke Booking.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    // Method untuk menghapus sebuah item dari booking
    public function destroy(BookingDetail $bookingDetail)
    {
        $booking = $bookingDetail->booking;

        if ($booking->status !== 'pending') {
            return back()->withErrors(['error' => 'Tidak dapat menghapus item, Booking sudah diproses.']);
        }

        DB::beginTransaction();
        try {
            // Kurangi harga di tabel induk Booking
            $booking->decrement('total_harga', $bookingDetail->subtotal);

            // Hapus baris detail
            $bookingDetail->delete();

            DB::commit();

            return back()->with('success', 'Item berhasil dihapus dari Booking.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Terjadi kesalahan saat menghapus item.']);
        }
    }
}
