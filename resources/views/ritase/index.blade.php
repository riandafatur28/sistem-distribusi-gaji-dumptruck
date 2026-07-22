<x-layouts.dashboard
    :title="'Kelola Ritase'"
    :pageTitle="'Kelola Ritase'"
    :user="auth()->user()">

    {{-- HEADER --}}
    <div class="border-b border-gray-200 pb-4 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Kelola Data Ritase</h1>
                <p class="text-base text-gray-500 mt-1">Input dan kelola ritase dump-truck dengan aturan sewa DT otomatis.</p>
            </div>
        </div>
    </div>

    {{-- ALERTS --}}
    @if(session('success'))
        <div class="border border-green-200 bg-green-50 text-green-700 px-4 py-3 rounded mb-4 text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="border border-red-200 bg-red-50 text-red-700 px-4 py-3 rounded mb-4 text-sm">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="border border-red-200 bg-red-50 text-red-700 px-4 py-3 rounded mb-4 text-sm">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- STATS CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white border border-gray-200 rounded p-4">
            <p class="text-xs text-gray-500 uppercase font-semibold">Total Ritase</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $totalRitase }}</p>
        </div>
        <div class="bg-white border border-gray-200 rounded p-4">
            <p class="text-xs text-gray-500 uppercase font-semibold">Ritase Valid</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $ritaseValid }}</p>
        </div>
        <div class="bg-white border border-gray-200 rounded p-4">
            <p class="text-xs text-gray-500 uppercase font-semibold">Pending</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $ritasePending }}</p>
        </div>
        <div class="bg-white border border-gray-200 rounded p-4">
            <p class="text-xs text-gray-500 uppercase font-semibold">Gagal Produksi</p>
            <p class="text-2xl font-bold text-red-600 mt-1">{{ $ritaseGagal }}</p>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- FORM TAMBAH RITASE --}}
    {{-- ============================================================ --}}
    <div class="bg-white border border-gray-200 rounded mb-6 overflow-hidden">
        <div class="bg-gray-50 border-b border-gray-200 px-5 py-3">
            <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wider">
                Tambah Ritase Baru
                <span class="font-normal text-gray-400 text-xs ml-2">Sistem akan otomatis mengecek aturan sewa DT</span>
            </h3>
        </div>
        <div class="px-5 py-4">
            <form id="formTambahRitase" class="space-y-4">
                @csrf

                {{-- Row 1: Periode, Sopir, Tujuan --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Periode <span class="text-red-500">*</span></label>
                        <select id="periode_id" name="periode_id" required
                            class="w-full px-4 py-2.5 border border-gray-200 rounded text-sm focus:outline-none focus:border-[#1a1a2e] focus:ring-1 focus:ring-[#1a1a2e]/20 transition bg-white">
                            <option value="">-- Pilih Periode --</option>
                            @foreach($periodes as $periode)
                                <option value="{{ $periode->id }}">{{ $periode->nama_periode }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Sopir <span class="text-red-500">*</span></label>
                        <select id="kode_sopir" name="kode_sopir" required
                            class="w-full px-4 py-2.5 border border-gray-200 rounded text-sm focus:outline-none focus:border-[#1a1a2e] focus:ring-1 focus:ring-[#1a1a2e]/20 transition bg-white">
                            <option value="">-- Pilih Sopir --</option>
                            @foreach($sopirs as $sopir)
                                <option value="{{ $sopir->kode_sopir }}">{{ $sopir->nama }} ({{ $sopir->kode_sopir }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Tujuan <span class="text-red-500">*</span></label>
                        <select id="kode_tujuan" name="kode_tujuan" required
                            class="w-full px-4 py-2.5 border border-gray-200 rounded text-sm focus:outline-none focus:border-[#1a1a2e] focus:ring-1 focus:ring-[#1a1a2e]/20 transition bg-white">
                            <option value="">-- Pilih Tujuan --</option>
                            @foreach(\App\Models\Tujuan::where('status', 'aktif')->orderBy('id', 'asc')->get() as $tujuan)
                                <option value="{{ $tujuan->kode_tujuan }}">{{ $tujuan->nama }} ({{ $tujuan->kode_tujuan }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Row 2: Tanggal, Waktu, Kabupaten --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Tanggal <span class="text-red-500">*</span></label>
                        <input type="date" id="tanggal" name="tanggal" required
                            class="w-full px-4 py-2.5 border border-gray-200 rounded text-sm focus:outline-none focus:border-[#1a1a2e] focus:ring-1 focus:ring-[#1a1a2e]/20 transition bg-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Waktu <span class="text-red-500">*</span></label>
                        <select id="waktu" name="waktu" required
                            class="w-full px-4 py-2.5 border border-gray-200 rounded text-sm focus:outline-none focus:border-[#1a1a2e] focus:ring-1 focus:ring-[#1a1a2e]/20 transition bg-white">
                            <option value="">-- Pilih Waktu --</option>
                            <option value="pagi">Pagi</option>
                            <option value="malam">Malam</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Kabupaten <span class="text-red-500">*</span></label>
                        <select id="kabupaten" name="kabupaten" required
                            class="w-full px-4 py-2.5 border border-gray-200 rounded text-sm focus:outline-none focus:border-[#1a1a2e] focus:ring-1 focus:ring-[#1a1a2e]/20 transition bg-white">
                            <option value="">-- Pilih Kabupaten --</option>
                            <option value="Nganjuk">Nganjuk</option>
                            <option value="Kediri">Kediri</option>
                            <option value="Kota Kediri">Kota Kediri</option>
                            <option value="Jombang">Jombang</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                </div>

                {{-- Row 3: Status, DT --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Status <span class="text-red-500">*</span></label>
                        <select id="status" name="status" required onchange="toggleKompensasiField()"
                            class="w-full px-4 py-2.5 border border-gray-200 rounded text-sm focus:outline-none focus:border-[#1a1a2e] focus:ring-1 focus:ring-[#1a1a2e]/20 transition bg-white">
                            <option value="pending">Pending</option>
                            <option value="valid">Selesai</option>
                            <option value="gagal_produksi">Gagal Produksi</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">DT (Sewa Dump Truck)</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm">Rp</span>
                            <input type="number" id="dt" name="dt" min="0" readonly
                                class="w-full pl-8 pr-3 py-2.5 border border-gray-200 rounded text-sm bg-gray-100 text-gray-600 cursor-not-allowed"
                                value="0">
                        </div>
                        <p class="text-xs text-gray-800 mt-1">*DT akan dihitung otomatis berdasarkan aturan</p>
                    </div>
                </div>

                {{-- Row 4: Kompensasi & Catatan --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div id="kompensasi_container" class="hidden">
                        <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">
                            Nominal Kompensasi
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm">Rp</span>
                            <input type="number" id="nominal_kompensasi" name="nominal_kompensasi" min="0"
                                class="w-full pl-8 pr-3 py-2.5 border border-gray-200 rounded text-sm focus:outline-none focus:border-[#1a1a2e] focus:ring-1 focus:ring-[#1a1a2e]/20 transition bg-white"
                                placeholder="0">
                        </div>
                        <p class="text-red-500 text-xs mt-1 hidden" id="error_kompensasi">Nominal harus angka positif.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Catatan</label>
                        <input type="text" id="catatan" name="catatan"
                            class="w-full px-4 py-2.5 border border-gray-200 rounded text-sm focus:outline-none focus:border-[#1a1a2e] focus:ring-1 focus:ring-[#1a1a2e]/20 transition bg-white"
                            placeholder="Catatan tambahan (opsional)">
                        <p class="text-red-500 text-xs mt-1 hidden" id="error_catatan">Catatan hanya boleh huruf, angka, spasi, dan strip.</p>
                    </div>
                </div>

                {{-- Preview Aturan Sewa DT --}}
                <div id="previewAturan" class="hidden border border-gray-200 rounded p-4 bg-gray-50">
                    <div class="flex-1">
                        <h4 class="font-semibold text-gray-800 text-sm mb-1">Preview Aturan Sewa DT</h4>
                        <p class="text-sm text-gray-600" id="previewKeterangan">-</p>
                        <div class="mt-2 flex flex-wrap items-center gap-4">
                            <span class="text-sm text-gray-600">Rit ke-<span id="previewRitKe" class="font-semibold text-gray-900">-</span></span>
                            <span class="text-sm text-gray-600">Sewa DT: <span id="previewSewaDT" class="font-semibold text-gray-900">-</span></span>
                            <span id="previewKompensasiContainer" class="hidden text-sm text-gray-800">Kompensasi: Rp <span id="previewKompensasi">0</span></span>
                        </div>
                    </div>
                </div>

                {{-- Tombol Submit --}}
                <div class="flex justify-end pt-2">
                    <button type="submit" class="bg-[#1a1a2e] text-white rounded text-sm font-semibold px-5 py-2.5 hover:bg-[#2d2d44] transition">
                        Tambah Ritase
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- BULK INPUT MASSAL --}}
    {{-- ============================================================ --}}
    <div class="bg-white border border-gray-200 rounded mb-6 overflow-hidden">
        <div class="bg-gray-50 border-b border-gray-200 px-5 py-3 flex items-center justify-between cursor-pointer" onclick="toggleBulk()">
            <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wider">
                Input Massal
                <span class="font-normal text-gray-400 text-xs ml-2">Paste data ritase untuk banyak sopir sekaligus</span>
            </h3>
            <svg id="bulkChevron" class="w-4 h-4 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>
        <div id="bulkContent" class="hidden px-5 py-4">
            <form id="formBulkRitase" action="{{ route('ritase.bulk') }}" method="POST">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Periode <span class="text-red-500">*</span></label>
                        <select name="periode_id" required class="w-full px-4 py-2.5 border border-gray-200 rounded text-sm focus:outline-none focus:border-[#1a1a2e] focus:ring-1 focus:ring-[#1a1a2e]/20 transition bg-white">
                            <option value="">-- Pilih Periode --</option>
                            @foreach($periodes as $periode)
                                <option value="{{ $periode->id }}">{{ $periode->nama_periode }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Tanggal <span class="text-red-500">*</span></label>
                        <input type="date" name="tanggal" required class="w-full px-4 py-2.5 border border-gray-200 rounded text-sm focus:outline-none focus:border-[#1a1a2e] focus:ring-1 focus:ring-[#1a1a2e]/20 transition bg-white">
                        <p class="text-xs text-gray-400 mt-0.5">Bisa diisi otomatis dari baris pertama teks</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Waktu <span class="text-red-500">*</span></label>
                        <select name="waktu" required class="w-full px-4 py-2.5 border border-gray-200 rounded text-sm focus:outline-none focus:border-[#1a1a2e] focus:ring-1 focus:ring-[#1a1a2e]/20 transition bg-white">
                            <option value="pagi">Pagi</option>
                            <option value="malam">Malam</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Kabupaten <span class="text-red-500">*</span></label>
                        <select name="kabupaten" required class="w-full px-4 py-2.5 border border-gray-200 rounded text-sm focus:outline-none focus:border-[#1a1a2e] focus:ring-1 focus:ring-[#1a1a2e]/20 transition bg-white">
                            <option value="">-- Pilih Kabupaten --</option>
                            <option value="Nganjuk">Nganjuk</option>
                            <option value="Kediri">Kediri</option>
                            <option value="Kota Kediri">Kota Kediri</option>
                            <option value="Jombang">Jombang</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Status <span class="text-red-500">*</span></label>
                        <select name="status" required class="w-full px-4 py-2.5 border border-gray-200 rounded text-sm focus:outline-none focus:border-[#1a1a2e] focus:ring-1 focus:ring-[#1a1a2e]/20 transition bg-white">
                            <option value="pending">Pending</option>
                            <option value="valid">Selesai</option>
                            <option value="gagal_produksi">Gagal Produksi</option>
                        </select>
                    </div>
                </div>

                {{-- Tujuan nggak perlu field — otomatis dari baris Paket ... --}}

                <div class="mb-4">
                    <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">
                        Daftar Sopir <span class="text-red-500">*</span>
                        <span class="font-normal text-gray-400 text-xs ml-2">
                            Format: <code class="bg-gray-100 px-1 rounded">1. Nama</code> atau <code class="bg-gray-100 px-1 rounded">Nama</code> per baris. <code class="bg-gray-100 px-1 rounded">Paket ...</code> untuk grup.
                        </span>
                    </label>
                    <textarea name="daftar_sopir" rows="12" required
                        class="w-full px-4 py-3 border border-gray-200 rounded text-sm focus:outline-none focus:border-[#1a1a2e] focus:ring-1 focus:ring-[#1a1a2e]/20 transition bg-white font-mono"
                        placeholder="Contoh:&#10;22 07 26 rabu&#10;Bondan patching pare kota&#10;Paket cmm blitar kota&#10;1. Riki&#10;2. Kola&#10;3. Firsa&#10;&#10;Paket watualang ngawi&#10;1. Gun&#10;2. Anjar&#10;3. Wilujeng"></textarea>
                </div>

                <div class="flex items-center justify-between">
                    <p class="text-xs text-gray-500" id="bulkCount">Belum ada sopir terdeteksi</p>
                    <div class="flex gap-3">
                        <button type="button" onclick="prakiraBulk()"
                            class="border border-gray-300 rounded text-sm font-medium text-gray-700 px-4 py-2.5 hover:bg-gray-50 transition">
                            Prakira
                        </button>
                        <button type="submit"
                            class="bg-[#1a1a2e] text-white rounded text-sm font-semibold px-5 py-2.5 hover:bg-[#2d2d44] transition">
                            Input Massal
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- TABEL DATA RITASE + FILTER LANGSUNG --}}
    {{-- ============================================================ --}}
    <div class="bg-white border border-gray-200 rounded overflow-hidden">
        <div class="border-b border-gray-200 px-5 py-4">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wider">Daftar Ritase</h3>
                    <p class="text-xs text-gray-400 mt-0.5" id="tableInfo">Memuat data...</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-3" id="filterBar">
                    <select name="periode" onchange="filterChanged()" class="px-3 py-2 border border-gray-200 rounded text-sm bg-white">
                        <option value="">Semua Periode</option>
                        @foreach($periodes as $periode)
                            <option value="{{ $periode->id }}" {{ $filterPeriode == $periode->id ? 'selected' : '' }}>{{ $periode->nama_periode }}</option>
                        @endforeach
                    </select>
                    <select name="sopir" onchange="filterChanged()" class="px-3 py-2 border border-gray-200 rounded text-sm bg-white">
                        <option value="">Semua Sopir</option>
                        @foreach($sopirs as $sopir)
                            <option value="{{ $sopir->kode_sopir }}" {{ $filterSopir == $sopir->kode_sopir ? 'selected' : '' }}>{{ $sopir->nama }}</option>
                        @endforeach
                    </select>
                    <input type="date" name="tanggal_mulai" value="{{ $tglMulai }}" onchange="filterChanged()"
                        class="px-3 py-2 border border-gray-200 rounded text-sm bg-white" placeholder="Tgl Mulai">
                    <input type="date" name="tanggal_selesai" value="{{ $tglSelesai }}" onchange="filterChanged()"
                        class="px-3 py-2 border border-gray-200 rounded text-sm bg-white" placeholder="Tgl Selesai">
                    <div class="relative w-full sm:w-56">
                        <input type="text" id="liveSearch" value="{{ $search }}"
                            class="w-full pl-10 pr-10 py-2 border border-gray-200 rounded text-sm focus:outline-none focus:border-[#1a1a2e] focus:ring-1 focus:ring-[#1a1a2e]/20 transition bg-white"
                            placeholder="Cari kode, sopir, tujuan..." autocomplete="off">
                        <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        <button id="clearSearch" class="hidden absolute right-3 top-1/2 transform -translate-y-1/2 p-1 hover:bg-gray-200 rounded-full">
                            <svg class="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto" id="tableContainer">
            @include('ritase._table', ['ritases' => $ritases, 'search' => $search ?? ''])
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- MODAL EDIT RITASE --}}
    {{-- ============================================================ --}}
    <div id="editModal" class="fixed inset-0 bg-black/40 z-50 hidden items-center justify-center">
        <div class="bg-white rounded shadow-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Edit Data Ritase</h3>
                    <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <form id="editForm" method="POST" class="space-y-4" action="">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Kode Ritase</label>
                            <input type="text" id="edit_kode_ritase" disabled class="w-full px-4 py-2.5 border border-gray-200 rounded text-sm bg-gray-100 text-gray-600 cursor-not-allowed">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Periode <span class="text-red-500">*</span></label>
                            <select id="edit_periode_id" name="periode_id" required class="w-full px-4 py-2.5 border border-gray-200 rounded text-sm focus:outline-none focus:border-[#1a1a2e] focus:ring-1 focus:ring-[#1a1a2e]/20 transition bg-white">
                                @foreach($periodes as $periode)
                                    <option value="{{ $periode->id }}">{{ $periode->nama_periode }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Sopir <span class="text-red-500">*</span></label>
                            <select id="edit_kode_sopir" name="kode_sopir" required class="w-full px-4 py-2.5 border border-gray-200 rounded text-sm focus:outline-none focus:border-[#1a1a2e] focus:ring-1 focus:ring-[#1a1a2e]/20 transition bg-white">
                                @foreach($sopirs as $sopir)
                                    <option value="{{ $sopir->kode_sopir }}">{{ $sopir->nama }} ({{ $sopir->kode_sopir }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Tujuan <span class="text-red-500">*</span></label>
                            <select id="edit_kode_tujuan" name="kode_tujuan" required class="w-full px-4 py-2.5 border border-gray-200 rounded text-sm focus:outline-none focus:border-[#1a1a2e] focus:ring-1 focus:ring-[#1a1a2e]/20 transition bg-white">
                                @foreach(\App\Models\Tujuan::where('status', 'aktif')->orderBy('id', 'asc')->get() as $tujuan)
                                    <option value="{{ $tujuan->kode_tujuan }}">{{ $tujuan->nama }} ({{ $tujuan->kode_tujuan }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Tanggal <span class="text-red-500">*</span></label>
                            <input type="date" id="edit_tanggal" name="tanggal" required class="w-full px-4 py-2.5 border border-gray-200 rounded text-sm focus:outline-none focus:border-[#1a1a2e] focus:ring-1 focus:ring-[#1a1a2e]/20 transition bg-white">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Waktu <span class="text-red-500">*</span></label>
                            <select id="edit_waktu" name="waktu" required class="w-full px-4 py-2.5 border border-gray-200 rounded text-sm focus:outline-none focus:border-[#1a1a2e] focus:ring-1 focus:ring-[#1a1a2e]/20 transition bg-white">
                                <option value="pagi">Pagi</option>
                                <option value="malam">Malam</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Kabupaten <span class="text-red-500">*</span></label>
                            <select id="edit_kabupaten" name="kabupaten" required class="w-full px-4 py-2.5 border border-gray-200 rounded text-sm focus:outline-none focus:border-[#1a1a2e] focus:ring-1 focus:ring-[#1a1a2e]/20 transition bg-white">
                                <option value="Nganjuk">Nganjuk</option>
                                <option value="Kediri">Kediri</option>
                                <option value="Kota Kediri">Kota Kediri</option>
                                <option value="Jombang">Jombang</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Status <span class="text-red-500">*</span></label>
                            <select id="edit_status" name="status" required onchange="toggleEditKompensasiField()" class="w-full px-4 py-2.5 border border-gray-200 rounded text-sm focus:outline-none focus:border-[#1a1a2e] focus:ring-1 focus:ring-[#1a1a2e]/20 transition bg-white">
                                <option value="pending">Pending</option>
                                <option value="valid">Selesai</option>
                                <option value="gagal_produksi">Gagal Produksi</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">DT (Sewa Dump Truck)</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm">Rp</span>
                                <input type="number" id="edit_dt" name="dt" min="0" readonly
                                    class="w-full pl-8 pr-3 py-2.5 border border-gray-200 rounded text-sm bg-gray-100 text-gray-600 cursor-not-allowed"
                                    value="0">
                            </div>
                        </div>
                        <div id="edit_kompensasi_container" class="hidden">
                            <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Nominal Kompensasi</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm">Rp</span>
                                <input type="number" id="edit_nominal_kompensasi" name="nominal_kompensasi" min="0"
                                    class="w-full pl-8 pr-3 py-2.5 border border-gray-200 rounded text-sm focus:outline-none focus:border-[#1a1a2e] focus:ring-1 focus:ring-[#1a1a2e]/20 transition bg-white">
                            </div>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Catatan</label>
                            <input type="text" id="edit_catatan" name="catatan" class="w-full px-4 py-2.5 border border-gray-200 rounded text-sm focus:outline-none focus:border-[#1a1a2e] focus:ring-1 focus:ring-[#1a1a2e]/20 transition bg-white">
                        </div>
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button type="button" onclick="closeEditModal()" class="flex-1 border border-gray-300 rounded text-sm font-medium text-gray-700 px-4 py-2.5 hover:bg-gray-50 transition">Batal</button>
                        <button type="submit" class="flex-1 bg-[#1a1a2e] text-white rounded text-sm font-semibold px-5 py-2.5 hover:bg-[#2d2d44] transition">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- MODAL KONFIRMASI TAMBAH --}}
    {{-- ============================================================ --}}
    <div id="tambahModal" class="fixed inset-0 bg-black/40 z-50 hidden items-center justify-center">
        <div class="bg-white rounded shadow-xl w-full max-w-md mx-4">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Konfirmasi Tambah Ritase</h3>
                    <button onclick="closeTambahModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div id="konfirmasiDetail" class="text-sm text-gray-600 mb-4 bg-gray-50 p-4 rounded max-h-60 overflow-y-auto"></div>
                <div class="flex gap-3">
                    <button onclick="closeTambahModal()" class="flex-1 border border-gray-300 rounded text-sm font-medium text-gray-700 px-4 py-2.5 hover:bg-gray-50 transition">Batal</button>
                    <button onclick="submitTambahRitase()" class="flex-1 bg-[#1a1a2e] text-white rounded text-sm font-semibold px-5 py-2.5 hover:bg-[#2d2d44] transition">Ya, Tambah</button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // ===== VALIDASI INPUT =====
        function validasiNama(input) {
            return /^[a-zA-Z0-9\s\-\.]+$/.test(input);
        }

        function validasiCatatan(input) {
            return /^[a-zA-Z0-9\s\-\.]+$/.test(input);
        }

        function validasiNominal(input) {
            return /^\d+$/.test(input) && parseInt(input) >= 0;
        }

        // ===== INITIALIZE TOM SELECT =====
        let tomSopir, tomTujuan, tomEditSopir, tomEditTujuan;
        let formDataTambah = null;

        document.addEventListener('DOMContentLoaded', function() {
            // Form Tambah - Sopir
            if (document.getElementById('kode_sopir')) {
                tomSopir = new TomSelect('#kode_sopir', {
                    create: false,
                    sortField: { field: "text", direction: "asc" },
                    placeholder: 'Ketik nama atau kode sopir...',
                    allowEmptyOption: true,
                    searchField: ['text'],
                });
            }

            // Form Tambah - Tujuan
            if (document.getElementById('kode_tujuan')) {
                tomTujuan = new TomSelect('#kode_tujuan', {
                    create: false,
                    sortField: { field: "text", direction: "asc" },
                    placeholder: 'Ketik nama tujuan...',
                    allowEmptyOption: true,
                    searchField: ['text'],
                });
            }

            // Initialize kompensasi field
            toggleKompensasiField();

            // ===== TAMBAH RITASE DENGAN KONFIRMASI =====
            document.getElementById('formTambahRitase').addEventListener('submit', function(e) {
                e.preventDefault();

                const form = this;
                const formData = new FormData(form);

                // Validasi field wajib
                const requiredFields = ['periode_id', 'kode_sopir', 'kode_tujuan', 'tanggal', 'waktu', 'kabupaten', 'status'];
                let valid = true;
                let errorMessage = '';

                requiredFields.forEach(field => {
                    const value = formData.get(field);
                    if (!value || value === '') {
                        valid = false;
                        errorMessage += 'Field ' + field.replace('_', ' ') + ' wajib diisi!\n';
                    }
                });

                if (!valid) {
                    alert(errorMessage);
                    return;
                }

                // Validasi catatan
                const catatan = formData.get('catatan');
                const errorCatatan = document.getElementById('error_catatan');
                if (catatan && !validasiCatatan(catatan)) {
                    errorCatatan.classList.remove('hidden');
                    document.getElementById('catatan').classList.add('border-red-500');
                    return;
                } else {
                    errorCatatan.classList.add('hidden');
                    document.getElementById('catatan').classList.remove('border-red-500');
                }

                // Validasi nominal kompensasi
                const nominal = formData.get('nominal_kompensasi');
                const errorKompensasi = document.getElementById('error_kompensasi');
                if (nominal && !validasiNominal(nominal)) {
                    errorKompensasi.classList.remove('hidden');
                    document.getElementById('nominal_kompensasi').classList.add('border-red-500');
                    return;
                } else {
                    errorKompensasi.classList.add('hidden');
                    document.getElementById('nominal_kompensasi').classList.remove('border-red-500');
                }

                // Simpan form data untuk submit nanti
                formDataTambah = formData;

                // Tampilkan detail di modal
                const sopir = document.getElementById('kode_sopir');
                const tujuan = document.getElementById('kode_tujuan');
                const periode = document.getElementById('periode_id');
                const status = formData.get('status');
                const nominalValue = parseFloat(formData.get('nominal_kompensasi') || 0);
                const dt = parseFloat(formData.get('dt') || 0);

                let kompensasiHtml = '';
                if (status === 'gagal_produksi') {
                    if (nominalValue > 0) {
                        kompensasiHtml = `
                            <div class="flex justify-between">
                                <span class="text-gray-500">Kompensasi:</span>
                                <span class="font-semibold text-red-600">Rp ${nominalValue.toLocaleString('id-ID')}</span>
                            </div>
                        `;
                    } else {
                        kompensasiHtml = `
                            <div class="flex justify-between">
                                <span class="text-gray-500">Kompensasi:</span>
                                <span class="font-semibold text-gray-600">Belum ditentukan</span>
                            </div>
                        `;
                    }
                }

                document.getElementById('konfirmasiDetail').innerHTML = `
                    <div class="space-y-2">
                        <div class="flex justify-between"><span class="text-gray-500">Periode:</span><span class="font-semibold text-gray-900">${periode.options[periode.selectedIndex].text}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Sopir:</span><span class="font-semibold text-gray-900">${sopir.options[sopir.selectedIndex].text}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Tujuan:</span><span class="font-semibold text-gray-900">${tujuan.options[tujuan.selectedIndex].text}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Tanggal:</span><span class="font-semibold text-gray-900">${formData.get('tanggal')}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Waktu:</span><span class="font-semibold text-gray-900 capitalize">${formData.get('waktu')}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Kabupaten:</span><span class="font-semibold text-gray-900">${formData.get('kabupaten')}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Status:</span><span class="font-semibold text-gray-900 capitalize">${status.replace('_', ' ')}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">DT (Sewa DT):</span><span class="font-semibold text-gray-800">Rp ${dt.toLocaleString('id-ID')}</span></div>
                        ${kompensasiHtml}
                    </div>
                `;

                // Tampilkan modal
                const modal = document.getElementById('tambahModal');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            });

            // ===== AUTO CALCULATE DT =====
            autoCalculateDT();
        });

        // ===== AUTO CALCULATE DT =====
        function autoCalculateDT() {
            const kabupaten = document.getElementById('kabupaten');
            const waktu = document.getElementById('waktu');
            const status = document.getElementById('status');
            const kompensasi = document.getElementById('nominal_kompensasi');
            const dtInput = document.getElementById('dt');
            const kodeSopir = document.getElementById('kode_sopir');
            const tanggal = document.getElementById('tanggal');

            function hitungDT() {
                const kab = kabupaten.value;
                const waktuVal = waktu.value;
                const statusVal = status.value;
                const sopir = kodeSopir.value;
                const tgl = tanggal.value;

                // Default
                let dt = 0;
                let keterangan = '';

                // Jika status Gagal Produksi - DT = 0
                if (statusVal === 'gagal_produksi') {
                    dt = 0;
                    keterangan = 'Gagal Produksi - Tidak dapat DT';
                    dtInput.value = dt;
                    document.getElementById('previewKeterangan').textContent = keterangan;
                    document.getElementById('previewRitKe').textContent = '-';
                    document.getElementById('previewSewaDT').textContent = '0';
                    document.getElementById('previewAturan').classList.remove('hidden');
                    return;
                }

                // Jika sopir dan tanggal dipilih, cek rit lain
                if (sopir && tgl && kab && waktuVal) {
                    // Cek via AJAX ke server
                    fetch('{{ route("ritase.cek.aturan") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            kode_sopir: sopir,
                            tanggal: tgl,
                            waktu: waktuVal,
                            kabupaten: kab,
                            status: statusVal,
                            nominal_kompensasi: parseFloat(kompensasi.value) || 0
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        dt = data.sewa_dt || 0;
                        keterangan = data.keterangan || '';

                        dtInput.value = dt;
                        document.getElementById('previewKeterangan').textContent = keterangan;
                        document.getElementById('previewRitKe').textContent = data.rit_keberapa || '-';
                        document.getElementById('previewSewaDT').textContent = dt.toLocaleString('id-ID');

                        // Update preview
                        document.getElementById('previewAturan').classList.remove('hidden');
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        dt = 0;
                        dtInput.value = 0;
                    });
                } else {
                    dt = 0;
                    dtInput.value = 0;
                    document.getElementById('previewAturan').classList.add('hidden');
                }
            }

            // Event listeners
            [kabupaten, waktu, status, kompensasi, kodeSopir, tanggal].forEach(el => {
                if (el) {
                    el.addEventListener('change', hitungDT);
                    el.addEventListener('input', hitungDT);
                }
            });

            // Initial hitung
            setTimeout(hitungDT, 100);
        }

        // ===== AUTO CALCULATE DT (EDIT MODAL) =====
        function autoCalculateEditDT() {
            const kabupaten = document.getElementById('edit_kabupaten');
            const waktu = document.getElementById('edit_waktu');
            const status = document.getElementById('edit_status');
            const kompensasi = document.getElementById('edit_nominal_kompensasi');
            const dtInput = document.getElementById('edit_dt');
            const kodeSopir = document.getElementById('edit_kode_sopir');
            const tanggal = document.getElementById('edit_tanggal');

            function hitungEditDT() {
                const kab = kabupaten.value;
                const waktuVal = waktu.value;
                const statusVal = status.value;
                const sopir = kodeSopir.value;
                const tgl = tanggal.value;

                // Jika status Gagal Produksi - DT = 0
                if (statusVal === 'gagal_produksi') {
                    dtInput.value = 0;
                    return;
                }

                // Jika sopir dan tanggal dipilih, cek rit lain
                if (sopir && tgl && kab && waktuVal) {
                    fetch('{{ route("ritase.cek.aturan") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            kode_sopir: sopir,
                            tanggal: tgl,
                            waktu: waktuVal,
                            kabupaten: kab,
                            status: statusVal,
                            nominal_kompensasi: parseFloat(kompensasi.value) || 0
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        dtInput.value = data.sewa_dt || 0;
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        dtInput.value = 0;
                    });
                } else {
                    dtInput.value = 0;
                }
            }

            // Event listeners
            [kabupaten, waktu, status, kompensasi, kodeSopir, tanggal].forEach(el => {
                if (el) {
                    el.addEventListener('change', hitungEditDT);
                }
            });
        }

        // ===== TOGGLE KOMPENSASI FIELD =====
        function toggleKompensasiField() {
            const status = document.getElementById('status').value;
            const kompContainer = document.getElementById('kompensasi_container');

            if (status === 'gagal_produksi') {
                kompContainer.classList.remove('hidden');
            } else {
                kompContainer.classList.add('hidden');
                document.getElementById('nominal_kompensasi').value = '';
            }
        }

        // ===== TOGGLE KOMPENSASI FIELD (EDIT) =====
        function toggleEditKompensasiField() {
            const status = document.getElementById('edit_status').value;
            const kompContainer = document.getElementById('edit_kompensasi_container');

            if (status === 'gagal_produksi') {
                kompContainer.classList.remove('hidden');
            } else {
                kompContainer.classList.add('hidden');
                document.getElementById('edit_nominal_kompensasi').value = '';
            }
        }

        // ===== LIVE SEARCH + FILTER VIA AJAX =====
        let debounceTimer;
        let fetchAbort = null;

        function filterChanged() {
            loadTable();
        }

        function loadTable() {
            if (fetchAbort) fetchAbort.abort();
            fetchAbort = new AbortController();

            const params = new URLSearchParams();

            document.querySelectorAll('#filterBar select, #filterBar input').forEach(el => {
                if (el.name && el.value) params.set(el.name, el.value);
            });
            const sq = document.getElementById('liveSearch').value.trim();
            if (sq) params.set('search', sq);
            params.set('partial', '1');

            const container = document.getElementById('tableContainer');
            container.innerHTML = '<div class="text-center py-12 text-gray-400">Memuat...</div>';

            fetch('{{ route("ritase.index") }}?' + params.toString(), {
                signal: fetchAbort.signal,
                headers: { 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(data => {
                container.innerHTML = data.html;
            })
            .catch(err => {
                if (err.name !== 'AbortError') {
                    container.innerHTML = '<div class="text-center py-12 text-red-500">Gagal memuat data</div>';
                }
            });
        }

        (function() {
            const searchInput = document.getElementById('liveSearch');
            const clearSearch = document.getElementById('clearSearch');

            function debounce(func, wait) {
                let t;
                return (...args) => {
                    clearTimeout(t);
                    t = setTimeout(() => func(...args), wait);
                };
            }

            searchInput.addEventListener('input', debounce(loadTable, 300));
            clearSearch.addEventListener('click', function() {
                searchInput.value = '';
                clearSearch.classList.add('hidden');
                loadTable();
                searchInput.focus();
            });
            if (searchInput.value) clearSearch.classList.remove('hidden');
        })();

        // Pagination via AJAX
        document.addEventListener('click', function(e) {
            const link = e.target.closest('#tableContainer a[href]');
            if (!link) return;
            const href = link.getAttribute('href');
            if (!href || href === '#') return;
            e.preventDefault();
            const params = new URLSearchParams(href.includes('?') ? href.split('?')[1] : '');
            params.set('partial', '1');
            document.getElementById('tableContainer').innerHTML = '<div class="text-center py-12 text-gray-400">Memuat...</div>';
            fetch(href.split('?')[0] + '?' + params.toString(), {
                headers: { 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(data => { document.getElementById('tableContainer').innerHTML = data.html; })
            .catch(() => {});
        });

        // ===== EDIT RITASE =====
        function openEditModal(ritase) {
            document.getElementById('editForm').action = '/ritase/' + ritase.id;
            document.getElementById('edit_kode_ritase').value = ritase.kode_ritase;
            document.getElementById('edit_periode_id').value = ritase.periode_id;
            document.getElementById('edit_tanggal').value = ritase.tanggal;
            document.getElementById('edit_waktu').value = ritase.waktu;
            document.getElementById('edit_kabupaten').value = ritase.kabupaten;
            document.getElementById('edit_status').value = ritase.status;
            document.getElementById('edit_dt').value = ritase.dt || 0;
            document.getElementById('edit_catatan').value = ritase.catatan || '';

            // Initialize Tom Select untuk edit
            setTimeout(() => {
                if (tomEditSopir) tomEditSopir.destroy();
                if (tomEditTujuan) tomEditTujuan.destroy();

                tomEditSopir = new TomSelect('#edit_kode_sopir', {
                    create: false,
                    sortField: { field: "text", direction: "asc" },
                    placeholder: 'Ketik nama atau kode sopir...',
                    searchField: ['text'],
                });

                tomEditTujuan = new TomSelect('#edit_kode_tujuan', {
                    create: false,
                    sortField: { field: "text", direction: "asc" },
                    placeholder: 'Ketik nama tujuan...',
                    searchField: ['text'],
                });

                tomEditSopir.setValue(ritase.kode_sopir);
                tomEditTujuan.setValue(ritase.kode_tujuan);
            }, 100);

            // Show/hide kompensasi
            const kompContainer = document.getElementById('edit_kompensasi_container');
            const nominalInput = document.getElementById('edit_nominal_kompensasi');

            if (ritase.status === 'gagal_produksi') {
                kompContainer.classList.remove('hidden');
                nominalInput.value = ritase.nominal_kompensasi || '';
            } else {
                kompContainer.classList.add('hidden');
                nominalInput.value = '';
            }

            const modal = document.getElementById('editModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeEditModal() {
            const modal = document.getElementById('editModal');
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }

        // ===== MODAL KONFIRMASI TAMBAH =====
        function closeTambahModal() {
            const modal = document.getElementById('tambahModal');
            modal.classList.remove('flex');
            modal.classList.add('hidden');
            formDataTambah = null;
        }

        function submitTambahRitase() {
            if (formDataTambah) {
                // Buat form baru untuk submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route("ritase.store") }}';

                // Tambahkan CSRF token
                const csrf = document.createElement('input');
                csrf.type = 'hidden';
                csrf.name = '_token';
                csrf.value = '{{ csrf_token() }}';
                form.appendChild(csrf);

                // Tambahkan semua data
                for (let [key, value] of formDataTambah.entries()) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = value;
                    form.appendChild(input);
                }

                document.body.appendChild(form);
                closeTambahModal();
                form.submit();
            }
        }

        
        // ===== BULK INPUT MASSAL =====
        function toggleBulk() {
            const content = document.getElementById('bulkContent');
            const chevron = document.getElementById('bulkChevron');
            content.classList.toggle('hidden');
            chevron.classList.toggle('rotate-180');
        }

                function prakiraBulk() {
            const textarea = document.querySelector('[name=daftar_sopir]');
            const countEl = document.getElementById('bulkCount');
            const dateInput = document.querySelector('[name=tanggal]');
            const text = textarea.value;
            if (!text.trim()) {
                countEl.textContent = 'Teks kosong.';
                return;
            }

            const lines = text.split('\n');
            let sopirCount = 0;
            let groupCount = 0;
            let parsedDate = '';
            let sopirNames = [];

            lines.forEach(line => {
                line = line.trim();
                if (!line) return;
                if (/^[\d\s\-\_\|\/]+$/.test(line)) return;

                const dateMatch = line.match(/^(\d{1,2})\s+(\d{1,2})\s+(\d{2,4})\s/);
                if (dateMatch) {
                    let d = dateMatch[1].padStart(2, '0');
                    let m = dateMatch[2].padStart(2, '0');
                    let y = dateMatch[3];
                    if (y.length === 2) y = '20' + y;
                    parsedDate = y + '-' + m + '-' + d;
                    return;
                }

                if (/^Paket\s/i.test(line)) {
                    groupCount++;
                    return;
                }

                const numMatch = line.match(/^\s*\d+[\.\s]\s*(.+)$/);
                if (numMatch) {
                    sopirCount++;
                    sopirNames.push(numMatch[1].trim());
                    return;
                }

                if (line && !/^\d+$/.test(line)) {
                    sopirCount++;
                    sopirNames.push(line);
                }
            });

            if (parsedDate && dateInput) {
                dateInput.value = parsedDate;
            }

            let msg = '';
            if (parsedDate) msg += '\ud83d\udcc5 Tanggal: ' + parsedDate + '\n';
            if (groupCount > 0) msg += '\ud83d\udce6 Grup: ' + groupCount + ' paket\n';
            msg += '\ud83d\udccb Sopir: ' + sopirCount + ' orang\n\n';
            sopirNames.slice(0, 25).forEach((n, i) => {
                msg += (i+1) + '. ' + n + '\n';
            });
            if (sopirNames.length > 25) {
                msg += '... dan ' + (sopirNames.length - 25) + ' lainnya\n';
            }
            msg += '\n\u26a0\ufe0f Pastikan semua sopir terdaftar di Kelola Sopir.';
            if (parsedDate) msg += '\n\u2705 Tanggal sudah otomatis terisi.';

            countEl.textContent = '\ud83d\udcca ' + sopirCount + ' sopir, ' + groupCount + ' grup' + (parsedDate ? ', tanggal terisi' : '');
            alert(msg);
        }

        // Tutup modal saat klik overlay
        document.querySelectorAll('.fixed.inset-0.bg-black\\/40').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('flex');
                    this.classList.add('hidden');
                    if (this.id === 'tambahModal') {
                        formDataTambah = null;
                    }
                }
            });
        });

        // Tutup dengan Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEditModal();
                closeTambahModal();
            }
        });
    </script>
    @endpush

</x-layouts.dashboard>
