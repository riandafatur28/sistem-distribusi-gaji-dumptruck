<?php

namespace App\Http\Controllers;

use App\Models\Periode;
use App\Models\Ritase;
use App\Models\Sopir;
use App\Models\Tujuan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RitaseController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search', '');
        $filterPeriode = $request->get('periode', '');
        $filterSopir = $request->get('sopir', '');
        $tglMulai = $request->get('tanggal_mulai', '');
        $tglSelesai = $request->get('tanggal_selesai', '');
        $partial = $request->get('partial', '');

        if (empty($filterPeriode) && !$tglMulai && !$tglSelesai) {
            $today = now()->toDateString();
            $default = Periode::where('tanggal_mulai', '<=', $today)
                ->where('tanggal_selesai', '>=', $today)
                ->first();
            if ($default) $filterPeriode = (string) $default->id;
        }

        $periodes = Cache::remember('ritase_periodes', 300, fn() => Periode::orderBy('id', 'asc')->get());
        $sopirs = Cache::remember('ritase_sopirs_aktif', 300, fn() => Sopir::where('status', 'aktif')->orderBy('id', 'asc')->get());

        $ritases = Ritase::with(['periode', 'sopir', 'tujuan'])
            ->when($filterPeriode, function ($query) use ($filterPeriode) {
                $query->where('periode_id', $filterPeriode);
            })
            ->when($filterSopir, function ($query) use ($filterSopir) {
                $query->where('kode_sopir', $filterSopir);
            })
            ->when($tglMulai, function ($query) use ($tglMulai) {
                $query->where('tanggal', '>=', $tglMulai);
            })
            ->when($tglSelesai, function ($query) use ($tglSelesai) {
                $query->where('tanggal', '<=', $tglSelesai);
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('kode_ritase', 'like', "%{$search}%")
                      ->orWhereHas('sopir', function ($sq) use ($search) {
                          $sq->where('nama', 'like', "%{$search}%");
                      })
                      ->orWhereHas('tujuan', function ($sq) use ($search) {
                          $sq->where('nama', 'like', "%{$search}%");
                      });
                });
            })
            ->orderBy('id', 'asc')
            ->paginate(15)
            ->withQueryString();

        // Kalau partial request (live search via AJAX), kirim HTML tabel aja
        if ($partial === '1') {
            $html = view('ritase._table', compact('ritases', 'search'))->render();
            return response()->json(['html' => $html]);
        }

        $totalRitase = Ritase::count();
        $ritaseValid = Ritase::where('status', 'valid')->count();
        $ritasePending = Ritase::where('status', 'pending')->count();
        $ritaseGagal = Ritase::where('status', 'gagal_produksi')->count();

        return view('ritase.index', compact(
            'ritases',
            'periodes',
            'sopirs',
            'search',
            'filterPeriode',
            'filterSopir',
            'tglMulai',
            'tglSelesai',
            'totalRitase',
            'ritaseValid',
            'ritasePending',
            'ritaseGagal'
        ));
    }

    public function store(Request $request)
    {
        $rules = [
            'periode_id' => 'required|exists:periodes,id',
            'kode_sopir' => 'required|exists:sopirs,kode_sopir',
            'kode_tujuan' => 'required|exists:tujuans,kode_tujuan',
            'tanggal' => 'required|date',
            'waktu' => 'required|in:pagi,malam',
            'kabupaten' => 'required|in:Nganjuk,Kediri,Kota Kediri,Jombang,Lainnya',
            'status' => 'required|in:valid,pending,gagal_produksi',
            'nominal_kompensasi' => 'nullable',
            'catatan' => 'nullable|string|max:500',
        ];

        $validated = $request->validate($rules);
        $validated['nominal_kompensasi'] = is_numeric($validated['nominal_kompensasi'] ?? 0) ? (float) $validated['nominal_kompensasi'] : 0;

        if (cache()->get('aturan_validasi_enabled', false)) {
            $validasi = \App\Models\ValidasiBukti::where('kode_sopir', $request->kode_sopir)
                ->where('tanggal', $request->tanggal)
                ->where('kode_tujuan', $request->kode_tujuan)
                ->where('status', 'disetujui')
                ->exists();
            if (!$validasi) {
                return back()->withInput()->with('error', 'Sopir ini belum memiliki bukti validasi yang disetujui untuk tanggal dan tujuan ini.');
            }
        }

        // 🔥🔥🔥 HITUNG DT - PASTIKAN INI BERJALAN 🔥🔥🔥
        $dtValue = $this->hitungDT($request, null);

        // Debug: log nilai DT (hapus setelah berhasil)
        \Log::info('STORE - DT Value:', [
            'dt' => $dtValue,
            'status' => $request->status,
            'kabupaten' => $request->kabupaten,
            'kode_sopir' => $request->kode_sopir,
            'tanggal' => $request->tanggal,
            'waktu' => $request->waktu
        ]);

        $ritase = Ritase::create([
            'periode_id' => $request->periode_id,
            'kode_sopir' => $request->kode_sopir,
            'kode_tujuan' => $request->kode_tujuan,
            'tanggal' => $request->tanggal,
            'waktu' => $request->waktu,
            'kabupaten' => $request->kabupaten,
            'status' => $request->status,
            'dt' => $dtValue,
            'upah_sopir' => 0,
            'nominal_kompensasi' => $validated['nominal_kompensasi'],
            'catatan' => $request->catatan,
        ]);

        return redirect()->route('ritase.index')
            ->with('success', 'Ritase berhasil ditambahkan! DT: Rp ' . number_format($dtValue, 0, ',', '.'));
    }

    public function update(Request $request, $id)
    {
        $rules = [
            'periode_id' => 'required|exists:periodes,id',
            'kode_sopir' => 'required|exists:sopirs,kode_sopir',
            'kode_tujuan' => 'required|exists:tujuans,kode_tujuan',
            'tanggal' => 'required|date',
            'waktu' => 'required|in:pagi,malam',
            'kabupaten' => 'required|in:Nganjuk,Kediri,Kota Kediri,Jombang,Lainnya',
            'status' => 'required|in:valid,pending,gagal_produksi',
            'nominal_kompensasi' => 'nullable',
            'catatan' => 'nullable|string|max:500',
        ];

        $validated = $request->validate($rules);
        $validated['nominal_kompensasi'] = is_numeric($validated['nominal_kompensasi'] ?? 0) ? (float) $validated['nominal_kompensasi'] : 0;

        $ritase = Ritase::findOrFail($id);

        // HITUNG ULANG DT
        $dtValue = $this->hitungDT($request, $id);

        \Log::info('UPDATE - DT Value:', [
            'dt' => $dtValue,
            'status' => $request->status,
            'id' => $id
        ]);

        $ritase->update([
            'periode_id' => $request->periode_id,
            'kode_sopir' => $request->kode_sopir,
            'kode_tujuan' => $request->kode_tujuan,
            'tanggal' => $request->tanggal,
            'waktu' => $request->waktu,
            'kabupaten' => $request->kabupaten,
            'status' => $request->status,
            'dt' => $dtValue,
            'nominal_kompensasi' => $validated['nominal_kompensasi'],
            'catatan' => $request->catatan,
        ]);

        return redirect()->route('ritase.index')
            ->with('success', 'Data ritase berhasil diperbarui! DT: Rp ' . number_format($dtValue, 0, ',', '.'));
    }

    public function destroy($id)
    {
        try {
            $ritase = Ritase::findOrFail($id);
            $ritase->delete();

            return redirect()->route('ritase.index')
                ->with('success', 'Data ritase berhasil dihapus!');
        } catch (\Exception $e) {
            return redirect()->route('ritase.index')
                ->with('error', 'Gagal menghapus data: ' . $e->getMessage());
        }
    }

    /**
     * 🔥🔥🔥 FUNGSI HITUNG DT (DIPERBAIKI) 🔥🔥🔥
     *
     * ATURAN:
     * 1. Status = 'gagal_produksi' → DT = 0
     * 2. Kabupaten = 'Lainnya' → TETAP DAPAT DT (Rp 330.000)
     * 3. Cek rit lain di tanggal yang SAMA dengan kabupaten SAMA dan waktu SAMA:
     *    - Jika ada → DT = 0 (hitung 1x)
     *    - Jika tidak ada → DT = 330.000
     */
    private function hitungDT($request, $excludeId = null)
    {
        // 🔥 Jika status Gagal Produksi → DT = 0
        if ($request->status === 'gagal_produksi') {
            return 0;
        }

        // 🔥🔥🔥 KABUPATEN LAINNYA TETAP DAPAT DT 🔥🔥🔥
        // HAPUS SEMUA PENGECEKAN KABUPATEN LAINNYA
        // if ($request->kabupaten === 'Lainnya') {
        //     return 0; // ← INI YANG HARUS DIHAPUS!
        // }

        // Cari rit lain dengan kriteria:
        // - Sopir sama
        // - Tanggal sama
        // - Kabupaten SAMA
        // - Waktu SAMA
        // - Status BUKAN gagal_produksi
        $query = Ritase::where('kode_sopir', $request->kode_sopir)
            ->where('tanggal', $request->tanggal)
            ->where('kabupaten', $request->kabupaten)
            ->where('waktu', $request->waktu)
            ->where('status', '!=', 'gagal_produksi');

        // Jika update, exclude ID sendiri
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $ritLain = $query->first();

        // 🔥 Jika ada rit lain dengan kabupaten & waktu SAMA → DT = 0 (hitung 1x)
        if ($ritLain) {
            return 0;
        }

        // 🔥 Default: DT = 330.000
        return 330000;
    }

    public function bulkStore(Request $request)
    {
        $request->validate([
            'periode_id' => 'required|exists:periodes,id',
            'tanggal' => 'required|date',
            'waktu' => 'required|in:pagi,malam',
            'kabupaten' => 'required|in:Nganjuk,Kediri,Kota Kediri,Jombang,Lainnya',
            'status' => 'required|in:valid,pending,gagal_produksi',
            'daftar_sopir' => 'required|string',
        ]);

        // Muat semua alias tujuan
        $tujuanAliases = \App\Models\TujuanAlias::pluck('canonical', 'alias');

        // Ganti alias di depan nama, misal "cmm blitar kota" -> "kormuling blitar kota"
        $resolveAlias = function ($nama) use ($tujuanAliases) {
            $first = explode(' ', trim($nama), 2);
            $kataDepan = strtolower($first[0]);
            if ($tujuanAliases->has($kataDepan)) {
                $sisa = $first[1] ?? '';
                return trim($tujuanAliases[$kataDepan] . ' ' . $sisa);
            }
            return trim($nama);
        };

        // Cari/buat Tujuan berdasarkan nama (exact -> fuzzy -> create)
        $findOrCreateTujuan = function ($namaTujuan) use ($resolveAlias) {
            $nama = $resolveAlias(trim($namaTujuan));
            $t = Tujuan::where('nama', $nama)->first();
            if ($t) return $t;
            // Fuzzy — huruf depan sama + jarak ≤ 1
            $all = Tujuan::all(['id', 'nama']);
            $bestDist = PHP_INT_MAX;
            $best = null;
            $lower = strtolower($nama);
            $first = $lower[0] ?? '';
            foreach ($all as $t) {
                $tLower = strtolower($t->nama);
                if (($tLower[0] ?? '') !== $first) continue;
                $dist = levenshtein($lower, $tLower);
                if ($dist <= 1 && $dist < $bestDist) {
                    $bestDist = $dist;
                    $best = $t;
                }
            }
            if ($best) return $best;
            return Tujuan::create(['nama' => $nama, 'status' => 'aktif']);
        };

        $lines = explode("\n", $request->daftar_sopir);
        $sopirs = \App\Models\Sopir::where('status', 'aktif')->get()->keyBy('nama');
        $sopirsLower = [];
        foreach ($sopirs as $nama => $s) {
            $sopirsLower[strtolower(trim($nama))] = $s;
        }

        $created = 0;
        $errors = [];
        $currentTujuan = null;
        $currentGroup = '';
        $parsedTanggal = $request->tanggal;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Skip jika cuma angka atau delimiter
            if (preg_match('~^[\d\s\-_|\\/]+$~', $line)) continue;

            // Parse tanggal dari baris pertama: "22 07 26 rabu" → 2026-07-22
            if (preg_match('/^(\d{1,2})\s+(\d{1,2})\s+(\d{2,4})\s/', $line, $m)) {
                $d = str_pad($m[1], 2, '0', STR_PAD_LEFT);
                $mo = str_pad($m[2], 2, '0', STR_PAD_LEFT);
                $y = strlen($m[3]) === 2 ? '20'.$m[3] : $m[3];
                if (checkdate((int)$mo, (int)$d, (int)$y)) {
                    $parsedTanggal = "{$y}-{$mo}-{$d}";
                }
                continue;
            }

            // "Paket ..." -> set tujuan untuk sopir setelahnya
            if (preg_match('/^Paket\s+(.+)$/i', $line, $m)) {
                $currentGroup = trim($m[1]);
                $currentTujuan = $findOrCreateTujuan($currentGroup);
                continue;
            }

            // Try to extract sopir name from the line
            $name = null;
            $catatanTambahan = null;

            // Numbered: "1. Riki" or "1.Riki"
            if (preg_match('/^\s*\d+[\.\s]\s*(.+)$/', $line, $m)) {
                $name = trim($m[1]);
            } else {
                // Plain line: first word = sopir, sisanya = catatan
                $parts = explode(' ', $line, 2);
                $name = $parts[0];
                $catatanTambahan = $parts[1] ?? null;
            }

            if (!$name) continue;

            // Normalize: remove extra spaces
            $name = preg_replace('/\s+/', ' ', $name);

            // Try to match sopir (case-insensitive)
            $namaLower = strtolower(trim($name));
            $sopir = $sopirsLower[$namaLower] ?? null;

            // Fuzzy — huruf depan sama + jarak ≤ 1
            if (!$sopir) {
                $bestDist = PHP_INT_MAX;
                $bestKey = null;
                $first = $namaLower[0] ?? '';
                foreach ($sopirsLower as $key => $s) {
                    if (($key[0] ?? '') !== $first) continue;
                    $dist = levenshtein($namaLower, $key);
                    if ($dist <= 1 && $dist < $bestDist) {
                        $bestDist = $dist;
                        $bestKey = $key;
                    }
                }
                if ($bestKey !== null) {
                    $sopir = $sopirsLower[$bestKey];
                }
            }

            if (!$sopir) {
                // Auto-create sopir baru
                $sopir = \App\Models\Sopir::create([
                    'nama' => trim($name),
                    'status' => 'aktif',
                ]);
                // Refresh cache
                $sopirsLower[strtolower(trim($sopir->nama))] = $sopir;
            }

            // Tentukan tujuan untuk sopir ini
            $tujuanUntukSopir = $currentTujuan;
            // Standalone line (belum ada Paket) -> catatanTambahan jadi tujuan
            if (!$tujuanUntukSopir && $catatanTambahan) {
                $tujuanUntukSopir = $findOrCreateTujuan($catatanTambahan);
            }
            if (!$tujuanUntukSopir) {
                $errors[] = "Sopir '{$name}' tidak punya tujuan. Tambahkan 'Paket ...'.";
                continue;
            }

            // Build catatan (jangan duplikat tujuan yg udah jadi kode_tujuan)
            $catatanParts = [];
            if ($catatanTambahan && !$currentTujuan) {
                $catatanParts[] = $catatanTambahan;
            }

            // DT
            $dtValue = $this->hitungDT(new Request([
                'kode_sopir' => $sopir->kode_sopir,
                'tanggal' => $parsedTanggal,
                'kabupaten' => $request->kabupaten,
                'waktu' => $request->waktu,
                'status' => $request->status,
            ]), null);

            Ritase::create([
                'periode_id' => $request->periode_id,
                'kode_sopir' => $sopir->kode_sopir,
                'kode_tujuan' => $tujuanUntukSopir->kode_tujuan,
                'tanggal' => $parsedTanggal,
                'waktu' => $request->waktu,
                'kabupaten' => $request->kabupaten,
                'status' => $request->status,
                'dt' => $dtValue,
                'upah_sopir' => 0,
                'nominal_kompensasi' => 0,
                'catatan' => !empty($catatanParts) ? implode('; ', $catatanParts) : ($currentGroup ?: null),
            ]);

            $created++;
        }

        $msg = "✅ {$created} ritase berhasil ditambahkan!";
        if (!empty($errors)) {
            $msg .= ' ' . implode(' ', array_slice($errors, 0, 10));
            if (count($errors) > 10) {
                $msg .= ' Dan ' . (count($errors) - 10) . ' error lainnya.';
            }
            return redirect()->route('ritase.index')->with('error', $msg);
        }

        return redirect()->route('ritase.index')->with('success', $msg);
    }

    public function cekAturanSewaDT(Request $request)
    {
        $kabupaten = $request->kabupaten;
        $waktu = $request->waktu;
        $status = $request->status;
        $kodeSopir = $request->kode_sopir;
        $tanggal = $request->tanggal;
        $nominalKompensasi = $request->nominal_kompensasi ?? 0;

        // HITUNG DT
        $dt = 0;
        $keterangan = '';

        // Jika status Gagal Produksi → DT = 0
        if ($status === 'gagal_produksi') {
            $dt = 0;
            $keterangan = '❌ Gagal Produksi → Tidak dapat DT';
        }
        // 🔥🔥🔥 KABUPATEN LAINNYA TETAP DAPAT DT 🔥🔥🔥
        // HAPUS PENGECEKAN INI
        // else if ($kabupaten === 'Lainnya') {
        //     $dt = 0;
        //     $keterangan = '❌ Kabupaten Lainnya → Tidak dapat DT';
        // }
        else {
            // Cek rit lain di tanggal yang SAMA dengan kabupaten SAMA dan waktu SAMA
            $ritLain = Ritase::where('kode_sopir', $kodeSopir)
                ->where('tanggal', $tanggal)
                ->where('kabupaten', $kabupaten)
                ->where('waktu', $waktu)
                ->where('status', '!=', 'gagal_produksi')
                ->first();

            if ($ritLain) {
                $dt = 0;
                $keterangan = "⚠️ Rit ke-2 dengan kabupaten {$kabupaten} waktu {$waktu} (hitung 1x)";
            } else {
                $dt = 330000;
                if ($kabupaten === 'Lainnya') {
                    $keterangan = "✅ Kabupaten Lainnya → DT 1x Rp 330.000";
                } else {
                    $keterangan = "✅ Rit pertama dengan kabupaten {$kabupaten} waktu {$waktu} → DT 1x Rp 330.000";
                }
            }
        }

        // HITUNG RIT KE BERAPA
        $ritLain = Ritase::where('kode_sopir', $kodeSopir)
            ->where('tanggal', $tanggal)
            ->where('kabupaten', $kabupaten)
            ->where('waktu', $waktu)
            ->where('status', '!=', 'gagal_produksi')
            ->first();

        if ($ritLain) {
            $ritKeberapa = 2;
        } else {
            $totalRitHariIni = Ritase::where('kode_sopir', $kodeSopir)
                ->where('tanggal', $tanggal)
                ->where('status', '!=', 'gagal_produksi')
                ->count();

            $ritKeberapa = $totalRitHariIni + 1;
        }

        return response()->json([
            'rit_keberapa' => $ritKeberapa,
            'sewa_dt' => $dt,
            'keterangan' => $keterangan,
            'kompensasi' => $nominalKompensasi,
        ]);
    }
}
