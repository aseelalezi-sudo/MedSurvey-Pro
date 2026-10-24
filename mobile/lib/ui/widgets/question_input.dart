import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/theme.dart';
import '../../data/models.dart';

class QuestionInput extends StatelessWidget {
  const QuestionInput({
    super.key,
    required this.question,
    required this.value,
    required this.onChanged,
    required this.reason,
    required this.onReasonChanged,
  });

  final SurveyQuestion question;
  final dynamic value;
  final ValueChanged<dynamic> onChanged;
  final String reason;
  final ValueChanged<String> onReasonChanged;

  int get _numericValue {
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _buildInput(context),
        AnimatedSize(
          duration: const Duration(milliseconds: 220),
          curve: Curves.easeOutCubic,
          alignment: Alignment.topCenter,
          child:
              question.supportsFollowUp &&
                  _numericValue >= 1 &&
                  _numericValue <= 2
              ? Padding(
                  padding: const EdgeInsets.only(top: 14),
                  child: Container(
                    padding: const EdgeInsets.all(13),
                    decoration: BoxDecoration(
                      color: palette.sand,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: palette.warnBorder),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Icon(
                              Icons.rate_review_outlined,
                              size: 17,
                              color: palette.warnFg,
                            ),
                            const SizedBox(width: 6),
                            Expanded(
                              child: Text(
                                'ما سبب هذا التقييم؟ (اختياري)',
                                style: TextStyle(
                                  color: palette.warnFg,
                                  fontSize: 12,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 9),
                        TextFormField(
                          key: ValueKey('reason_${question.id}'),
                          initialValue: reason,
                          onChanged: onReasonChanged,
                          maxLines: 2,
                          maxLength: 1000,
                          cursorColor: palette.accent,
                          onTapOutside: (_) => FocusScope.of(context).unfocus(),
                          style: TextStyle(color: palette.ink, fontSize: 12.5),
                          decoration: InputDecoration(
                            hintText:
                                'اكتب سببًا مختصرًا لمساعدتنا على التحسين...',
                            counterText: '',
                            fillColor: palette.inputFill,
                            isDense: true,
                          ),
                        ),
                      ],
                    ),
                  ),
                )
              : const SizedBox.shrink(),
        ),
      ],
    );
  }

  Widget _buildInput(BuildContext context) {
    final palette = context.palette;

    switch (question.type) {
      case 'stars':
        return _StarsInput(value: _numericValue, onChanged: onChanged);
      case 'emoji':
        return _EmojiInput(value: _numericValue, onChanged: onChanged);
      case 'rating':
        return _RatingInput(value: _numericValue, onChanged: onChanged);
      case 'nps':
        return _NpsInput(
          value: _numericValue,
          hasValue: value != null,
          onChanged: onChanged,
        );
      case 'yes_no':
        return _YesNoInput(value: value?.toString(), onChanged: onChanged);
      case 'multiple_choice':
        return _ChoiceInput(
          options: question.options,
          value: value?.toString(),
          onChanged: onChanged,
        );
      case 'text':
      default:
        return TextFormField(
          key: ValueKey('text_${question.id}'),
          initialValue: value?.toString() ?? '',
          onChanged: onChanged,
          minLines: 3,
          maxLines: 6,
          maxLength: 1000,
          cursorColor: palette.accent,
          onTapOutside: (_) => FocusScope.of(context).unfocus(),
          style: TextStyle(color: palette.ink, fontSize: 12.5),
          decoration: InputDecoration(
            hintText: 'اكتب ملاحظاتك أو تعليقاتك هنا...',
            counterText: '',
            fillColor: palette.inputFill,
            alignLabelWithHint: true,
          ),
        );
    }
  }
}

/// Pill that names the currently selected score.
class _ScoreLabel extends StatelessWidget {
  const _ScoreLabel({super.key, required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return AnimatedContainer(
      duration: const Duration(milliseconds: 180),
      padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 5),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(30),
        border: Border.all(color: color.withValues(alpha: 0.35)),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 11.5,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _StarsInput extends StatelessWidget {
  const _StarsInput({required this.value, required this.onChanged});

  final int value;
  final ValueChanged<dynamic> onChanged;

  static const _labels = ['سيء جدًا', 'سيء', 'مقبول', 'جيد', 'ممتاز'];

  @override
  Widget build(BuildContext context) {
    final hasValue = value >= 1 && value <= 5;
    final palette = context.palette;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: List.generate(5, (index) {
            final star = index + 1;
            final selected = value >= star;
            return Semantics(
              button: true,
              selected: value == star,
              label: '${_labels[index]} - $star من 5',
              child: InkResponse(
                onTap: () {
                  HapticFeedback.lightImpact();
                  onChanged(star);
                },
                radius: 30,
                child: AnimatedScale(
                  scale: selected ? 1 : 0.92,
                  duration: const Duration(milliseconds: 160),
                  curve: Curves.easeOutBack,
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 180),
                    width: 50,
                    height: 50,
                    decoration: BoxDecoration(
                      color: selected
                          ? appGold.withValues(alpha: 0.12)
                          : palette.surfaceAlt,
                      shape: BoxShape.circle,
                      border: Border.all(
                        color: selected
                            ? appGold.withValues(alpha: 0.4)
                            : palette.line,
                      ),
                    ),
                    child: Icon(
                      selected
                          ? Icons.star_rounded
                          : Icons.star_outline_rounded,
                      size: 30,
                      color: selected ? appGold : palette.faint,
                    ),
                  ),
                ),
              ),
            );
          }),
        ),
        const SizedBox(height: 10),
        AnimatedSwitcher(
          duration: const Duration(milliseconds: 180),
          child: hasValue
              ? _ScoreLabel(
                  key: ValueKey('stars-$value'),
                  label: _labels[value - 1],
                  color: scoreColor(value),
                )
              : Text(
                  'اضغط على النجوم لتقييم الخدمة',
                  key: const ValueKey('stars-empty'),
                  style: TextStyle(color: palette.faint, fontSize: 11),
                ),
        ),
      ],
    );
  }
}

class _EmojiInput extends StatelessWidget {
  const _EmojiInput({required this.value, required this.onChanged});

  final int value;
  final ValueChanged<dynamic> onChanged;

  static const _emojis = ['😡', '🙁', '😐', '🙂', '😍'];
  static const _labels = ['غاضب', 'غير راضٍ', 'محايد', 'راضٍ', 'سعيد'];

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;

    return Row(
      children: List.generate(5, (index) {
        final score = index + 1;
        final selected = value == score;
        final color = scoreColor(score);

        return Expanded(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 3),
            child: Semantics(
              button: true,
              selected: selected,
              label: _labels[index],
              child: InkWell(
                borderRadius: BorderRadius.circular(16),
                onTap: () {
                  HapticFeedback.selectionClick();
                  onChanged(score);
                },
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 180),
                  padding: const EdgeInsets.symmetric(vertical: 9),
                  decoration: BoxDecoration(
                    color: selected
                        ? color.withValues(alpha: 0.1)
                        : palette.surfaceAlt,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(
                      color: selected
                          ? color.withValues(alpha: 0.55)
                          : palette.line,
                      width: selected ? 1.6 : 1,
                    ),
                  ),
                  child: Column(
                    children: [
                      AnimatedScale(
                        scale: selected ? 1.15 : 1,
                        duration: const Duration(milliseconds: 180),
                        curve: Curves.easeOutBack,
                        child: Text(
                          _emojis[index],
                          style: const TextStyle(fontSize: 26),
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        _labels[index],
                        textAlign: TextAlign.center,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: selected ? color : palette.faint,
                          fontSize: 9.5,
                          fontWeight: selected
                              ? FontWeight.w800
                              : FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        );
      }),
    );
  }
}

class _RatingInput extends StatelessWidget {
  const _RatingInput({required this.value, required this.onChanged});

  final int value;
  final ValueChanged<dynamic> onChanged;

  static const _labels = ['سيء جدًا', 'سيء', 'مقبول', 'جيد', 'ممتاز'];

  @override
  Widget build(BuildContext context) {
    final hasValue = value >= 1 && value <= 5;
    final palette = context.palette;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: List.generate(5, (index) {
            final score = index + 1;
            final selected = value == score;

            return Expanded(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 3),
                child: Semantics(
                  button: true,
                  selected: selected,
                  label: '$_labels[index] - $score',
                  child: InkWell(
                    borderRadius: BorderRadius.circular(14),
                    onTap: () {
                      HapticFeedback.selectionClick();
                      onChanged(score);
                    },
                    child: AnimatedContainer(
                      duration: const Duration(milliseconds: 170),
                      height: 50,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        gradient: selected
                            ? LinearGradient(
                                begin: Alignment.topCenter,
                                end: Alignment.bottomCenter,
                                colors: [
                                  scoreColor(score),
                                  Color.lerp(
                                        scoreColor(score),
                                        Colors.black,
                                        0.15,
                                      ) ??
                                      scoreColor(score),
                                ],
                              )
                            : null,
                        color: selected ? null : palette.surface,
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(
                          color: selected ? scoreColor(score) : palette.line,
                          width: selected ? 1.4 : 1,
                        ),
                        boxShadow: selected
                            ? [
                                BoxShadow(
                                  color: scoreColor(
                                    score,
                                  ).withValues(alpha: 0.28),
                                  blurRadius: 12,
                                  offset: const Offset(0, 5),
                                ),
                              ]
                            : null,
                      ),
                      child: Text(
                        '$score',
                        style: TextStyle(
                          color: selected ? Colors.white : palette.ink,
                          fontSize: 17,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            );
          }),
        ),
        const SizedBox(height: 10),
        AnimatedSwitcher(
          duration: const Duration(milliseconds: 180),
          child: hasValue
              ? _ScoreLabel(
                  key: ValueKey('rating-$value'),
                  label: _labels[value - 1],
                  color: scoreColor(value),
                )
              : Text(
                  'اختر تقييمًا من 1 إلى 5',
                  key: const ValueKey('rating-empty'),
                  style: TextStyle(color: palette.faint, fontSize: 11),
                ),
        ),
      ],
    );
  }
}

class _NpsInput extends StatelessWidget {
  const _NpsInput({
    required this.value,
    required this.hasValue,
    required this.onChanged,
  });

  final int value;
  final bool hasValue;
  final ValueChanged<dynamic> onChanged;

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Wrap(
          spacing: 6,
          runSpacing: 6,
          children: List.generate(11, (score) {
            final selected = hasValue && value == score;
            final color = scoreColor(score, min: 0, max: 10);

            return Semantics(
              button: true,
              selected: selected,
              label: '$score من 10',
              child: InkWell(
                borderRadius: BorderRadius.circular(11),
                onTap: () {
                  HapticFeedback.selectionClick();
                  onChanged(score);
                },
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 160),
                  width: 42,
                  height: 42,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: selected ? color : palette.surface,
                    borderRadius: BorderRadius.circular(11),
                    border: Border.all(color: selected ? color : palette.line),
                    boxShadow: selected
                        ? [
                            BoxShadow(
                              color: color.withValues(alpha: 0.3),
                              blurRadius: 10,
                              offset: const Offset(0, 4),
                            ),
                          ]
                        : null,
                  ),
                  child: Text(
                    '$score',
                    style: TextStyle(
                      color: selected ? Colors.white : palette.ink,
                      fontSize: 13.5,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ),
            );
          }),
        ),
        const SizedBox(height: 9),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              'غير محتمل إطلاقًا',
              style: TextStyle(fontSize: 10.5, color: palette.faint),
            ),
            Text(
              'محتمل جدًا',
              style: TextStyle(fontSize: 10.5, color: palette.faint),
            ),
          ],
        ),
      ],
    );
  }
}

class _YesNoInput extends StatelessWidget {
  const _YesNoInput({required this.value, required this.onChanged});

  final String? value;
  final ValueChanged<dynamic> onChanged;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: _BinaryButton(
            label: 'نعم',
            icon: Icons.check_rounded,
            selected: value == 'yes',
            color: appTeal,
            onTap: () {
              HapticFeedback.selectionClick();
              onChanged('yes');
            },
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: _BinaryButton(
            label: 'لا',
            icon: Icons.close_rounded,
            selected: value == 'no',
            color: appRose,
            onTap: () {
              HapticFeedback.selectionClick();
              onChanged('no');
            },
          ),
        ),
      ],
    );
  }
}

class _BinaryButton extends StatelessWidget {
  const _BinaryButton({
    required this.label,
    required this.icon,
    required this.selected,
    required this.color,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final bool selected;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;

    return Semantics(
      button: true,
      selected: selected,
      label: label,
      child: InkWell(
        borderRadius: BorderRadius.circular(15),
        onTap: onTap,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 170),
          padding: const EdgeInsets.symmetric(vertical: 13),
          decoration: BoxDecoration(
            color: selected ? color : palette.surface,
            borderRadius: BorderRadius.circular(15),
            border: Border.all(
              color: selected ? color : palette.line,
              width: selected ? 1.4 : 1,
            ),
            boxShadow: selected
                ? [
                    BoxShadow(
                      color: color.withValues(alpha: 0.28),
                      blurRadius: 12,
                      offset: const Offset(0, 5),
                    ),
                  ]
                : null,
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 22,
                height: 22,
                decoration: BoxDecoration(
                  color: selected
                      ? Colors.white.withValues(alpha: 0.22)
                      : palette.surfaceAlt,
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  icon,
                  size: 15,
                  color: selected ? Colors.white : palette.faint,
                ),
              ),
              const SizedBox(width: 8),
              Text(
                label,
                style: TextStyle(
                  color: selected ? Colors.white : palette.muted,
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ChoiceInput extends StatelessWidget {
  const _ChoiceInput({
    required this.options,
    required this.value,
    required this.onChanged,
  });

  final List<QuestionOption> options;
  final String? value;
  final ValueChanged<dynamic> onChanged;

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;

    if (options.isEmpty) {
      return Text(
        'لا توجد خيارات متاحة لهذا السؤال.',
        style: TextStyle(color: palette.faint, fontSize: 12),
      );
    }

    return Column(
      children: options.map((option) {
        final selected = value == option.value;

        return Padding(
          padding: const EdgeInsets.only(bottom: 8),
          child: Semantics(
            button: true,
            selected: selected,
            label: option.label,
            child: InkWell(
              borderRadius: BorderRadius.circular(15),
              onTap: () {
                HapticFeedback.selectionClick();
                onChanged(option.value);
              },
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 160),
                padding: const EdgeInsets.symmetric(
                  horizontal: 13,
                  vertical: 13,
                ),
                decoration: BoxDecoration(
                  color: selected ? palette.mint : palette.surface,
                  borderRadius: BorderRadius.circular(15),
                  border: Border.all(
                    color: selected ? palette.accent : palette.line,
                    width: selected ? 1.5 : 1,
                  ),
                ),
                child: Row(
                  children: [
                    AnimatedContainer(
                      duration: const Duration(milliseconds: 160),
                      width: 22,
                      height: 22,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: selected ? palette.accent : Colors.transparent,
                        border: Border.all(
                          color: selected ? palette.accent : palette.line,
                          width: 1.5,
                        ),
                      ),
                      child: selected
                          ? const Icon(
                              Icons.check,
                              size: 14,
                              color: Colors.white,
                            )
                          : null,
                    ),
                    const SizedBox(width: 11),
                    Expanded(
                      child: Text(
                        option.label,
                        style: TextStyle(
                          color: selected ? palette.accent : palette.ink,
                          fontSize: 13,
                          fontWeight: selected
                              ? FontWeight.w800
                              : FontWeight.w500,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        );
      }).toList(),
    );
  }
}
