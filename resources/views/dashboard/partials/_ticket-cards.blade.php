@forelse ($tickets as $ticket)
  @php
    $ticketCode = '#' . strtoupper(substr($ticket->id, -8));
    $statusTextColor = match ($ticket->status) {
        'open' => 'text-rose-500',
        'in_progress' => 'text-amber-500',
        default => 'text-emerald-500',
    };
    $statusColorAccent = match ($ticket->status) {
        'open' => 'bg-rose-500',
        'in_progress' => 'bg-amber-500',
        default => 'bg-emerald-500',
    };
  @endphp

  <article
    class="dashboard-panel group relative flex flex-col justify-between p-5 transition-all duration-300 border bg-white dark:bg-slate-900 rounded-2xl shadow-sm hover:shadow-md"
    :class="'{{ $ticket->status }}' === 'open' ? 'border-rose-100 dark:border-rose-900/30' : 'border-slate-100 dark:border-slate-800/80'"
  >
    <div class="absolute top-0 right-0 left-0 h-1.5 rounded-t-2xl {{ $statusColorAccent }}"></div>

    <div class="flex-1 flex flex-col justify-between mt-2">
      <div>
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-1.5 font-bold text-xs {{ $statusTextColor }}">
            @if($ticket->status === 'open')
              <i data-lucide="circle-alert" class="w-4 h-4"></i>
            @elseif($ticket->status === 'in_progress')
              <i data-lucide="timer" class="w-4 h-4"></i>
            @else
              <i data-lucide="check-circle-2" class="w-4 h-4"></i>
            @endif
            <span>{{ $statusLabels[$ticket->status] ?? $ticket->status }}</span>
          </div>
          <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500" dir="ltr">
            {{ $isAr ? 'م' : '' }} {{ optional($ticket->createdAt)->format('Y/m/d H:i:s') }}
          </span>
        </div>

        <div class="flex items-center justify-between mb-3">
          <h3 class="font-black text-slate-900 dark:text-white flex items-center gap-2 text-base">
            <i data-lucide="building-2" class="w-5 h-5 {{ $statusTextColor }}"></i>
            <span class="truncate">{{ __('tickets_dept_label') }} {{ $ticket->department }}</span>
          </h3>
          <span class="shrink-0 rounded-xl bg-slate-100 dark:bg-slate-800 px-3 py-1 font-mono text-xs font-black tracking-widest text-slate-600 dark:text-slate-200" dir="ltr">
            {{ $ticketCode }}
          </span>
        </div>

        <p class="text-xs sm:text-sm leading-relaxed text-slate-500 dark:text-slate-400 mb-4 line-clamp-3" dir="{{ $isAr ? 'rtl' : 'ltr' }}">
          {{ $ticket->description }}
        </p>

        @if($ticket->resolutionNotes)
          <div class="mb-4 rounded-xl border border-slate-200 dark:border-emerald-900/30 bg-slate-50 dark:bg-[#0f172a] p-3 flex flex-col items-center text-center">
            <div class="flex items-center justify-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-bold text-xs mb-1.5">
              <i data-lucide="file-text" class="w-4 h-4"></i>
              <span>{{ __('tickets_form_notes_label') }}</span>
            </div>
            <p class="text-xs text-slate-700 dark:text-slate-300 font-medium">
              {{ $ticket->resolutionNotes }}
            </p>
          </div>
        @endif
      </div>

      <div class="mt-2 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 mb-4">
        <div class="flex items-center gap-1.5 text-start">
          <i data-lucide="user" class="w-4 h-4"></i>
          <span class="truncate">{{ $isAr ? 'المراجع:' : 'Reviewer:' }} {{ $ticket->patientName ?: ($isAr ? 'مجهول الهوية' : 'Guest') }}</span>
        </div>
        @if($ticket->patientPhone)
          <div class="flex items-center gap-1.5" dir="ltr">
            <i data-lucide="phone" class="w-4 h-4"></i>
            <span>{{ $ticket->patientPhone }}</span>
          </div>
        @else
          <div></div>
        @endif
      </div>
    </div>

    <div class="pt-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-3">
      <div class="flex-1">
        @if($ticket->status !== 'resolved')
          @if(auth()->user()->role === 'unit_manager')
            <button
              type="button"
              disabled
              class="w-full bg-slate-200 dark:bg-slate-800 text-slate-400 dark:text-slate-500 px-4 py-2.5 rounded-xl text-sm font-black cursor-not-allowed opacity-60 transition-all"
              title="{{ $isAr ? 'لا تملك صلاحية اتخاذ إجراء' : 'You do not have permission to take action' }}"
            >
              {{ __('tickets_action_btn') }}
            </button>
          @else
            <button
              type="button"
              @click="openResolutionNotes('{{ $ticket->id }}', '{{ addslashes($ticket->resolutionNotes) }}')"
              class="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2.5 rounded-xl text-sm font-black transition-all"
            >
              {{ __('tickets_action_btn') }}
            </button>
          @endif
        @else
          <div class="text-emerald-500 dark:text-emerald-400 text-xs font-black flex items-center gap-1.5">
            <i data-lucide="check-circle" class="w-4 h-4"></i>
            <span>{{ __('tickets_status_resolved_msg') }}</span>
          </div>
        @endif
      </div>

      <div
        class="relative"
        x-data="{ localOpen: false }"
        :style="localOpen ? 'z-index: 50;' : ''"
        @click.away="localOpen = false"
      >
        <button
          type="button"
          @click="localOpen = !localOpen"
          class="p-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors"
        >
          <i data-lucide="more-vertical" class="w-5 h-5"></i>
        </button>

        <div
          x-show="localOpen"
          x-transition:enter="transition ease-out duration-150"
          x-transition:enter-start="opacity-0 translate-y-1 scale-95"
          x-transition:enter-end="opacity-100 translate-y-0 scale-100"
          x-transition:leave="transition ease-in duration-100"
          x-transition:leave-start="opacity-100 translate-y-0 scale-100"
          x-transition:leave-end="opacity-0 translate-y-1 scale-95"
          class="absolute {{ $isRtl ? 'left-0 origin-bottom-left' : 'right-0 origin-bottom-right' }} bottom-full mb-2 min-w-[16rem] w-max bg-white dark:bg-slate-900 rounded-2xl shadow-xl ring-1 ring-slate-200 dark:ring-slate-700/50 py-2.5 z-[999] text-start"
          style="display: none;"
        >
          @if(auth()->user()->role !== 'unit_manager')
            <div class="px-4 pb-2 text-[11px] font-bold text-slate-400 dark:text-slate-500 text-start">
              {{ $isAr ? 'تغيير الحالة' : 'Change Status' }}
            </div>

            <form method="POST" action="{{ route('dashboard.tickets.update', $ticket->id) }}" class="block">
              @csrf
              @method('PATCH')
              <input type="hidden" name="status" value="open">
              <button type="submit" class="w-full flex items-center justify-between px-4 py-2.5 text-sm font-bold whitespace-nowrap {{ $ticket->status === 'open' ? 'text-rose-600 dark:text-rose-500' : 'text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800' }} cursor-pointer transition-colors">
                <div class="flex items-center gap-2">
                  <i data-lucide="circle-alert" class="w-4 h-4 shrink-0"></i>
                  <span>{{ __('ticket_status_open') }}</span>
                </div>
                @if($ticket->status === 'open')
                  <i data-lucide="check" class="w-4 h-4 shrink-0"></i>
                @else
                  <div></div>
                @endif
              </button>
            </form>

            <form method="POST" action="{{ route('dashboard.tickets.update', $ticket->id) }}" class="block">
              @csrf
              @method('PATCH')
              <input type="hidden" name="status" value="in_progress">
              <button type="submit" class="w-full flex items-center justify-between px-4 py-2.5 text-sm font-bold whitespace-nowrap {{ $ticket->status === 'in_progress' ? 'text-amber-600 dark:text-amber-500' : 'text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800' }} cursor-pointer transition-colors">
                <div class="flex items-center gap-2">
                  <i data-lucide="timer" class="w-4 h-4 shrink-0"></i>
                  <span>{{ __('ticket_status_in_progress') }}</span>
                </div>
                @if($ticket->status === 'in_progress')
                  <i data-lucide="check" class="w-4 h-4 shrink-0"></i>
                @else
                  <div></div>
                @endif
              </button>
            </form>

            <button
              type="button"
              @click="localOpen = false; openResolutionNotes('{{ $ticket->id }}', '{{ addslashes($ticket->resolutionNotes) }}')"
              class="w-full flex items-center justify-between px-4 py-2.5 text-sm font-bold whitespace-nowrap {{ $ticket->status === 'resolved' ? 'text-emerald-600 dark:text-emerald-500' : 'text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800' }} cursor-pointer transition-colors"
            >
              <div class="flex items-center gap-2">
                <i data-lucide="check-circle-2" class="w-4 h-4 shrink-0"></i>
                <span>{{ __('ticket_status_resolved') }}</span>
              </div>
              @if($ticket->status === 'resolved')
                <i data-lucide="check" class="w-4 h-4 shrink-0"></i>
              @else
                <div></div>
              @endif
            </button>

            <div class="my-2 border-t border-slate-100 dark:border-slate-700/60"></div>
          @endif

          <div class="px-4 py-2 text-[11px] font-bold text-slate-400 dark:text-slate-500 text-start">
            {{ $isAr ? 'خيارات أخرى' : 'Other Options' }}
          </div>

          @if($ticket->responseId)
            <button
              type="button"
              @click="localOpen = false; viewSurveyDetails('{{ $ticket->responseId }}')"
              class="w-full flex items-center justify-between px-4 py-2.5 text-sm font-bold whitespace-nowrap text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer transition-colors"
            >
              <div class="flex items-center gap-2">
                <i data-lucide="file-text" class="w-4 h-4 shrink-0"></i>
                <span>{{ __('tickets_view_survey_option') }}</span>
              </div>
              <div></div>
            </button>
          @endif

          @can('manage-users')
            <button
              type="button"
              @click="localOpen = false; deletingTicketId = '{{ $ticket->id }}'"
              class="w-full flex items-center justify-between px-4 py-2.5 text-sm font-bold whitespace-nowrap text-rose-500 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20 cursor-pointer transition-colors"
            >
              <div class="flex items-center gap-2">
                <i data-lucide="trash-2" class="w-4 h-4 shrink-0"></i>
                <span>{{ __('tickets_delete_option') }}</span>
              </div>
              <div></div>
            </button>
          @endcan
        </div>
      </div>
    </div>
  </article>
@empty
  <div class="dashboard-panel p-16 text-center text-slate-500 lg:col-span-3 flex flex-col items-center justify-center gap-3">
    <div class="w-16 h-16 bg-slate-50 dark:bg-slate-950 rounded-full flex items-center justify-center">
      <i data-lucide="circle-alert" class="h-8 w-8 text-slate-300 dark:text-slate-650"></i>
    </div>
    <p class="font-bold text-sm text-slate-400">{{ __('tickets_no_tickets_msg') }}</p>
  </div>
@endforelse
