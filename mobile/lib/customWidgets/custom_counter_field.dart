import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

/// Title/subtitle row with a −/+ stepper, used for the Adults / Children guest
/// counts on Plan Your Stay. Controlled: it renders [value] and reports the
/// requested value through [onChanged], clamping to [min]..[max] itself so
/// callers never receive an out-of-range value. The − button greys out at [min]
/// and + at [max]. Styling is from Figma (white card, 12px radius, 32px round
/// buttons — teal +, grey −, primary 18px count).
class CustomCounterField extends StatelessWidget {
  final String title;
  final String? subtitle;
  final int value;
  final ValueChanged<int> onChanged;
  final int min;
  final int max;

  const CustomCounterField({
    required this.title,
    required this.value,
    required this.onChanged,
    this.subtitle,
    this.min = 0,
    this.max = 99,
    super.key,
  }) : assert(min <= max);

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final canDecrement = value > min;
    final canIncrement = value < max;

    return Container(
      padding: const EdgeInsets.all(17.18),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.hairline, width: 1.18),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0A6B6B6B),
            blurRadius: 4,
            offset: Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  title,
                  style: textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.w500,
                    color: AppColors.navLabel,
                  ),
                ),
                if (subtitle != null) ...[
                  const SizedBox(height: 2),
                  Text(
                    subtitle!,
                    style: const TextStyle(
                      fontFamily: 'DM Sans',
                      fontSize: 12,
                      color: AppColors.textMuted,
                    ),
                  ),
                ],
              ],
            ),
          ),
          Row(
            children: [
              _StepButton(
                iconPath: 'assets/icons/minus.svg',
                filled: false,
                enabled: canDecrement,
                onTap: () => onChanged(value - 1),
              ),
              const SizedBox(width: 14),
              ConstrainedBox(
                constraints: const BoxConstraints(minWidth: 20),
                child: Text(
                  '$value',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontFamily: 'Plus Jakarta Sans',
                    fontSize: 18,
                    fontWeight: FontWeight.w600,
                    color: AppColors.primary,
                  ),
                ),
              ),
              const SizedBox(width: 14),
              _StepButton(
                iconPath: 'assets/icons/plus.svg',
                filled: true,
                enabled: canIncrement,
                onTap: () => onChanged(value + 1),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _StepButton extends StatelessWidget {
  final String iconPath;
  final bool filled;
  final bool enabled;
  final VoidCallback onTap;

  const _StepButton({
    required this.iconPath,
    required this.filled,
    required this.enabled,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final bg = filled ? AppColors.primary : AppColors.neutralIconBg;
    final iconColor = filled ? Colors.white : AppColors.navLabel;
    return Opacity(
      opacity: enabled ? 1 : 0.4,
      child: Material(
        color: bg,
        shape: const CircleBorder(),
        child: InkWell(
          onTap: enabled ? onTap : null,
          customBorder: const CircleBorder(),
          child: SizedBox(
            width: 32,
            height: 32,
            child: Center(
              child: SvgPicture.asset(
                iconPath,
                width: 14,
                height: 14,
                colorFilter: ColorFilter.mode(iconColor, BlendMode.srcIn),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
