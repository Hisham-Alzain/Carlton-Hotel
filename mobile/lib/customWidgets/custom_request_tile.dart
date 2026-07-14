import 'package:carlton/customWidgets/custom_chip.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

class CustomRequestTile extends StatelessWidget {
  final String title;
  final String detail;

  final String iconPath;
  final Color iconBackgroundColor;

  final String statusLabel;
  final Color statusTextColor;
  final Color statusBackgroundColor;

  final VoidCallback onEdit;
  final VoidCallback onCancel;

  const CustomRequestTile({
    required this.title,
    required this.detail,
    required this.iconPath,
    required this.iconBackgroundColor,
    required this.statusLabel,
    required this.statusTextColor,
    required this.statusBackgroundColor,
    required this.onEdit,
    required this.onCancel,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return CustomCard(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 30,
            height: 30,

            margin: const EdgeInsetsDirectional.only(end: 12),
            decoration: BoxDecoration(
              color: iconBackgroundColor,
              borderRadius: BorderRadius.circular(4),
            ),
            child: Center(
              child: SvgPicture.asset(iconPath, height: 14, width: 14),
            ),
          ),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              spacing: 2,
              children: [
                Text(
                  title,
                  maxLines: 2,
                  style: const TextStyle(
                    color: AppColors.textPrimary,
                    fontSize: 13,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                Text(
                  detail,
                  maxLines: 2,
                  style: const TextStyle(
                    color: AppColors.textMuted,
                    fontSize: 12,
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: CustomChip.status(
                    label: statusLabel,
                    textColor: statusTextColor,
                    backgroundColor: statusBackgroundColor,
                  ),
                ),
              ],
            ),
          ),
          PopupMenuButton<VoidCallback>(
            icon: const Icon(Icons.more_vert, color: AppColors.textMuted),
            color: Colors.white,
            elevation: 2,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
            ),
            onSelected: (action) => action(),
            itemBuilder: (context) => [
              PopupMenuItem(
                value: onEdit,
                child: _menuRow(Icons.edit_outlined, 'Edit Request', 15),
              ),
              PopupMenuItem(
                value: onCancel,
                child: _menuRow(Icons.delete_outline, 'Cancel Request', 18),
              ),
            ],
          ),
        ],
      ),
    );
  }

  /// A row in the 3-dot popover (matches the Figma "Home" menu frame): a small
  /// outline icon + label in #2A2A2A medium.
  Widget _menuRow(IconData icon, String label, double iconSize) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      spacing: 10,
      children: [
        Icon(icon, size: iconSize, color: const Color(0xFF2A2A2A)),
        Text(
          label,
          style: const TextStyle(
            color: Color(0xFF2A2A2A),
            fontSize: 14,
            fontWeight: FontWeight.w500,
          ),
        ),
      ],
    );
  }
}
