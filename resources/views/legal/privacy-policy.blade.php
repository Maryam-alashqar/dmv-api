<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $privacyTitleAr }} — DMV-Arabic</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            background: #f4f5f7;
            color: #1f2937;
            font-family: Tahoma, 'Segoe UI', Arial, sans-serif;
            line-height: 1.8;
        }
        .wrap { max-width: 820px; margin: 0 auto; padding: 40px 20px 80px; }
        header { text-align: center; margin-bottom: 32px; }
        header h1 { font-size: 22px; margin: 0 0 4px; color: #0b1220; }
        header p { margin: 0; color: #6b7280; font-size: 14px; }
        nav.toc {
            background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
            padding: 16px 20px; margin-bottom: 28px; font-size: 14px;
        }
        nav.toc ul { margin: 0; padding-inline-start: 20px; }
        nav.toc li { margin-bottom: 6px; }
        nav.toc li:last-child { margin-bottom: 0; }
        nav.toc li::marker { color: #0B84D8; }
        nav.toc a { color: #0B84D8; text-decoration: none; }
        nav.toc a:hover { text-decoration: underline; }
        section {
            background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
            padding: 28px 28px; margin-bottom: 24px;
        }
        section h2 { font-size: 18px; margin: 0 0 16px; color: #0b1220; }
        section .content { font-size: 15px; color: #374151; }
        section .content h3 {
            font-size: 15.5px; font-weight: bold; color: #0b1220;
            margin: 22px 0 8px;
        }
        section .content h3:first-child { margin-top: 0; }
        section .content p { margin: 0 0 12px; }
        section .content ul { margin: 0 0 14px; padding-inline-start: 22px; }
        section .content li { margin-bottom: 8px; }
        section .content li::marker { color: #0B84D8; }
        .lang-block + .lang-block { margin-top: 24px; padding-top: 24px; border-top: 1px dashed #e5e7eb; }
        .lang-label {
            display: inline-block; font-size: 12px; font-weight: bold; color: #0B84D8;
            background: #eaf4fc; border-radius: 6px; padding: 2px 10px; margin-bottom: 10px;
        }
        .en { direction: ltr; text-align: left; }
        footer { text-align: center; color: #9ca3af; font-size: 12px; margin-top: 24px; }
    </style>
</head>
<body>
    <div class="wrap">
        <header>
            <h1>DMV-Arabic</h1>
            <p>Arabic DMV Exam Simulator</p>
        </header>

        <nav class="toc">
            <ul>
                <li><a href="#privacy-policy">{{ $privacyTitleAr }} / {{ $privacyTitleEn }}</a></li>
                <li><a href="#account-deletion">{{ $deletionTitleAr }} / {{ $deletionTitleEn }}</a></li>
            </ul>
        </nav>

        <section id="privacy-policy">
            <h2>{{ $privacyTitleAr }}</h2>
            <div class="lang-block">
                <span class="lang-label">العربية</span>
                <div class="content">{!! $privacyContentAr !!}</div>
            </div>
            <div class="lang-block en">
                <span class="lang-label">English</span>
                <div class="content">{!! $privacyContentEn !!}</div>
            </div>
        </section>

        <section id="account-deletion">
            <h2>{{ $deletionTitleAr }} — {{ $deletionTitleEn }}</h2>
            <div class="lang-block">
                <span class="lang-label">العربية</span>
                <div class="content">{!! $deletionContentAr !!}</div>
            </div>
            <div class="lang-block en">
                <span class="lang-label">English</span>
                <div class="content">{!! $deletionContentEn !!}</div>
            </div>
        </section>

        <footer>DMV-Arabic — {{ date('Y') }}</footer>
    </div>
</body>
</html>
