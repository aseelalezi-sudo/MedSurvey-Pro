@php
  $isAr = app()->getLocale() === 'ar';
  $metaTitle = 'MedSurvey Pro - '.__('system_description');
  $metaDescription = __('system_description');
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $isAr ? 'rtl' : 'ltr' }}">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}" />
    
    <!-- Favicon Configuration -->
    <link rel="icon" type="image/png" href="/favicon.png" />
    <link rel="apple-touch-icon" href="/favicon.png" />
    <link rel="manifest" href="/build/manifest.webmanifest" />
    <meta name="theme-color" content="#0f172a" />

    <!-- Open Graph / Facebook Meta Tags -->
    <meta property="og:type" content="website" />
    <meta property="og:url" content="{{ url('/') }}" />
    <meta property="og:title" content="{{ $metaTitle }}" />
    <meta property="og:description" content="{{ $metaDescription }}" />
    <meta property="og:image" content="/og-image.png" />
    <meta property="og:site_name" content="MedSurvey Pro" />
    <meta property="og:locale" content="{{ $isAr ? 'ar_SA' : 'en_US' }}" />
    <meta property="og:locale:alternate" content="{{ $isAr ? 'en_US' : 'ar_SA' }}" />

    <!-- Twitter Cards Meta Tags -->
    <meta property="twitter:card" content="summary_large_image" />
    <meta property="twitter:url" content="{{ url('/') }}" />
    <meta property="twitter:title" content="{{ $metaTitle }}" />
    <meta property="twitter:description" content="{{ $metaDescription }}" />
    <meta property="twitter:image" content="/og-image.png" />

    <!-- Fonts pre-connecting -->

    @vite('resources/js/main.ts')
  </head>
  <body>
    <div id="root"></div>
  </body>
</html>
