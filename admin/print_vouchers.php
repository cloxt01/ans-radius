<?php
require_once '../includes/auth.php';

$profileMetaMap = [];
if (function_exists('radiusGetHotspotProfilesCloud')) {
    foreach (radiusGetHotspotProfilesCloud() as $profile) {
        $profileName = (string) ($profile['name'] ?? '');
        if ($profileName === '') {
            continue;
        }

        $metaPrice = isset($profile['selling-price']) && is_numeric($profile['selling-price']) ? (float) $profile['selling-price'] : 0;
        if ($metaPrice <= 0) {
            $metaPrice = isset($profile['price']) && is_numeric($profile['price']) ? (float) $profile['price'] : 0;
        }

        $profileMetaMap[$profileName] = [
            'price' => $metaPrice,
            'validity' => (string) ($profile['session-timeout'] ?? ''),
        ];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Print Voucher</title>
    <style>
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            margin: 0;
        }

        .toolbar {
            padding: 10px;
            text-align: center;
        }

        .toolbar button {
            padding: 10px 20px;
            margin: 0 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-print {
            background: #00c6ff;
            color: white;
        }

        .btn-back {
            background: #6c757d;
            color: white;
        }

        .btn-print:hover {
            background: #00a8cc;
        }

        .btn-back:hover {
            background: #5a6268;
       }

        .print-container {
            width: 200mm;
            margin: auto;
        }

        .voucher-grid {
            display: grid;
            grid-template-columns: repeat(3, 63mm);
            grid-auto-rows: 52mm;
            gap: 4mm;
            justify-content: center;
        }

        .voucher-item {
            width: 63mm;
            height: 52mm;
            border-radius: 3mm;
            overflow: hidden;
        }

        .voucher-frame {
            width: 100%;
            height: 100%;
            border: none;
            border-radius: 3mm;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
            }

            .toolbar {
                display: none;
            }

            @page {
                size: A4;
                margin: 5mm;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="btn-back" onclick="window.location.href='../admin/hotspot-user.php';">
            Kembali
        </button>
        <button class="btn-print" onclick="window.print();">
            Cetak
        </button>
    </div>

    <div class="print-container">
        <div class="voucher-grid" id="voucherGrid">
            <!-- Vouchers will be loaded here -->
        </div>
    </div>

    <script>
        const profileMetaMap = <?php echo json_encode($profileMetaMap); ?>;

        // Get voucher data and template from URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const vouchers = urlParams.get('vouchers') ? JSON.parse(urlParams.get('vouchers')) : [];
        const template = urlParams.get('template') || 'default.php';
        
        const voucherGrid = document.getElementById('voucherGrid');
        
        if (vouchers.length === 0) {
            document.querySelector('.print-container').innerHTML = '<p style="text-align: center; padding: 50px;">Tidak ada voucher untuk dicetak. <a href="../admin/hotspot-user.php">Kembali</a></p>';
        } else {
            // Fetch template content
            fetch('../templates/vouchers/' + template)
                .then(response => response.text())
                .then(templateContent => {
                    vouchers.forEach((voucher, index) => {
                        const slot = document.createElement('div');
                        slot.className = 'voucher-item';

                        const frame = document.createElement('iframe');
                        frame.className = 'voucher-frame';
                        frame.setAttribute("scrolling", "no");

                        let resolvedPrice = voucher.price || '';
                        if (!resolvedPrice || resolvedPrice === '-') {
                            const meta = profileMetaMap[voucher.profile] || null;
                            if (meta && Number(meta.price) > 0) {
                                resolvedPrice = 'Rp ' + Number(meta.price).toLocaleString('id-ID');
                            }
                        }

                        let resolvedValidity = voucher.validity || '';
                        if (!resolvedValidity || resolvedValidity === '-') {
                            const meta = profileMetaMap[voucher.profile] || null;
                            if (meta && meta.validity) {
                                resolvedValidity = meta.validity;
                            }
                        }

                        let html = templateContent
                            .replace(/\{\{username\}\}/g, voucher.username)
                            .replace(/\{\{password\}\}/g, voucher.password)
                            .replace(/\{\{profile\}\}/g, voucher.profile || '')
                            .replace(/\{\{price\}\}/g, resolvedPrice)
                            .replace(/\{\{validity\}\}/g, resolvedValidity)
                            .replace(/\{\{hotspotname\}\}/g, voucher.hotspotname || 'Gembok WiFi')
                            .replace(/\{\{dnsname\}\}/g, voucher.dnsname || 'hotspot.net')
                            .replace(/\{\{num\}\}/g, index + 1)
                            .replace(/\{\{qrcode\}\}/g, '<div style="width:30px;height:30px;background:#000;color:#fff;display:flex;align-items:center;justify-content:center;font-size:6px;border:1px solid #fff;">QR</div>');

                        // Fix sizing in iframe
                        html = html.replace('</head>', `
                        <style>
                        html, body {
                            width: 63mm;
                            height: 52mm;
                            margin: 0;
                            padding: 0;
                            box-sizing: border-box;
                            overflow: hidden;
                        }
                        </style>
                        </head>`);

                        frame.srcdoc = html;
                        slot.appendChild(frame);
                        voucherGrid.appendChild(slot);
                    });
                })
                .catch(error => {
                    console.error('Failed to load template:', error);
                    document.querySelector('.print-container').innerHTML = '<p style="text-align: center; padding: 50px;">Gagal memuat template. <a href="../admin/hotspot-user.php">Kembali</a></p>';
                });
        }
    </script>
</body>
</html>
