import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:carlton/customWidgets/custom_texts.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

class CustomRequestCard extends StatelessWidget {
  final String title;
  final String subtitle;

  final String iconPath;
  final Color iconBackgroundColor;

  final String statusLabel;
  final Color statusTextColor;
  final Color statusBackgroundColor;

  final VoidCallback onEdit;
  final VoidCallback onCancel;

  const CustomRequestCard({
    required this.title,
    required this.subtitle,
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
    final TextTheme textStyle = Get.textTheme;

    return Card(
      margin: EdgeInsets.all(10),
      child: Padding(
        padding: const EdgeInsets.all(10),
        child: Row(
          spacing: 10,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 30,
              height: 30,
              margin: const EdgeInsetsDirectional.all(10),
              decoration: BoxDecoration(
                color: iconBackgroundColor,
                borderRadius: BorderRadius.circular(4),
              ),
              child: Center(
                child: SvgPicture.asset(iconPath, height: 15, width: 15),
              ),
            ),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                spacing: 10,
                children: [
                  Text(
                    title,
                    maxLines: 2,
                    style: textStyle.labelLarge?.copyWith(
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  Text(
                    subtitle,
                    maxLines: 2,
                    style: textStyle.labelMedium?.copyWith(
                      color: AppColors.taupeBrown,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  PillContainer(
                    backgroundColor: statusBackgroundColor,
                    radius: 4,
                    child: Text(
                      statusLabel,
                      style: textStyle.labelSmall?.copyWith(
                        color: statusTextColor,
                      ),
                    ),
                  ),
                ],
              ),
            ),

            PopupMenuButton<VoidCallback>(
              icon: const Icon(Icons.more_vert),
              onSelected: (action) => action(),
              itemBuilder: (context) => [
                PopupMenuItem(
                  value: onEdit,
                  child: _menuRow(Icons.edit_outlined, 'Edit Request', 15),
                ),
                PopupMenuItem(
                  value: onCancel,
                  child: _menuRow(Icons.delete_outline, 'Cancel Request', 15),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  /// A row in the 3-dot popover (matches the Figma "Home" menu frame): a small
  /// outline icon + label in #2A2A2A medium.
  Widget _menuRow(IconData icon, String label, double iconSize) {
    final TextTheme textStyle = Get.textTheme;
    return RowTextComponent(
      text: label,
      icon: icon,
      iconSize: iconSize,
      iconColor: AppColors.charcoal,
      spacing: 10,
      textStyle: textStyle.labelLarge?.copyWith(color: AppColors.charcoal),
    );
  }
}
