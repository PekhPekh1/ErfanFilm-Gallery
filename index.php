<?php
$data_path = 'gallery_data/'; // محل ذخیره لایک و توضیحات روی هاست اصلی

if (!is_dir($data_path)) {
    mkdir($data_path, 0755, true);
}

// API روی همین هاست اصلی اجرا می‌شود و از طریق HTTP/HTTPS به Directory Listing هاست دانلود دسترسی دارد.
require_once __DIR__ . '/config.php';
$selected_category_name = isset($_GET['cat']) ? (string)$_GET['cat'] : null;
$categoriesData = [];

// این فایل روی همان هاست API اجرا می‌شود. cURL به URL کامل نیاز دارد؛
// استفاده از 'api.php?...' به‌عنوان URL نسبی باعث می‌شد صفحه اصلی خالی بماند.
function current_site_url(string $path): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443);

    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';

    if ($host === '') {
        return $path;
    }

    return $scheme . '://' . $host . '/' . ltrim($path, '/');
}

$api_url = current_site_url('api.php');

// خواندن لیست فولدرها از API روی همان هاست اصلی.
if (!$selected_category_name) {
    $ch = curl_init($api_url . '?action=categories');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => ['X-API-Key: ' . API_KEY, 'Accept: application/json'],
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response !== false && $httpCode === 200) {
        $result = json_decode($response, true);
        if (is_array($result) && isset($result['categories']) && is_array($result['categories'])) {
            $categoriesData = $result['categories'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>آرشیو عرفان‌فیلم</title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style>
        :root { 
            --amber: #e9b458; 
            --cream: #f6eed9; 
            --gold: #f0c168;
            --dark-gold: #c98c2c;
            --tile: 96px;
        }
        
        html { 
            scroll-behavior: smooth; 
        }
        
        body { 
            font-family: 'Vazirmatn', system-ui, sans-serif;
            background: linear-gradient(135deg, #0b0907, #1a1410, #0f0c0a);
            background-attachment: fixed;
            color: #f6eed9;
            min-height: 100vh;
        }
        
        ::selection { 
            background: #e9b458; 
            color: #1c1408; 
        }
        
        ::-webkit-scrollbar { 
            width: 9px; 
        }
        
        ::-webkit-scrollbar-track { 
            background: #0b0907; 
        }
        
        ::-webkit-scrollbar-thumb { 
            background: #3a2f1f; 
            border-radius: 99px; 
        }
        
        ::-webkit-scrollbar-thumb:hover { 
            background: #e9b458; 
        }

        /* ───── grain texture ───── */
        .grain {
            position: fixed;
            inset: -60%;
            z-index: 70;
            pointer-events: none;
            opacity: 0.075;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='300'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.7'/%3E%3C/svg%3E");
            animation: grain 7s steps(8) infinite;
        }

        @keyframes grain {
            0%, 100% { transform: translate(0, 0); }
            12% { transform: translate(-4%, -8%); }
            25% { transform: translate(-10%, 4%); }
            37% { transform: translate(5%, -12%); }
            50% { transform: translate(-6%, 10%); }
            62% { transform: translate(8%, 2%); }
            75% { transform: translate(2%, 8%); }
            87% { transform: translate(-8%, 6%); }
        }

        /* ───── reveal animation ───── */
        [data-reveal] {
            opacity: 0;
            transform: translateY(26px);
            filter: blur(8px);
            transition: opacity 1s cubic-bezier(.22, 1, .36, 1), 
                        transform 1s cubic-bezier(.22, 1, .36, 1), 
                        filter 1s ease;
            transition-delay: var(--d, 0s);
        }

        body.ready [data-reveal] {
            opacity: 1;
            transform: none;
            filter: blur(0);
        }

        /* ───── title shine ───── */
        .shine {
            background: linear-gradient(100deg, #f7ecd2 15%, #d9a44a 38%, #fff9e9 50%, #d9a44a 62%, #f7ecd2 85%);
            background-size: 220% auto;
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            animation: shine 7s ease-in-out infinite;
        }

        @keyframes shine {
            0%, 100% { background-position: 200% center; }
            50% { background-position: 0% center; }
        }

        /* ───── film strips ───── */
        .film-strip {
            position: fixed;
            left: 0;
            right: 0;
            height: 44px;
            z-index: 40;
            pointer-events: none;
            overflow: hidden;
            -webkit-mask-image: linear-gradient(90deg, transparent, #000 6%, #000 94%, transparent);
            mask-image: linear-gradient(90deg, transparent, #000 6%, #000 94%, transparent);
        }

        @media (min-width: 640px) {
            .film-strip { height: 58px; }
        }

        .mover {
            position: absolute;
            top: 0;
            bottom: 0;
            left: calc(var(--tile) * -2);
            width: calc(100% + var(--tile) * 4);
            background:
                repeating-linear-gradient(90deg, rgba(238, 214, 164, .20) 0 16px, transparent 16px 48px),
                repeating-linear-gradient(90deg, rgba(238, 214, 164, .20) 0 16px, transparent 16px 48px),
                repeating-linear-gradient(90deg, rgba(238, 214, 164, .09) 0 2px, transparent 2px var(--tile)),
                linear-gradient(180deg, rgba(26, 20, 14, .92), rgba(13, 10, 8, .94));
            background-size: 100% 9px, 100% 9px, 100% calc(100% - 34px), 100% 100%;
            background-position: 0 8px, 0 calc(100% - 8px), 0 100%, 0 0;
            background-repeat: no-repeat, no-repeat, no-repeat, no-repeat;
            animation: stripX 16s linear infinite;
        }

        .film-strip.rev .mover { animation-direction: reverse; }

        @keyframes stripX {
            to { transform: translateX(var(--tile)); }
        }

        #glow {
            position: fixed;
            top: 0;
            left: 0;
            width: 540px;
            height: 540px;
            border-radius: 50%;
            z-index: 5;
            translate: -50% -50%;
            pointer-events: none;
            background: radial-gradient(circle, rgba(233, 180, 88, .085), transparent 62%);
        }

        /* ───── golden button ───── */
        .btn-gold {
            background: linear-gradient(120deg, #f0c168, #c98c2c);
            color: #241806;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px -10px rgba(233, 180, 88, .5);
            transition: transform .3s ease, box-shadow .3s ease, filter .3s ease;
            padding: 12px 24px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
        }

        .btn-gold:hover {
            transform: translateY(-2px);
            filter: brightness(1.06);
            box-shadow: 0 16px 38px -12px rgba(233, 180, 88, .65);
        }

        .btn-gold:active {
            transform: translateY(0) scale(.98);
        }

        .btn-gold::after {
            content: "";
            position: absolute;
            inset: 0;
            translate: -120% 0;
            background: linear-gradient(100deg, transparent 30%, rgba(255, 255, 255, .55), transparent 70%);
            transition: translate .7s ease;
        }

        .btn-gold:hover::after {
            translate: 120% 0;
        }

        /* ───── glass effect cards ───── */
        .glass-card {
            background: rgba(255, 255, 255, .04);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(233, 180, 88, .15);
            border-radius: 16px;
            transition: all .4s cubic-bezier(.22, 1, .36, 1);
        }

        .glass-card:hover {
            transform: translateY(-6px);
            border-color: rgba(233, 180, 88, .38);
            background: rgba(233, 180, 88, .06);
            box-shadow: 0 20px 40px rgba(233, 180, 88, .1);
        }

        /* ───── header ───── */
        .header {
            position: sticky;
            top: 0;
            z-index: 35;
            padding: 75px 30px 20px;
            margin-bottom: 30px;
            background: rgba(11, 9, 7, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(233, 180, 88, 0.1);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.5);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(100deg, #f7ecd2 15%, #d9a44a 38%, #fff9e9 50%, #d9a44a 62%, #f7ecd2 85%);
            background-size: 220% auto;
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin: 0;
            letter-spacing: -0.5px;
        }

        .search-box {
            flex: 1;
            max-width: 400px;
            padding: 12px 20px;
            border-radius: 20px;
            border: 1px solid rgba(233, 180, 88, .2);
            background: rgba(255, 255, 255, .04);
            backdrop-filter: blur(15px);
            color: var(--cream);
            font-size: 14px;
            font-family: 'Vazirmatn', sans-serif;
        }

        .search-box::placeholder {
            color: rgba(246, 238, 217, .4);
        }

        .search-box:focus {
            outline: none;
            border-color: rgba(233, 180, 88, .4);
            background: rgba(255, 255, 255, .06);
        }

        /* ───── folders grid ───── */
        .folders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
            padding: 0 30px;
            position: relative;
            z-index: 10;
        }

        .folder-item {
            padding: 24px 16px;
            text-align: center;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 12px;
            min-height: 140px;
            border-radius: 16px;
            background: rgba(255, 255, 255, .03);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(233, 180, 88, .12);
            transition: all .4s cubic-bezier(.22, 1, .36, 1);
        }

        .folder-item:hover {
            transform: translateY(-8px);
            border-color: rgba(233, 180, 88, .35);
            background: rgba(233, 180, 88, .06);
            box-shadow: 0 20px 40px rgba(233, 180, 88, .15);
        }

        .folder-item-icon {
            font-size: 32px;
            color: var(--gold);
        }

        .folder-item-name {
            font-weight: 600;
            font-size: 15px;
            color: var(--cream);
        }

        .folder-item-count {
            font-size: 12px;
            color: rgba(233, 180, 88, .6);
        }

        /* ───── gallery grid ───── */
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
            padding: 0 30px;
            position: relative;
            z-index: 10;
        }

        .gallery-item {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            cursor: pointer;
            aspect-ratio: 2/3;
            min-width: 0;
            background: rgba(0, 0, 0, .32);
            border: 1px solid rgba(233, 180, 88, .12);
            transition: all .3s ease;
        }

        .gallery-item img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center center;
            transition: transform .4s ease, filter .4s ease;
        }

        .gallery-item:hover img {
            transform: scale(1.08);
            filter: brightness(1.2) saturate(1.1);
        }

        .gallery-item-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(180deg, transparent, rgba(0, 0, 0, .95));
            padding: 16px;
            color: white;
            opacity: 0;
            transform: translateY(20px);
            transition: all .3s ease;
        }

        .gallery-item:hover .gallery-item-overlay {
            opacity: 1;
            transform: translateY(0);
        }

        .gallery-item-title {
            font-weight: 600;
            font-size: 13px;
            color: var(--cream);
        }

        /* ───── lightbox ───── */
        .lightbox {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, .97);
            backdrop-filter: blur(5px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            padding: 30px;
        }

        .lightbox.show {
            display: flex;
        }

        .lightbox-wrapper {
            position: relative;
            display: flex;
            gap: 30px;
            width: 100%;
            max-width: 1200px;
            align-items: center;
        }

        .zoom-container {
            position: relative;
            width: 100%;
            max-width: 800px;
            height: 600px;
            background: rgba(233, 180, 88, .05);
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid rgba(233, 180, 88, .2);
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .zoom-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
            cursor: grab;
            transition: transform .1s ease-out;
            user-select: none;
        }

        .zoom-image.panning {
            cursor: grabbing;
        }

        /* ───── controls ───── */
        .lightbox-controls {
            display: flex;
            flex-direction: column;
            gap: 12px;
            width: 100%;
            max-width: 320px;
        }

        .control-btn {
            background: rgba(255, 255, 255, .05);
            border: 1px solid rgba(233, 180, 88, .15);
            color: var(--cream);
            padding: 12px 16px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 13px;
            font-family: 'Vazirmatn', sans-serif;
            transition: all .3s ease;
        }

        .control-btn:hover {
            background: rgba(233, 180, 88, .1);
            border-color: rgba(233, 180, 88, .3);
            transform: translateY(-2px);
        }

        .control-btn:active {
            transform: translateY(0) scale(.98);
        }

        /* ───── filter controls ───── */
        .filter-controls {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin: 12px 0;
        }

        .filter-btn {
            background: rgba(255, 255, 255, .04);
            border: 1px solid rgba(233, 180, 88, .15);
            color: rgba(246, 238, 217, .6);
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            transition: all .3s ease;
        }

        .filter-btn:hover {
            background: rgba(233, 180, 88, .08);
            border-color: rgba(233, 180, 88, .25);
        }

        .filter-btn.active {
            background: linear-gradient(120deg, #f0c168, #c98c2c);
            border-color: rgba(233, 180, 88, .5);
            color: #241806;
            font-weight: 600;
        }

        /* ───── info panel ───── */
        .info-panel {
            background: rgba(255, 255, 255, .04);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(233, 180, 88, .15);
            border-radius: 16px;
            padding: 20px;
            margin-top: 16px;
        }

        .info-label {
            font-size: 12px;
            color: rgba(233, 180, 88, .7);
            font-weight: 600;
            margin-bottom: 4px;
        }

        .info-input {
            width: 100%;
            background: rgba(255, 255, 255, .04);
            border: 1px solid rgba(233, 180, 88, .15);
            color: var(--cream);
            padding: 10px 12px;
            border-radius: 8px;
            font-family: 'Vazirmatn', sans-serif;
            margin-bottom: 12px;
            font-size: 13px;
            transition: all .3s ease;
        }

        .info-input:focus {
            outline: none;
            background: rgba(255, 255, 255, .06);
            border-color: rgba(233, 180, 88, .3);
        }

        .info-display {
            font-size: 13px;
            color: rgba(246, 238, 217, .7);
            padding: 8px 0;
            border-bottom: 1px solid rgba(233, 180, 88, .1);
            margin-bottom: 8px;
        }

        .info-display span {
            color: var(--gold);
            font-weight: 600;
        }

        /* ───── close button ───── */
        .close-lightbox {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, .1);
            border: none;
            border-radius: 10px;
            color: var(--cream);
            cursor: pointer;
            font-size: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all .3s ease;
            z-index: 10;
        }

        .close-lightbox:hover {
            background: rgba(233, 180, 88, .2);
            transform: rotate(90deg);
        }

        /* ───── navigation arrows ───---- */
        .nav-arrow {
            position: absolute;
            top: 50%;
            translate: 0 -50%;
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, .08);
            border: 1px solid rgba(233, 180, 88, .2);
            border-radius: 12px;
            color: var(--gold);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            transition: all .3s ease;
            z-index: 5;
        }

        .nav-arrow:hover {
            background: rgba(233, 180, 88, .15);
            border-color: rgba(233, 180, 88, .4);
            transform: scale(1.1);
        }

        .nav-arrow.prev {
            left: -60px;
        }

        .nav-arrow.next {
            right: -60px;
        }

        /* ───── responsive ───── */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                max-width: 100%;
            }

            .lightbox-wrapper {
                flex-direction: column;
                padding: 0;
            }

            .zoom-container {
                max-width: 100%;
                height: 400px;
                width: 100%;
            }

            .lightbox-controls {
                max-width: 100%;
                width: 100%;
            }

            .nav-arrow {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }

            .nav-arrow.prev {
                left: 10px;
            }

            .nav-arrow.next {
                right: 10px;
            }

            .folders-grid,
            .gallery-grid {
                padding: 0 15px;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 14px;
            }

            /* در موبایل تصویر کامل نمایش داده شود و بالا/پایین آن crop نشود. */
            .gallery-item {
                width: 100%;
                aspect-ratio: 2 / 3;
                min-height: 0;
                background: rgba(0, 0, 0, .38);
            }

            .gallery-item img {
                width: 100%;
                height: 100%;
                object-fit: contain;
                object-position: center center;
                transform: none !important;
            }

            .gallery-item-overlay {
                opacity: 1;
                transform: translateY(0);
                padding: 10px;
                background: linear-gradient(180deg, transparent 35%, rgba(0, 0, 0, .9));
            }

            .gallery-item-title {
                font-size: 11px;
                line-height: 1.5;
            }

            .header {
                padding: 65px 20px 20px; /* تنظیم فاصله هدر چسبنده برای موبایل */
            }
        }
    </style>
</head>
<body>
    <!-- grain texture -->
    <div class="grain"></div>

    <!-- cursor glow -->
    <div id="glow"></div>

    <!-- film strips -->
    <div class="film-strip" style="top: 0">
        <div class="mover"></div>
    </div>
    <div class="film-strip rev" style="bottom: 0">
        <div class="mover"></div>
    </div>

    <!-- header -->
    <header class="header">
        <div class="header-content" data-reveal style="--d: 0s">
            <h1>🎬 عرفان‌فیلم</h1>
            <input type="text" class="search-box" id="searchBox" placeholder="جستجو برای دسته‌بندی...">
        </div>
    </header>

    <!-- main content -->
    <main style="position: relative; z-index: 20;">
        <?php if (!$selected_category_name): ?>
            <!-- folders view -->
            <div id="foldersView">
                <div class="folders-grid" data-reveal style="--d: 0.2s">
                    <?php foreach ($categoriesData as $index => $cat): ?>
                        <a href="?cat=<?php echo urlencode($cat['name']); ?>" class="folder-item" data-folder="<?php echo htmlspecialchars($cat['name']); ?>">
                            <div class="folder-item-icon">📁</div>
                            <div class="folder-item-name"><?php echo htmlspecialchars($cat['name']); ?></div>
                            <div class="folder-item-count"><?php echo $cat['count']; ?> عکس</div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- gallery view -->
            <div style="padding: 0 30px 40px; position: relative; z-index: 10;" data-reveal style="--d: 0.1s">
                <div style="margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
                    <a href="?" style="display: inline-flex; align-items: center; gap: 6px; color: var(--gold); text-decoration: none; font-size: 14px; transition: all .3s ease;" onmouseover="this.style.transform='translateX(4px)'" onmouseout="this.style.transform='translateX(0)'">
                        ← بازگشت
                    </a>
                    <span style="color: rgba(246, 238, 217, .5);">/</span>
                    <span style="font-weight: 600; color: var(--cream);"><?php echo htmlspecialchars($selected_category_name); ?></span>
                </div>
            </div>

            <div id="galleryContainer" class="gallery-grid">
                <!-- images will be loaded here -->
            </div>
        <?php endif; ?>
    </main>

    <!-- lightbox -->
    <div id="lightbox" class="lightbox">
        <button class="close-lightbox" onclick="closeLightbox()">✕</button>
        <button class="nav-arrow prev" onclick="changeImage(-1)">‹</button>

        <div class="lightbox-wrapper">
            <div class="zoom-container">
                <img id="lightboxImg" class="zoom-image" src="" alt="تصویر بزرگ">
            </div>

            <div class="lightbox-controls">
                <div>
                    <div class="info-label">ابزارها</div>
                    <div class="filter-controls">
                        <button class="filter-btn active" onclick="applyLightboxFilter('normal', this)">عادی</button>
                        <button class="filter-btn" onclick="applyLightboxFilter('grayscale', this)">سیاه‌سفید</button>
                        <button class="filter-btn" onclick="applyLightboxFilter('sepia', this)">سپیا</button>
                        <button class="filter-btn" onclick="applyLightboxFilter('saturate', this)">رنگین</button>
                    </div>
                </div>

                <button class="control-btn" onclick="zoomIn()">🔍 بزرگ‌نمایی</button>
                <button class="control-btn" onclick="zoomOut()">🔍 کاهش</button>
                <button class="control-btn" onclick="resetZoom()">↺ ریست</button>
                <button class="control-btn" onclick="toggleLike()">❤ پسندیدن</button>
                <button class="control-btn" onclick="downloadImage()">⬇ دانلود</button>

                <div class="info-panel">
                    <div class="info-display">
                        <span id="zoomLevel">100%</span> / بزرگنمایی
                    </div>
                    <div class="info-display">
                        👁 <span id="viewCount">0</span> بازدید
                    </div>
                    <div class="info-display">
                        ❤ <span id="likeCount">0</span> پسند
                    </div>

                    <div class="info-label" style="margin-top: 12px;">عنوان</div>
                    <input type="text" id="movieTitle" class="info-input" onchange="saveImageData()" placeholder="عنوان فیلم">

                    <div class="info-label">سال</div>
                    <input type="text" id="movieYear" class="info-input" onchange="saveImageData()" placeholder="سال انتشار">

                    <div class="info-label">کارگردان</div>
                    <input type="text" id="movieDirector" class="info-input" onchange="saveImageData()" placeholder="نام کارگردان">

                    <div class="info-label">توضیحات</div>
                    <textarea id="movieDescription" class="info-input" style="min-height: 80px; resize: none;" onchange="saveImageData()" placeholder="توضیحات..."></textarea>

                    <button class="btn-gold" style="width: 100%; margin-top: 8px;" onclick="saveImageData()">💾 ذخیره</button>
                </div>
            </div>
        </div>

        <button class="nav-arrow next" onclick="changeImage(1)">›</button>
    </div>

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script>
        lucide.createIcons();

        // Global variables
        let categoryName = <?php echo json_encode($selected_category_name, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        let imagesList = [];
        let imageNames = [];
        let currentIndex = 0;
        let currentFilter = 'normal';
        let zoomLevel = 1;
        let panX = 0, panY = 0;
        let isPanning = false;
        let startX = 0, startY = 0;

        const lightbox = document.getElementById('lightbox');
        const lightboxImg = document.getElementById('lightboxImg');
        const zoomContainer = document.querySelector('.zoom-container');
        const galleryContainer = document.getElementById('galleryContainer');

        // Load gallery
        async function loadGallery() {
            if (!categoryName) return;
            try {
                const response = await fetch('list_images.php?cat=' + encodeURIComponent(categoryName));
                const data = await response.json();
                
                if (data.error) {
                    galleryContainer.innerHTML = '<div style="text-align: center; padding: 40px; color: rgba(246, 238, 217, .5); grid-column: 1/-1;">❌ ' + data.error + '</div>';
                    return;
                }
                
                imagesList = data.images || [];
                imageNames = data.names || [];
                renderGallery();
            } catch (e) {
                console.error('خطا:', e);
                if (galleryContainer) {
                   galleryContainer.innerHTML = '<div style="text-align: center; padding: 40px; color: rgba(246, 238, 217, .5); grid-column: 1/-1;">❌ خطا در بارگذاری تصاویر</div>';
                }
            }
        }

        function renderGallery() {
            if (!galleryContainer) return;
            galleryContainer.innerHTML = '';
            imagesList.forEach((img, idx) => {
                const item = document.createElement('div');
                item.className = 'gallery-item';
                item.innerHTML = `
                    <img src="${img}" alt="تصویر">
                    <div class="gallery-item-overlay">
                        <div class="gallery-item-title">${imageNames[idx]}</div>
                    </div>
                `;
                item.onclick = () => openLightbox(idx);
                galleryContainer.appendChild(item);
            });
        }

        function openLightbox(index) {
            currentIndex = index;
            updateImage();
            resetZoom();
            resetFilter();
            incrementViews();
            loadImageData();
            lightbox.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeLightbox() {
            lightbox.classList.remove('show');
            document.body.style.overflow = '';
        }

        function updateImage() {
            if (imagesList[currentIndex]) {
                lightboxImg.src = imagesList[currentIndex];
            }
        }

        function changeImage(step) {
            currentIndex += step;
            if (currentIndex >= imagesList.length) currentIndex = 0;
            if (currentIndex < 0) currentIndex = imagesList.length - 1;
            updateImage();
            resetZoom();
            resetFilter();
            incrementViews();
            loadImageData();
        }

        function zoomIn() {
            zoomLevel = Math.min(zoomLevel + 0.2, 3);
            applyZoom();
        }

        function zoomOut() {
            zoomLevel = Math.max(zoomLevel - 0.2, 0.5);
            applyZoom();
        }

        function resetZoom() {
            zoomLevel = 1;
            panX = 0;
            panY = 0;
            applyZoom();
        }

        function applyZoom() {
            lightboxImg.style.transform = 'scale(' + zoomLevel + ') translate(' + (panX / zoomLevel) + 'px, ' + (panY / zoomLevel) + 'px)';
            document.getElementById('zoomLevel').innerText = Math.round(zoomLevel * 100) + '%';
        }

        // Pan support
        lightboxImg.addEventListener('mousedown', (e) => {
            if (zoomLevel > 1) {
                isPanning = true;
                startX = e.clientX - panX;
                startY = e.clientY - panY;
                lightboxImg.classList.add('panning');
            }
        });

        document.addEventListener('mousemove', (e) => {
            if (isPanning && lightbox.classList.contains('show')) {
                panX = e.clientX - startX;
                panY = e.clientY - startY;
                
                const maxPanX = (zoomLevel - 1) * zoomContainer.offsetWidth / 2;
                const maxPanY = (zoomLevel - 1) * zoomContainer.offsetHeight / 2;
                
                panX = Math.max(-maxPanX, Math.min(maxPanX, panX));
                panY = Math.max(-maxPanY, Math.min(maxPanY, panY));
                
                applyZoom();
            }
        });

        document.addEventListener('mouseup', () => {
            isPanning = false;
            lightboxImg.classList.remove('panning');
        });

        // Touch support
        let touchStartX = 0, touchStartY = 0;
        
        lightboxImg.addEventListener('touchstart', (e) => {
            if (zoomLevel > 1 && e.touches.length === 1) {
                isPanning = true;
                touchStartX = e.touches[0].clientX - panX;
                touchStartY = e.touches[0].clientY - panY;
            }
        });

        lightboxImg.addEventListener('touchmove', (e) => {
            if (isPanning && e.touches.length === 1) {
                e.preventDefault();
                panX = e.touches[0].clientX - touchStartX;
                panY = e.touches[0].clientY - touchStartY;
                
                const maxPanX = (zoomLevel - 1) * zoomContainer.offsetWidth / 2;
                const maxPanY = (zoomLevel - 1) * zoomContainer.offsetHeight / 2;
                
                panX = Math.max(-maxPanX, Math.min(maxPanX, panX));
                panY = Math.max(-maxPanY, Math.min(maxPanY, panY));
                
                applyZoom();
            }
        });

        lightboxImg.addEventListener('touchend', () => {
            isPanning = false;
        });

        // Mouse wheel zoom
        lightboxImg.addEventListener('wheel', (e) => {
            e.preventDefault();
            if (e.deltaY > 0) zoomOut();
            else zoomIn();
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (!lightbox.classList.contains('show')) return;
            if (e.key === 'ArrowLeft') changeImage(-1);
            if (e.key === 'ArrowRight') changeImage(1);
            if (e.key === 'Escape') closeLightbox();
            if (e.key === '+' || e.key === '=') { e.preventDefault(); zoomIn(); }
            if (e.key === '-') { e.preventDefault(); zoomOut(); }
            if (e.key === '0') { e.preventDefault(); resetZoom(); }
            if (e.key === ' ') { e.preventDefault(); toggleLike(); }
        });

        // Filter functions
        function applyLightboxFilter(filter, btn) {
            currentFilter = filter;
            document.querySelectorAll('.lightbox .filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            updateFilter();
        }

        function updateFilter() {
            let filters = '';
            if (currentFilter === 'grayscale') filters = 'grayscale(100%)';
            else if (currentFilter === 'sepia') filters = 'sepia(100%)';
            else if (currentFilter === 'saturate') filters = 'saturate(2)';
            lightboxImg.style.filter = filters;
        }

        function resetFilter() {
            currentFilter = 'normal';
            lightboxImg.style.filter = '';
            document.querySelectorAll('.lightbox .filter-btn').forEach(b => b.classList.remove('active'));
            document.querySelector('.lightbox .filter-btn:first-child').classList.add('active');
        }

        // Save image data
        function saveImageData() {
            const data = {
                title: document.getElementById('movieTitle').value,
                year: document.getElementById('movieYear').value,
                director: document.getElementById('movieDirector').value,
                description: document.getElementById('movieDescription').value
            };

            fetch('save_image_data.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    cat: categoryName,
                    index: currentIndex,
                    data: data
                })
            });
        }

        // Load image data
        function loadImageData() {
            fetch('get_image_data.php?cat=' + encodeURIComponent(categoryName) + '&index=' + currentIndex)
                .then(r => r.json())
                .then(data => {
                    document.getElementById('movieTitle').value = data.title || '';
                    document.getElementById('movieYear').value = data.year || '';
                    document.getElementById('movieDirector').value = data.director || '';
                    document.getElementById('movieDescription').value = data.description || '';
                    document.getElementById('viewCount').innerText = data.views || 0;
                    document.getElementById('likeCount').innerText = data.likes || 0;
                })
                .catch(e => console.log('خطا:', e));
        }

        // Toggle like
        function toggleLike() {
            fetch('save_like.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    cat: categoryName,
                    index: currentIndex
                })
            }).then(() => loadImageData());
        }

        // Increment views
        function incrementViews() {
            fetch('increment_views.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    cat: categoryName,
                    index: currentIndex
                })
            });
        }

        // Download image
        function downloadImage() {
            const img = new Image();
            img.crossOrigin = 'Anonymous';
            img.onload = function() {
                const canvas = document.createElement('canvas');
                canvas.width = img.width;
                canvas.height = img.height;
                canvas.getContext('2d').drawImage(img, 0, 0);
                
                const link = document.createElement('a');
                link.href = canvas.toDataURL();
                link.download = 'image_' + (currentIndex + 1) + '.jpg';
                link.click();
            };
            img.src = imagesList[currentIndex];
        }

        // Search functionality
        document.getElementById('searchBox').addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            document.querySelectorAll('.folder-item').forEach(item => {
                const folderName = item.dataset.folder.toLowerCase();
                item.style.display = folderName.includes(query) ? 'flex' : 'none';
            });
        });

        // Initialize
        document.body.classList.add('ready');
        if (categoryName) {
            loadGallery();
        }
    </script>
</body>
</html>