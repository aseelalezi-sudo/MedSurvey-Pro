<!-- Delete Confirmation Modal -->
    <div
      x-show="deleteModal.show"
      x-transition.opacity.duration.200ms
      style="display: none;"
      class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/65 p-4 backdrop-blur-sm"
      @keydown.escape.window="closeDelete()"
    >
      <div
        @click.away="closeDelete()"
        x-transition.scale.origin.center
        class="w-full max-w-md rounded-2xl border border-red-100 bg-white p-6 text-start shadow-2xl dark:border-red-950/50 dark:bg-slate-900"
      >
        <div class="mb-5 flex items-start gap-4">
          <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-red-50 text-red-600 dark:bg-red-950/30 dark:text-red-400">
            <i data-lucide="trash-2" class="h-6 w-6"></i>
          </div>
          <div>
            <h3 class="text-lg font-black text-gray-900 dark:text-white">
              {{ $isAr ? 'تأكيد حذف الاستبيان' : 'Confirm survey deletion' }}
            </h3>
            <p class="mt-2 text-sm font-bold leading-7 text-gray-500 dark:text-slate-400">
              {{ $isAr ? 'سيتم حذف الاستبيان بالكامل مع أقسامه وأسئلته. هل تريد المتابعة؟' : 'This will permanently delete the survey with its sections and questions. Do you want to continue?' }}
            </p>
          </div>
        </div>

        <div class="mb-5 rounded-xl border border-red-100 bg-red-50/60 px-4 py-3 text-sm font-black text-red-700 dark:border-red-950/40 dark:bg-red-950/20 dark:text-red-300" x-text="deleteModal.title"></div>

        <div
          x-show="deleteModal.responseCount > 0"
          class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold leading-7 text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-300"
        >
          <span>{{ $isAr ? 'تنبيه: هذا الاستبيان مرتبط بعدد' : 'Warning: this survey is linked to' }}</span>
          <span class="font-black" x-text="deleteModal.responseCount"></span>
          <span>{{ $isAr ? 'استجابة. الحذف متاح للمدير العام فقط وسيحذف الاستجابات المرتبطة وتذاكرها.' : 'responses. Only a super admin can delete it, and linked responses and tickets will be removed.' }}</span>
        </div>

        <div class="flex items-center justify-end gap-3">
          <button
            type="button"
            @click="closeDelete()"
            class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-bold text-gray-600 transition hover:bg-gray-50 dark:border-slate-750 dark:bg-slate-950 dark:text-slate-300 dark:hover:bg-slate-850"
          >
            {{ $isAr ? 'إلغاء' : 'Cancel' }}
          </button>
          <form method="POST" :action="deleteModal.action" @submit.prevent="submitSurveyAction($event.target, '{{ $isAr ? 'تم حذف الاستبيان بنجاح' : 'Survey deleted successfully' }}', () => closeDelete())">
            @csrf
            @method('DELETE')
            <button
              type="submit"
              class="rounded-xl bg-red-600 px-5 py-2.5 text-sm font-black text-white shadow-lg shadow-red-500/20 transition hover:bg-red-700"
            >
              {{ $isAr ? 'حذف نهائي' : 'Delete permanently' }}
            </button>
          </form>
        </div>
      </div>
    </div>