import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/theme.dart';
import '../core/utils.dart';
import '../data/models.dart';
import '../state/app_state.dart';
import 'survey_form_screen.dart';
import 'widgets/common.dart';

/// Home tab: a website-style landing hero followed by the surveys available
/// to the signed-in collector.
class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  /// Id of the survey being refreshed from the server before opening, so the
  /// card can show a spinner instead of appearing unresponsive.
  String? _openingId;

  Future<void> _openSurvey(Survey survey) async {
    if (_openingId != null) return;

    final state = context.read<AppState>();
    setState(() => _openingId = survey.id);

    // Always ask the server for the current definition: question text, answer
    // type and required flags may have been edited on the website.
    final fresh = await state.freshSurvey(survey);

    if (!mounted) return;
    setState(() => _openingId = null);

    // The session may have expired while fetching; the app will show login.
    if (!state.isLoggedIn) return;

    if (fresh.questions.isEmpty) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          const SnackBar(
            content: Text('هذا الاستبيان لا يحتوي على أسئلة متاحة حالياً.'),
          ),
        );
      return;
    }

    await Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => SurveyFormScreen(survey: fresh)),
    );
  }

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();
    final surveys = state.surveys;
    final palette = context.palette;

    return RefreshIndicator(
      color: palette.accent,
      backgroundColor: palette.surface,
      onRefresh: () async {
        await state.refreshBootstrap();
        await state.syncNow(silent: true);
      },
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
        children: [
          if (!state.online) const OfflineBanner(),
          if (state.authError != null)
            MessageBanner(message: state.authError!, isError: true),
          if (state.refreshError != null)
            MessageBanner(message: state.refreshError!, isError: true),
          if (state.syncError != null && state.online)
            MessageBanner(message: state.syncError!, isError: true),
          _LandingHero(state: state),
          const SizedBox(height: 14),
          _FreshnessBar(state: state),
          const SizedBox(height: 20),
          _SurveyHeading(count: surveys.length),
          const SizedBox(height: 12),
          if (state.bootstrap == null && state.busy)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 40),
              child: Center(child: CircularProgressIndicator()),
            )
          else if (surveys.isEmpty)
            EmptyState(
              icon: Icons.assignment_late_outlined,
              title: 'لا توجد استبيانات متاحة',
              message: state.online
                  ? 'لم يتم العثور على استبيانات نشطة لهذا الحساب. اسحب للأسفل للتحديث.'
                  : 'لا توجد نسخة محفوظة على الجهاز بعد. اتصل بالإنترنت ثم اسحب للأسفل للتحديث.',
              action: OutlinedButton.icon(
                onPressed: state.busy ? null : () => state.refreshBootstrap(),
                icon: const Icon(Icons.refresh_rounded),
                label: const Text('تحديث الاستبيانات'),
              ),
            )
          else
            ...surveys.map(
              (survey) => Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: _SurveyCard(
                  survey: survey,
                  loading: _openingId == survey.id,
                  onTap: () => _openSurvey(survey),
                ),
              ),
            ),
          const SizedBox(height: 8),
          const _OfflineHint(),
        ],
      ),
    );
  }
}

/// Website-style hero: welcome pill, headline, subtitle and trust chips.
class _LandingHero extends StatelessWidget {
  const _LandingHero({required this.state});

  final AppState state;

  static const _fallbackSubtitle =
      'شاركنا تجربتك في المستشفى لتمكيننا من تحسين وتطوير الخدمات الصحية '
      'المقدمة لكم. استبيان سري وآمن لا يتجاوز 3 دقائق.';
  static const _fallbackWelcome = 'أهلاً بكم في نظام التقييم والاستبيانات';
  static const _highlight = Color(0xFF8CF0DC);

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;
    final welcome = state.bootstrap?.hospitalWelcomeMessage.trim() ?? '';
    final tagline = state.bootstrap?.hospitalTagline.trim() ?? '';
    final subtitle = tagline.isEmpty ? _fallbackSubtitle : tagline;

    return ClipRRect(
      borderRadius: BorderRadius.circular(28),
      child: Container(
        decoration: BoxDecoration(
          gradient: palette.heroGradient,
          boxShadow: palette.shadow,
        ),
        child: Stack(
          children: [
            Positioned(
              top: -55,
              left: -40,
              child: _Glow(size: 160, opacity: 0.07),
            ),
            Positioned(
              bottom: -70,
              right: -30,
              child: _Glow(size: 180, opacity: 0.05),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(18, 22, 18, 20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Center(
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 14,
                        vertical: 7,
                      ),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.12),
                        borderRadius: BorderRadius.circular(30),
                        border: Border.all(
                          color: Colors.white.withValues(alpha: 0.22),
                        ),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(
                            Icons.favorite_rounded,
                            size: 14,
                            color: _highlight,
                          ),
                          const SizedBox(width: 7),
                          Flexible(
                            child: Text(
                              welcome.isEmpty ? _fallbackWelcome : welcome,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 11.5,
                                height: 1.4,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Text.rich(
                    TextSpan(
                      children: [
                        const TextSpan(text: 'رأيكم يصنع '),
                        const TextSpan(
                          text: 'الفرق',
                          style: TextStyle(color: _highlight),
                        ),
                        const TextSpan(text: ' في تطوير خدماتنا'),
                      ],
                    ),
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 22.5,
                      height: 1.45,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    subtitle,
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: 0.86),
                      fontSize: 12,
                      height: 1.85,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  const SizedBox(height: 16),
                  Wrap(
                    alignment: WrapAlignment.center,
                    spacing: 8,
                    runSpacing: 8,
                    children: const [
                      _HeroChip(
                        icon: Icons.schedule_rounded,
                        label: '3 دقائق فقط',
                      ),
                      _HeroChip(
                        icon: Icons.verified_user_outlined,
                        label: 'آمن 100%',
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Glow extends StatelessWidget {
  const _Glow({required this.size, required this.opacity});

  final double size;
  final double opacity;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: Colors.white.withValues(alpha: opacity),
      ),
    );
  }
}

class _HeroChip extends StatelessWidget {
  const _HeroChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(30),
        border: Border.all(color: Colors.white.withValues(alpha: 0.18)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: Colors.white.withValues(alpha: 0.9)),
          const SizedBox(width: 6),
          Text(
            label,
            style: TextStyle(
              color: Colors.white.withValues(alpha: 0.92),
              fontSize: 10.5,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

/// Shows when the app last pulled survey definitions from the website, with a
/// manual refresh button.
class _FreshnessBar extends StatelessWidget {
  const _FreshnessBar({required this.state});

  final AppState state;

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;
    final last = state.lastBootstrapAt;

    return Container(
      padding: const EdgeInsets.fromLTRB(12, 8, 8, 8),
      decoration: BoxDecoration(
        color: palette.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: palette.line),
      ),
      child: Row(
        children: [
          Container(
            width: 32,
            height: 32,
            decoration: BoxDecoration(
              color: palette.mint,
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(
              state.busy ? Icons.sync_rounded : Icons.update_rounded,
              size: 17,
              color: palette.accent,
            ),
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  state.busy ? 'جارٍ تحديث بيانات الموقع...' : 'بيانات الموقع',
                  style: TextStyle(
                    color: palette.muted,
                    fontSize: 10.5,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 1),
                Text(
                  last == null
                      ? 'لم يتم التحديث بعد'
                      : 'آخر تحديث ${formatRelative(last)}',
                  style: TextStyle(
                    color: palette.ink,
                    fontSize: 11.5,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          TextButton.icon(
            onPressed: state.busy || !state.online
                ? null
                : () => state.refreshBootstrap(),
            style: TextButton.styleFrom(
              foregroundColor: palette.accent,
              padding: const EdgeInsets.symmetric(horizontal: 10),
              minimumSize: const Size(0, 34),
              textStyle: const TextStyle(
                fontSize: 11.5,
                fontWeight: FontWeight.w800,
              ),
            ),
            icon: state.busy
                ? const SizedBox(
                    width: 14,
                    height: 14,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.refresh_rounded, size: 17),
            label: const Text('تحديث'),
          ),
        ],
      ),
    );
  }
}

class _SurveyHeading extends StatelessWidget {
  const _SurveyHeading({required this.count});

  final int count;

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;

    return Row(
      children: [
        Container(
          width: 4,
          height: 20,
          decoration: BoxDecoration(
            color: palette.accent,
            borderRadius: BorderRadius.circular(4),
          ),
        ),
        const SizedBox(width: 9),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'الاستبيانات المتاحة',
                style: TextStyle(
                  color: palette.ink,
                  fontSize: 15.5,
                  fontWeight: FontWeight.w800,
                ),
              ),
              if (count > 0) ...[
                const SizedBox(height: 2),
                Text(
                  '$count استبيان متاح لحسابك',
                  style: TextStyle(color: palette.muted, fontSize: 10.5),
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }
}

class _SurveyCard extends StatelessWidget {
  const _SurveyCard({
    required this.survey,
    required this.onTap,
    this.loading = false,
  });

  final Survey survey;
  final VoidCallback onTap;
  final bool loading;

  @override
  Widget build(BuildContext context) {
    final departments = survey.assignedDepartments;
    final enabled = survey.questionCount > 0;
    final palette = context.palette;

    return Material(
      color: palette.surface,
      borderRadius: BorderRadius.circular(22),
      child: InkWell(
        borderRadius: BorderRadius.circular(22),
        onTap: enabled ? onTap : null,
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(22),
            border: Border.all(color: palette.line),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 48,
                    height: 48,
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        begin: Alignment.topRight,
                        end: Alignment.bottomLeft,
                        colors: [appTealDark, appTealLight],
                      ),
                      borderRadius: BorderRadius.circular(15),
                      boxShadow: [
                        BoxShadow(
                          color: appTeal.withValues(alpha: 0.25),
                          blurRadius: 12,
                          offset: const Offset(0, 5),
                        ),
                      ],
                    ),
                    child: const Icon(
                      Icons.assignment_turned_in_outlined,
                      color: Colors.white,
                      size: 24,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          survey.title,
                          style: TextStyle(
                            color: palette.ink,
                            fontSize: 14.5,
                            fontWeight: FontWeight.w800,
                            height: 1.45,
                          ),
                        ),
                        if (survey.description.trim().isNotEmpty) ...[
                          const SizedBox(height: 4),
                          Text(
                            survey.description,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: palette.muted,
                              fontSize: 11.5,
                              height: 1.55,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Wrap(
                spacing: 7,
                runSpacing: 7,
                children: [
                  MetaPill(
                    icon: Icons.help_outline_rounded,
                    label: '${survey.questionCount} سؤال',
                  ),
                  MetaPill(
                    icon: Icons.grid_view_rounded,
                    label: '${survey.sections.length} أقسام',
                  ),
                  if (departments != null && departments.isNotEmpty)
                    MetaPill(
                      icon: Icons.apartment_rounded,
                      label: departments.length == 1
                          ? departments.first
                          : '${departments.length} أقسام',
                    ),
                ],
              ),
              const SizedBox(height: 13),
              Row(
                children: [
                  Icon(Icons.schedule_rounded, size: 14, color: palette.faint),
                  const SizedBox(width: 4),
                  Text(
                    'حوالي 3 دقائق',
                    style: TextStyle(color: palette.faint, fontSize: 10.5),
                  ),
                  const Spacer(),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 11,
                      vertical: 6,
                    ),
                    decoration: BoxDecoration(
                      color: enabled ? palette.mint : palette.surfaceAlt,
                      borderRadius: BorderRadius.circular(30),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          !enabled
                              ? 'غير متاح'
                              : loading
                              ? 'جارٍ التحديث'
                              : 'ابدأ الاستبيان',
                          style: TextStyle(
                            color: enabled ? palette.accent : palette.faint,
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        if (enabled) ...[
                          const SizedBox(width: 5),
                          if (loading)
                            SizedBox(
                              width: 12,
                              height: 12,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: palette.accent,
                              ),
                            )
                          else
                            Icon(
                              Icons.arrow_back_ios_new_rounded,
                              size: 11,
                              color: palette.accent,
                            ),
                        ],
                      ],
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _OfflineHint extends StatelessWidget {
  const _OfflineHint();

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
      decoration: BoxDecoration(
        color: palette.surfaceAlt,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: palette.line),
      ),
      child: Row(
        children: [
          Icon(Icons.offline_bolt_outlined, size: 16, color: palette.muted),
          const SizedBox(width: 7),
          Expanded(
            child: Text(
              'جميع الاستبيانات محفوظة على الجهاز وتعمل دون اتصال',
              style: TextStyle(color: palette.muted, fontSize: 10.5),
            ),
          ),
        ],
      ),
    );
  }
}
