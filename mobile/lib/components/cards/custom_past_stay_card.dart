import 'package:carlton/components/custom_status_chip.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';

/// Past-stay card for the My Stays "Past" tab: room + dates + COMPLETED chip,
/// total charged, and the View Receipt / Book Again button row. Kept as a
/// feature component so `StaysView` stays declarative.
class CustomPastStayCard extends StatelessWidget {
  final Stay stay;
  final VoidCallback onViewReceipt;
  final VoidCallback onBookAgain;

  const CustomPastStayCard({
    required this.stay,
    required this.onViewReceipt,
    required this.onBookAgain,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(15.18),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.pastCardBorder, width: 1.18),
        boxShadow: const [
          BoxShadow(
            color: Color(0x59E2E2E2),
            blurRadius: 4,
            offset: Offset(0, 1),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      stay.roomName,
                      style: const TextStyle(
                        fontFamily: 'Plus Jakarta Sans',
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                        color: AppColors.navLabel,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      stay.dateRangeLabel ?? '',
                      style: const TextStyle(
                        fontFamily: 'DM Sans',
                        fontSize: 12,
                        color: AppColors.textMuted,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              const CustomStatusChip.completed(),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Total charged',
                style: TextStyle(
                  fontFamily: 'DM Sans',
                  fontSize: 13,
                  color: AppColors.textMuted,
                ),
              ),
              Text(
                stay.totalCharged ?? '',
                style: const TextStyle(
                  fontFamily: 'Plus Jakarta Sans',
                  fontSize: 15,
                  fontWeight: FontWeight.w600,
                  color: AppColors.primary,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: _CardButton(
                  label: 'View Receipt',
                  background: AppColors.softButtonBg,
                  foreground: AppColors.navLabel,
                  onTap: onViewReceipt,
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _CardButton(
                  label: 'Book Again',
                  background: AppColors.primary,
                  foreground: Colors.white,
                  onTap: onBookAgain,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _CardButton extends StatelessWidget {
  final String label;
  final Color background;
  final Color foreground;
  final VoidCallback onTap;

  const _CardButton({
    required this.label,
    required this.background,
    required this.foreground,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: background,
      borderRadius: BorderRadius.circular(8),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(8),
        child: SizedBox(
          height: 36,
          child: Center(
            child: Text(
              label,
              style: TextStyle(
                fontFamily: 'DM Sans',
                fontSize: 11,
                fontWeight: FontWeight.w500,
                color: foreground,
              ),
            ),
          ),
        ),
      ),
    );
  }
}
