part of 'custom_active_booking_card.dart';

class _DndRow extends StatelessWidget {
  final bool doNotDisturb;
  final ValueChanged<bool> onChanged;

  const _DndRow({required this.doNotDisturb, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return PillContainer(
      backgroundColor: Colors.white.withValues(alpha: 0.08),
      radius: 10,
      child: Row(
        spacing: 10,
        children: [
          CustomIconChip(
            size: 30,
            radius: 8,
            backgroundColor: Colors.white.withValues(alpha: 0.10),
            child: const Icon(
              Icons.notifications_outlined,
              color: Colors.white,
              size: 15,
            ),
          ),
          Expanded(
            child: Column(
              spacing: 10,
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  AppTranslations.dndTitle,
                  style: textStyle.labelMedium?.copyWith(
                    fontWeight: FontWeight.w500,
                    color: AppColors.white,
                  ),
                ),
                Text(
                  doNotDisturb ? AppTranslations.dndOn : AppTranslations.dndOff,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: textStyle.dmLabelSmall?.copyWith(
                    color: Colors.white.withValues(alpha: 0.5),
                  ),
                ),
              ],
            ),
          ),
          Switch(value: doNotDisturb, onChanged: onChanged),
        ],
      ),
    );
  }
}

class _QuickAction extends StatelessWidget {
  final String iconAsset;
  final String label;
  final VoidCallback onTap;

  const _QuickAction({
    required this.iconAsset,
    required this.label,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: InkWell(
        onTap: onTap,
        child: PillContainer(
          backgroundColor: Colors.white.withValues(alpha: 0.10),
          radius: 10,
          border: Border.all(color: Colors.white.withValues(alpha: 0.15)),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            spacing: 10,
            children: [
              CustomIconChip.circle(
                size: 30,
                backgroundColor: Colors.white.withValues(alpha: 0.15),
                child: SvgPicture.asset(
                  iconAsset,
                  width: 16,
                  height: 16,
                  colorFilter: const ColorFilter.mode(
                    AppColors.white,
                    BlendMode.srcIn,
                  ),
                ),
              ),
              Text(
                label,
                style: Get.textTheme.dmLabelSmall?.copyWith(
                  fontWeight: FontWeight.w500,
                  color: Colors.white.withValues(alpha: 0.75),
                  fontSize: 8,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
