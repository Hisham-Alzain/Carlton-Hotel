part of 'profile_view.dart';

/// Identity card: teal gradient with a gold hairline and the Account/Loyalty
/// glow texture; the gold-ringed initial beside the name and verified phone.
class _ProfileHero extends StatelessWidget {
  final Guest guest;

  const _ProfileHero({required this.guest});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final phone = guest.phone ?? '';

    return TealFoilCard(
      radius: 20,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          spacing: 14,
          children: [
            // Gold ring around a gold avatar — a teal one would vanish
            // into the card behind it.
            Container(
              padding: const EdgeInsets.all(3),
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(color: AppColors.sandGold, width: 1.5),
              ),
              child: CustomInitialAvatar(
                initial: guest.firstName ?? '',
                size: 58,
                backgroundColor: AppColors.antiqueGold,
                foregroundColor: AppColors.espressoBrown,
              ),
            ),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                spacing: 6,
                children: [
                  Text(
                    guest.fullName,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: textStyle.titleLarge?.copyWith(
                      fontFamily: 'The Seasons',
                      color: AppColors.white,
                      letterSpacing: 0.4,
                    ),
                  ),
                  const _GoldRule(width: 48),
                  if (phone.isNotEmpty)
                    Text(
                      phone,
                      textDirection: TextDirection.ltr,
                      overflow: TextOverflow.ellipsis,
                      style: textStyle.dmLabelMedium?.copyWith(
                        color: AppColors.white73,
                      ),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DetailsCard extends StatelessWidget {
  final List<Widget> rows;

  const _DetailsCard({required this.rows});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 4),
      decoration: BoxDecoration(
        color: AppColors.pearlCream,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.antiqueGold20),
        boxShadow: const [
          BoxShadow(
            color: AppColors.slateShadow04,
            blurRadius: 12,
            offset: Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        spacing: 2,
        children: [
          Text(
            AppTranslations.personalInformation.toUpperCase(),
            style: Get.textTheme.labelSmall?.copyWith(
              fontWeight: FontWeight.w700,
              letterSpacing: 1.6,
              color: AppColors.bronzeGold,
            ),
          ),
          for (var i = 0; i < rows.length; i++) ...[
            if (i > 0) const _GoldRule(),
            rows[i],
          ],
        ],
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  final IconData icon;
  final String label;
  final String? value;
  final Widget? field;
  final bool verified;

  /// Emails read left-to-right even in Arabic.
  final bool ltrValue;

  const _DetailRow({
    required this.icon,
    required this.label,
    required this.value,
    this.field,
    this.verified = false,
    this.ltrValue = false,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final hasValue = value != null && value!.trim().isNotEmpty;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 9),
      child: Row(
        spacing: 12,
        children: [
          Container(
            width: 36,
            height: 36,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: AppColors.antiqueGold09,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: AppColors.antiqueGold20),
            ),
            child: Icon(icon, size: 18, color: AppColors.walnutGold),
          ),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              spacing: 2,
              children: [
                Text(
                  label,
                  style: textStyle.dmLabelSmall?.copyWith(
                    color: AppColors.taupeBrown,
                  ),
                ),
                field ??
                    Text(
                      hasValue ? value! : AppTranslations.notAdded,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      textDirection: hasValue && ltrValue
                          ? TextDirection.ltr
                          : null,
                      style: textStyle.titleSmall?.copyWith(
                        fontWeight: FontWeight.w600,
                        color: hasValue
                            ? AppColors.inkBlack
                            : AppColors.stoneTaupe,
                        fontStyle: hasValue
                            ? FontStyle.normal
                            : FontStyle.italic,
                      ),
                    ),
              ],
            ),
          ),
          if (field == null && hasValue && verified)
            Tooltip(
              message: AppTranslations.verified,
              child: const Icon(
                Icons.verified,
                size: 18,
                color: AppColors.successGreen,
              ),
            ),
        ],
      ),
    );
  }
}

/// A gold hairline fading out at both ends.
class _GoldRule extends StatelessWidget {
  final double? width;

  const _GoldRule({this.width});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: width,
      height: 1,
      child: const DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: [
              AppColors.antiqueGold08,
              AppColors.antiqueGold56,
              AppColors.antiqueGold08,
            ],
          ),
        ),
      ),
    );
  }
}

class _EditButton extends GetView<ProfileController> {
  const _EditButton();

  @override
  Widget build(BuildContext context) {
    return CustomFilledButton(
      width: double.infinity,
      height: 52,
      onPressed: controller.startEditing,
      backgroundColor: AppColors.primary,
      foregroundColor: AppColors.sandGold,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: const BorderSide(color: AppColors.antiqueGold56),
      ),
      child: RowTextComponent(
        text: AppTranslations.editProfile,
        icon: Icons.edit_outlined,
        iconSize: 18,
        spacing: 8,
        mainAxisAlignment: MainAxisAlignment.center,
      ),
    );
  }
}

class _EditActions extends GetView<ProfileController> {
  const _EditActions();

  @override
  Widget build(BuildContext context) {
    return Row(
      spacing: 12,
      children: [
        Expanded(
          child: CustomFilledButton(
            height: 52,
            onPressed: controller.cancelEditing,
            backgroundColor: AppColors.white,
            foregroundColor: AppColors.primary,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(14),
              side: const BorderSide(color: AppColors.antiqueGold56),
            ),
            child: Text(AppTranslations.cancel),
          ),
        ),
        Expanded(
          child: Obx(
            () => CustomFilledButton(
              height: 52,
              isLoading: controller.isSaving.value,
              onPressed: controller.save,
              backgroundColor: AppColors.primary,
              foregroundColor: AppColors.sandGold,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14),
              ),
              child: Text(AppTranslations.saveChanges),
            ),
          ),
        ),
      ],
    );
  }
}
