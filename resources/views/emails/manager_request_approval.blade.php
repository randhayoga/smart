{{--
    Email notification template sent to Department Manager or Project Manager for borrowing or supply request approvals.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Persetujuan {{ $type }} - SMART</title>
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
            max-width: 600px;
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
            padding: 15px 32px;
            border-bottom: 1px solid #f1f5f9;
        }
        .app-title {
            font-size: 18px;
            font-weight: 750;
            color: #4f46e5;
            letter-spacing: -0.02em;
            margin: 0;
        }
        .app-subtitle {
            font-size: 12px;
            color: #64748b;
            margin-top: 2px;
        }
        .content {
            padding: 24px 32px;
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
            margin: 0 0 20px 0;
        }
        .info-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 20px;
        }
        .info-table {
            width: 100%;
            font-size: 13px;
        }
        .info-table td {
            padding: 5px 0;
            vertical-align: top;
        }
        .info-label {
            width: 140px;
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
            color: #4f46e5;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin-top: 8px;
        }
        .items-table th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 600;
            text-align: left;
            padding: 8px 10px;
            border-bottom: 1px solid #cbd5e1;
        }
        .items-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            color: #1e293b;
        }
        .btn-container {
            text-align: center;
            margin: 28px 0 16px 0;
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
        .security-note {
            font-size: 12px;
            color: #64748b;
            text-align: center;
            margin-bottom: 16px;
        }
        .fallback-text {
            font-size: 12px;
            color: #94a3b8;
            line-height: 1.5;
            margin-top: 20px;
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

            <!-- Brand Header -->
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

            <!-- Content Area -->
            <div class="content">
                <div class="greeting">
                    Yth. {{ $recipientName }},
                </div>

                <p class="message">
                    Terdapat permohonan <strong>{{ strtolower($type) }}</strong> baru yang diajukan oleh <strong>{{ $requesterName }}</strong> untuk <strong>{{ $destinationName }}</strong> dan saat ini memerlukan persetujuan dari Anda.
                </p>

                <!-- Request Summary Box -->
                <div class="info-box">
                    <table class="info-table">
                        <tr>
                            <td class="info-label">Nomor Pengajuan</td>
                            <td class="info-value info-code">{{ $request->request_number }}</td>
                        </tr>
                        <tr>
                            <td class="info-label">Tipe Pengajuan</td>
                            <td class="info-value">{{ $type }}</td>
                        </tr>
                        <tr>
                            <td class="info-label">Pemohon</td>
                            <td class="info-value">{{ $requesterName }}</td>
                        </tr>
                        <tr>
                            <td class="info-label">Pemanfaatan</td>
                            <td class="info-value">{{ $destinationName }}</td>
                        </tr>
                        @if($isBorrow && $loanPeriod)
                        <tr>
                            <td class="info-label">Periode Pinjam</td>
                            <td class="info-value">{{ $loanPeriod }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td class="info-label">Alasan</td>
                            <td class="info-value" style="font-weight: 500; font-style: italic; color: #334155;">"{{ $request->reasoning }}"</td>
                        </tr>
                    </table>
                </div>

                <!-- Items Table -->
                <div style="margin-bottom: 24px;">
                    <div style="font-size: 13px; font-weight: 700; color: #0f172a; margin-bottom: 8px;">Daftar Barang yang Diajukan:</div>
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th style="width: 30px;">No</th>
                                <th>Nama Barang</th>
                                <th style="width: 130px;">Kategori</th>
                                <th style="width: 70px; text-align: center;">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $index => $item)
                            <tr>
                                <td style="text-align: center; color: #64748b;">{{ $index + 1 }}</td>
                                <td style="font-weight: 600;">
                                    {{ $item['name'] }}
                                    @if(!empty($item['spec']))
                                        <div style="font-size: 11px; font-weight: 400; color: #64748b; margin-top: 2px;">{{ $item['spec'] }}</div>
                                    @endif
                                </td>
                                <td style="color: #475569;">{{ $item['category'] }}</td>
                                <td style="text-align: center; font-weight: 700; color: #4f46e5;">{{ $item['quantity'] }} {{ $item['uom'] ?? '' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Action Button -->
                <div class="btn-container">
                    <a href="{{ $actionUrl }}" class="btn-primary" target="_blank">
                        Tinjau & Berikan Keputusan &#129125;
                    </a>
                </div>
                <div class="security-note">
                    *Tautan ini aman dan berlaku selama 48 jam. Anda dapat menyetujui atau menolak secara langsung tanpa login.
                </div>

                <!-- Fallback / Standard Login URL -->
                <div class="fallback-text">
                    Jika tombol di atas tidak berfungsi atau tautan telah kadaluarsa (lebih dari 48 jam), silakan masuk ke SMART untuk meninjau permohonan:<br>
                    <a href="{{ $loginUrl }}">{{ $loginUrl }}</a>
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
