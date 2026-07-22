<x-layouts.dashboard
    :title="'Slip Gaji - ' . $periode->nama_periode"
    :pageTitle="'Slip Gaji'"
    :user="auth()->user()">

    <div class="border-b border-gray-200 pb-4 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Slip Gaji</h1>
                <p class="text-base text-gray-500 mt-1">{{ $periode->nama_periode }} ({{ \Carbon\Carbon::parse($periode->tanggal_mulai)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($periode->tanggal_selesai)->format('d/m/Y') }})</p>
            </div>
            <a href="{{ route('gaji.slip-pdf', $periode->id) }}"
               data-no-turbo
               class="bg-[#1a1a2e] text-white rounded text-sm font-semibold px-5 py-2.5 hover:bg-[#2d2d44] transition inline-flex items-center gap-2 no-print">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Download PDF
            </a>
        </div>
    </div>

    <div class="space-y-8">
        @forelse($allSlips as $slip)
            @php
                $sopir = $slip['sopir'];
                $dataPerHari = $slip['dataPerHari'];
                $totalSolarAll = $slip['totalSolarAll'];
                $totalUpahAll = $slip['totalUpahAll'];
                $totalJumlahAll = $slip['totalJumlahAll'];
                $totalDTAll = $slip['totalDTAll'];
                $grandTotal = $totalJumlahAll + $totalDTAll;

                // Nama hari Indonesia
                $namaHari = [
                    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
                    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => "Jum'at", 'Saturday' => 'Sabtu',
                ];

                // Group per rit_ke
                $groups = collect($dataPerHari)->groupBy('rit_ke')->sortKeys();

                // Unique sorted dates
                $allDates = collect($dataPerHari)->pluck('tanggal')->unique()->sort()->values();

                // Build date range from periode
                $start = \Carbon\Carbon::parse($periode->tanggal_mulai);
                $end = \Carbon\Carbon::parse($periode->tanggal_selesai);
                $dateHeaders = [];
                for ($d = $start->copy(); $d <= $end; $d->addDay()) {
                    $dateHeaders[] = $d->format('Y-m-d');
                }

                // Per-date total rits for label
                $ritsPerDay = collect($dataPerHari)->groupBy('tanggal')->map->count();
            @endphp

            {{-- Card per sopir --}}
            <div class="bg-white border border-gray-300 rounded overflow-hidden shadow-sm">
                {{-- Header Sopir --}}
                <div class="bg-gray-100 border-b-2 border-gray-300 px-5 py-3 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">{{ $sopir->nama }}</h3>
                        <p class="text-xs text-gray-500">{{ $sopir->kode_sopir }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-600">Grand Total</p>
                        <p class="text-xl font-bold text-gray-900">Rp {{ number_format($grandTotal, 0, ',', '.') }}</p>
                    </div>
                </div>

                {{-- Tabel per rit_ke --}}
                @foreach($groups as $ritKe => $pageData)
                    @php
                        $ritMap = $pageData->keyBy('tanggal');
                        $pageDT = $pageData->sum('dt');
                        $pageJumlah = $pageData->sum('jumlah');
                        $colCount = count($dateHeaders) + 3; // + NAMA + DT + JUMLAH
                    @endphp

                    <div class="overflow-x-auto border-t border-gray-200">
                        <table class="w-full text-sm border-collapse">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="border border-gray-300 px-2 py-1.5 text-left font-semibold" style="width: 90px;">NAMA</th>
                                    @foreach($dateHeaders as $tgl)
                                        @php
                                            $dt = \Carbon\Carbon::parse($tgl);
                                            $dayName = $namaHari[$dt->format('l')] ?? $dt->format('l');
                                            $label = $dayName . ' ' . $dt->format('d/m');
                                            $totalRit = $ritsPerDay[$tgl] ?? 0;
                                            if ($totalRit > 1) {
                                                $label .= ' (' . $ritKe . '/' . $totalRit . ')';
                                            }
                                        @endphp
                                        <th class="border border-gray-300 px-1.5 py-1 text-center font-semibold whitespace-nowrap">{{ $label }}</th>
                                    @endforeach
                                    <th class="border border-gray-300 px-2 py-1.5 text-center font-semibold" style="width: 60px;">DT</th>
                                    <th class="border border-gray-300 px-2 py-1.5 text-center font-semibold" style="width: 80px;">JUMLAH</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- Row: Solar --}}
                                <tr class="hover:bg-gray-50">
                                    <td class="border border-gray-300 px-2 py-1.5 font-bold text-left text-sm">Solar</td>
                                    @foreach($dateHeaders as $tgl)
                                        @php
                                            $entry = $ritMap[$tgl] ?? null;
                                            $val = $entry && !($entry['is_gagal'] ?? false) && $entry['solar'] > 0
                                                ? 'Rp ' . number_format($entry['solar'], 0, ',', '.')
                                                : ($entry && ($entry['is_gagal'] ?? false) ? 'GAGAL' : '');
                                        @endphp
                                        <td class="border border-gray-300 px-1.5 py-1 text-right whitespace-nowrap">{{ $val }}</td>
                                    @endforeach
                                    <td class="border border-gray-300 px-2 py-1.5"></td>
                                    <td class="border border-gray-300 px-2 py-1.5"></td>
                                </tr>

                                {{-- Row: Sopir --}}
                                <tr class="hover:bg-gray-50">
                                    <td class="border border-gray-300 px-2 py-1.5 font-bold text-left text-sm">Sopir</td>
                                    @foreach($dateHeaders as $tgl)
                                        @php
                                            $entry = $ritMap[$tgl] ?? null;
                                            $val = $entry && !($entry['is_gagal'] ?? false) && $entry['upah'] > 0
                                                ? 'Rp ' . number_format($entry['upah'], 0, ',', '.')
                                                : ($entry && ($entry['is_gagal'] ?? false) ? '-' : '');
                                        @endphp
                                        <td class="border border-gray-300 px-1.5 py-1 text-right whitespace-nowrap">{{ $val }}</td>
                                    @endforeach
                                    <td class="border border-gray-300 px-2 py-1.5"></td>
                                    <td class="border border-gray-300 px-2 py-1.5"></td>
                                </tr>

                                {{-- Row: Jumlah --}}
                                <tr class="hover:bg-gray-50 font-bold">
                                    <td class="border border-gray-300 px-2 py-1.5 font-bold text-left text-sm">Jumlah</td>
                                    @foreach($dateHeaders as $tgl)
                                        @php
                                            $entry = $ritMap[$tgl] ?? null;
                                            $val = $entry && $entry['jumlah'] > 0
                                                ? 'Rp ' . number_format($entry['jumlah'], 0, ',', '.')
                                                : '';
                                        @endphp
                                        <td class="border border-gray-300 px-1.5 py-1 text-right whitespace-nowrap">{{ $val }}</td>
                                    @endforeach
                                    <td class="border border-gray-300 px-2 py-1.5 text-right">
                                        @if($pageDT > 0) Rp {{ number_format($pageDT, 0, ',', '.') }} @endif
                                    </td>
                                    <td class="border border-gray-300 px-2 py-1.5 text-right">
                                        Rp {{ number_format($pageJumlah + $pageDT, 0, ',', '.') }}
                                    </td>
                                </tr>

                                {{-- Row: Tujuan --}}
                                <tr class="hover:bg-gray-50">
                                    <td class="border border-gray-300 px-2 py-1.5 font-bold text-left text-sm">Tujuan</td>
                                    @foreach($dateHeaders as $tgl)
                                        @php
                                            $entry = $ritMap[$tgl] ?? null;
                                            $tujuanLabel = $entry
                                                ? (($entry['is_gagal'] ?? false) ? 'Gagal' : $entry['tujuan'])
                                                : '';
                                        @endphp
                                        <td class="border border-gray-300 px-1.5 py-1 text-left text-sm">{{ $tujuanLabel }}</td>
                                    @endforeach
                                    <td class="border border-gray-300 px-2 py-1.5"></td>
                                    <td class="border border-gray-300 px-2 py-1.5"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @endforeach

                {{-- Footer total per sopir --}}
                <div class="bg-yellow-50 border-t-2 border-gray-300 px-5 py-2 flex justify-end items-center gap-6 text-sm">
                    <span class="font-semibold">Total Solar: Rp {{ number_format($totalSolarAll, 0, ',', '.') }}</span>
                    <span class="font-semibold">Total Upah: Rp {{ number_format($totalUpahAll, 0, ',', '.') }}</span>
                    <span class="font-semibold">Total DT: Rp {{ number_format($totalDTAll, 0, ',', '.') }}</span>
                    <span class="text-base font-bold text-gray-900">Grand Total: Rp {{ number_format($grandTotal, 0, ',', '.') }}</span>
                </div>
            </div>
        @empty
            <div class="text-center py-12 text-gray-400">
                <p class="text-lg font-semibold">Belum ada data slip</p>
                <p class="text-sm">Tidak ada ritase untuk periode ini.</p>
            </div>
        @endforelse
    </div>

    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .space-y-8 > div { page-break-inside: avoid; }
        }
    </style>
</x-layouts.dashboard>
