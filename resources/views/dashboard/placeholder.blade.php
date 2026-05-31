@extends('layouts.dashboard')

@section('title', $page.' - MedSurvey Pro')

@section('dashboard')
  <div class="web-card rounded-lg p-8">
    <p class="text-sm font-black text-teal-700 dark:text-teal-300">قيد النقل إلى Laravel</p>
    <h1 class="mt-3 text-2xl font-black text-slate-950 dark:text-white">{{ $page }}</h1>
    <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-500 dark:text-slate-400">
      هذه الصفحة أصبحت ضمن راوتات Laravel، وسيتم نقل التفاعل الكامل من React في المرحلة التالية.
      يمكنك استخدام الواجهة القديمة مؤقتاً من الرابط الجانبي عند الحاجة.
    </p>
  </div>
@endsection
