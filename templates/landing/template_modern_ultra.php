<?php
/**
 * Template 8: Modern Ultra Theme
 * Ultra-modern design with 3D effects, particles, and smooth animations
 */

$defaultPackageServices = [
];

$defaultPackageServiceTypes = [
    'router_2' => 'router',
    'member_2' => 'pppoe',
    'voucher_5000' => 'voucher',
    'online_250' => 'online',
    'vpn_radius' => 'vpn',
    'vpn_remote' => 'vpn',
    'wa_notif' => 'general',
    'payment_gateway' => 'general',
    'client_area' => 'general',
    'custom_domain' => 'general',
    'annual_12m' => 'general'
];

$packageFeatureList = $defaultPackageServices;
$packageFeatureTypes = $defaultPackageServiceTypes;
try {
    if (function_exists('tableExists') && tableExists('available_services')) {
        try {
            $serviceRows = fetchAll("SELECT service_key, service_name, service_type FROM available_services WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
        } catch (Exception $e) {
            $serviceRows = fetchAll("SELECT service_key, service_name FROM available_services WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
        }
        if (!empty($serviceRows)) {
            $packageFeatureList = [];
            $packageFeatureTypes = [];
            foreach ($serviceRows as $row) {
                $key = (string) ($row['service_key'] ?? '');
                $name = (string) ($row['service_name'] ?? '');
                $type = strtolower(trim((string) ($row['service_type'] ?? '')));
                if ($key !== '' && $name !== '') {
                    $packageFeatureList[$key] = $name;
                    $packageFeatureTypes[$key] = $type !== '' ? $type : (explode('_', $key)[0] ?? 'general');
                }
            }
        }
    }
} catch (Exception $e) {
    $packageFeatureList = $defaultPackageServices;
    $packageFeatureTypes = $defaultPackageServiceTypes;
}

if (!function_exists('modernUltraPackageServices')) {
    function modernUltraPackageServices($pkg)
    {
        $services = [];
        $raw = (string) ($pkg['package_services'] ?? '');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $services = array_values(array_filter(array_map('strval', $decoded)));
            }
        }
        return $services;
    }
}

if (!function_exists('modernUltraServiceActive')) {
    function modernUltraServiceActive($pkg, $serviceKey)
    {
        return in_array($serviceKey, modernUltraPackageServices($pkg), true);
    }
}

if (!function_exists('modernUltraNormalizeType')) {
    function modernUltraNormalizeType($rawType)
    {
        $type = strtolower(trim((string) $rawType));
        $type = preg_replace('/[^a-z0-9_]+/', '_', $type);
        $type = trim((string) $type, '_');
        return $type !== '' ? $type : 'general';
    }
}

if (!function_exists('modernUltraResolveServiceType')) {
    function modernUltraResolveServiceType($serviceKey, $serviceTypeMap)
    {
        if (isset($serviceTypeMap[$serviceKey])) {
            return modernUltraNormalizeType($serviceTypeMap[$serviceKey]);
        }
        $parts = explode('_', (string) $serviceKey);
        return modernUltraNormalizeType($parts[0] ?? 'general');
    }
}

if (!function_exists('modernUltraServiceWeight')) {
    function modernUltraServiceWeight($serviceKey, $serviceName)
    {
        $source = (string) $serviceName . ' ' . (string) $serviceKey;
        if (preg_match('/\d[\d\.,]*/', $source, $m)) {
            $num = preg_replace('/[^0-9]/', '', (string) $m[0]);
            return $num !== '' ? (int) $num : 0;
        }
        return 0;
    }
}

if (!function_exists('modernUltraBuildVisibleServiceMap')) {
    function modernUltraBuildVisibleServiceMap($pkg, $featureList, $featureTypes)
    {
        // Options: max|min. Default max (paket lebih tinggi menampilkan value terbesar per kategori).
        $pickMode = 'max';
        $groups = [];

        foreach ($featureList as $serviceKey => $serviceName) {
            $serviceType = modernUltraResolveServiceType($serviceKey, $featureTypes);
            $isIncluded = modernUltraServiceActive($pkg, $serviceKey);

            $groups[$serviceType][] = [
                'key' => (string) $serviceKey,
                'name' => (string) $serviceName,
                'weight' => modernUltraServiceWeight($serviceKey, $serviceName),
                'included' => $isIncluded
            ];
        }

        $visible = [];
        foreach ($groups as $category => $items) {
            $pool = array_values(array_filter($items, function ($item) {
                return !empty($item['included']);
            }));
            if (empty($pool)) {
                $pool = $items;
            }

            usort($pool, function ($a, $b) use ($pickMode) {
                $aw = (int) ($a['weight'] ?? 0);
                $bw = (int) ($b['weight'] ?? 0);
                if ($aw === $bw) {
                    return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
                }
                return $pickMode === 'min' ? ($aw <=> $bw) : ($bw <=> $aw);
            });

            $chosen = $pool[0] ?? null;
            if (!empty($chosen['key'])) {
                $visible[(string) $chosen['key']] = true;
            }
        }

        return $visible;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $appName; ?> - Internet Service Provider</title>
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#0f172a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?php echo $appName; ?>">
    <link rel="manifest" href="<?php echo APP_URL; ?>/manifest.json">
    <link rel="apple-touch-icon" href="<?php echo APP_URL; ?>/assets/icons/icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo APP_URL; ?>/assets/icons/icon.png">
    <link rel="preload" as="image" href="<?php echo APP_URL; ?>/assets/icons/icon.webp" fetchpriority="high">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0ea5e9;
            --primary-light: #38bdf8;
            --secondary: #0369a1;
            --accent: #f59e0b;
            --dark: #0f172a;
            --light: #ffffff;
            --text-color: #111827;
            --card-text: #334155;
            --card-muted: #94a3b8;
            --gray: #64748b;
            --surface: #f8fbff;
            --gradient: linear-gradient(135deg, #0ea5e9 0%, #38bdf8 100%);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Arial, sans-serif;
            background: var(--surface);
            color: var(--text-color);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Animated Gradient Background */
        .bg-gradient {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 10% 5%, rgba(14, 165, 233, 0.12) 0, rgba(14, 165, 233, 0) 48%),
                        radial-gradient(circle at 90% 22%, rgba(56, 189, 248, 0.1) 0, rgba(56, 189, 248, 0) 44%),
                        linear-gradient(180deg, #f9fcff 0%, #f3f9ff 100%);
            z-index: -2;
        }

        /* Floating Particles */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            width: 30px;
            height: 30px;
            background: var(--primary);
            border-radius: 50%;
            opacity: 0.18;
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            25% { transform: translateY(-100px) rotate(90deg); }
            50% { transform: translateY(0) rotate(180deg); }
            75% { transform: translateY(100px) rotate(270deg); }
        }

        /* Glassmorphism Cards */
        .glass {
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 10px;
            box-shadow: 0 14px 28px rgba(15, 23, 42, 0.06);
        }

        /* 3D Card Effect */
        .card-3d {
            transform-style: preserve-3d;
            perspective: 1000px;
        }

        .card-3d-inner {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(236, 72, 153, 0.1));
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            transition: transform 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .card-3d:hover .card-3d-inner {
            transform: rotateY(5deg) rotateX(5deg) translateZ(20px);
            box-shadow: 0 20px 40px rgba(139, 92, 246, 0.3);
        }

        /* Navbar */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
            padding: 20px 5%;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            backdrop-filter: blur(14px);
            background: rgba(255, 255, 255, 0.92);
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
        }

        .logo { 
            font-size: 1.5rem; 
            font-weight: 700; 
            background: var(--primary);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-links { display: flex; gap: 30px; }
        .nav-links a { 
            color: var(--dark);
            text-decoration: none; 
            font-weight: 500;
            transition: 0.3s;
            position: relative;
        }
        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary);
            transition: 0.3s;
        }
        .nav-links a:hover::after { width: 100%; }

        .nav-toggle {
            display: none;
            width: 44px;
            height: 44px;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: var(--dark);
            border-radius: 12px;
            cursor: pointer;
            font-size: 18px;
            backdrop-filter: blur(20px);
        }

        /* Dropdown Menu */
        .dropdown { position: relative; display: inline-block; }
        .dropdown-content {
            display: none;
            position: absolute;
            background: rgba(255, 255, 255, 0.98);
            min-width: 220px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12);
            border-radius: 14px;
            border: 1px solid rgba(148, 163, 184, 0.25);
            top: 100%;
            right: 0;
            overflow: hidden;
            margin-top: 10px;
        }
        .dropdown-content a {
            color: var(--dark);
            padding: 14px 18px;
            background: transparent;
            text-decoration: none;
            display: block;
            text-align: left;
            transition: 0.3s;
            font-size: 0.9rem;
        }
        .dropdown-content a:hover {
            color: var(--primary);
        }
        .dropdown:hover .dropdown-content { display: block; }

        .login-btn {
            background: var(--primary);
            padding: 12px 28px;
            border-radius: 50px;
            color: #fff !important;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: 0 4px 15px rgba(61, 168, 255, 0.3);
        }
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(61, 168, 255, 0.4);
        }


        /* Hero Section */
        .hero {
            min-height: 92vh;
            display: flex;
            align-items: center;
            justify-content: space-evenly;
            padding: 130px 5% 70px;
            text-align: left;
            position: relative;
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }
        .hero-image {

            position: relative;
            z-index: 1;
            max-width: 42%;
        }
        .hero-image img {
            width: 100%;
            height: auto;
            max-width: 500px;
            display: block;
        }
        .hero-image img:hover  {
            cursor: pointer;
            transform: scale(1.15);
            transition: 0.4s;
        }

        .hero h1 { 
            font-size: 4rem; 
            font-weight: 900; 
            line-height: 1.1; 
            margin-bottom: 22px;
            background: var(--primary);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -2px;
        }
        .hero p { 
            font-size: 1.05rem; 
            color: #334155; 
            margin-bottom: 36px; 
            line-height: 1.8;
            max-width: 760px;
            margin-right: auto;
        }
        
        /* Hero fade-up animation */
        .hero-element.fade-up {
            opacity: 0;
            transform: translateY(24px) scale(0.985);
            filter: blur(2px);
            animation: fadeUpHero 1.35s ease forwards;
            will-change: opacity, transform, filter;
        }
        .get-started span:hover {
            transition: 0.5s;
            transform: translateX(5px);
        }
        
        
        @keyframes fadeUpHero {
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
                filter: blur(0);
            }
        }
        

        .cta-buttons { display: flex; gap: 20px; justify-content: left; }
        .btn { 
            padding: 16px 36px; 
            border-radius: 5px; 
            text-decoration: none; 
            font-weight: 600;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .btn-primary { 
            background: linear-gradient(135deg, #0ea5e9, #0284c7); 
            color: #fff;
            box-shadow: 0 10px 24px rgba(14, 165, 233, 0.3);
        }
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 34px rgba(14, 165, 233, 0.38);
        }
        .btn-outline { 
            border: 2px solid var(--primary);
            color: var(--dark);
        }
        .btn-outline:hover {
            background: var(--primary);
            color: #fff;
        }
        .package-cta {
            margin-top: 8px;
        }

        /* Features Section */
        .features { padding: 90px 5%; }

        .support-container {
            width: 100%;
            padding: 40px; 
            border-radius: 18px; 
        }
        .support-title {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--dark);
        }

        .section-title { 
            font-size: 2.6rem; 
            margin-bottom: 50px; 
            text-align: center;
            color: var(--dark);
        }

        .feature-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); 
            gap: 30px;
        }
        .feature-card {
            text-align: center;
            padding: 34px;
            border: 1px solid rgba(148, 163, 184, 0.2);
            box-shadow: 0 10px 26px rgba(15, 23, 42, 0.06);
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), border-color 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            background: #fff;
            opacity: 0;
            transform: translateY(24px);
        }
        .feature-card.fade-up.visible {
            animation: featureCardFadeUp 1.35s ease-out forwards;
        }
        @keyframes featureCardFadeUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .feature-card:hover {
            transform: translateY(-8px);
            cursor: pointer;
            border-color: rgba(14, 165, 233, 0.28);
        }
        .feature-icon { 
            font-size: 3rem; 
            margin-bottom: 20px;
            background: var(--primary);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .feature-icon-svg {
            width: 300px;
            height: auto;
            margin: 0 auto 18px;
            display: block;
        }
        .feature-card:hover .feature-icon-svg {
            filter: drop-shadow(0 4px 8px rgba(14, 165, 233, 0.3));
        }
        .feature-card h3 { color: var(--dark); font-size: 1.4rem; margin-bottom: 15px; }
        .feature-card p { color: var(--gray); line-height: 1.6; }

        /* Packages Section */
        .packages { padding: 90px 5%; text-align: center; }
        
        .package-grid { 
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); 
            gap: 30px; 
            margin-top: 60px; 
        }
        .package-icon {
            width: 150px;
            height: 150px;
            margin-bottom: 20px;
            filter: drop-shadow(0 2px 4px rgba(14, 165, 233, 0.3));
        }
        .package-card {
            padding: 80px 125px;
            background: #ffffff;
            border: 1px solid rgba(148, 163, 184, 0.22);
            box-shadow: 0 14px 32px rgba(15, 23, 42, 0.08);
            transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }
        .package-services-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 18px;
            width: 100%;
            max-width: 420px;
            margin: 0 auto;
        }
        .package-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #0ea5e9, #0284c7);
        }
        .package-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 22px 46px rgba(15, 23, 42, 0.12);
            border-color: rgba(14, 165, 233, 0.32);
        }
        .package-card h3 { color: var(--dark); font-size: 1.5rem; margin-bottom: 20px; }
        .package-desc {
            color: var(--card-text);
            font-size: 0.97rem;
            line-height: 1.7;
            margin-top: 4px;
        }
        .package-services {
            list-style: none;
            padding: 0;
            margin: 24px 0 34px;
            text-align: center;
            display: grid;
            gap: 10px;
            justify-items: center;
            width: 100%;
        }
        .package-service-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 2px 0;
            color: var(--card-text);
            line-height: 1.45;
            justify-content: center;
            width: 100%;
        }
        .package-service-item i {
            margin-top: 3px;
            flex-shrink: 0;
            font-size: 0.92rem;
        }
        .package-service-item span {
            font-size: 0.96rem;
            font-weight: 500;
            text-align: center;
        }
        .package-service-item.is-included i { color: #22c55e; }
        .package-service-item.is-included span { text-decoration: none; }
        .package-service-item.is-missing {
            opacity: 0.62;
        }
        .package-service-item.is-missing i { color: rgba(100, 116, 139, 0.75); }
        .package-service-item.is-missing span {
            text-decoration: line-through;
            text-decoration-thickness: 1.5px;
            text-decoration-color: rgba(100, 116, 139, 0.65);
        }
        .package-price { 
            font-family: 'Inter', sans-serif;
            font-size: 3rem; 
            font-weight: 800;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            gap: 2px;
            line-height: 1;
            margin: 20px 0;
            flex-wrap: nowrap;
            white-space: nowrap;
            text-align: center;
        }
        .package-price-main {
            background: var(--primary);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1;
            white-space: nowrap;
        }
        .package-price-period {
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 400;
            letter-spacing: 0.02em;
            -webkit-text-fill-color: currentColor;
        }

        /* FAQ Section */
        .faq { padding: 90px 5%; text-align: center; }
        .faq-container {
            margin-top: 60px;
            width: 100%;
        }
        .faq-container .row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 24px;
            align-items: start;
        }
        .faq-container .col-lg-6 {
            width: 100%;
        }
        .accordion {
            display: grid;
            gap: 14px;
        }
        .accordion-item.faq-item {
            text-align: left;
            padding: 18px 20px;
            background: #ffffff;
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 12px;
            box-shadow: 0 10px 26px rgba(15, 23, 42, 0.06);
            transition: 0.35s ease;
        }
        .accordion-item.faq-item:hover {
            transform: translateY(-4px);
            border-color: rgba(14, 165, 233, 0.28);
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.1);
        }
        .accordion-button.faq-question {
            width: 100%;
            border: 0;
            background: transparent;
            padding: 0;
            text-align: left;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            gap: 12px;
        }
        .faq-title {
            color: var(--dark);
            font-size: 1.05rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            line-height: 1.45;
        }
        .faq-title::before {
            content: 'Q';
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            color: #fff;
            border-radius: 50%;
            font-weight: 800;
            font-size: 0.9rem;
            flex-shrink: 0;
        }
        .faq-chevron {
            color: var(--primary);
            font-size: 0.95rem;
            transition: transform 0.28s ease;
            flex-shrink: 0;
        }
        .faq-item.open .faq-chevron {
            transform: rotate(180deg);
        }
        .accordion-collapse.faq-answer {
            max-height: 0;
            overflow: hidden;
            opacity: 0;
            transition: max-height 0.35s ease, opacity 0.25s ease;
        }
        .faq-item.open .faq-answer {
            max-height: 420px;
            opacity: 1;
            margin-top: 12px;
        }
        .accordion-body {
            color: #334155;
            font-size: 0.95rem;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        /* Contact Section */
        .contact { padding: 90px 5%; text-align: center; }
        .contact-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
            gap: 30px; 
            margin-top: 60px; 
        }

        .contact-item {border: 1px solid rgba(148, 163, 184, 0.2); padding: 34px; box-shadow: 0 10px 26px rgba(15, 23, 42, 0.06); transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); background: #fff;}
        .contact-item i { 
            font-size: 2.5rem; 
            margin-bottom: 15px;
            background: var(--primary);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .contact-item h3 { color: var(--dark); margin-bottom: 10px; }
        .contact-item p { color: var(--gray); }

        /* Footer */
        .footer { 
            padding: 40px 5%; 
            text-align: center;
            border-top: 1px solid rgba(148, 163, 184, 0.25);
        }
       
        .social-links { 
            display: flex; 
            justify-content: center; 
            gap: 20px; 
            margin-bottom: 20px; 
        }
        .social-links a { 
            color: var(--dark); 
            font-size: 1.5rem; 
            transition: 0.3s;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #fff;
            border: 1px solid rgba(148, 163, 184, 0.24);
        }
        .social-links a:hover { 
            background: var(--primary);
            transform: translateY(-5px);
        }

        /* Scroll Animations */
        .fade-up {
            opacity: 0;
            transform: translateY(40px) scale(0.96);
            
            transition:
                opacity 0.9s cubic-bezier(0.16, 1, 0.3, 1),
                transform 1.2s cubic-bezier(0.16, 1, 0.3, 1),
                filter 1.2s ease;

            will-change: transform, opacity, filter;
        }

        .fade-up.visible {
            opacity: 1;
            transform: translateY(0) scale(1);
            filter: blur(0);
        }

        /* Keep above-the-fold hero content visible immediately for faster LCP */
        .hero .fade-up,
        .hero-element.fade-up {
            opacity: 1;
            transform: none;
            filter: none;
            transition: none !important;
            animation: fadeUpHero 1.35s ease forwards !important;
        }

        /* Staggered Animation */
        .stagger-1 { transition-delay: 0.2s; animation-delay: 0.2s; }
        .stagger-2 { transition-delay: 0.36s; animation-delay: 0.36s; }
        .stagger-3 { transition-delay: 0.52s; animation-delay: 0.52s; }
        .stagger-4 { transition-delay: 0.68s; animation-delay: 0.68s; }
        .stagger-5 { transition-delay: 0.84s; animation-delay: 0.84s; }
        .stagger-6 { transition-delay: 1.0s; animation-delay: 1.0s; }

        .package-grid .package-card:nth-child(1) { transition-delay: 0.2s; }
        .package-grid .package-card:nth-child(2) { transition-delay: 0.34s; }
        .package-grid .package-card:nth-child(3) { transition-delay: 0.48s; }
        .package-grid .package-card:nth-child(4) { transition-delay: 0.62s; }
        .package-grid .package-card:nth-child(5) { transition-delay: 0.76s; }
        .package-grid .package-card:nth-child(6) { transition-delay: 0.9s; }

        .faq-container .faq-item {
            opacity: 0;
            transform: translateY(24px);
            transition: opacity 1.35s ease, transform 1.35s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .faq-container.visible .faq-item {
            opacity: 1;
            transform: translateY(0);
        }
        .faq-container.visible .faq-item:nth-child(1) { transition-delay: 0.2s; }
        .faq-container.visible .faq-item:nth-child(2) { transition-delay: 0.34s; }
        .faq-container.visible .faq-item:nth-child(3) { transition-delay: 0.48s; }
        .faq-container.visible .faq-item:nth-child(4) { transition-delay: 0.62s; }
        .faq-container.visible .faq-item:nth-child(5) { transition-delay: 0.76s; }
        .faq-container.visible .faq-item:nth-child(6) { transition-delay: 0.9s; }

        .package-card .package-service-item {
            opacity: 0;
            transform: translateY(8px);
            transition: opacity 0.9s ease, transform 0.9s ease;
        }
        .package-card.visible .package-service-item {
            opacity: 1;
            transform: translateY(0);
        }
        .package-card.visible .package-service-item:nth-child(1) { transition-delay: 0.2s; }
        .package-card.visible .package-service-item:nth-child(2) { transition-delay: 0.28s; }
        .package-card.visible .package-service-item:nth-child(3) { transition-delay: 0.36s; }
        .package-card.visible .package-service-item:nth-child(4) { transition-delay: 0.44s; }
        .package-card.visible .package-service-item:nth-child(5) { transition-delay: 0.52s; }
        .package-card.visible .package-service-item:nth-child(6) { transition-delay: 0.6s; }

        @media (prefers-reduced-motion: reduce) {
            .fade-up,
            .package-card .package-service-item,
            .particle {
                animation: none !important;
                transition: none !important;
                transform: none !important;
                filter: none !important;
                opacity: 1 !important;
            }
        }

        @media (max-width: 768px) {
            .hero {
                flex-direction: column;
                text-align: center;
                gap: 36px;
                padding: 110px 18px 56px;
            }
            .hero-content,
            .hero-image {
                max-width: 100%;
            }
            .hero-image {
                order: -1;
            }
            .hero h1 { font-size: 2.45rem; }
            .hero p {
                max-width: 100%;
                margin-left: auto;
                margin-right: auto;
                font-size: 0.98rem;
            }
            .navbar { padding: 15px 20px; }
            .nav-toggle { display: inline-flex; }
            .nav-links {
                display: none;
                position: absolute;
                left: 0;
                right: 0;
                top: 100%;
                padding: 14px 20px 18px;
                background: rgba(253, 253, 253, 0.95);
                border-bottom: 1px solid rgba(255, 255, 255, 0.08);
                flex-direction: column;
                gap: 14px;
                align-items: stretch;
                backdrop-filter: blur(20px);
            }
            .nav-links.open { display: flex; }
            .dropdown:hover .dropdown-content { display: none; }
            .dropdown.open .dropdown-content {
                display: block;
                position: static;
                margin-top: 10px;
                width: 100%;
            }
            .nav-links a { width: 100%; }
            .nav-links .dropdown { width: 100%; }
            .nav-links .dropdown-content { width: 100%; box-sizing: border-box; }
            .login-btn { width: 100%; text-align: center; display: inline-flex; justify-content: center; }
            .hero-buttons {
                flex-direction: column;
            }
            .cta-buttons {
                justify-content: center;
                flex-wrap: wrap;
            }
            .section-title {
                font-size: 2rem;
                margin-bottom: 32px;
            }
            .feature-grid,
            .contact-grid,
            .package-grid {
                grid-template-columns: 1fr;
            }
            .feature-card,
            .contact-item,
            .package-card,
            .faq-item {
                padding: 24px 18px;
            }
            .feature-icon-svg {
                width: 170px;
            }
            .package-icon {
                width: 110px;
                height: 110px;
            }
            .package-price {
                font-size: 2.1rem;
            }
            .package-services-wrapper {
                max-width: 100%;
            }
            .package-services {
                width: 100%;
            }
            .package-card {
                padding: 24px 18px;
            }
            .package-card h3 {
                font-size: 1.25rem;
            }
            .package-service-item span {
                font-size: 0.92rem;
            }
            .package-services {
                max-width: 100%;
            }
            .faq-container .row {
                grid-template-columns: 1fr;
            }
            .faq-title {
                font-size: 1rem;
            }
            .accordion-body {
                font-size: 0.9rem;
            }
        }

        @media (max-width: 480px) {
            .hero {
                padding-top: 96px;
            }
            .hero h1 {
                font-size: 2rem;
            }
            .btn {
                width: 100%;
                justify-content: center;
            }
            .cta-buttons {
                width: 100%;
            }
            .package-price {
                font-size: 1.8rem;
                gap: 6px;
                flex-wrap: nowrap;
                white-space: nowrap;
            }
            .package-price-period {
                font-size: 0.82rem;
            }
            .package-service-item {
                gap: 8px;
            }
            .package-service-item i {
                margin-top: 2px;
            }
            .faq-item {
                padding: 16px;
            }
            .faq-title {
                font-size: 0.95rem;
            }
            .accordion-body {
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <div class="bg-gradient"></div>
    <div class="particles" id="particles"></div>

    <nav class="navbar">
        <img src="<?php echo APP_URL; ?>/assets/icons/icon.webp" class="sidebar-brand-logo" style="width: 58px; height: 58px;" alt="Logo" width="58" height="58" fetchpriority="high" decoding="async">
        <button class="nav-toggle" type="button" onclick="window.__gembokToggleNav && window.__gembokToggleNav()"><i class="fas fa-bars"></i></button>
        <div class="nav-links">
            <a href="#features">Fitur</a>
            <a href="#packages">Paket</a>
            <a href="voucher-order.php">Voucher</a>
            <a href="#" onclick="window.__gembokOpenRegisterModal && window.__gembokOpenRegisterModal(); return false;">Daftar</a>
            <a href="#contact">Kontak</a>
            <div class="dropdown">
                <a href="#" class="login-btn" onclick="window.__gembokToggleLogin && window.__gembokToggleLogin(event)">Login <i class="fas fa-chevron-down"></i></a>
                <div class="dropdown-content">
                    <a href="portal/login.php"><i class="fas fa-user"></i> Pelanggan</a>
                    <a href="sales/login.php"><i class="fas fa-user-tie"></i> Sales / Agen</a>
                    <a href="technician/login.php"><i class="fas fa-tools"></i> Teknisi</a>
                </div>
            </div>
        </div>
    </nav>

    <section class="hero">
        <div class="hero-content">
            <h1 class="fade-up hero-element" style="animation-delay: 0s;">
                <?php echo strip_tags($heroTitle); ?>
            </h1>
            <p class="fade-up hero-element" style="animation-delay: 0.1s;">
                <?php echo $heroDesc; ?>
            </p>
            <div class="cta-buttons fade-up hero-element" style="animation-delay: 0.2s;">

                <a href="#packages" class="btn btn-primary get-started">Mulai Sekarang <span class="fas fa-arrow-right"></span></a>
            </div>
        </div>
        <div class="hero-image fade-up hero-element" style="animation-delay: 0.3s;">
            <img src="<?php echo APP_URL; ?>/assets/hero.png" alt="Hero Image" width="500" height="300" fetchpriority="high" decoding="async">
        </div>
    </section>

    <section class="features" id="features">
        <h2 class="section-title fade-up">Kenapa Memilih Kami</h2>
        <div class="feature-grid">
            <div class="feature-card glass fade-up stagger-1">
                <img src="<?php echo APP_URL; ?>/assets/why-us/easy.svg" class="feature-icon-svg" alt="Fitur 1" loading="lazy" decoding="async">
                <h3><?php echo $f1_title; ?></h3>
                <p><?php echo $f1_desc; ?></p>
            </div>
            <div class="feature-card glass fade-up stagger-2">
                <img src="<?php echo APP_URL; ?>/assets/why-us/phone.svg" class="feature-icon-svg" alt="Fitur 1" loading="lazy" decoding="async">
                <h3><?php echo $f2_title; ?></h3>
                <p><?php echo $f2_desc; ?></p>
            </div>
            <div class="feature-card glass fade-up stagger-3">
                <img src="<?php echo APP_URL; ?>/assets/why-us/data.svg" class="feature-icon-svg" alt="Fitur 1" loading="lazy" decoding="async">
                <h3><?php echo $f3_title; ?></h3>
                <p><?php echo $f3_desc; ?></p>
            </div>
        </div>
    </section>

    <section class="features">
        
        <div class="support-container glass fade-up stagger-1">
            <h2 class="support-title fade-up">100% Gratis Support</h2>
            <p style="color: #334155; font-size: 1.1rem; line-height: 1.8;">Pelanggan akan dilayani langsung oleh teknisi, yang sudah bertahun tahun berkecimpung di dunia jaringan internet dalam menangani berbagai macam permasalahan, support yang kami berikan bersifat gratis selama permasalahan berkaitan dengan aplikasi RL Radius</p>
        </div>
    </section>

    <section class="packages" id="packages">
        <h2 class="section-title fade-up">Paket Internet</h2>
        <div class="package-grid">
            <?php foreach ($packages as $pkg): ?>
            <div class="package-card glass fade-up">
                <h3><?php echo htmlspecialchars($pkg['name']); ?></h3>
                <img class="package-icon" src="<?php echo APP_URL; ?>/assets/icons/cloud.svg" alt="<?php echo htmlspecialchars($pkg['name']); ?>">
                <div class="package-price"><span class="package-price-main"><?php echo formatCurrency($pkg['price']); ?></span><span class="package-price-period">/bulan</span></div>
                <p class="package-desc"><?php echo htmlspecialchars($pkg['description'] ?? ''); ?></p>

                <div class="package-services-wrapper">
                    <?php if (!empty($packageFeatureList)): ?>
                        <?php $visibleServiceMap = modernUltraBuildVisibleServiceMap($pkg, $packageFeatureList, $packageFeatureTypes); ?>
                        <ul class="package-services">
                            <?php foreach ($packageFeatureList as $serviceKey => $serviceName): ?>
                                <?php if (empty($visibleServiceMap[$serviceKey])) { continue; } ?>
                                <?php $serviceIncluded = modernUltraServiceActive($pkg, $serviceKey); ?>
                                <li class="package-service-item <?php echo $serviceIncluded ? 'is-included' : 'is-missing'; ?>">
                                    <i class="fas <?php echo $serviceIncluded ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                    <span><?php echo htmlspecialchars($serviceName); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <a href="?reg=open&pkg=<?php echo rawurlencode((string) $pkg['name']); ?>#register" class="btn btn-primary package-cta" onclick='try { window.__gembokOpenRegisterModalWithPackage && window.__gembokOpenRegisterModalWithPackage(<?php echo json_encode((string) $pkg['name']); ?>); return false; } catch (e) { return true; }'>Pilih Paket</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>


    <section class="faq" id="faq">
        <h2 class="section-title fade-up">Pertanyaan yang Sering Diajukan</h2>
        <div class="faq-container">
            <?php
            $faqItems = is_array($faqs ?? null) ? array_values($faqs) : [];
            $halfFaq = (int) ceil(count($faqItems) / 2);
            $faqLeft = array_slice($faqItems, 0, $halfFaq);
            $faqRight = array_slice($faqItems, $halfFaq);
            ?>
            <div class="row">
                <div class="col-lg-6">
                    <div class="accordion accordion-flush faq-accordion fade-up" id="faqlist1">
                        <?php foreach ($faqLeft as $i => $faq): ?>
                            <?php $faqId = 'faq-content-1-' . ($i + 1); ?>
                            <div class="accordion-item faq-item glass">
                                <h3 class="accordion-header">
                                    <button class="accordion-button collapsed faq-question" type="button" aria-expanded="false" aria-controls="<?php echo $faqId; ?>">
                                        <span class="faq-title"><?php echo htmlspecialchars($faq['question']); ?></span>
                                        <i class="fas fa-chevron-down faq-chevron" aria-hidden="true"></i>
                                    </button>
                                </h3>
                                <div id="<?php echo $faqId; ?>" class="accordion-collapse collapse faq-answer">
                                    <div class="accordion-body">
                                        <?php echo nl2br(htmlspecialchars($faq['answer'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="accordion accordion-flush faq-accordion fade-up" id="faqlist2">
                        <?php foreach ($faqRight as $i => $faq): ?>
                            <?php $faqId = 'faq-content-2-' . ($i + 1); ?>
                            <div class="accordion-item faq-item glass">
                                <h3 class="accordion-header">
                                    <button class="accordion-button collapsed faq-question" type="button" aria-expanded="false" aria-controls="<?php echo $faqId; ?>">
                                        <span class="faq-title"><?php echo htmlspecialchars($faq['question']); ?></span>
                                        <i class="fas fa-chevron-down faq-chevron" aria-hidden="true"></i>
                                    </button>
                                </h3>
                                <div id="<?php echo $faqId; ?>" class="accordion-collapse collapse faq-answer">
                                    <div class="accordion-body">
                                        <?php echo nl2br(htmlspecialchars($faq['answer'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="contact" id="contact">
        <h2 class="section-title fade-up">Hubungi Kami</h2>
        <div class="contact-grid">
            <div class="contact-item glass">
                <i class="fas fa-phone"></i>
                <h3>Telepon</h3>
                <p><?php echo $contactPhone; ?></p>
            </div>
            <div class="contact-item glass">
                <i class="fas fa-envelope"></i>
                <h3>Email</h3>
                <p><?php echo $contactEmail; ?></p>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="social-links">
            <a href="<?php echo $s_fb; ?>"><i class="fab fa-facebook"></i></a>
            <a href="<?php echo $s_ig; ?>"><i class="fab fa-instagram"></i></a>
            <a href="<?php echo $s_tw; ?>"><i class="fab fa-twitter"></i></a>
            <a href="<?php echo $s_yt; ?>"><i class="fab fa-youtube"></i></a>
        </div>
        <p><?php echo $footerAbout; ?></p>
    </footer>

    <script>
        // Create floating particles
        function createParticles() {
            const container = document.getElementById('particles');
            if (!container) return;

            const isMobile = window.innerWidth <= 768;
            const particleCount = isMobile ? 16 : 28;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.top = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 20 + 's';
                particle.style.animationDuration = (15 + Math.random() * 10) + 's';
                container.appendChild(particle);
            }
        }

        // Scroll animations
        function initScrollAnimations() {
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -100px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.fade-up').forEach(el => observer.observe(el));
            
            // Observe FAQ container for staggered animation
            const faqContainer = document.querySelector('.faq-container');
            if (faqContainer) {
                observer.observe(faqContainer);
            }
            
            // Observe package container for cards
            const packageGrid = document.querySelector('.package-grid');
            if (packageGrid) {
                observer.observe(packageGrid);
            }
        }

        function initFaqDropdown() {
            const accordions = document.querySelectorAll('.faq-accordion');
            accordions.forEach((accordion) => {
                const faqItems = accordion.querySelectorAll('.faq-item');
                faqItems.forEach((item) => {
                    const trigger = item.querySelector('.faq-question');
                    if (!trigger) return;
                    trigger.addEventListener('click', () => {
                        const isOpen = item.classList.contains('open');
                        faqItems.forEach((other) => {
                            other.classList.remove('open');
                            const otherTrigger = other.querySelector('.faq-question');
                            if (otherTrigger) {
                                otherTrigger.setAttribute('aria-expanded', 'false');
                            }
                        });
                        if (!isOpen) {
                            item.classList.add('open');
                            trigger.setAttribute('aria-expanded', 'true');
                        }
                    });
                });
            });
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            initScrollAnimations();
            initFaqDropdown();

            const startParticles = () => createParticles();
            if (document.visibilityState === 'hidden') {
                document.addEventListener('visibilitychange', function onVisible() {
                    if (document.visibilityState === 'visible') {
                        document.removeEventListener('visibilitychange', onVisible);
                        startParticles();
                    }
                });
            } else if ('requestIdleCallback' in window) {
                requestIdleCallback(startParticles, { timeout: 1200 });
            } else {
                setTimeout(startParticles, 250);
            }
        });

        // Register Service Worker after initial rendering work settles
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                const registerSW = function() {
                    navigator.serviceWorker.register('/sw.js')
                        .then(function(registration) {
                            console.log('ServiceWorker registration successful with scope: ', registration.scope);
                        })
                        .catch(function(error) {
                            console.log('ServiceWorker registration failed: ', error);
                        });
                };

                if ('requestIdleCallback' in window) {
                    requestIdleCallback(registerSW, { timeout: 2000 });
                } else {
                    setTimeout(registerSW, 1200);
                }
            });
        }

        (function() {
            const navLinks = document.querySelector('.nav-links');
            const dropdown = document.querySelector('.dropdown');

            window.__gembokToggleNav = function() {
                if (!navLinks) return;
                navLinks.classList.toggle('open');
                if (!navLinks.classList.contains('open') && dropdown) {
                    dropdown.classList.remove('open');
                }
            };

            window.__gembokToggleLogin = function(e) {
                if (e) e.preventDefault();
                if (!dropdown) return;
                dropdown.classList.toggle('open');
                if (navLinks && !navLinks.classList.contains('open')) {
                    navLinks.classList.add('open');
                }
            };

            document.addEventListener('click', function(e) {
                const target = e.target;
                if (!target) return;
                const inNav = navLinks && navLinks.contains(target);
                const inDropdown = dropdown && dropdown.contains(target);
                const isToggle = target.closest && target.closest('.nav-toggle');
                if (!inNav && !inDropdown && !isToggle) {
                    if (navLinks) navLinks.classList.remove('open');
                    if (dropdown) dropdown.classList.remove('open');
                }
            });

            if (navLinks) {
                navLinks.addEventListener('click', function(e) {
                    const a = e.target && e.target.closest ? e.target.closest('a') : null;
                    const href = a && a.getAttribute ? a.getAttribute('href') : null;
                    if (a && href && href.startsWith('#') && href !== '#' && !a.closest('.dropdown')) {
                        navLinks.classList.remove('open');
                        if (dropdown) dropdown.classList.remove('open');
                    }
                });
            }
        })();
    </script>
</body>
</html>

