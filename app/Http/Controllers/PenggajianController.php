<?php

namespace App\Http\Controllers;

use App\Models\Penggajian;
use App\Models\PenggajianDetail;
use App\Models\Periode;
use App\Models\Tujuan;
use App\Models\Sopir;
use App\Models\Ritase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class PenggajianController extends Controller
{
    public function index(Request $request)
    {
        $periodeId = $request->get('periode');

        // Default ke periode yang mencakup hari ini
        if (empty($periodeId)) {
            $today = now()->toDateString();
            $default = Periode::where('tanggal_mulai', '<=', $today)
                ->where('tanggal_selesai', '>=', $today)
                ->first();
            if ($default) $periodeId = (string) $default->id;
        }

        $allPeriodes = Periode::orderBy('id', 'desc')->get();
        $periodeIds = $allPeriodes->pluck('id');

        $ritaseSummary = Ritase::whereIn('periode_id', $periodeIds)
            ->where('status', '!=', 'gagal_produksi')
            ->selectRaw('periode_id, SUM(upah_sopir) as total_upah_rit, SUM(dt) as total_dt_rit, COUNT(*) as total_rit_rit')
            ->groupBy('periode_id')
            ->get()
            ->keyBy('periode_id');

        $gajiSummary = Penggajian::whereIn('periode_id', $periodeIds)
            ->selectRaw('periode_id, SUM(uang_solar) as total_solar, SUM(upah_sopir) as total_upah, SUM(dt) as total_dt, SUM(total) as grand_total, COUNT(*) as gaji_count')
            ->groupBy('periode_id')
            ->get()
            ->keyBy('periode_id');

        $summary = Cache::remember('gaji_index_summary', 120, function () use ($periodeIds) {
            $rit = Ritase::whereIn('periode_id', $periodeIds)
                ->where('status', '!=', 'gagal_produksi')
                ->selectRaw('periode_id, SUM(upah_sopir) as total_upah_rit, SUM(dt) as total_dt_rit, COUNT(*) as total_rit_rit')
                ->groupBy('periode_id')
                ->get()
                ->keyBy('periode_id');
            $gaji = Penggajian::whereIn('periode_id', $periodeIds)
                ->selectRaw('periode_id, SUM(uang_solar) as total_solar, SUM(upah_sopir) as total_upah, SUM(dt) as total_dt, SUM(total) as grand_total, COUNT(*) as gaji_count')
                ->groupBy('periode_id')
                ->get()
                ->keyBy('periode_id');
            return compact('rit', 'gaji');
        });
        $ritaseSummary = $summary['rit'];
        $gajiSummary = $summary['gaji'];

        $periodes = $allPeriodes->map(function ($periode) use ($ritaseSummary, $gajiSummary) {
            $rit = $ritaseSummary->get($periode->id);
            $gaji = $gajiSummary->get($periode->id);
            $hasGaji = $gaji && $gaji->gaji_count > 0;
            return [
                'id' => $periode->id,
                'nama_periode' => $periode->nama_periode,
                'total_ritase' => $hasGaji ? ($rit->total_rit_rit ?? 0) : ($rit->total_rit_rit ?? 0),
                'total_solar' => $hasGaji ? (floatval($gaji->total_solar ?? 0)) : 0,
                'total_sopir' => $hasGaji
                    ? (floatval($gaji->total_upah ?? 0))
                    : (floatval($rit->total_upah_rit ?? 0)),
                'total_dt' => $hasGaji
                    ? (floatval($gaji->total_dt ?? 0))
                    : (floatval($rit->total_dt_rit ?? 0)),
                'grand_total' => $hasGaji
                    ? (floatval($gaji->grand_total ?? 0))
                    : ((floatval($rit->total_upah_rit ?? 0)) + (floatval($rit->total_dt_rit ?? 0))),
            ];
        });

        // Filter tujuan sesuai periode yang dipilih
        if ($periodeId) {
            $tujuanIds = Ritase::where('periode_id', $periodeId)
                ->select('kode_tujuan')
                ->distinct()
                ->pluck('kode_tujuan');
            $allTujuans = Tujuan::whereIn('kode_tujuan', $tujuanIds)
                ->orderBy('id', 'asc')
                ->get();
        } else {
            $allTujuans = Tujuan::where('status', 'aktif')->orderBy('id', 'asc')->get();
        }

        $periodesForDropdown = Periode::all();

        return view('penggajian.index', compact('periodes', 'allTujuans', 'periodesForDropdown', 'periodeId'));
    }

    public function getRitaseData(Request $request)
    {
        try {
            $periodeId = $request->get('periode');

            if (!$periodeId) {
                return response()->json(['error' => 'Parameter tidak lengkap'], 400);
            }

            // Cache per periode — invalidate when gaji is stored/updated
            $cacheKey = 'ritase_data_' . $periodeId;
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return response()->json($cached);
            }

            $penggajianData = Penggajian::with(['sopir', 'details'])
                ->where('periode_id', $periodeId)
                ->get();

            $result = [];
            $existingSopirCodes = [];

            // Batch all gagal_rits for existing gaji entries
            $allGagalRits = Ritase::where('periode_id', $periodeId)
                ->where('status', 'gagal_produksi')
                ->orderBy('tanggal')
                ->get(['id', 'tanggal', 'kode_tujuan', 'kode_sopir'])
                ->groupBy('kode_sopir');

            foreach ($penggajianData as $gaji) {
                $existingSopirCodes[] = $gaji->kode_sopir;

                $ritPerTujuan = [];
                foreach ($gaji->details as $detail) {
                    $ritPerTujuan[$detail->kode_tujuan] = [
                        'total_rit' => $detail->jumlah_rit,
                        'solar_per_rit' => $detail->solar_per_rit,
                        'upah_per_rit' => $detail->upah_per_rit,
                        'total_solar' => $detail->total_solar,
                        'total_upah' => $detail->total_upah,
                        'subtotal' => $detail->subtotal,
                    ];
                }

                $gagalRits = collect();
                if ($allGagalRits->has($gaji->kode_sopir)) {
                    $gagalRits = $allGagalRits->get($gaji->kode_sopir)->map(function ($rit) {
                        return [
                            'id' => $rit->id,
                            'tanggal' => $rit->tanggal instanceof \Carbon\Carbon ? $rit->tanggal->format('Y-m-d') : $rit->tanggal,
                            'kode_tujuan' => $rit->kode_tujuan,
                        ];
                    });
                }

                $result[] = [
                    'kode_sopir' => $gaji->kode_sopir,
                    'nama_sopir' => $gaji->sopir ? $gaji->sopir->nama : 'Unknown',
                    'periode_id' => $gaji->periode_id,
                    'total_dt' => floatval($gaji->dt),
                    'total_kompensasi' => floatval($gaji->kompensasi_gagal ?? 0),
                    'total_solar' => floatval($gaji->uang_solar),
                    'total_upah' => floatval($gaji->upah_sopir),
                    'grand_total' => floatval($gaji->total),
                    'rit_per_tujuan' => $ritPerTujuan,
                    'gagal_rits' => $gagalRits->values()->toArray(),
                    'belum_dihitung' => false,
                ];
            }

            // Default rates — batch
            $defaultRates = [];
            $refPeriodeId = Penggajian::where('periode_id', $periodeId)->exists()
                ? $periodeId
                : Penggajian::where('periode_id', '<', $periodeId)->max('periode_id');

            if ($refPeriodeId) {
                $rates = PenggajianDetail::whereHas('penggajian', function ($q) use ($refPeriodeId) {
                    $q->where('periode_id', $refPeriodeId);
                })->selectRaw('kode_tujuan, AVG(solar_per_rit) as bbm, AVG(upah_per_rit) as upah')
                  ->groupBy('kode_tujuan')
                  ->get();
                foreach ($rates as $r) {
                    $defaultRates[$r->kode_tujuan] = [
                        'bbm_per_rit' => floatval($r->bbm),
                        'upah_per_rit' => floatval($r->upah),
                    ];
                }
            }

            $kompensasiPerTujuan = Ritase::where('periode_id', $periodeId)
                ->where('status', 'gagal_produksi')
                ->selectRaw('kode_tujuan, MAX(nominal_kompensasi) as kompensasi_per_rit')
                ->groupBy('kode_tujuan')
                ->pluck('kompensasi_per_rit', 'kode_tujuan')
                ->toArray();

            foreach ($kompensasiPerTujuan as $kodeTujuan => $kompPerRit) {
                if (!isset($defaultRates[$kodeTujuan])) {
                    $defaultRates[$kodeTujuan] = ['bbm_per_rit' => 0, 'upah_per_rit' => 0];
                }
                $defaultRates[$kodeTujuan]['kompensasi_gagal'] = floatval($kompPerRit);
            }

            // Sopir with ritase but no gaji entry yet
            $sopirs = Sopir::whereHas('ritase', function ($q) use ($periodeId) {
                $q->where('periode_id', $periodeId);
            })->whereNotIn('kode_sopir', $existingSopirCodes)->get();

            if ($sopirs->isNotEmpty()) {
                // Batch: get all rit counts per sopir+tujuan in one query
                $allRitCounts = Ritase::where('periode_id', $periodeId)
                    ->whereIn('kode_sopir', $sopirs->pluck('kode_sopir'))
                    ->where('status', '!=', 'gagal_produksi')
                    ->selectRaw('kode_sopir, kode_tujuan, COUNT(*) as total_rit')
                    ->groupBy('kode_sopir', 'kode_tujuan')
                    ->get()
                    ->groupBy('kode_sopir');

                // Batch: DT sums per sopir
                $allDtSums = Ritase::where('periode_id', $periodeId)
                    ->whereIn('kode_sopir', $sopirs->pluck('kode_sopir'))
                    ->where('status', '!=', 'gagal_produksi')
                    ->selectRaw('kode_sopir, COALESCE(SUM(dt), 0) as total_dt')
                    ->groupBy('kode_sopir')
                    ->pluck('total_dt', 'kode_sopir');
            }

            foreach ($sopirs as $sopir) {
                $ritPerTujuan = [];
                $totalRit = 0;

                $sopirRitCounts = $allRitCounts->get($sopir->kode_sopir, collect());

                foreach ($sopirRitCounts as $rc) {
                    $kodeTujuan = $rc->kode_tujuan;
                    $jumlahRit = (int) $rc->total_rit;
                    $rate = $defaultRates[$kodeTujuan] ?? null;
                    $ritPerTujuan[$kodeTujuan] = [
                        'total_rit' => $jumlahRit,
                        'solar_per_rit' => $rate ? $rate['bbm_per_rit'] : 0,
                        'upah_per_rit' => $rate ? $rate['upah_per_rit'] : 0,
                        'total_solar' => $rate ? ($rate['bbm_per_rit'] * $jumlahRit) : 0,
                        'total_upah' => $rate ? ($rate['upah_per_rit'] * $jumlahRit) : 0,
                        'subtotal' => $rate ? (($rate['bbm_per_rit'] + $rate['upah_per_rit']) * $jumlahRit) : 0,
                    ];
                    $totalRit += $jumlahRit;
                }

                if ($totalRit > 0) {
                    $totalDT = floatval($allDtSums->get($sopir->kode_sopir, 0));
                    $gagalRits = collect();
                    if ($allGagalRits->has($sopir->kode_sopir)) {
                        $gagalRits = $allGagalRits->get($sopir->kode_sopir)->map(function ($rit) {
                            return [
                                'id' => $rit->id,
                                'tanggal' => $rit->tanggal instanceof \Carbon\Carbon ? $rit->tanggal->format('Y-m-d') : $rit->tanggal,
                                'kode_tujuan' => $rit->kode_tujuan,
                            ];
                        });
                    }

                    $previewSolar = array_sum(array_column($ritPerTujuan, 'total_solar'));
                    $previewUpah = array_sum(array_column($ritPerTujuan, 'total_upah'));

                    $result[] = [
                        'kode_sopir' => $sopir->kode_sopir,
                        'nama_sopir' => $sopir->nama,
                        'periode_id' => $periodeId,
                        'total_dt' => $totalDT,
                        'total_kompensasi' => 0,
                        'total_solar' => $previewSolar,
                        'total_upah' => $previewUpah,
                        'grand_total' => $previewSolar + $previewUpah + $totalDT,
                        'rit_per_tujuan' => $ritPerTujuan,
                        'gagal_rits' => $gagalRits->values()->toArray(),
                        'belum_dihitung' => true,
                    ];
                }
            }

            $response = [
                'sopir' => $result,
                'default_rates' => $defaultRates,
            ];

            // Cache for 60 seconds – invalidated on store/update
            Cache::put($cacheKey, $response, 60);

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'periode_id' => 'required|exists:periodes,id',
            'detail' => 'required|array|min:1',
            'detail.*.kode_tujuan' => 'required|exists:tujuans,kode_tujuan',
            'detail.*.bbm_per_rit' => 'required|numeric|min:0',
            'detail.*.upah_per_rit' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $periodeId = $request->periode_id;
            $detail = $request->detail;

            // Hapus data gaji lama untuk periode ini
            Penggajian::where('periode_id', $periodeId)->delete();
            Cache::forget('ritase_data_' . $periodeId);

            $sopirIds = [];
            foreach ($detail as $d) {
                if (!in_array($d['kode_sopir'], $sopirIds)) {
                    $sopirIds[] = $d['kode_sopir'];
                }
            }

            $allSopirs = Sopir::whereIn('kode_sopir', $sopirIds)->get()->keyBy('kode_sopir');
            $allTujuans = Tujuan::where('status', 'aktif')->get()->keyBy('kode_tujuan');
            $allRitase = Ritase::where('periode_id', $periodeId)
                ->whereIn('kode_sopir', $sopirIds)
                ->with(['tujuan'])
                ->get()
                ->groupBy('kode_sopir');

            $allGagalRits = Ritase::where('periode_id', $periodeId)
                ->whereIn('kode_sopir', $sopirIds)
                ->where('status', 'gagal_produksi')
                ->get()
                ->groupBy('kode_sopir');

            // Hitung total dt dan kompensasi per sopir
            $allDtPerSopir = Ritase::where('periode_id', $periodeId)
                ->whereIn('kode_sopir', $sopirIds)
                ->where('status', '!=', 'gagal_produksi')
                ->selectRaw('kode_sopir, COALESCE(SUM(dt), 0) as total_dt')
                ->groupBy('kode_sopir')
                ->pluck('total_dt', 'kode_sopir');

            $allKompensasiPerSopir = Ritase::where('periode_id', $periodeId)
                ->whereIn('kode_sopir', $sopirIds)
                ->where('status', 'gagal_produksi')
                ->selectRaw('kode_sopir, COALESCE(SUM(nominal_kompensasi), 0) as total_kompensasi')
                ->groupBy('kode_sopir')
                ->pluck('total_kompensasi', 'kode_sopir');

            foreach ($detail as $d) {
                $kodeSopir = $d['kode_sopir'];
                $kodeTujuan = $d['kode_tujuan'];
                $bbmPerRit = floatval($d['bbm_per_rit']);
                $upahPerRit = floatval($d['upah_per_rit']);
                $jumlahRit = intval($d['jumlah_rit']);

                $sopir = $allSopirs->get($kodeSopir);
                if (!$sopir) continue;

                $tujuan = $allTujuans->get($kodeTujuan);
                if (!$tujuan) continue;

                // Cari atau buat Penggajian untuk sopir ini
                $penggajian = Penggajian::firstOrNew([
                    'kode_sopir' => $kodeSopir,
                    'periode_id' => $periodeId,
                ]);

                $penggajian->kode_sopir = $kodeSopir;
                $penggajian->periode_id = $periodeId;

                // Akumulasi
                $penggajian->uang_solar = ($penggajian->uang_solar ?? 0) + ($bbmPerRit * $jumlahRit);
                $penggajian->upah_sopir = ($penggajian->upah_sopir ?? 0) + ($upahPerRit * $jumlahRit);
                $totalDtSopir = floatval($allDtPerSopir->get($kodeSopir, 0));
                $penggajian->dt = $totalDtSopir;
                $penggajian->kompensasi_gagal = floatval($allKompensasiPerSopir->get($kodeSopir, 0));
                $penggajian->total = ($penggajian->uang_solar ?? 0) + ($penggajian->upah_sopir ?? 0) + $totalDtSopir + $penggajian->kompensasi_gagal;

                $penggajian->save();

                // Detail
                $detailModel = new PenggajianDetail();
                $detailModel->penggajian_id = $penggajian->id;
                $detailModel->kode_tujuan = $kodeTujuan;
                $detailModel->jumlah_rit = $jumlahRit;
                $detailModel->solar_per_rit = $bbmPerRit;
                $detailModel->upah_per_rit = $upahPerRit;
                $detailModel->total_solar = $bbmPerRit * $jumlahRit;
                $detailModel->total_upah = $upahPerRit * $jumlahRit;
                $detailModel->subtotal = ($bbmPerRit * $jumlahRit) + ($upahPerRit * $jumlahRit);
                $detailModel->save();
            }

            DB::commit();

            return redirect()->route('gaji.index', ['periode' => $periodeId])
                ->with('success', 'Data gaji berhasil disimpan!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('error', 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $penggajian = Penggajian::with(['details', 'sopir'])->findOrFail($id);
        $periode = Periode::find($penggajian->periode_id);
        $periodes = Periode::orderBy('id', 'desc')->get();
        $allTujuans = Tujuan::where('status', 'aktif')->orderBy('id', 'asc')->get();
        $allSopirs = Sopir::where('status', 'aktif')->orderBy('id', 'asc')->get();

        // Build detailPerTujuan from existing PenggajianDetail for this periode
        $detailPerTujuan = PenggajianDetail::whereHas('penggajian', function ($q) use ($periode) {
            $q->where('periode_id', $periode->id);
        })->get(['kode_tujuan', 'solar_per_rit', 'upah_per_rit', 'kompensasi_gagal'])
            ->keyBy('kode_tujuan')
            ->map(function ($d) {
                return [
                    'bbm_per_rit' => floatval($d->solar_per_rit),
                    'upah_per_rit' => floatval($d->upah_per_rit),
                    'kompensasi_gagal' => floatval($d->kompensasi_gagal),
                ];
            });

        // Get existing gaji for this periode
        $existingGaji = Penggajian::with(['sopir', 'details'])->where('periode_id', $periode->id)->get();

        // Build kompensasiGagal per sopir
        $kompensasiGagal = [];
        foreach ($existingGaji as $gaji) {
            $komp = $gaji->details->where('kompensasi_gagal', '>', 0)->sum('kompensasi_gagal');
            if ($komp > 0) {
                $kompensasiGagal[$gaji->kode_sopir] = $komp;
            }
        }

        return view('penggajian.edit', compact('penggajian', 'periode', 'periodes', 'allTujuans', 'allSopirs', 'detailPerTujuan', 'existingGaji', 'kompensasiGagal'));    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'periode_id' => 'required|exists:periodes,id',
            'detail' => 'required|array|min:1',
            'detail.*.kode_tujuan' => 'required|exists:tujuans,kode_tujuan',
            'detail.*.bbm_per_rit' => 'required|numeric|min:0',
            'detail.*.upah_per_rit' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $penggajian = Penggajian::findOrFail($id);
            $oldPeriodeId = $penggajian->periode_id;
            $newPeriodeId = $request->periode_id;
            $detail = $request->detail;

            // Clear cache for both old and new periode
            Cache::forget('ritase_data_' . $oldPeriodeId);
            if ($oldPeriodeId != $newPeriodeId) {
                Cache::forget('ritase_data_' . $newPeriodeId);
            }
            Cache::forget('gaji_index_summary');

            $kodeSopir = $penggajian->kode_sopir;
            $allTujuans = Tujuan::where('status', 'aktif')->get()->keyBy('kode_tujuan');

            $allRitase = Ritase::where('periode_id', $newPeriodeId)
                ->where('kode_sopir', $kodeSopir)
                ->with(['tujuan'])
                ->get()
                ->groupBy('kode_sopir');

            // Hapus detail lama
            $penggajian->details()->delete();

            // Hitung ulang
            $totalSolar = 0;
            $totalUpah = 0;

            foreach ($detail as $d) {
                $kodeTujuan = $d['kode_tujuan'];
                $bbmPerRit = floatval($d['bbm_per_rit']);
                $upahPerRit = floatval($d['upah_per_rit']);
                $jumlahRit = intval($d['jumlah_rit']);

                $tujuan = $allTujuans->get($kodeTujuan);
                if (!$tujuan) continue;

                $detailModel = new PenggajianDetail();
                $detailModel->penggajian_id = $penggajian->id;
                $detailModel->kode_tujuan = $kodeTujuan;
                $detailModel->jumlah_rit = $jumlahRit;
                $detailModel->solar_per_rit = $bbmPerRit;
                $detailModel->upah_per_rit = $upahPerRit;
                $detailModel->total_solar = $bbmPerRit * $jumlahRit;
                $detailModel->total_upah = $upahPerRit * $jumlahRit;
                $detailModel->subtotal = ($bbmPerRit * $jumlahRit) + ($upahPerRit * $jumlahRit);
                $detailModel->save();

                $totalSolar += $bbmPerRit * $jumlahRit;
                $totalUpah += $upahPerRit * $jumlahRit;
            }

            // Hitung DT dari tabel ritase
            $totalDT = Ritase::where('periode_id', $newPeriodeId)
                ->where('kode_sopir', $kodeSopir)
                ->where('status', '!=', 'gagal_produksi')
                ->sum('dt') ?? 0;

            $kompensasiGagal = Ritase::where('periode_id', $newPeriodeId)
                ->where('kode_sopir', $kodeSopir)
                ->where('status', 'gagal_produksi')
                ->sum('nominal_kompensasi') ?? 0;

            $penggajian->periode_id = $newPeriodeId;
            $penggajian->uang_solar = $totalSolar;
            $penggajian->upah_sopir = $totalUpah;
            $penggajian->dt = $totalDT;
            $penggajian->kompensasi_gagal = $kompensasiGagal;
            $penggajian->total = $totalSolar + $totalUpah + $totalDT + $kompensasiGagal;
            $penggajian->save();

            DB::commit();

            return redirect()->route('gaji.index', ['periode' => $newPeriodeId])
                ->with('success', 'Data gaji berhasil diperbarui!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('error', 'Gagal memperbarui: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        $gaji = Penggajian::findOrFail($id);
        Cache::forget('ritase_data_' . $gaji->periode_id);
        Cache::forget('gaji_index_summary');
        $gaji->details()->delete();
        $gaji->delete();

        return redirect()->route('gaji.index')->with('success', 'Data gaji berhasil dihapus!');
    }

    public function slipGaji($periodeId, $kodeSopir)
    {
        $periode = Periode::findOrFail($periodeId);
        $sopir = Sopir::where('kode_sopir', $kodeSopir)->firstOrFail();

        $gaji = Penggajian::where('periode_id', $periodeId)
            ->where('kode_sopir', $kodeSopir)
            ->first();

        $slipData = $this->buildSlipData($periodeId, $kodeSopir);

        if (!$slipData || count($slipData['dataPerHari']) == 0) {
            abort(404, 'Data ritase tidak ditemukan untuk sopir ini.');
        }

        $dataPerHari = $slipData['dataPerHari'];
        $totalSolarAll = $slipData['totalSolarAll'];
        $totalUpahAll = $slipData['totalUpahAll'];
        $totalJumlahAll = $slipData['totalJumlahAll'];
        $totalDTAll = $slipData['totalDTAll'];
        $grandTotal = $totalJumlahAll + $totalDTAll;

        return view('penggajian.slip', compact(
            'periode', 'sopir', 'gaji', 'dataPerHari',
            'totalSolarAll', 'totalUpahAll', 'totalJumlahAll', 'totalDTAll', 'grandTotal'
        ));
    }

    public function riwayat(Request $request)
    {
        $periodeId = $request->get('periode');

        // Auto-select periode
        $today = now()->toDateString();
        $currentPeriode = Periode::where('tanggal_mulai', '<=', $today)
            ->where('tanggal_selesai', '>=', $today)
            ->first();
        if (empty($periodeId) && $currentPeriode) {
            $periodeId = (string) $currentPeriode->id;
        }

        $query = Penggajian::with(['sopir', 'periode']);

        if ($periodeId) {
            $query->where('periode_id', $periodeId);
        }

        $penggajians = $query->orderBy('created_at', 'desc')->paginate(15);

        $periodes = Periode::orderBy('id', 'desc')->get();
        $currentPeriodeId = $currentPeriode ? (string) $currentPeriode->id : null;

        // Summary per periode (tanpa pagination)
        $periodeIds = $periodes->pluck('id');

        $ritaseSummary = Ritase::whereIn('periode_id', $periodeIds)
            ->where('status', '!=', 'gagal_produksi')
            ->selectRaw('periode_id, SUM(upah_sopir) as total_upah_rit, SUM(dt) as total_dt_rit, COUNT(*) as total_rit_rit')
            ->groupBy('periode_id')
            ->get()
            ->keyBy('periode_id');

        $gajiSummary = Penggajian::whereIn('periode_id', $periodeIds)
            ->selectRaw('periode_id, SUM(uang_solar) as total_solar, SUM(upah_sopir) as total_upah, SUM(dt) as total_dt, SUM(total) as grand_total, COUNT(*) as gaji_count')
            ->groupBy('periode_id')
            ->get()
            ->keyBy('periode_id');

        $periodes = $periodes->map(function ($periode) use ($ritaseSummary, $gajiSummary, $currentPeriode) {
            $rit = $ritaseSummary->get($periode->id);
            $gaji = $gajiSummary->get($periode->id);
            $hasGaji = $gaji && $gaji->gaji_count > 0;
            return [
                'id' => $periode->id,
                'nama_periode' => $periode->nama_periode,
                'tanggal_mulai' => $periode->tanggal_mulai,
                'tanggal_selesai' => $periode->tanggal_selesai,
                'total_ritase' => $hasGaji ? ($rit->total_rit_rit ?? 0) : ($rit->total_rit_rit ?? 0),
                'jumlah_sopir' => $hasGaji ? ($gaji->gaji_count ?? 0) : 0,
                'total_solar' => $hasGaji ? floatval($gaji->total_solar ?? 0) : 0,
                'total_upah' => $hasGaji ? floatval($gaji->total_upah ?? 0) : (floatval($rit->total_upah_rit ?? 0)),
                'total_dt' => $hasGaji ? floatval($gaji->total_dt ?? 0) : floatval($rit->total_dt_rit ?? 0),
                'grand_total' => $hasGaji ? floatval($gaji->grand_total ?? 0) : (floatval($rit->total_upah_rit ?? 0) + floatval($rit->total_dt_rit ?? 0)),
                'count_gaji' => $gaji ? $gaji->gaji_count : 0,
                'is_current' => $currentPeriode && $currentPeriode->id == $periode->id,
            ];
        });

        return view('penggajian.riwayat', compact('penggajians', 'periodes', 'periodeId', 'currentPeriodeId'));
    }

    public function laporan(Request $request)
    {
        $periodeId = $request->get('periode');
        $periodes = Periode::orderBy('id', 'desc')->get();

        // Default ke periode yang mencakup hari ini
        if (empty($periodeId)) {
            $today = now()->toDateString();
            $default = Periode::where('tanggal_mulai', '<=', $today)
                ->where('tanggal_selesai', '>=', $today)
                ->first();
            if ($default) $periodeId = (string) $default->id;
        }

        $data = null;

        if ($periodeId) {
            $periode = Periode::find($periodeId);
            if (!$periode) {
                return view('penggajian.laporan', compact('data', 'periodes', 'periodeId', 'periode'));
            }

            $startDate = \Carbon\Carbon::parse($periode->tanggal_mulai);
            $endDate = \Carbon\Carbon::parse($periode->tanggal_selesai);
            $hariKerja = $startDate->diffInDays($endDate) + 1;

            // Total sopir yang punya ritase di periode ini
            $totalSopir = Sopir::whereHas('ritase', function ($q) use ($periodeId) {
                $q->where('periode_id', $periodeId);
            })->count();

            // Total ritase (all)
            $totalRitase = Ritase::where('periode_id', $periodeId)->count();

            // Total ritase gagal
            $totalRitaseGagal = Ritase::where('periode_id', $periodeId)
                ->where('status', 'gagal_produksi')->count();

            // Gaji per tujuan dengan subquery
            $gajiPerTujuan = PenggajianDetail::whereHas('penggajian', function ($q) use ($periodeId) {
                $q->where('periode_id', $periodeId);
            })
                ->selectRaw('kode_tujuan,
                    SUM(jumlah_rit) as total_rit,
                    SUM(total_solar) as total_solar,
                    SUM(total_upah) as total_upah,
                    SUM(subtotal) as total,
                    AVG(solar_per_rit) as avg_solar,
                    AVG(upah_per_rit) as avg_upah')
                ->groupBy('kode_tujuan')
                ->get()
                ->map(function ($g) {
                    $tujuan = Tujuan::where('kode_tujuan', $g->kode_tujuan)->first();
                    $bbmPerRit = round(floatval($g->avg_solar));
                    $upahPerRit = round(floatval($g->avg_upah));
                    return [
                        'kode_tujuan' => $g->kode_tujuan,
                        'nama_tujuan' => $tujuan ? $tujuan->nama : $g->kode_tujuan,
                        'total_rit' => (int) $g->total_rit,
                        'total_solar' => floatval($g->total_solar),
                        'total_upah' => floatval($g->total_upah),
                        'total' => floatval($g->total),
                        'solar_per_rit' => $bbmPerRit,
                        'upah_per_rit' => $upahPerRit,
                    ];
                })->values();

            $totalSolar = $gajiPerTujuan->sum('total_solar');
            $totalUpah = $gajiPerTujuan->sum('total_upah');
            $totalGaji = $gajiPerTujuan->sum('total');

            // Build detail_rows for view
            $detailRows = [];
            $no = 0;
            foreach ($gajiPerTujuan as $g) {
                // Row Solar
                $no++;
                $detailRows[] = [
                    'is_subtotal' => false,
                    'no' => $no,
                    'tujuan' => $g['nama_tujuan'],
                    'jenis' => 'Solar',
                    'harga' => $g['solar_per_rit'],
                    'qty' => $g['total_rit'],
                    'total' => $g['total_solar'],
                ];
                // Row Upah/Sopir
                $no++;
                $detailRows[] = [
                    'is_subtotal' => false,
                    'no' => $no,
                    'tujuan' => $g['nama_tujuan'],
                    'jenis' => 'Upah',
                    'harga' => $g['upah_per_rit'],
                    'qty' => $g['total_rit'],
                    'total' => $g['total_upah'],
                ];
                // Row Subtotal per tujuan
                $no++;
                $detailRows[] = [
                    'is_subtotal' => true,
                    'no' => $no,
                    'tujuan' => $g['nama_tujuan'],
                    'jenis' => 'SUB TOTAL',
                    'harga' => 0,
                    'qty' => $g['total_rit'],
                    'total' => $g['total'],
                ];
            }

            // Total DT
            $totalDT = floatval(Penggajian::where('periode_id', $periodeId)->sum('dt'));
            // Kompensasi
            $totalKompensasi = floatval(Penggajian::where('periode_id', $periodeId)->sum('kompensasi_gagal'));

            $data = [
                'periode' => $periode,
                'hari_kerja' => $hariKerja,
                'total_sopir' => $totalSopir,
                'total_ritase' => $totalRitase,
                'total_ritase_gagal' => $totalRitaseGagal,
                'gaji_per_tujuan' => $gajiPerTujuan,
                'detail_rows' => $detailRows,
                'total_solar' => $totalSolar,
                'total_upah' => $totalUpah,
                'total_dt' => $totalDT,
                'total_kompensasi' => $totalKompensasi,
                'total_gaji' => $totalGaji,
                'grand_total' => $totalGaji + $totalDT + $totalKompensasi,
            ];
        }

        return view('penggajian.laporan', compact('data', 'periodes', 'periodeId', 'periode'));
    }

    public function viewAllSlips($periodeId)
    {
        $periode = Periode::findOrFail($periodeId);

        $sopirIds = Penggajian::where('periode_id', $periodeId)
            ->pluck('kode_sopir')
            ->unique()
            ->values();

        $ritaseSopirIds = Ritase::where('periode_id', $periodeId)
            ->whereNotIn('kode_sopir', $sopirIds)
            ->pluck('kode_sopir')
            ->unique()
            ->values();

        $sopirIds = $sopirIds->concat($ritaseSopirIds)->unique()->values();

        $allSlips = [];
        foreach ($sopirIds as $kodeSopir) {
            $slipData = $this->buildSlipData($periodeId, $kodeSopir);
            if ($slipData && count($slipData['dataPerHari']) > 0) {
                $allSlips[] = $slipData;
            }
        }

        usort($allSlips, function ($a, $b) {
            return $a['sopir']->id <=> $b['sopir']->id;
        });

        return view('penggajian.slip-all', compact('allSlips', 'periode'));
    }

    public function downloadSlipPdf($periodeId)
    {
        $periode = Periode::findOrFail($periodeId);

        $sopirIds = Penggajian::where('periode_id', $periodeId)
            ->pluck('kode_sopir')
            ->unique()
            ->values();

        $ritaseSopirIds = Ritase::where('periode_id', $periodeId)
            ->whereNotIn('kode_sopir', $sopirIds)
            ->pluck('kode_sopir')
            ->unique()
            ->values();

        $sopirIds = $sopirIds->concat($ritaseSopirIds)->unique()->values();

        $allSlips = [];
        foreach ($sopirIds as $kodeSopir) {
            $slipData = $this->buildSlipData($periodeId, $kodeSopir);
            if ($slipData && count($slipData['dataPerHari']) > 0) {
                $allSlips[] = $slipData;
            }
        }

        usort($allSlips, function ($a, $b) {
            return $a['sopir']->id <=> $b['sopir']->id;
        });

        $startDate = \Carbon\Carbon::parse($periode->tanggal_mulai);
        $endDate = \Carbon\Carbon::parse($periode->tanggal_selesai);

        $namaHari = [
            'Saturday' => 'Sabtu', 'Sunday' => 'Minggu',
            'Monday' => 'Senin', 'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => "Jum'at",
        ];

        $dateHeaders = [];
        for ($d = $startDate->copy(); $d <= $endDate; $d->addDay()) {
            $dayName = $namaHari[$d->format('l')] ?? $d->format('l');
            $dateHeaders[] = [
                'label' => $dayName,
                'date' => $d->format('d/m'),
                'tanggal' => $d->format('Y-m-d'),
            ];
        }

        $maxRitKe = 1;
        foreach ($allSlips as $slip) {
            foreach ($slip['dataPerHari'] as $entry) {
                $maxRitKe = max($maxRitKe, $entry['rit_ke']);
            }
        }

        $organizedSlips = [];
        foreach ($allSlips as $slip) {
            $ritMap = [];
            $allDt = $slip['totalDTAll'];
            $allJumlah = $slip['totalJumlahAll'];
            $allSolar = $slip['totalSolarAll'];
            $allUpah = $slip['totalUpahAll'];

            foreach ($slip['dataPerHari'] as $entry) {
                $tgl = $entry['tanggal'];
                $rit = $entry['rit_ke'];
                if (!isset($ritMap[$tgl])) $ritMap[$tgl] = [];
                $ritMap[$tgl][$rit] = $entry;
            }

            $organizedSlips[] = [
                'sopir' => $slip['sopir'],
                'ritMap' => $ritMap,
                'totalSolarAll' => $allSolar,
                'totalUpahAll' => $allUpah,
                'totalJumlahAll' => $allJumlah,
                'totalDTAll' => $allDt,
                'grandTotal' => $allJumlah + $allDt,
            ];
        }

        $slipEntries = [];
        foreach ($organizedSlips as $slip) {
            $sopirRits = [];
            foreach ($slip['ritMap'] as $tgl => $rits) {
                foreach ($rits as $rit => $entry) {
                    $sopirRits[$rit] = true;
                }
            }
            $sopirRits = array_keys($sopirRits);
            sort($sopirRits);

            foreach ($sopirRits as $rit) {
                $totalSolar = 0; $totalUpah = 0; $totalJumlah = 0; $totalDT = 0;
                foreach ($slip['ritMap'] as $tgl => $rits) {
                    if (isset($rits[$rit])) {
                        $e = $rits[$rit];
                        $totalSolar += $e['solar'];
                        $totalUpah += $e['upah'];
                        $totalJumlah += $e['jumlah'];
                        $totalDT += $e['dt'];
                    }
                }
                $slipEntries[] = [
                    'sopir' => $slip['sopir'],
                    'ritMap' => $slip['ritMap'],
                    'ritKe' => $rit,
                    'totalSolarAll' => $totalSolar,
                    'totalUpahAll' => $totalUpah,
                    'totalJumlahAll' => $totalJumlah,
                    'totalDTAll' => $totalDT,
                    'grandTotal' => $totalJumlah + $totalDT,
                ];
            }
        }

        usort($slipEntries, function ($a, $b) {
            if ($a['ritKe'] !== $b['ritKe']) return $a['ritKe'] <=> $b['ritKe'];
            return $a['sopir']->id <=> $b['sopir']->id;
        });

        $sopirPerPages = collect($slipEntries)->chunk(4)->map->values()->toArray();

        $fileName = 'Slip_Gaji_' . str_replace(' ', '_', $periode->nama_periode) . '.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('penggajian.slip-pdf', compact(
            'sopirPerPages', 'dateHeaders', 'periode'
        ))->setPaper([0, 0, 595, 935], 'landscape')
          ->setOption('isPhpEnabled', true)
          ->setOption('defaultFont', 'Times New Roman')
          ->setOption('isRemoteEnabled', false)
          ->setOption('dpi', 72);

        return $pdf->download($fileName);
    }

    public function downloadLaporanPdf(Request $request, $periodeId)
    {
        $periode = Periode::findOrFail($periodeId);

        $startDate = \Carbon\Carbon::parse($periode->tanggal_mulai);
        $endDate = \Carbon\Carbon::parse($periode->tanggal_selesai);
        $hariKerja = $startDate->diffInDays($endDate) + 1;

        $totalSopir = Sopir::whereHas('ritase', function ($q) use ($periodeId) {
            $q->where('periode_id', $periodeId);
        })->count();

        $totalRitase = Ritase::where('periode_id', $periodeId)->count();
        $totalDT = floatval(Ritase::where('periode_id', $periodeId)
            ->where('status', '!=', 'gagal_produksi')
            ->sum('dt'));

        $solarTotal = floatval(Penggajian::where('periode_id', $periodeId)->sum('uang_solar'));
        $upahTotal = floatval(Penggajian::where('periode_id', $periodeId)->sum('upah_sopir'));
        $totalKompensasi = floatval(Penggajian::where('periode_id', $periodeId)->sum('kompensasi_gagal'));

        $totalGaji = floatval(Penggajian::where('periode_id', $periodeId)->sum('total'));

        // Per Tujuan
        $gajiPerTujuan = PenggajianDetail::whereHas('penggajian', function ($q) use ($periodeId) {
            $q->where('periode_id', $periodeId);
        })
            ->selectRaw('kode_tujuan, SUM(jumlah_rit) as total_rit, SUM(total_solar) as total_solar, SUM(total_upah) as total_upah, SUM(subtotal) as total, AVG(solar_per_rit) as avg_solar, AVG(upah_per_rit) as avg_upah')
            ->groupBy('kode_tujuan')
            ->get()
            ->map(function ($g) {
                $tujuan = Tujuan::where('kode_tujuan', $g->kode_tujuan)->first();
                $bbmPerRit = round(floatval($g->avg_solar));
                $upahPerRit = round(floatval($g->avg_upah));
                return [
                    'kode_tujuan' => $g->kode_tujuan,
                    'nama_tujuan' => $tujuan ? $tujuan->nama : $g->kode_tujuan,
                    'total_rit' => (int) $g->total_rit,
                    'total_solar' => floatval($g->total_solar),
                    'total_upah' => floatval($g->total_upah),
                    'total' => floatval($g->total),
                    'solar_per_rit' => $bbmPerRit,
                    'upah_per_rit' => $upahPerRit,
                ];
            })->values();

        $totalRitaseGagal = Ritase::where('periode_id', $periodeId)
            ->where('status', 'gagal_produksi')->count();

        // Build detail_rows same as laporan()
        $detailRows = [];
        $no = 0;
        foreach ($gajiPerTujuan as $g) {
            $no++;
            $detailRows[] = ['is_subtotal' => false, 'no' => $no, 'tujuan' => $g['nama_tujuan'], 'jenis' => 'Solar', 'harga' => $g['solar_per_rit'], 'qty' => $g['total_rit'], 'total' => $g['total_solar']];
            $no++;
            $detailRows[] = ['is_subtotal' => false, 'no' => $no, 'tujuan' => $g['nama_tujuan'], 'jenis' => 'Upah', 'harga' => $g['upah_per_rit'], 'qty' => $g['total_rit'], 'total' => $g['total_upah']];
            $no++;
            $detailRows[] = ['is_subtotal' => true, 'no' => $no, 'tujuan' => $g['nama_tujuan'], 'jenis' => 'SUB TOTAL', 'harga' => 0, 'qty' => $g['total_rit'], 'total' => $g['total']];
        }

        $grandTotal = $totalGaji + $totalDT + $totalKompensasi;

        $data = [
            'detail_rows' => $detailRows,
            'grand_total' => $grandTotal,
        ];

        $namaHari = ['Saturday' => 'Sabtu', 'Sunday' => 'Minggu',
            'Monday' => 'Senin', 'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => "Jum'at"];

        $dateHeaders = [];
        for ($d = $startDate->copy(); $d <= $endDate; $d->addDay()) {
            $dateHeaders[] = [
                'label' => $namaHari[$d->format('l')] ?? $d->format('l'),
                'date' => $d->format('d/m'),
            ];
        }

        $now = \Carbon\Carbon::now();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('penggajian.laporan-pdf', compact(
            'periode', 'hariKerja', 'totalSopir', 'totalRitase',
            'totalRitaseGagal', 'totalDT', 'solarTotal', 'upahTotal',
            'totalKompensasi', 'totalGaji', 'gajiPerTujuan', 'dateHeaders', 'data', 'now'
        ))->setPaper('a4', 'portrait')
          ->setOption('isPhpEnabled', true)
          ->setOption('defaultFont', 'Times New Roman');

        $fileName = 'Laporan_Gaji_' . str_replace(' ', '_', $periode->nama_periode) . '.pdf';
        return $pdf->download($fileName);
    }

    private function buildSlipData($periodeId, $kodeSopir)
    {
        $periode = Periode::find($periodeId);
        if (!$periode) return null;

        $sopir = Sopir::where('kode_sopir', $kodeSopir)->first();
        if (!$sopir) return null;

        $startDate = \Carbon\Carbon::parse($periode->tanggal_mulai);
        $endDate = \Carbon\Carbon::parse($periode->tanggal_selesai);

        // Batch all ritase data for this sopir per periode
        $ritaseData = Ritase::with(['tujuan:id,kode_tujuan,nama'])
            ->where('kode_sopir', $kodeSopir)
            ->where('periode_id', $periodeId)
            ->orderBy('tanggal')
            ->orderBy('waktu', 'desc')
            ->get(['id', 'kode_ritase', 'tanggal', 'waktu', 'kode_tujuan', 'kabupaten', 'upah_sopir', 'dt', 'status', 'nominal_kompensasi']);

        if ($ritaseData->isEmpty()) return null;

        // Per-tujuan rates
        $detailTujuan = PenggajianDetail::whereHas('penggajian', function ($q) use ($periodeId, $kodeSopir) {
            $q->where('periode_id', $periodeId)->where('kode_sopir', $kodeSopir);
        })->get(['kode_tujuan', 'solar_per_rit', 'upah_per_rit']);

        $dataPerHari = [];
        $totalSolarAll = 0;
        $totalUpahAll = 0;
        $totalJumlahAll = 0;
        $totalDTAll = 0;

        $ritPerHariGroup = $ritaseData->groupBy(function ($rit) {
            return $rit->tanggal instanceof \Carbon\Carbon ? $rit->tanggal->format('Y-m-d') : $rit->tanggal;
        });

        foreach ($ritPerHariGroup as $tgl => $rits) {
            $ritKe = 0;
            $rits->sortByDesc('waktu')->values()->each(function ($rit) use (&$ritKe, &$dataPerHari, &$totalSolarAll, &$totalUpahAll, &$totalJumlahAll, &$totalDTAll, $detailTujuan, $tgl) {
                if ($rit->status === 'gagal_produksi') return;

                $ritKe++;
                $detail = $detailTujuan->first(function ($d) use ($rit) {
                    return $d->kode_tujuan === $rit->kode_tujuan;
                });

                $solar = $detail ? floatval($detail->solar_per_rit) : 0;
                $upah = $detail ? floatval($detail->upah_per_rit) : floatval($rit->upah_sopir);
                $jumlah = $solar + $upah;
                $dt = floatval($rit->dt ?? 0);

                $totalSolarAll += $solar;
                $totalUpahAll += $upah;
                $totalJumlahAll += $jumlah;
                $totalDTAll += $dt;

                $dataPerHari[] = [
                    'tanggal' => $tgl,
                    'hari' => \Carbon\Carbon::parse($tgl)->translatedFormat('l'),
                    'tujuan' => $rit->tujuan ? $rit->tujuan->nama : '-',
                    'rit_ke' => $ritKe,
                    'total_rit_hari' => 0,
                    'is_gagal' => false,
                    'solar' => $solar,
                    'upah' => $upah,
                    'jumlah' => $jumlah,
                    'dt' => $dt,
                ];
            });
        }

        if (empty($dataPerHari)) return null;

        // Fill total_rit_hari per group
        $grouped = collect($dataPerHari)->groupBy('tanggal');
        foreach ($grouped as $tgl => $items) {
            $count = count($items);
            foreach ($items as $i => &$item) {
                $item['total_rit_hari'] = $count;
            }
        }
        $dataPerHari = $grouped->flatten(1)->values()->all();

        return compact('sopir', 'dataPerHari', 'totalSolarAll', 'totalUpahAll', 'totalJumlahAll', 'totalDTAll');
    }
}
