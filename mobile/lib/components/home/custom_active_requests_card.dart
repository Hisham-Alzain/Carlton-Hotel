import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_texts.dart';
import 'package:carlton/models/service_request.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// "Active Requests" card on the reservation-state Home (Figma 2197:3262): a
/// heading, one thumbnail row per in-house request with a right-aligned status
/// pill, and a New Request button.
class CustomActiveRequestsCard extends StatelessWidget {
  final List<ServiceRequest> requests;
  final ValueChanged<ServiceRequest> onOpen;
  final VoidCallback onNewRequest;

  /// Hidden when the surrounding screen already labels the section (e.g. the
  /// Services tab, whose TabBar reads "Active Requests").
  final bool showHeading;

  const CustomActiveRequestsCard({
    required this.requests,
    required this.onOpen,
    required this.onNewRequest,
    this.showHeading = true,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Card(
      margin: const EdgeInsets.all(10),
      color: AppColors.white,
      surfaceTintColor: Colors.transparent,
      elevation: 1,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: const BorderSide(color: AppColors.black06),
      ),
      child: Padding(
        padding: const EdgeInsets.all(10),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          spacing: 10,
          children: [
            if (showHeading)
              Text(
                'Active Requests',
                style: textStyle.labelLarge?.copyWith(
                  fontWeight: FontWeight.w600,
                  color: AppColors.inkBlack,
                ),
              ),
            // The index is only used to suppress the divider above the first
            // row, so it stays inside the row rather than being interleaved
            // here.
            ...requests.indexed.map(
              (entry) => _RequestRow(
                request: entry.$2,
                onTap: () => onOpen(entry.$2),
                showDivider: entry.$1 > 0,
              ),
            ),
            CustomFilledButton(
              width: double.infinity,
              height: 50,
              onPressed: onNewRequest,
              backgroundColor: AppColors.whisperGrey,
              foregroundColor: AppColors.primary,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8),
                side: const BorderSide(color: AppColors.black10),
              ),
              // The Icon takes its colour from the button's foregroundColor.
              child: RowTextComponent(text: 'New Request', icon: Icons.add),
            ),
          ],
        ),
      ),
    );
  }
}

class _RequestRow extends StatelessWidget {
  final ServiceRequest request;
  final VoidCallback onTap;

  /// Separator above this row — false for the first one in the list.
  final bool showDivider;

  const _RequestRow({
    required this.request,
    required this.onTap,
    this.showDivider = false,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Column(
      mainAxisSize: MainAxisSize.min,
      // Load-bearing: the parent Column's spacing only sits above the divider
      // now that it lives in here, so this supplies the matching gap below it.
      // Without it the hairline would sit flush against the row's content.
      spacing: 10,
      children: [
        if (showDivider) const Divider(height: 1, color: AppColors.black06),
        // Divider stays outside the InkWell so it is not part of the tap
        // target and takes no ripple.
        InkWell(
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.all(10),
            child: Row(
              spacing: 10,
              children: [
                PillContainer(
                  width: 30,
                  height: 30,
                  radius: 8,
                  backgroundColor: AppColors.primary06,
                  // PillContainer.child is non-nullable, so a request with no icon
                  // gets an empty box rather than null.
                  child: request.iconAsset != null
                      ? Opacity(
                          opacity: 0.7,
                          child: Image.asset(
                            request.iconAsset!,
                            fit: BoxFit.contain,
                          ),
                        )
                      : const SizedBox.shrink(),
                ),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        request.title,
                        style: textStyle.labelMedium?.copyWith(
                          fontWeight: FontWeight.w500,
                          color: AppColors.inkBlack,
                        ),
                      ),
                      Text(
                        request.detail,
                        style: textStyle.labelSmall?.copyWith(
                          fontFamily: 'DM Sans',
                          color: AppColors.slateGrey,
                        ),
                      ),
                    ],
                  ),
                ),
                // EdgeInsets.all(10) is already the PillContainer default.
                PillContainer(
                  backgroundColor: request.status.bgColor,
                  radius: 4,
                  child: Text(
                    request.status.label,
                    style: textStyle.labelSmall?.copyWith(
                      fontWeight: FontWeight.w600,
                      color: request.status.textColor,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}
