<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>خطأ في الاتصال بقاعدة البيانات - MedSurvey Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-50">
<div class="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8 font-cairo" dir="rtl">
    <div class="sm:mx-auto sm:w-full sm:max-w-lg">
        <div class="bg-white py-12 px-8 shadow-2xl rounded-3xl sm:px-12 text-center relative overflow-hidden border border-red-100">
            
            <div class="absolute top-0 right-0 w-32 h-32 bg-red-50 rounded-bl-full -z-10"></div>
            <div class="absolute bottom-0 left-0 w-24 h-24 bg-red-50 rounded-tr-full -z-10"></div>

            <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-red-100 mb-6 shadow-inner">
                <svg class="h-10 w-10 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                    <line x1="4" y1="12" x2="20" y2="12" stroke="white" stroke-width="3" />
                    <line x1="4" y1="12" x2="20" y2="12" stroke="red" stroke-width="2" stroke-dasharray="4" />
                </svg>
            </div>
            
            <h2 class="text-3xl font-black text-gray-900 mb-4 tracking-tight">عذراً، لا يمكن الاتصال بقاعدة البيانات!</h2>
            <p class="text-gray-600 text-base leading-relaxed mb-8">
                النظام لا يستطيع الوصول إلى خادم البيانات حالياً (MySQL). إذا كنت مسؤول النظام، يرجى التحقق من تشغيل حاويات <span class="font-semibold text-gray-800" dir="ltr">Docker</span> أو إعدادات الاتصال في ملف <span class="font-mono text-sm bg-gray-100 px-2 py-1 rounded text-gray-800" dir="ltr">.env</span>.
            </p>

            <div class="bg-red-50 rounded-2xl p-6 text-right mb-8 border border-red-100 shadow-sm">
                <h3 class="text-red-800 font-bold mb-3 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    كيفية الحل السريع:
                </h3>
                <ul class="text-red-700 text-sm space-y-3 font-medium">
                    <li class="flex items-start gap-2">
                        <span class="bg-red-200 text-red-800 rounded-full w-5 h-5 flex items-center justify-center shrink-0 mt-0.5">1</span>
                        <span>افتح سطر الأوامر (Terminal) في مجلد المشروع.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="bg-red-200 text-red-800 rounded-full w-5 h-5 flex items-center justify-center shrink-0 mt-0.5">2</span>
                        <span class="leading-loose">قم بتشغيل الأمر: <br><code class="bg-white border border-red-200 px-2 py-1 rounded text-red-900 shadow-sm mt-1 inline-block" dir="ltr">docker-compose up -d</code></span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="bg-red-200 text-red-800 rounded-full w-5 h-5 flex items-center justify-center shrink-0 mt-0.5">3</span>
                        <span>أعد تحميل هذه الصفحة بعد ثوانٍ.</span>
                    </li>
                </ul>
            </div>

            <a href="{{ url('/') }}" class="inline-flex items-center justify-center gap-2 w-full px-6 py-4 border border-transparent text-base font-bold rounded-xl shadow-md text-white bg-linear-to-l from-red-600 to-red-500 hover:from-red-700 hover:to-red-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                تحديث الصفحة وإعادة المحاولة
            </a>
        </div>
    </div>
</body>
</html>
