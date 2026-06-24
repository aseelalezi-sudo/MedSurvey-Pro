@extends('layouts.web')

@php
  $hideHeader = true;
  $hideFooter = true;
@endphp

@section('title', __('login_title') . ' - MedSurvey Pro')

@section('content')
  <div 
    x-data="{ 
      username: '{{ old('username') }}', 
      password: '', 
      showPassword: false,
      isLoading: false
    }"
    class="min-h-screen bg-linear-to-r from-slate-900 via-slate-800 to-teal-900 flex items-center justify-center p-4 relative overflow-hidden text-gray-900 dark:text-slate-100"
  >
    <!-- Background decorations -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
      <div class="absolute -top-40 -left-40 w-80 h-80 bg-teal-500 rounded-full opacity-10 blur-3xl"></div>
      <div class="absolute -bottom-40 -right-40 w-80 h-80 bg-emerald-500 rounded-full opacity-10 blur-3xl"></div>
      <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-teal-600 rounded-full opacity-5 blur-3xl"></div>
    </div>

    <div class="w-full max-w-md relative z-10">
      <!-- Logo -->
      <div class="text-center mb-8 animate-slide-up">
        <div class="w-24 h-24 flex items-center justify-center mx-auto mb-4 rounded-2xl overflow-hidden drop-shadow-2xl">
          <img src="/system-logo.png" alt="MedSurvey Pro" class="w-full h-full object-contain">
        </div>
        <h1 class="text-3xl font-black text-white mb-2">MedSurvey Pro</h1>
        <p class="text-slate-400 text-sm">{{ __('system_description') }}</p>
      </div>

      <!-- Login Card -->
      <div class="bg-white/10 backdrop-blur-xl rounded-3xl p-8 border border-white/10 shadow-2xl animate-scale-in">
        <div class="text-center mb-6">
          <h2 class="text-xl font-bold text-white mb-1">{{ __('login_title') }}</h2>
          <p class="text-slate-400 text-sm">{{ __('login_subtitle') }}</p>
        </div>

        <form method="POST" action="{{ route('login.store') }}" @submit="isLoading = true" class="space-y-5">
          @csrf

          <!-- Error Messages -->
          @if ($errors->any())
            <div class="flex items-center gap-2 bg-red-500/20 border border-red-500/30 rounded-xl px-4 py-3 text-red-350 text-sm animate-slide-up">
              <i data-lucide="alert-circle" class="w-5 h-5 shrink-0"></i>
              <span>{{ $errors->first() }}</span>
            </div>
          @endif

          <!-- Username -->
          <div class="space-y-2 text-start">
            <label for="username" class="flex items-center gap-2 text-sm font-medium text-slate-300">
              <i data-lucide="user" class="w-4 h-4 text-teal-400"></i>
              <span>{{ __('username') }}</span>
            </label>
            <input
              id="username"
              name="username"
              type="text"
              autocomplete="username"
              x-model="username"
              placeholder="{{ __('username_placeholder') }}"
              required
              class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 outline-none transition-all"
              dir="ltr"
              autofocus
            >
          </div>

          <!-- Password -->
          <div class="space-y-2 text-start">
            <label for="password" class="flex items-center gap-2 text-sm font-medium text-slate-300">
              <i data-lucide="lock" class="w-4 h-4 text-teal-400"></i>
              <span>{{ __('password') }}</span>
            </label>
            <div class="relative">
              <input
                id="password"
                name="password"
                autocomplete="current-password"
                :type="showPassword ? 'text' : 'password'"
                x-model="password"
                placeholder="{{ __('password_placeholder') }}"
                required
                class="w-full px-4 py-3 pl-12 bg-white/5 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 outline-none transition-all"
                dir="ltr"
              >
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white transition-colors cursor-pointer"
              >
                <template x-if="showPassword">
                  <i data-lucide="eye-off" class="w-5 h-5"></i>
                </template>
                <template x-if="!showPassword">
                  <i data-lucide="eye" class="w-5 h-5"></i>
                </template>
              </button>
            </div>
          </div>

          <!-- Submit Button -->
          <button
            type="submit"
            :disabled="!username || !password || isLoading"
            :class="username && password && !isLoading
              ? 'bg-linear-to-r from-teal-600 to-emerald-600 shadow-lg shadow-teal-500/30 hover:shadow-xl hover:-translate-y-0.5'
              : 'bg-slate-600 cursor-not-allowed'"
            class="w-full flex items-center justify-center gap-2 py-3.5 rounded-xl font-bold text-white transition-all duration-300 cursor-pointer"
          >
            <template x-if="isLoading">
              <div class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
            </template>
            <template x-if="!isLoading">
              <span class="flex items-center justify-center gap-2">
                <i data-lucide="log-in" class="w-5 h-5"></i>
                <span>{{ __('login_button') }}</span>
              </span>
            </template>
          </button>
        </form>

        <!-- Back to main site -->
        <div class="mt-6 text-center">
          <a
            href="{{ route('home') }}"
            class="text-slate-400 hover:text-white text-sm transition-colors"
          >
            {{ __('back_to_site') }}
          </a>
        </div>
      </div>
    </div>
  </div>
@endsection
