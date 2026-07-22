import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_svg/flutter_svg.dart';

/// Cream pill showing a reference/confirmation code with a copy affordance,
/// used in the Receipt sheet and the Upcoming stay card. Tapping copies [value]
/// to the clipboard and confirms with a snackbar. Pass [trailing] to add a
/// second action (e.g. share) alongside the copy control.
class CustomCopyPill extends StatelessWidget {
  final String value;
  final String copyLabel;
  final String copiedMessage;
  final Widget? trailing;

  const CustomCopyPill({
    required this.value,
    this.copyLabel = 'Copy ref',
    this.copiedMessage = 'Reference copied to clipboard',
    this.trailing,
    super.key,
  });

  Future<void> _copy() async {
    await Clipboard.setData(ClipboardData(text: value));
    CustomSnackbars.showSuccess(message: copiedMessage);
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: AppColors.cream,
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Flexible(
            child: Text(
              value,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                fontFamily: 'Plus Jakarta Sans',
                fontSize: 14,
                fontWeight: FontWeight.w700,
                letterSpacing: 0.8,
                color: AppColors.primary,
              ),
            ),
          ),
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              InkWell(
                onTap: _copy,
                borderRadius: BorderRadius.circular(6),
                child: Padding(
                  padding: const EdgeInsets.all(2),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      SvgPicture.asset(
                        'assets/icons/copy.svg',
                        width: 13,
                        height: 13,
                        colorFilter: const ColorFilter.mode(
                          AppColors.textMuted,
                          BlendMode.srcIn,
                        ),
                      ),
                      const SizedBox(width: 4),
                      Text(
                        copyLabel,
                        style: const TextStyle(
                          fontFamily: 'DM Sans',
                          fontSize: 11,
                          fontWeight: FontWeight.w500,
                          color: AppColors.textMuted,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              if (trailing != null) ...[const SizedBox(width: 10), trailing!],
            ],
          ),
        ],
      ),
    );
  }
}
