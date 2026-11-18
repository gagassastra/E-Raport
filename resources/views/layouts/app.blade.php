<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dasbor') — E-Raport {{ $school->nama_lembaga ?? 'PKBM AL-QUDWAH' }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
@php($user = auth()->user())
<div class="app">
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            @if($school->logo_path)
                <img src="{{ route('school.logo') }}?v={{ $school->updated_at?->timestamp }}" alt="Logo">
            @else
                <span class="brand-logo">PK</span>
            @endif
            <div><b>E-RAPORT</b><small>{{ $school->nama_lembaga }}</small></div>
        </div>
        <nav class="nav">
            @foreach($menuGroups as $g)
                @if($g['label'])<div class="nav-label">{{ $g['label'] }}</div>@endif
                @foreach($g['items'] as $it)
                    <a href="{{ route($it['route']) }}" class="{{ request()->routeIs($it['pattern']) ? 'active' : '' }}">@icon($it['icon']) <span>{{ $it['label'] }}</span></a>
                @endforeach
            @endforeach
        </nav>
        <div class="sidebar-foot">Sistem Informasi E-Raport<br>{{ $school->jenis_lembaga }}</div>
    </aside>
    <div class="overlay"></div>

    <div class="main">
        <header class="topbar">
            <button class="icon-btn menu-btn" data-nav-toggle aria-label="Menu">@icon('menu')</button>
            <div>
                <div class="page-title">@yield('title', 'Dasbor')</div>
                <div class="ctx">
                    @if($activeYear) Tahun Ajaran {{ $activeYear->nama }} @endif
                    @if($activeSemester) · Semester {{ ucfirst($activeSemester->jenis) }} @endif
                    @if(!$activeYear) Belum ada tahun ajaran aktif @endif
                </div>
            </div>
            <div class="grow"></div>
            <div class="dropdown" data-dropdown>
                <button class="icon-btn" data-dropdown-btn aria-label="Notifikasi">
                    @icon('bell')
                    @if($unreadNotifications->count())<span class="badge-dot">{{ $unreadNotifications->count() }}</span>@endif
                </button>
                <div class="dropdown-menu notif-list">
                    <div class="dropdown-head flex between items-center"><b>Notifikasi</b>
                        @if($unreadNotifications->count())
                        <form method="POST" action="{{ route('notifications.read') }}">@csrf<button class="small" style="width:auto;padding:2px 8px">Tandai dibaca</button></form>
                        @endif
                    </div>
                    @forelse($unreadNotifications as $n)
                        <a class="notif-item unread" href="{{ $n->data['url'] ?? '#' }}">{{ $n->data['pesan'] ?? '' }}<div class="muted small">{{ $n->created_at->diffForHumans() }}</div></a>
                    @empty
                        <div class="empty" style="padding:20px">Tidak ada notifikasi baru</div>
                    @endforelse
                </div>
            </div>
            <div class="dropdown" data-dropdown>
                <button class="userbtn" data-dropdown-btn>
                    <span class="avatar">{{ $user->inisial }}</span>
                    <span class="who"><b>{{ \Illuminate\Support\Str::limit($user->name, 22) }}</b><small>{{ $user->role->nama }}</small></span>
                    @icon('chevron-down')
                </button>
                <div class="dropdown-menu">
                    <div class="dropdown-head"><b>{{ $user->name }}</b><span class="muted small">{{ $user->username }}</span></div>
                    <a href="{{ route('profile.show') }}">@icon('user') Profil Saya</a>
                    @perm('signature.manage')<a href="{{ route('profile.signature') }}">@icon('pen') Tanda Tangan Digital</a>@endperm
                    <a href="{{ route('profile.password') }}">@icon('key') Ganti Kata Sandi</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" data-noload="1">@icon('logout') Keluar</button></form>
                </div>
            </div>
        </header>

        <main class="content">
            @if(session('warning') || ($errors->any() && !isset($hideErrorSummary)))
                <div class="toast-wrap">
                    @if(session('warning'))<div class="toast warning">@icon('alert')<div>{{ session('warning') }}</div><button type="button">@icon('x')</button></div>@endif
                    @if($errors->any() && !isset($hideErrorSummary))
                        <div class="toast danger">@icon('x-circle')<div><b>Data belum dapat diproses:</b><br>{{ $errors->first() }}@if($errors->count() > 1) <span class="small">(+{{ $errors->count() - 1 }} kesalahan lain)</span>@endif</div><button type="button">@icon('x')</button></div>
                    @endif
                </div>
            @endif
            @if(session('success') || session('error') || session('info'))
                <div class="toast-wrap">
                    @if(session('success'))<div class="toast success">@icon('check-circle')<div>{{ session('success') }}</div><button type="button">@icon('x')</button></div>@endif
                    @if(session('error'))<div class="toast danger">@icon('x-circle')<div>{{ session('error') }}</div><button type="button">@icon('x')</button></div>@endif
                    @if(session('info'))<div class="toast">@icon('info')<div>{{ session('info') }}</div><button type="button">@icon('x')</button></div>@endif
                </div>
            @endif

            @yield('content')
        </main>
        <footer class="foot">© {{ date('Y') }} {{ $school->nama_lembaga }} — Sistem Informasi E-Raport</footer>
    </div>
</div>

<div class="modal" id="confirm-modal" role="dialog" aria-modal="true">
    <div class="modal-box">
        <h3 id="confirm-title">Konfirmasi</h3>
        <p id="confirm-text" class="muted" style="margin:0"></p>
        <div class="field mt" id="confirm-prompt-wrap" hidden>
            <label id="confirm-prompt-label">Alasan</label>
            <textarea class="input" id="confirm-prompt" rows="3"></textarea>
            <div class="err" id="confirm-err" hidden>Kolom ini wajib diisi.</div>
        </div>
        <div class="btn-group"><button type="button" class="btn light" id="confirm-cancel">Batal</button><button type="button" class="btn red" id="confirm-ok">Ya, lanjutkan</button></div>
    </div>
</div>

<script src="{{ asset('vendor/chart.umd.min.js') }}"></script>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
@stack('scripts')
</body>
</html>
