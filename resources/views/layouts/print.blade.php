<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Yazdır')</title>

    {{--
        Yazdırma görünümü uygulamanın koyu temasını KULLANMAZ ve Vite CSS'ini
        yüklemez: kağıt beyazdır, mürekkep pahalıdır ve koyu zemin okunmaz.
        Bu yüzden sayfa kendi kendine yeten sade CSS taşır.
    --}}
    <style>
        :root { color-scheme: light; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 16mm 12mm;
            background: #fff;
            color: #111;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.45;
        }

        h1 { margin: 0; font-size: 16pt; font-weight: 600; }
        h2 { margin: 0 0 6px; font-size: 11pt; font-weight: 600; }

        .head { border-bottom: 1.5px solid #111; padding-bottom: 8px; margin-bottom: 14px; }
        .head p { margin: 3px 0 0; color: #444; font-size: 10pt; }

        .summaries { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; }
        .summary { border: 1px solid #999; padding: 8px 10px; min-width: 46mm; }
        .summary h2 { border-bottom: 1px solid #ccc; padding-bottom: 4px; }
        .summary dl { margin: 0; font-size: 10pt; }
        .summary .line { display: flex; justify-content: space-between; gap: 12px; }
        .summary .total { border-top: 1px solid #999; margin-top: 5px; padding-top: 5px; font-weight: 600; }
        .summary .meaning { margin: 3px 0 0; font-size: 9pt; color: #444; font-weight: 400; }

        .carry { border: 1px solid #999; background: #f2f2f2; padding: 6px 10px; margin-bottom: 10px;
                 display: flex; justify-content: space-between; gap: 12px; font-size: 10pt; }

        table { width: 100%; border-collapse: collapse; font-size: 10pt; }
        thead { display: table-header-group; }   /* her sayfada başlık tekrarlansın */
        th, td { padding: 5px 6px; border-bottom: 1px solid #ddd; text-align: left; vertical-align: top; }
        th { border-bottom: 1.5px solid #111; font-weight: 600; white-space: nowrap; }
        tr { page-break-inside: avoid; }
        .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .rev { font-style: italic; }

        /* Konaklama raporu: tarih ve başlık hizalarını net, kompakt ve tutarlı tut. */
        .stays-print-table { table-layout: fixed; font-size: 9.5pt; }
        .stays-print-table col.stay-col-date { width: 10%; }
        .stays-print-table col.stay-col-guest { width: 20%; }
        .stays-print-table col.stay-col-room { width: 10%; }
        .stays-print-table col.stay-col-company { width: 18%; }
        .stays-print-table col.stay-col-amount { width: 11%; }
        .stays-print-table col.stay-col-status { width: 8%; }
        .stays-print-table col.stay-col-transaction { width: 13%; }
        .stays-print-table th,
        .stays-print-table td { padding: 5px 6px; vertical-align: middle; }
        .stays-print-table thead th {
            background: #eef1f4;
            color: #111;
            border-top: 1px solid #9aa1a8;
            border-bottom: 1.5px solid #4f5963;
            font-weight: 700;
        }
        .stays-print-table tbody td { border-bottom-color: #cfd4d9; }
        .stays-print-table .stay-date-cell {
            text-align: left;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }
        .stays-print-table .stay-amount-cell {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }
        .stays-print-table .stay-transaction-time {
            text-align: left;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }
        .stays-print-table .stay-transaction-date,
        .stays-print-table .stay-transaction-clock { display: block; }
        .stays-print-table .stay-transaction-clock { color: #444; font-size: 9pt; }

        /* Yabancı para biriminin altındaki küçük TL karşılığı (v2.1.1-D §3). */
        .fx { display: block; font-size: 8.5pt; color: #666; font-weight: 400; font-variant-numeric: tabular-nums; }
        .fx.incomplete { font-style: italic; }

        .foot { margin-top: 14px; border-top: 1px solid #999; padding-top: 6px;
                font-size: 9pt; color: #444; display: flex; justify-content: space-between; gap: 12px; }

        .toolbar { margin-bottom: 14px; display: flex; gap: 8px; }
        /*
            Araç çubuğu yalnızca EKRANDA vardır (yazdırmada gizlenir) ve parmakla
            kullanılır: dokunma hedefi 44px'in altına düşmemelidir (CLAUDE.md §11.5).
        */
        .toolbar button, .toolbar a {
            font: inherit; font-size: 10pt; padding: 6px 12px; border: 1px solid #666;
            background: #fff; color: #111; cursor: pointer; text-decoration: none; border-radius: 4px;
            min-height: 44px; display: inline-flex; align-items: center;
        }

        /*
            Geniş tablo (ekstre satırları, kasa hareketleri) EKRANDA kendi
            kutusunda kayar; sayfanın kendisi yatay kaymaz. Yazdırmada bu sarmal
            saydamdır — kağıtta kaydırma diye bir şey yoktur.
        */
        .scroll-x { overflow-x: auto; }

        /*
            Gün 10: yazdırma önizlemesi telefonda da açılıyor (devir raporu
            operasyonel bir ekran). A4 için tasarlanan mm cinsinden kenar
            boşluğu dar ekranda tek başına ~90px yiyor ve sayfayı yatay
            kaydırılabilir yapıyordu. Kağıt ölçüleri @media print'te korunur;
            ekranda dar viewport'a uyan boşluk kullanılır.
        */
        @media screen and (max-width: 640px) {
            body { padding: 12px 10px; font-size: 12px; }
            .summaries { gap: 8px; }
            .summary { min-width: 0; flex: 1 1 100%; }
        }

        @media print {
            body { padding: 0; }
            .toolbar { display: none; }
            .scroll-x { overflow-x: visible; }
            @page { size: A4; margin: 14mm 10mm; }
        }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
