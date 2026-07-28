import 'package:carlton/customWidgets/custom_pill_button.dart';
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
      margin: EdgeInsets.zero,
      color: AppColors.white,
      surfaceTintColor: Colors.transparent,
      elevation: 0.5,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: const BorderSide(color: AppColors.black06),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          spacing: 12,
          children: [
            if (showHeading)
              Text(
                'Active Requests',
                style: textStyle.labelLarge?.copyWith(
                  fontWeight: FontWeight.w600,
                  color: AppColors.inkBlack,
                ),
              ),
            for (var i = 0; i < requests.length; i++) ...[
              if (i > 0) const Divider(height: 1, color: AppColors.black06),
              _RequestRow(
                request: requests[i],
                onTap: () => onOpen(requests[i]),
              ),
            ],
            CustomPillButton(
              label: 'New Request',
              onTap: onNewRequest,
              icon: Icons.add,
              backgroundColor: AppColors.whisperGrey,
              foregroundColor: AppColors.primary,
              borderColor: AppColors.black10,
              radius: 8,
              height: 40,
              fontSize: 13,
              expand: true,
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

  const _RequestRow({required this.request, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 9),
        child: Row(
          spacing: 10,
          children: [
            Container(
              width: 30,
              height: 30,
              padding: const EdgeInsets.all(5),
              decoration: BoxDecoration(
                color: AppColors.primary06,
                borderRadius: BorderRadius.circular(8),
              ),
              child: request.iconAsset != null
                  ? Opacity(
                      opacity: 0.7,
                      child: Image.asset(
                        request.iconAsset!,
                        fit: BoxFit.contain,
                      ),
                    )
                  : null,
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
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
              decoration: BoxDecoration(
                color: request.status.bgColor,
                borderRadius: BorderRadius.circular(4),
              ),
              child: Text(
                request.status.label,
                style: textStyle.labelSmall?.copyWith(
                  fontWeight: FontWeight.w600,
                  fontSize: 9,
                  letterSpacing: 0.72,
                  color: request.status.textColor,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
