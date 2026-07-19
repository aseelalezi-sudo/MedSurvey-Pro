@php
  $isAr = $isAr ?? app()->getLocale() === 'ar';
  $compactNumber = [\App\Support\NumberFormatter::class, 'compact'];
  $rangeStart = $responses->total() === 0 ? 0 : (($responses->currentPage() - 1) * $responses->perPage()) + 1;
  $rangeEnd = min($responses->currentPage() * $responses->perPage(), $responses->total());
@endphp

@if ($responses->total() > 0)
  <div class="flex flex-col gap-3 rounded-2xl border border-slate-100 bg-white px-4 py-3 text-xs font-bold text-slate-500 shadow-xs sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
    <span>
      {{ $isAr ? 'صفحة' : 'Page' }}
      <span class="text-slate-800 dark:text-slate-200">{{ $compactNumber($responses->currentPage()) }}</span>
      {{ $isAr ? 'من' : 'of' }}
      <span class="text-slate-800 dark:text-slate-200">{{ $compactNumber($responses->lastPage()) }}</span>
      <span class="mx-1 text-slate-300 dark:text-slate-600">|</span>
      <span class="text-slate-800 dark:text-slate-200">{{ $compactNumber($rangeStart) }}-{{ $compactNumber($rangeEnd) }}</span>
      {{ $isAr ? 'معروضة من' : 'shown of' }}
      <span class="text-slate-800 dark:text-slate-200">{{ $compactNumber($responses->total()) }}</span>
    </span>

    <div class="flex flex-wrap items-center gap-2">
      <div class="flex items-center gap-1.5">
        <span class="hidden text-xs font-black text-slate-400 sm:inline">{{ $isAr ? 'السجلات المعروضة' : 'Rows shown' }}</span>
        <select
          id="responsesPerPage"
          name="responsesPerPage"
          aria-label="{{ $isAr ? 'السجلات المعروضة' : 'Rows shown' }}"
          data-response-per-page
          class="h-9 rounded-xl border border-slate-200 bg-white px-2 text-xs font-black text-slate-700 outline-none transition focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
        >
          @foreach ([10, 20, 50, 100] as $pageSize)
            <option value="{{ $pageSize }}" @selected($responses->perPage() === $pageSize)>{{ $compactNumber($pageSize) }}</option>
          @endforeach
        </select>
      </div>

      <button
        type="button"
        data-response-page="{{ max(1, $responses->currentPage() - 1) }}"
        @disabled($responses->onFirstPage())
        class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-black text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
      >
        <i data-lucide="{{ $isAr ? 'chevron-right' : 'chevron-left' }}" class="h-3.5 w-3.5"></i>
        <span>{{ $isAr ? 'السابق' : 'Previous' }}</span>
      </button>

      <button
        type="button"
        data-response-page="{{ min($responses->lastPage(), $responses->currentPage() + 1) }}"
        @disabled(! $responses->hasMorePages())
        class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-black text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
      >
        <span>{{ $isAr ? 'التالي' : 'Next' }}</span>
        <i data-lucide="{{ $isAr ? 'chevron-left' : 'chevron-right' }}" class="h-3.5 w-3.5"></i>
      </button>

      <div class="flex items-center gap-1.5">
        <span class="hidden text-xs font-black text-slate-400 sm:inline">{{ $isAr ? 'انتقل لصفحة' : 'Go to page' }}</span>
        <input
          id="responsesPageJump"
          name="responsesPageJump"
          aria-label="{{ $isAr ? 'انتقل لصفحة' : 'Go to page' }}"
          type="number"
          min="1"
          max="{{ $responses->lastPage() }}"
          data-response-page-jump
          class="h-9 w-16 rounded-xl border border-slate-200 bg-white px-2 text-center text-xs font-black text-slate-700 outline-none transition focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
          placeholder="#"
        />
        <button
          type="button"
          data-response-jump
          class="h-9 rounded-xl bg-slate-100 px-3 text-xs font-black text-slate-600 transition hover:bg-teal-100 hover:text-teal-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-teal-950/30 dark:hover:text-teal-300"
        >
          {{ $isAr ? 'انتقال' : 'Go' }}
        </button>
      </div>
    </div>
  </div>
@endif
