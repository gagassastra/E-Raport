<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak Rapor</title>
    <link rel="icon" type="image/png" href="{{ route('school.logo') }}">
    <style>
        @page { size: A4 portrait; margin: 10mm 12mm; }
        body { margin: 0; background: #e9edf3; font-family: Helvetica, Arial, sans-serif; }
        .toolbar { position: sticky; top: 0; z-index: 20; background: #0b2a5b; color: #fff; padding: 10px 16px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .toolbar button, .toolbar a { background: #4a90d9; color: #fff; border: 0; padding: 8px 16px; border-radius: 8px; font: inherit; font-weight: 600; cursor: pointer; text-decoration: none; }
        .toolbar a.light { background: #fff; color: #0b2a5b; }
        .sheet { width: 210mm; max-width: 100%; margin: 0 auto; box-sizing: border-box; }
        .sheet .rp-page { background: #fff; width: 210mm; max-width: 100%; min-height: 297mm; margin: 14px auto; padding: 10mm 12mm; box-shadow: 0 2px 12px rgba(0,0,0,.15); box-sizing: border-box; }
        .pagebreak { page-break-after: always; }
        @media print { body { background: #fff; } .toolbar { display: none; } .sheet, .sheet .rp-page { margin: 0; padding: 0; min-height: 0; box-shadow: none; width: auto; max-width: none; } }
    </style>
</head>
<body @if($autoprint ?? true) data-autoprint="1" @endif>
<div class="toolbar">
    <button onclick="window.print()">Cetak</button>
    <a class="light" href="{{ $backUrl ?? 'javascript:history.back()' }}">Kembali</a>
    <span>{{ count($reports) }} rapor siap dicetak (A4)</span>
</div>
@foreach($reports as $r)
    <div class="sheet {{ ! $loop->last ? 'pagebreak' : '' }}">
        @include('reports._sheet', ['r' => $r])
    </div>
@endforeach
<script>if (document.body.dataset.autoprint) setTimeout(function(){ window.print(); }, 700);</script>
</body>
</html>
