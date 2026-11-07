{{--
    "PDF indir" düğmesi (7 Kasım 2026). Tüm yazdırma sayfalarında "Yazdır" düğmesinin
    yanına eklenir; sunucu tarafında hiçbir şey yapmaz — sayfa TARAYICIDA PDF'e çevrilir
    (html2pdf.js, ilk tıklamada yüklenir; yazdırma sayfası yüklenirken ağ isteği yoktur).
    Telefonda üretilen PDF paylaşım ekranıyla (WhatsApp vb.) gönderilmeye hazır açılır;
    masaüstünde dosya olarak iner. Düğmenin bulunduğu araç çubuğu PDF'e girmez.
--}}
<script>
(function () {
    'use strict';

    var LIB = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js';
    var printButtons = document.querySelectorAll('button[onclick*="window.print"], [data-offer-print]');
    if (!printButtons.length) { return; }

    var style = document.createElement('style');
    style.textContent = '.pdf-capturing .toolbar,.pdf-capturing .receipt-toolbar,.pdf-capturing .offer-actions{display:none!important}';
    document.head.appendChild(style);

    var readyFile = null;

    function loadLib() {
        if (window.html2pdf) { return Promise.resolve(); }
        return new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = LIB;
            s.onload = resolve;
            s.onerror = function () { reject(new Error('lib')); };
            document.head.appendChild(s);
        });
    }

    function fileName() {
        var base = (document.title || 'rapor').toLowerCase()
            .replace(/ı/g, 'i').replace(/ğ/g, 'g').replace(/ü/g, 'u').replace(/ş/g, 's').replace(/ö/g, 'o').replace(/ç/g, 'c')
            .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'rapor';
        return base + '-' + new Date().toISOString().slice(0, 10) + '.pdf';
    }

    function canShareFile(file) {
        return !!(navigator.canShare && navigator.share && navigator.canShare({ files: [file] }))
            && window.matchMedia('(pointer: coarse)').matches;
    }

    function download(blob, name) {
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url; a.download = name;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(function () { URL.revokeObjectURL(url); }, 4000);
    }

    // Gövdeyi (body) kopyalamak html2pdf'te yerleşimi kaydırıyordu: sabit A4 genişlikli
    // (794px) bir kapsayıcıya içerik kopyalanır; araç çubukları ve betikler çıkarılır.
    function buildSource() {
        var cs = window.getComputedStyle(document.body);
        var wrap = document.createElement('div');
        wrap.style.cssText = 'width:794px;box-sizing:border-box;padding:16mm 12mm;background:white;'
            + 'color:' + cs.color + ';font-family:' + cs.fontFamily + ';line-height:' + cs.lineHeight + ';'
            + 'font-size:' + (window.innerWidth <= 640 ? '11pt' : cs.fontSize) + ';';
        wrap.innerHTML = document.body.innerHTML;
        wrap.querySelectorAll('.toolbar,.receipt-toolbar,.offer-actions,script,[data-pdf-skip]').forEach(function (n) { n.remove(); });
        return wrap;
    }

    function build() {
        window.scrollTo(0, 0);
        document.documentElement.classList.add('pdf-capturing');
        return window.html2pdf().set({
            margin: [8, 6, 8, 6],
            filename: fileName(),
            image: { type: 'jpeg', quality: 0.95 },
            html2canvas: { scale: 2, useCORS: true, windowWidth: Math.max(794, window.innerWidth), scrollX: 0, scrollY: 0, backgroundColor: 'white' },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
            pagebreak: { mode: ['css', 'legacy'], avoid: ['tr', '.summary', '.carry'] }
        }).from(buildSource()).outputPdf('blob').then(function (blob) {
            document.documentElement.classList.remove('pdf-capturing');
            return new File([blob], fileName(), { type: 'application/pdf' });
        }, function (e) {
            document.documentElement.classList.remove('pdf-capturing');
            throw e;
        });
    }

    function share(file, btn, label) {
        return navigator.share({ files: [file], title: file.name }).then(function () {
            readyFile = null; btn.textContent = label;
        }, function (e) {
            if (e && e.name === 'AbortError') { readyFile = null; btn.textContent = label; return; }
            // Paylaşım tarayıcı kuralı yüzünden (ör. iOS dokunma süresi) reddedildi:
            // PDF hazır kalır, kullanıcı bir kez daha dokunarak gönderir.
            readyFile = file;
            btn.textContent = 'PDF hazır — Gönder';
        });
    }

    printButtons.forEach(function (printBtn) {
        var btn = printBtn.cloneNode(false);
        var label = 'PDF indir';
        btn.removeAttribute('onclick');
        btn.removeAttribute('data-offer-print');
        btn.type = 'button';
        btn.textContent = label;
        btn.setAttribute('data-allow-resubmit', '');
        printBtn.insertAdjacentElement('afterend', btn);

        btn.addEventListener('click', function () {
            if (readyFile) { share(readyFile, btn, label); return; }
            btn.disabled = true;
            btn.textContent = 'PDF hazırlanıyor…';
            loadLib().then(build).then(function (file) {
                btn.disabled = false;
                if (canShareFile(file)) {
                    btn.textContent = label;
                    return share(file, btn, label);
                }
                download(file, file.name);
                btn.textContent = label;
            }).catch(function () {
                document.documentElement.classList.remove('pdf-capturing');
                btn.disabled = false;
                btn.textContent = label;
                alert('PDF oluşturulamadı. İnternet bağlantınızı kontrol edip tekrar deneyin veya "Yazdır" ile PDF olarak kaydedin.');
            });
        });
    });
})();
</script>
