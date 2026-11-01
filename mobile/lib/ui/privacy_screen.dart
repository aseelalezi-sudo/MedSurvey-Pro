import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/app_storage.dart';
import '../core/theme.dart';
import '../state/app_state.dart';
import 'widgets/common.dart';

/// The hospital's data notice.
///
/// Patient names and phone numbers are collected during a visit, so the app has
/// to state plainly what is stored, why, how it is protected and who can see it.
/// When [requireConsent] is true the screen is shown once after login and the
/// collector has to acknowledge it before reaching the surveys. It can be
/// reopened read-only at any time from the account screen.
class PrivacyScreen extends StatefulWidget {
  const PrivacyScreen({super.key, this.requireConsent = false});

  final bool requireConsent;

  @override
  State<PrivacyScreen> createState() => _PrivacyScreenState();
}

class _PrivacyScreenState extends State<PrivacyScreen> {
  bool _acknowledged = false;
  bool _saving = false;

  Future<void> _accept() async {
    if (_saving) return;
    setState(() => _saving = true);
    await context.read<AppState>().acceptPrivacy();
    // The root widget swaps this screen for the survey shell as soon as the
    // state flips, so there is nothing to pop here.
    if (mounted) setState(() => _saving = false);
  }

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;

    return Scaffold(
      backgroundColor: palette.background,
      appBar: AppBar(
        title: Text(
          widget.requireConsent ? 'إشعار الخصوصية' : 'الخصوصية والبيانات',
        ),
        automaticallyImplyLeading: !widget.requireConsent,
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
        children: [
          _HeaderCard(requireConsent: widget.requireConsent),
          const SizedBox(height: 14),
          const _NoticeSection(
            icon: Icons.dataset_outlined,
            title: 'البيانات التي يجمعها التطبيق',
            points: [
              'اسم المريض ورقم هاتفه، حسب ما يطلبه الاستبيان.',
              'الفئة العمرية ونوع الزيارة والقسم.',
              'إجابات أسئلة رضا المرضى، بما فيها الملاحظات النصية.',
            ],
            note:
                'لا يجمع التطبيق الموقع الجغرافي ولا جهات الاتصال ولا الصور، '
                'ولا يقرأ أي بيانات من هاتف الجامع.',
          ),
          _NoticeSection(
            icon: Icons.track_changes_outlined,
            title: 'الغرض من الاستخدام',
            points: [
              'قياس رضا المرضى وتحسين جودة الخدمة داخل المنشأة.',
              'ربط الاستبيان بالزيارة لتحليل النتائج حسب القسم ونوع الزيارة.',
            ],
            note:
                'لا تُستخدم البيانات للتسويق أو الإعلان، ولا تُشارك مع أي طرف '
                'خارج المنشأة.',
          ),
          _NoticeSection(
            icon: Icons.assignment_ind_outlined,
            title: 'أساس استخدام الاسم ورقم الهاتف',
            points: [
              'اسم المريض ورقمه مسجّلان أصلًا في نظام المنشأة ضمن سجل الزيارة.',
              'يُستخدمان هنا للربط الإداري بين الزيارة والاستبيان فقط.',
              'الغرض داخلي بحت، ويقتصر على تحسين الجودة داخل المنشأة.',
            ],
          ),
          _NoticeSection(
            icon: Icons.lock_outline_rounded,
            title: 'كيف تُحمى البيانات',
            points: [
              'الاستبيانات غير المرفوعة تُحفظ على الجهاز مشفّرة بمعيار '
                  'AES-256-GCM، والمفتاح محفوظ في Android Keystore.',
              'تُنقل البيانات عبر اتصال HTTPS إلى خادم المنشأة فقط.',
              'لا يسمح التطبيق بالنسخ الاحتياطي التلقائي لقاعدة بياناته.',
              'الوصول إلى الخادم محمي بحساب المستخدم وصلاحية الجلسة.',
            ],
          ),
          _NoticeSection(
            icon: Icons.visibility_outlined,
            title: 'من يطّلع على البيانات',
            points: [
              'جامع الاستبيان الذي أدخل البيانات.',
              'موظفو الجودة والإدارة المصرّح لهم داخل المنشأة.',
            ],
          ),
          _NoticeSection(
            icon: Icons.timer_outlined,
            title: 'مدة الاحتفاظ',
            points: [
              'تبقى الاستجابة على الجهاز حتى تنجح المزامنة، ثم تُحذف من الجهاز.',
              'على الخادم تتبع البيانات سياسة الاحتفاظ المعتمدة في المنشأة.',
            ],
          ),
          _NoticeSection(
            icon: Icons.gavel_rounded,
            title: 'حقوق المريض والتزام الجامع',
            points: [
              'للمريض الاطلاع على بياناته أو تصحيحها أو طلب حذفها عبر إدارة '
                  'الجودة في المنشأة.',
              'يلتزم الجامع باستخدام البيانات لغرض الجودة فقط، وعدم مشاركتها '
                  'أو تصويرها أو إخراجها من نطاق العمل.',
            ],
          ),
          const SizedBox(height: 6),
          Text(
            'آخر تحديث: أكتوبر 2026 · الإصدار ${AppStorage.noticeVersion}.',
            textAlign: TextAlign.center,
            style: TextStyle(color: palette.faint, fontSize: 10.5),
          ),
          const SizedBox(height: 6),
        ],
      ),
      bottomNavigationBar: widget.requireConsent
          ? _ConsentBar(
              acknowledged: _acknowledged,
              saving: _saving,
              onChanged: (value) => setState(() => _acknowledged = value),
              onAccept: _accept,
            )
          : null,
    );
  }
}

class _HeaderCard extends StatelessWidget {
  const _HeaderCard({required this.requireConsent});

  final bool requireConsent;

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: palette.heroGradient,
        borderRadius: BorderRadius.circular(22),
        boxShadow: palette.shadow,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.16),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(
                    color: Colors.white.withValues(alpha: 0.3),
                  ),
                ),
                child: const Icon(
                  Icons.shield_outlined,
                  color: Colors.white,
                  size: 22,
                ),
              ),
              const SizedBox(width: 12),
              const Expanded(
                child: Text(
                  'خصوصية بيانات المرضى',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            requireConsent
                ? 'قبل بدء العمل، اطّلع على كيفية تعامل التطبيق مع بيانات '
                      'المرضى ثم أكّد الاطلاع.'
                : 'ملخّص واضح لأنواع البيانات التي يجمعها التطبيق، والغرض منها، '
                      'وكيفية حمايتها، ومن يطّلع عليها.',
            style: TextStyle(
              color: Colors.white.withValues(alpha: 0.95),
              fontSize: 12.5,
              height: 1.7,
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ),
    );
  }
}

class _NoticeSection extends StatelessWidget {
  const _NoticeSection({
    required this.icon,
    required this.title,
    required this.points,
    this.note,
  });

  final IconData icon;
  final String title;
  final List<String> points;
  final String? note;

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;

    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: SoftCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Container(
                  width: 34,
                  height: 34,
                  decoration: BoxDecoration(
                    color: palette.mint,
                    borderRadius: BorderRadius.circular(11),
                  ),
                  child: Icon(icon, size: 18, color: palette.accent),
                ),
                const SizedBox(width: 9),
                Expanded(
                  child: Text(
                    title,
                    style: TextStyle(
                      color: palette.ink,
                      fontSize: 13.5,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            for (final point in points)
              Padding(
                padding: const EdgeInsets.only(bottom: 7),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 5,
                      height: 5,
                      margin: const EdgeInsets.only(top: 7, left: 8, right: 2),
                      decoration: BoxDecoration(
                        color: palette.accent,
                        shape: BoxShape.circle,
                      ),
                    ),
                    Expanded(
                      child: Text(
                        point,
                        style: TextStyle(
                          color: palette.muted,
                          fontSize: 12.5,
                          height: 1.7,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            if (note != null) ...[
              const SizedBox(height: 3),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 11,
                  vertical: 9,
                ),
                decoration: BoxDecoration(
                  color: palette.surfaceAlt,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: palette.line),
                ),
                child: Text(
                  note!,
                  style: TextStyle(
                    color: palette.muted,
                    fontSize: 11.5,
                    height: 1.65,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _ConsentBar extends StatelessWidget {
  const _ConsentBar({
    required this.acknowledged,
    required this.saving,
    required this.onChanged,
    required this.onAccept,
  });

  final bool acknowledged;
  final bool saving;
  final ValueChanged<bool> onChanged;
  final VoidCallback onAccept;

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;

    return SafeArea(
      top: false,
      child: Container(
        padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
        decoration: BoxDecoration(
          color: palette.surface,
          border: Border(top: BorderSide(color: palette.line)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            InkWell(
              borderRadius: BorderRadius.circular(10),
              onTap: () => onChanged(!acknowledged),
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 4),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    SizedBox(
                      width: 26,
                      height: 26,
                      child: Checkbox(
                        value: acknowledged,
                        onChanged: (value) => onChanged(value ?? false),
                        visualDensity: VisualDensity.compact,
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        'أقرّ بأنني اطّلعت على إشعار الخصوصية، وألتزم باستخدام '
                        'بيانات المرضى لغرض تحسين الجودة داخل المنشأة فقط.',
                        style: TextStyle(
                          color: palette.ink,
                          fontSize: 11.5,
                          height: 1.6,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 10),
            GradientButton(
              label: 'متابعة إلى الاستبيانات',
              icon: Icons.arrow_forward_rounded,
              height: 50,
              loading: saving,
              onPressed: acknowledged ? onAccept : null,
            ),
          ],
        ),
      ),
    );
  }
}
