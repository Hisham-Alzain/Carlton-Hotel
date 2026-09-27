import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/models/promotion.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';

/// Offer detail bottom sheet, shown via `DiscoverController.openOffer`.
///
/// Follows the order `PromotionResource` defines for an offer card: the title
/// (in the sheet shell's header), then [Promotion.description] **above** the
/// banner, then [Promotion.secondaryDescription] **below** it. An earlier
/// version put the secondary copy in the header and the main description under
/// the banner — the two blocks swapped, so the offer read out of order.
/// Validity and the small print close the sheet.
class OfferTermsSheet extends StatelessWidget {
  final Promotion offer;

  const OfferTermsSheet({required this.offer, super.key});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final banner = offer.banner;
    final validity = _validityLabel;
    final description = offer.description.value;
    final secondary = offer.secondaryDescription.value;
    final terms = offer.terms.value;

    return Column(
      spacing: 20,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        // Every field below is hidden when the CMS left it blank, rather than
        // rendering an empty heading — an offer with no small print is normal.
        // Above the banner, per the resource's card mapping.
        if (description.isNotEmpty)
          Text(
            description,
            style: textStyle.bodyMedium?.copyWith(color: AppColors.inkBlack),
          ),
        if (banner != null && banner.isNotEmpty)
          ClipRRect(
            borderRadius: BorderRadius.circular(14),
            child: CustomImage(source: banner, height: 160, fit: BoxFit.cover),
          ),
        // Below the banner.
        if (secondary.isNotEmpty)
          Text(
            secondary,
            style: textStyle.bodyMedium?.copyWith(
              fontFamily: 'DM Sans',
              color: AppColors.taupeBrown,
            ),
          ),
        if (validity.isNotEmpty)
          Row(
            spacing: 10,
            children: [
              const Icon(
                Icons.event_available_outlined,
                size: 16,
                color: AppColors.walnutGold,
              ),
              Expanded(
                child: Text(
                  validity,
                  style: textStyle.labelMedium?.copyWith(
                    fontFamily: 'DM Sans',
                    color: AppColors.taupeBrown,
                  ),
                ),
              ),
            ],
          ),
        if (terms.isNotEmpty)
          Column(
            spacing: 10,
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                AppTranslations.offerTerms,
                style: textStyle.titleSmall?.copyWith(
                  color: AppColors.inkBlack,
                ),
              ),
              Text(
                terms,
                style: textStyle.bodySmall?.copyWith(
                  fontFamily: 'DM Sans',
                  color: AppColors.taupeBrown,
                ),
              ),
            ],
          ),
      ],
    );
  }

  /// "Until Dec 31, 2026" / "From Jan 1, 2026" / a full range. Either end may be
  /// null (an open-ended offer), and with neither there is nothing to say.
  String get _validityLabel {
    final from = offer.validFrom;
    final until = offer.validUntil;
    if (from != null && until != null) {
      return '${_date.format(from)} – ${_date.format(until)}';
    }
    if (until != null) return AppTranslations.validUntil(_date.format(until));
    if (from != null) return AppTranslations.validFrom(_date.format(from));
    return '';
  }

  static DateFormat get _date => DateFormat('MMM d, yyyy');
}
