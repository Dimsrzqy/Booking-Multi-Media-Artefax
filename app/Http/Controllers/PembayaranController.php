<?php

namespace App\Http\Controllers;

use App\Models\Pembayaran;
use Illuminate\Http\Request;

use App\Models\Booking;
use Illuminate\Support\Facades\Auth;

class PembayaranController extends Controller
{
    public function index()
    {
        // Jika login sebagai admin, lihat semua. Jika customer, lihat milik sendiri.
        if (Auth::user()->role === 'admin') {
            $pembayarans = Pembayaran::with('booking.user')->latest()->get();
            return view('admin.pembayaran.index', compact('pembayarans'));
        } else {
            $pembayarans = Pembayaran::whereHas('booking', function ($query) {
                $query->where('user_id', Auth::id());
            })->with('booking')->latest()->get();
            return view('customer.pembayaran.index', compact('pembayarans'));
        }
    }

    public function create()
    {
        // Biasanya untuk customer menginput pembayaran
        $bookings = Booking::where('user_id', Auth::id())
            ->where('status', 'pending')
            ->get();
            
        return view('customer.pembayaran.create', compact('bookings'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => ['required', 'exists:booking,id'],
            'metode_pembayaran' => ['required', 'string', 'max:255'],
            'jumlah_bayar' => ['required', 'numeric', 'min:0'],
        ]);

        // Simpan dengan status default 'pending'
        Pembayaran::create([
            'booking_id' => $validated['booking_id'],
            'metode_pembayaran' => $validated['metode_pembayaran'],
            'jumlah_bayar' => $validated['jumlah_bayar'],
            'status' => 'pending',
        ]);

        return redirect()->route('pembayaran.index')->with('success', 'Pembayaran berhasil dikirim dan menunggu konfirmasi admin.');
    }

    public function show(Pembayaran $pembayaran)
    {
        // Keamanan: pastikan customer hanya melihat datanya sendiri
        if (Auth::user()->role === 'customer' && $pembayaran->booking->user_id !== Auth::id()) {
            abort(403, 'Anda tidak diizinkan melihat data ini.');
        }

        $viewPath = Auth::user()->role === 'admin' ? 'admin.pembayaran.show' : 'customer.pembayaran.show';
        return view($viewPath, compact('pembayaran'));
    }

    // Fungsi khusus untuk admin mengubah status konfirmasi pembayaran
    public function konfirmasi(Request $request, Pembayaran $pembayaran)
    {
        // Pastikan hanya admin yang dapat mengakses fungsi ini (bisa dilapis Middleware di routes)
        if (Auth::user()->role !== 'admin') {
            abort(403, 'Khusus Admin.');
        }

        $validated = $request->validate([
            'status' => ['required', 'in:berhasil,gagal'],
        ]);

        if ($validated['status'] === 'berhasil') {
            $pembayaran->update([
                'status' => 'berhasil',
                'tanggal_bayar' => now(),
            ]);

            // Otomatis update status Booking menjadi 'dibayar'
            $pembayaran->booking->update(['status' => 'dibayar']);
        } else {
            $pembayaran->update([
                'status' => 'gagal',
            ]);
        }

        return redirect()->route('pembayaran.index')->with('success', 'Status pembayaran berhasil dikonfirmasi.');
    }
}
