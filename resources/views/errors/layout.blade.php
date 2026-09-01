{{--
    Base layout template for custom HTTP error response pages.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - {{ config('app.name', 'SMART') }}</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('assets/images/favicon.ico') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

    <style>
        :root {
            --background: #F8FAFC;
            --foreground: #0F172A;
            --card: #FFFFFF;
            --card-foreground: #0F172A;
            --primary: #4F46E5;
            --primary-light: #EEF2FF;
            --secondary: #7C3AED;
            --muted-foreground: #64748B;
            --border: #E2E8F0;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--background);
            color: var(--foreground);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            position: relative;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* Static background decorative blobs matching Login.vue */
        .blobs-container {
            position: fixed;
            inset: 0;
            overflow: hidden;
            pointer-events: none;
            z-index: 0;
        }

        .blob-primary {
            position: absolute;
            border-radius: 9999px;
            filter: blur(64px);
            background-color: #4F46E5;
            pointer-events: none;
        }

        .blob-secondary {
            position: absolute;
            border-radius: 9999px;
            filter: blur(64px);
            background-color: #7C3AED;
            pointer-events: none;
        }

        /* Error Card */
        .card-wrapper {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 28rem;
            margin: auto;
        }

        .card {
            background-color: var(--card);
            border-radius: 0.75rem;
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 25px 50px -12px rgba(79, 70, 229, 0.2);
            padding: 2.25rem 2rem;
            text-align: center;
            overflow: hidden;
        }

        /* Brand Header: Logo + SMART side by side */
        .brand-header {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.625rem;
            margin-bottom: 0.35rem;
        }

        .logo-box {
            width: 1.85rem;
            height: 1.85rem;
            border-radius: 0.375rem;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }

        .logo-box svg,
        .logo-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .text-gradient-primary {
            background: linear-gradient(to right, #4F46E5, #7C3AED);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .brand-heading {
            font-size: 1.75rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -0.02em;
            margin: 0;
        }

        .brand-subheading {
            font-size: 0.875rem;
            color: var(--muted-foreground);
            margin-bottom: 1.5rem;
        }

        /* Enlarged Lucide Icon Container with uniform spacing */
        .icon-circle {
            width: 4.5rem;
            height: 4.5rem;
            border-radius: 9999px;
            background-color: var(--primary-light);
            color: var(--primary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
        }

        .icon-circle svg {
            width: 2.25rem;
            height: 2.25rem;
            stroke-width: 2;
        }

        /* Error Code Badge with uniform spacing */
        .error-badge-container {
            margin-bottom: 1.5rem;
        }

        .error-badge {
            display: inline-block;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--primary);
            background-color: var(--primary-light);
            border: 1px solid rgba(79, 70, 229, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
        }

        .error-title {
            font-size: 1.25rem;
            font-weight: 700;
            line-height: 1.3;
            color: var(--foreground);
            margin-bottom: 0.5rem;
        }

        .error-description {
            font-size: 0.875rem;
            line-height: 1.6;
            color: var(--muted-foreground);
            margin-bottom: 1.75rem;
        }

        /* Button matching Login.vue button style */
        .btn-primary {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            background: linear-gradient(to right, #4F46E5, #7C3AED);
            color: #FFFFFF;
            font-family: inherit;
            font-size: 0.9375rem;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            text-decoration: none;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 14px 0 rgba(79, 70, 229, 0.3);
            transition: opacity 0.2s ease;
        }

        .btn-primary:hover {
            opacity: 0.92;
        }

        .btn-primary svg {
            width: 1.25rem;
            height: 1.25rem;
            flex-shrink: 0;
        }

        /* Footer matching Login.vue footer */
        .footer {
            position: absolute;
            bottom: 1rem;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 0.875rem;
            color: var(--muted-foreground);
            pointer-events: none;
        }
    </style>
</head>
<body>
    <!-- Background decorative elements matching Login.vue -->
    <div class="blobs-container">
        <div class="blob-primary" style="width: 600px; height: 600px; top: -16rem; left: -16rem; opacity: 0.3;"></div>
        <div class="blob-secondary" style="width: 500px; height: 500px; top: 50%; right: -12rem; opacity: 0.25;"></div>
        <div class="blob-primary" style="width: 400px; height: 400px; bottom: -8rem; left: 33%; opacity: 0.2;"></div>
    </div>

    <!-- Main Card -->
    <div class="card-wrapper">
        <div class="card">
            <!-- App Logo + SMART side by side -->
            <div class="brand-header">
                <div class="logo-box">
                    {!! file_get_contents(resource_path('images/logo.svg')) !!}
                </div>
                <h2 class="brand-heading">
                    <span class="text-gradient-primary">SMART</span>
                </h2>
            </div>
            <p class="brand-subheading">
                Stock Management and Request Tracking
            </p>

            <!-- Enlarged Lucide Icon (spacing above: 1.5rem) -->
            <div class="icon-circle">
                @yield('icon')
            </div>

            <!-- Badge (spacing above: 1.5rem) -->
            <div class="error-badge-container">
                <span class="error-badge">@yield('badge', 'Status ' . $__env->yieldContent('code', '500'))</span>
            </div>

            <!-- Content (spacing above: 1.5rem) -->
            <h1 class="error-title">@yield('title')</h1>
            <p class="error-description">@yield('message')</p>

            <!-- Single Back Button -->
            <a href="/" class="btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m12 19-7-7 7-7"/>
                    <path d="M19 12H5"/>
                </svg>
                <span>Kembali</span>
            </a>
        </div>
    </div>

    <!-- Footer matching Login.vue -->
    <div class="footer">
        &copy; {{ date('Y') }} Integrated Facilities Services
    </div>
</body>
</html>
