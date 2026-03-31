<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;

use App\Models\Alat;
use App\Models\Jasa;
use App\Models\BookingDetail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function index()
    {
        // Admin melihat semua booking, customer hanya melihat booking miliknya
        if (Auth::user()->role === 'admin') {
            $bookings = Booking::with('user')->latest()->get();
            return view('admin.booking.index', compact('bookings'));
        } else {
            $bookings = Booking::where('user_id', Auth::id())->latest()->get();
            return view('customer.booking.index', compact('bookings'));
        }
    }

    public function create()
    {
        // Menampilkan form booking beserta pilihan alat dan jasa yang aktif
        $alats = Alat::where('status', 'aktif')->get();
        $jasas = Jasa::where('status', 'aktif')->get();

        return view('customer.booking.create', compact('alats', 'jasas'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'alamat' => ['required', 'string'],
            'tanggal_mulai' => ['required', 'date', 'after_or_equal:today'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'items' => ['required', 'array', 'min:1'], // ex: items[0][tipe]=alat, items[0][item_id]=1, items[0][qty]=2
            'items.*.tipe' => ['required', 'in:alat,jasa'],
            'items.*.item_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ]);

        DB::beginTransaction();
        try {
            $kodeBooking = 'BKG-' . strtoupper(uniqid());

            $booking = Booking::create([
                'kode_booking' => $kodeBooking,
                'user_id' => Auth::id(),
                'alamat' => $validated['alamat'],
                'tanggal_mulai' => $validated['tanggal_mulai'],
                'tanggal_selesai' => $validated['tanggal_selesai'],
                'total_harga' => 0,
                'status' => 'pending',
            ]);

            $totalHarga = 0;

            foreach ($validated['items'] as $item) {
                $harga = 0;

                if ($item['tipe'] === 'alat') {
                    $alat = Alat::findOrFail($item['item_id']);
                    $harga = $alat->harga_sewa;

                    // (Opsional) Cek stok alat bisa ditambahkan di sini
                    if ($alat->stok < $item['qty']) {
                        throw new \Exception("Stok alat {$alat->nama} tidak mencukupi.");
                    }
                } else {
                    $jasa = Jasa::findOrFail($item['item_id']);
                    $harga = $jasa->harga;
                }

                $subtotal = $harga * $item['qty'];
                $totalHarga += $subtotal;

                BookingDetail::create([
                    'booking_id' => $booking->id,
                    'tipe' => $item['tipe'],
                    'item_id' => $item['item_id'],
                    'qty' => $item['qty'],
                    'harga' => $harga,
                    'subtotal' => $subtotal,
                ]);
            }

            // Update total harga pada booking
            $booking->update(['total_harga' => $totalHarga]);

            DB::commit();

            return redirect()->route('booking.index')->with('success', 'Booking berhasil dibuat. Silakan lakukan pembayaran.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function show($id)
    {
        $booking = Booking::with('user')->findOrFail($id);
        
        // Memuat detail berdasarkan tipe secara manual karena item_id mereferensi ke tabel berbeda
        $details = BookingDetail::where('booking_id', $booking->id)->get()->map(function($detail) {
            if ($detail->tipe === 'alat') {
                $detail->item = Alat::find($detail->item_id);
            } else {
                $detail->item = Jasa::find($detail->item_id);
            }
            return $detail;
        });

        if (Auth::user()->role === 'customer' && $booking->user_id !== Auth::id()) {
            abort(403, 'Akses ditolak.');
        }

        $viewPath = Auth::user()->role === 'admin' ? 'admin.booking.show' : 'customer.booking.show';
        return view($viewPath, compact('booking', 'details'));
    }

    // Untuk admin mengubah status booking secara manual (misal: selesai atau batal)
    public function update(Request $request, Booking $booking)
    {
        if (Auth::user()->role !== 'admin') {
            abort(403, 'Khusus Admin.');
        }

        $validated = $request->validate([
            'status' => ['required', 'in:pending,dibayar,selesai,batal'],
        ]);

        $booking->update(['status' => $validated['status']]);

        return redirect()->route('booking.index')->with('success', 'Status Booking berhasil diperbarui.');
    }

    public function destroy(Booking $booking)
    {
        if (Auth::user()->role !== 'admin') {
            abort(403, 'Khusus Admin.');
        }

        $booking->delete();

        return redirect()->route('booking.index')->with('success', 'Booking berhasil dihapus.');
    }
}
