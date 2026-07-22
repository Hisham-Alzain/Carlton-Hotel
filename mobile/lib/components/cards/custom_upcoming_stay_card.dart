import 'package:carlton/components/custom_status_chip.dart';
import 'package:carlton/customWidgets/custom_copy_pill.dart';
import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

/// Upcoming-stay card for the My Stays "Upcoming" tab: photo header with an
/// "Upcoming" pill and nightly-price badge, room + room number, CHECK-IN /
/// CHECK-OUT chips, a copyable reservation pill with share, and the outlined
/// Cancel Reservation button.
class CustomUpcomingStayCard extends StatelessWidget {
  final Stay stay;
  final VoidCallback onCancel;
  final VoidCallback? onShare;

  const CustomUpcomingStayCard({
    required this.stay,
    required this.onCancel,
    this.onShare,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0x7AD9D9D9), width: 1.18),
        boxShadow: const [
          BoxShadow(
            color: Color(0x52DBDBDB),
            blurRadius: 4,
            offset: Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _header(),
          Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  stay.roomName,
                  style: const TextStyle(
                    fontFamily: 'Plus Jakarta Sans',
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                    color: AppColors.navLabel,
                  ),
                ),
                if (stay.subtitle != null) ...[
                  const SizedBox(height: 3),
                  Text(
                    stay.subtitle!,
                    style: const TextStyle(
                      fontFamily: 'DM Sans',
                      fontSize: 12,
                      color: AppColors.textMuted,
                    ),
                  ),
                ],
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: _DateChip(
                        label: 'Check-in',
                        value: stay.checkInLabel ?? '',
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: _DateChip(
                        label: 'Check-out',
                        value: stay.checkOutLabel ?? '',
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                _resAndShare(),
                const SizedBox(height: 12),
                _cancelButton(),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _header() {
    return SizedBox(
      height: 90,
      width: double.infinity,
      child: Stack(
        fit: StackFit.expand,
        children: [
          if (stay.imagePath != null)
            CustomImage(path: stay.imagePath!, fit: BoxFit.cover),
          const DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [Color(0xB3EDF1F2), Color(0xB334727F)],
              ),
            ),
          ),
          Positioned(
            left: 14,
            top: 14,
            child: const CustomStatusChip.upcoming(),
          ),
          if (stay.pricePerNight != null)
            Positioned(
              right: 14,
              top: 15,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: const Color(0xE6FFFFFF),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  stay.pricePerNight!,
                  style: const TextStyle(
                    fontFamily: 'DM Sans',
                    fontSize: 15,
                    fontWeight: FontWeight.w600,
                    color: AppColors.primary,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _resAndShare() {
    return CustomCopyPill(
      value: 'Res. #${stay.resCode ?? ''}',
      copiedMessage: 'Reservation number copied',
      trailing: InkWell(
        onTap:
            onShare ??
            () => CustomSnackbars.showInfo(message: 'Sharing coming soon'),
        borderRadius: BorderRadius.circular(6),
        child: SvgPicture.asset(
          'assets/icons/share.svg',
          width: 13,
          height: 13,
          colorFilter: const ColorFilter.mode(
            AppColors.textMuted,
            BlendMode.srcIn,
          ),
        ),
      ),
    );
  }

  Widget _cancelButton() {
    return InkWell(
      onTap: onCancel,
      borderRadius: BorderRadius.circular(8),
      child: Container(
        height: 48,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: AppColors.dangerBorder, width: 1.18),
        ),
        child: const Text(
          'Cancel Reservation',
          style: TextStyle(
            fontFamily: 'DM Sans',
            fontSize: 12,
            fontWeight: FontWeight.w500,
            color: AppColors.danger,
          ),
        ),
      ),
    );
  }
}

class _DateChip extends StatelessWidget {
  final String label;
  final String value;

  const _DateChip({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
      decoration: BoxDecoration(
        color: AppColors.dateChipBg,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            label.toUpperCase(),
            style: const TextStyle(
              fontFamily: 'DM Sans',
              fontSize: 9,
              letterSpacing: 0.5,
              color: AppColors.goldDeep,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            value,
            style: const TextStyle(
              fontFamily: 'Plus Jakarta Sans',
              fontSize: 13,
              fontWeight: FontWeight.w500,
              color: AppColors.navLabel,
            ),
          ),
        ],
      ),
    );
  }
}
