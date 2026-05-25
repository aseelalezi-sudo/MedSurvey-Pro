<!doctype html>
<html lang="ar" dir="rtl">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>MedSurvey Pro - نظام استبيانات رضا المرضى</title>
    <meta name="description" content="نظام متكامل لجمع وتحليل استبيانات رضا المرضى بطريقة ذكية وسرية تضمن تحسين جودة الرعاية الصحية." />
    
    <!-- Favicon Configuration -->
    <link rel="icon" type="image/png" href="/favicon.png" />
    <link rel="apple-touch-icon" href="/favicon.png" />

    <!-- Open Graph / Facebook Meta Tags -->
    <meta property="og:type" content="website" />
    <meta property="og:url" content="<?php echo e(url('/')); ?>" />
    <meta property="og:title" content="MedSurvey Pro - نظام استبيانات رضا المرضى" />
    <meta property="og:description" content="نظام متكامل لجمع وتحليل استبيانات رضا المرضى بطريقة ذكية وسرية تضمن تحسين جودة الرعاية الصحية." />
    <meta property="og:image" content="/og-image.png" />
    <meta property="og:site_name" content="MedSurvey Pro" />
    <meta property="og:locale" content="ar_SA" />
    <meta property="og:locale:alternate" content="en_US" />

    <!-- Twitter Cards Meta Tags -->
    <meta property="twitter:card" content="summary_large_image" />
    <meta property="twitter:url" content="<?php echo e(url('/')); ?>" />
    <meta property="twitter:title" content="MedSurvey Pro - نظام استبيانات رضا المرضى" />
    <meta property="twitter:description" content="نظام متكامل لجمع وتحليل استبيانات رضا المرضى بطريقة ذكية وسرية تضمن تحسين جودة الرعاية الصحية." />
    <meta property="twitter:image" content="/og-image.png" />

    <!-- Fonts pre-connecting -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <?php echo app('Illuminate\Foundation\Vite')->reactRefresh(); ?>
    <?php echo app('Illuminate\Foundation\Vite')('resources/js/main.tsx'); ?>
  </head>
  <body>
    <div id="root"></div>
  </body>
</html>
<?php /**PATH D:\MedSurvey Pro\resources\views/app.blade.php ENDPATH**/ ?>