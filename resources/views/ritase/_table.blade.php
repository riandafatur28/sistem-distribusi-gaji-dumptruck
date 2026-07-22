@if($ritases->count() > 0)
    <table class="w-full">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Kode</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Sopir</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Tujuan</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Tanggal</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Waktu</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Kabupaten</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">DT</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Kompensasi</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($ritases as $ritase)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 bg-gray-100 text-gray-700 rounded text-xs font-medium">
                            {{ $ritase->kode_ritase }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center space-x-2">
                            <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                                <span class="text-gray-700 font-bold text-xs">
                                    {{ $ritase->sopir ? substr($ritase->sopir->nama, 0, 1) : '?' }}
                                </span>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900">
                                    {{ $ritase->sopir ? $ritase->sopir->nama : 'Sopir tidak ditemukan' }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $ritase->sopir ? $ritase->sopir->kode_sopir : '-' }}
                                </p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-sm font-medium text-gray-900">
                            {{ $ritase->tujuan ? $ritase->tujuan->nama : 'Tujuan tidak ditemukan' }}
                        </p>
                        <p class="text-xs text-gray-500">
                            {{ $ritase->tujuan ? $ritase->tujuan->kode_tujuan : '-' }}
                        </p>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $ritase->tanggal->format('d M Y') }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2 py-1 rounded-full {{ $ritase->waktu == 'pagi' ? 'bg-yellow-100 text-yellow-700' : 'bg-indigo-100 text-indigo-700' }} text-xs font-semibold">
                            {{ ucfirst($ritase->waktu) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $ritase->kabupaten }}</td>
                    <td class="px-4 py-3">
                        @if($ritase->status == 'valid')
                            <span class="inline-flex items-center px-2 py-1 rounded-full bg-green-100 text-green-700 text-xs font-semibold">Selesai</span>
                        @elseif($ritase->status == 'pending')
                            <span class="inline-flex items-center px-2 py-1 rounded-full bg-yellow-100 text-yellow-700 text-xs font-semibold">Pending</span>
                        @else
                            <span class="inline-flex items-center px-2 py-1 rounded-full bg-red-100 text-red-700 text-xs font-semibold">Gagal Produksi</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        <span class="text-sm font-semibold {{ $ritase->dt > 0 ? 'text-gray-900' : 'text-gray-400' }}">
                            Rp {{ number_format($ritase->dt, 0, ',', '.') }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <span class="text-sm font-semibold {{ $ritase->nominal_kompensasi > 0 ? 'text-orange-600' : 'text-gray-400' }}">
                            Rp {{ number_format($ritase->nominal_kompensasi, 0, ',', '.') }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex justify-center gap-1">
                            <button onclick='openEditModal(@json($ritase))'
                                class="p-1.5 rounded hover:bg-blue-100 text-blue-600 transition"
                                title="Edit">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </button>
                            <form action="{{ route('ritase.destroy', $ritase->id) }}" method="POST" class="inline"
                                onsubmit="return confirm('Yakin hapus ritase {{ $ritase->kode_ritase }}?')">
                                @csrf @method('DELETE')
                                <button type="submit"
                                    class="p-1.5 rounded hover:bg-red-100 text-red-500 transition"
                                    title="Hapus">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="px-5 py-4 border-t border-gray-200">
        <div class="flex items-center justify-between">
            <p class="text-xs text-gray-500">
                Menampilkan {{ $ritases->firstItem() ?? 0 }} - {{ $ritases->lastItem() ?? 0 }} dari {{ $ritases->total() }} data
            </p>
            <div class="flex gap-1">
                {{ $ritases->appends(request()->except('partial'))->onEachSide(1)->links() }}
            </div>
        </div>
    </div>
@else
    <div class="px-5 py-12 text-center">
        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
        </svg>
        <p class="text-gray-500 text-sm">Tidak ada data ritase {{ !empty($search) ? "dengan kata kunci \"{$search}\"" : '' }}</p>
    </div>
@endif
