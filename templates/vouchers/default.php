<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">

<style>
:root {
    --accent: #0ea5e9;
    --accent-deep: #0369a1;
    --ink: #0f172a;
    --muted: #64748b;
    --line: #e2e8f0;
}

/* 🔧 FIX: biar nurut iframe/container */
html, body {
    width: 100%;
    height: 100%;
    margin: 0;
    box-sizing: border-box;
    overflow: hidden;
}

body {
    padding: 4px;
    display: flex;
    background:
        radial-gradient(circle at 15% 10%, #dbeafe 0%, transparent 38%),
        radial-gradient(circle at 85% 90%, #bae6fd 0%, transparent 32%),
        linear-gradient(145deg, #f8fafc 0%, #eef2f7 100%);
    color: var(--ink);
    font-family: 'Manrope', sans-serif;
}

/* 🔧 FIX: jangan max-width / max-height liar */
.voucher-card {
    width: 100%;
    height: 100%;
    background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    border-radius: 12px;
    border: 1px solid rgba(148, 163, 184, 0.20);
    box-shadow: 0 12px 24px rgba(2, 8, 23, 0.06);
    overflow: hidden;
    position: relative;
    display: flex;
    flex-direction: column;
    padding: 10px;
}

.voucher-card::before {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    background:
        radial-gradient(circle at 100% 0, rgba(14, 165, 233, 0.14) 0%, transparent 38%),
        radial-gradient(circle at 0 100%, rgba(56, 189, 248, 0.10) 0%, transparent 40%);
}

.top-row {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 6px;
    position: relative;
    z-index: 1;
}

.brand {
    display: flex;
    align-items: center;
    gap: 7px;
}

.brand-text strong {
    font-size: 12px;
}

.brand-text span {
    font-size: 9px;
    color: var(--muted);
}

.title {
    font-size: 19px;
    font-weight: 800;
    line-height: 1.05;
}

.subtitle {
    margin-top: 3px;
    font-size: 9px;
    color: var(--muted);
}

/* 🔥 FIX UTAMA: kasih padding khusus container */
.login-info {
    margin-top: 6px;
    padding: 6px; /* ⬅️ ini yang lu minta */
    border-radius: 8px;
    background: rgba(15, 23, 42, 0.05); /* subtle, gak ganggu style */
}

/* tetap style awal */
.input-box {
    display: grid;
    grid-template-columns: 54px 10px 1fr;
    align-items: center;
    column-gap: 5px;
    margin-bottom: 5px;
    padding: 6px 9px;
    border-radius: 8px;
    background: rgba(15, 23, 42, 0.92);
    color: #f8fafc;
    font-size: 10px;
}

.input-label {
    color: #cbd5e1;
    font-weight: 600;
}

.input-value {
    font-family: 'JetBrains Mono', monospace;
    font-weight: 600;
}

.duration {
    padding: 4px 9px;
    border-radius: 999px;
    background: linear-gradient(135deg, var(--accent), var(--accent-deep));
    color: #fff;
    font-size: 8px;
    font-weight: 700;
}

.bottom-row {
    display: flex;
    justify-content: space-between;
    margin-top: 6px;
    padding-top: 5px;
    border-top: 1px solid var(--line);
}

.currency {
    font-size: 14px;
    color: var(--muted);
}

.amount {
    font-size: 26px;
    font-weight: 800;
}

footer {
    font-size: 8px;
    color: var(--muted);
}

@media print {
    body {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* Fallback: tetap terbaca saat background tidak dicetak */
    .input-box {
        background: #ffffff !important;
        color: #111827 !important;
        border: 1px solid #334155;
    }

    .input-label,
    .input-value {
        color: #111827 !important;
    }
}
</style>
</head>

<body>

<div class="voucher-card">
    <div class="top-row">
        <div class="brand">
            <img style="width: 40px; height: 40px;" src="/assets/icons/icon.png">
            <div class="brand-text">
                <strong>ANS Radius</strong>
                <span>Premium Network Access</span>
            </div>
        </div>
        <span class="duration">{{validity}}</span>
    </div>

    <div class="login-info">
        <div class="input-box">
            <span class="input-label">User</span>
            <span>:</span>
            <span class="input-value">{{username}}</span>
        </div>
        <div class="input-box">
            <span class="input-label">Password</span>
            <span>:</span>
            <span class="input-value">{{password}}</span>
        </div>
    </div>

    <div class="bottom-row">
        <span class="currency">Rp</span>
        <span class="amount">{{price}}</span>
    </div>
</div>

</body>
</html>