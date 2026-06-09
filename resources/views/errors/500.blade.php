@php
$isDbError = false;
$e = $exception ?? null;
while($e) {
    if (str_contains($e->getMessage(), '2002') || str_contains($e->getMessage(), 'Connection refused')) {
        $isDbError = true;
        break;
    }
    $e = $e->getPrevious();
}
@endphp

@if($isDbError)
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
<div class="min-h-screen flex flex-col justify-center py-12 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-lg">
        <div class="bg-white py-12 px-8 shadow-2xl rounded-3xl sm:px-12 text-center relative overflow-hidden border border-red-100">
            <h2 class="text-3xl font-black text-gray-900 mb-4 tracking-tight">عذراً، لا يمكن الاتصال بقاعدة البيانات!</h2>
            <p class="text-gray-600 text-base leading-relaxed mb-8">النظام لا يستطيع الوصول إلى خادم البيانات حالياً (MySQL). يرجى التحقق من تشغيل حاويات Docker.</p>
        </div>
    </div>
</div>
</body>
</html>
@else
@extends('errors::minimal')

@section('title', __('Server Error'))
@section('code', '500')
@section('message', __('Server Error'))
@endif
