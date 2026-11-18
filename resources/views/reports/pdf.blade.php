<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rapor Peserta Didik</title>
    <style>
        @page { size: A4 portrait; margin: 10mm 12mm; }
        body { margin: 0; font-family: Helvetica, Arial, sans-serif; }
        .pagebreak { page-break-after: always; }
    </style>
</head>
<body>
@foreach($reports as $i => $r)
    <div class="{{ ! $loop->last ? 'pagebreak' : '' }}">
        @include('reports._sheet', ['r' => $r])
    </div>
@endforeach
</body>
</html>
