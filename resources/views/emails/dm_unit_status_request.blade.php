<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Persetujuan Status Aset - SMART</title>
    <!--[if mso]>
    <style type="text/css">
        body, table, td, a { font-family: Arial, Helvetica, sans-serif !important; }
    </style>
    <![endif]-->
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
            color: #0f172a;
            font-family: Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            -webkit-text-size-adjust: 100%;
        }
        table {
            border-spacing: 0;
            border-collapse: collapse;
        }
        td {
            padding: 0;
        }
        img {
            border: 0;
        }
        .wrapper {
            width: 100%;
            table-layout: fixed;
            background-color: #f8fafc;
            padding: 40px 16px;
        }
        .main-card {
            width: 100%;
            max-width: 580px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 4px 20px -2px rgba(79, 70, 229, 0.08);
        }
        .gradient-line {
            height: 4px;
            background: #4f46e5;
            background: linear-gradient(to right, #4f46e5, #7c3aed);
        }
        .header {
            padding: 15px 32px 15px 32px;
            border-bottom: 1px solid #f1f5f9;
        }
        .app-title {
            font-size: 18px;
            font-weight: 750;
            color: #4f46e5;
            letter-spacing: -0.02em;
            margin: 0;
        }
        .app-title span {
            color: #4f46e5;
        }
        .app-subtitle {
            font-size: 12px;
            color: #64748b;
            margin-top: 2px;
        }
        .content {
            padding: 20px 32px;
        }
        .greeting {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 10px 0;
        }
        .message {
            font-size: 14px;
            line-height: 1.6;
            color: #475569;
            margin: 0 0 24px 0;
        }
        .info-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 28px;
        }
        .info-table {
            width: 100%;
            font-size: 13px;
        }
        .info-table td {
            padding: 6px 0;
            vertical-align: top;
        }
        .info-label {
            width: 130px;
            color: #64748b;
            font-weight: 500;
        }
        .info-value {
            color: #0f172a;
            font-weight: 600;
        }
        .info-code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-weight: 700;
            color: #0f172a;
        }
        .info-highlight {
            color: #dc2626;
            font-weight: 700;
        }
        .btn-container {
            text-align: center;
        }
        .btn-primary {
            display: inline-block;
            background: #4f46e5;
            background: linear-gradient(to right, #4f46e5, #7c3aed);
            color: #ffffff !important;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 8px;
            box-shadow: 0 4px 14px 0 rgba(79, 70, 229, 0.3);
        }
        .fallback-text {
            font-size: 12px;
            color: #94a3b8;
            line-height: 1.5;
            margin-top: 24px;
            border-top: 1px solid #f1f5f9;
            padding-top: 16px;
            word-break: break-all;
        }
        .fallback-text a {
            color: #4f46e5;
            text-decoration: underline;
        }
        .footer {
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 18px 32px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="main-card">
            <!-- Brand Gradient Accent Bar -->
            <div class="gradient-line"></div>

            <!-- Clean Brand Header -->
            <div class="header">
                <table style="border-collapse: collapse; border-spacing: 0;">
                    <tr>
                        <td style="vertical-align: middle; padding-right: 12px;">
                            <img src="data:image/png;base64,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" width="38" height="38" alt="SMART Logo" style="display: block; width: 38px; height: 38px;">
                        </td>
                        <td style="vertical-align: middle;">
                            <h1 class="app-title">SMART</h1>
                            <div class="app-subtitle">Sistem Manajemen Aset & Request Tracking</div>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Main Content -->
            <div class="content">
                <div class="greeting">
                    Yth. {{ $recipientName ?? 'Department Manager IFS' }},
                </div>

                <p class="message">
                    Permohonan penonaktifan aset di bawah ini telah disetujui oleh <strong>BoD/BoC</strong> dan dokumen approval telah diunggah. Saat ini memerlukan <strong>persetujuan akhir</strong> dari Anda.
                </p>

                <!-- Asset Info Table -->
                <div class="info-box">
                    <table class="info-table">
                        <tr>
                            <td class="info-label">Nomor Aset</td>
                            <td class="info-value info-code" style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;">{{ $unit->number }}</td>
                        </tr>
                        <tr>
                            <td class="info-label">Nama Barang</td>
                            <td class="info-value">{{ $brandAndName }}</td>
                        </tr>
                        @if($unit->serial_number)
                        <tr>
                            <td class="info-label">Serial Number</td>
                            <td class="info-value info-code" style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;">{{ $unit->serial_number }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td class="info-label">Lokasi Aset</td>
                            <td class="info-value">{{ $locationText }}</td>
                        </tr>
                        <tr>
                            <td class="info-label">Kondisi Awal</td>
                            <td class="info-value">{{ $approval->previous_condition ?? $unit->condition }}</td>
                        </tr>
                        <tr>
                            <td class="info-label">Kondisi Diajukan</td>
                            <td class="info-value info-highlight">{{ $approval->proposed_condition ?? '-' }} (Nonaktif)</td>
                        </tr>
                        @if($approval && $approval->requester)
                        <tr>
                            <td class="info-label">Diajukan Oleh</td>
                            <td class="info-value">{{ $approval->requester->name }}</td>
                        </tr>
                        @endif
                        @if($approval && $approval->note)
                        <tr>
                            <td class="info-label">Catatan</td>
                            <td class="info-value" style="font-weight: 500; font-style: italic; color: #475569;">"{{ $approval->note }}"</td>
                        </tr>
                        @endif
                    </table>
                </div>

                <!-- CTA Button -->
                <div class="btn-container">
                    <a href="{{ $actionUrl }}" class="btn-primary" target="_blank">
                        Tinjau & Berikan Keputusan &#129125;
                    </a>
                </div>

                <!-- Fallback URL -->
                <div class="fallback-text">
                    Jika tombol di atas tidak berfungsi, buka tautan berikut di browser Anda:<br>
                    <a href="{{ $actionUrl }}">{{ $actionUrl }}</a>
                </div>
            </div>

            <!-- Footer -->
            <div class="footer">
                Email otomatis dari <strong>SMART</strong> &bull; Tidak perlu membalas email ini.
            </div>
        </div>
    </div>
</body>
</html>