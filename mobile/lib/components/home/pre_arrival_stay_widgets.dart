part of 'pre_arrival_sections.dart';

/// The barely-there courtyard photo behind the hero. At 10% it reads as
/// texture, not imagery — the tinted wash on top is what keeps the white type
/// legible over whatever the photo happens to contain.
class _StayHeroBackdrop extends StatelessWidget {
  const _StayHeroBackdrop();

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: Opacity(
        opacity: 0.1,
        child: Stack(
          fit: StackFit.expand,
          children: [
            // Full-bleed, and the bundled export is a 4x Figma asset — decode
            // it at screen width so it does not sit in memory at native size.
            Image.asset(
              'assets/images/room_classic_courtyard.jpg',
              fit: BoxFit.cover,
              cacheWidth:
                  (MediaQuery.sizeOf(context).width *
                          MediaQuery.devicePixelRatioOf(context))
                      .round(),
            ),
            DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [
                    AppColors.iceBlue.withValues(alpha: 0.5),
                    AppColors.mistTeal.withValues(alpha: 0.5),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// A small caps label on a solid fill — the room number and the pre-check-in
/// banner. [showLiveDot] adds the pulseless status dot the teal chip carries.
class _StatusChip extends StatelessWidget {
  final String label;
  final Color background;
  final bool showLiveDot;

  const _StatusChip({
    required this.label,
    required this.background,
    this.showLiveDot = false,
  });

  @override
  Widget build(BuildContext context) {
    return PillContainer(
      backgroundColor: background,
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      radius: 6,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        spacing: 6,
        children: [
          if (showLiveDot)
            const DecoratedBox(
              decoration: BoxDecoration(
                color: AppColors.white,
                shape: BoxShape.circle,
              ),
              child: SizedBox.square(dimension: 6),
            ),
          Text(
            label,
            style: Get.textTheme.labelSmall?.copyWith(
              fontSize: 10,
              fontWeight: FontWeight.w600,
              letterSpacing: 0.6,
              color: AppColors.white,
            ),
          ),
        ],
      ),
    );
  }
}

/// One cell of the hero's 2×2 reservation grid.
class _ReservationDetailTile extends StatelessWidget {
  final String label;
  final String value;
  final String hint;

  const _ReservationDetailTile({
    required this.label,
    required this.value,
    required this.hint,
  });

  @override
  Widget build(BuildContext context) {
    return PillContainer(
      backgroundColor: AppColors.deepTeal57,
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      radius: 10,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        spacing: 2,
        children: [
          Text(
            label.toUpperCase(),
            style: Get.textTheme.labelSmall?.copyWith(
              fontSize: 9,
              letterSpacing: 0.5,
              color: AppColors.white.withValues(alpha: 0.97),
            ),
          ),
          Text(
            value,
            style: Get.textTheme.titleSmall?.copyWith(
              fontSize: 13,
              fontWeight: FontWeight.w600,
              color: AppColors.white,
            ),
          ),
          if (hint.isNotEmpty)
            Text(
              hint,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Get.textTheme.dmBodySmall?.copyWith(
                fontSize: 9,
                color: AppColors.white.withValues(alpha: 0.94),
              ),
            ),
        ],
      ),
    );
  }
}
