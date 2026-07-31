import 'package:carlton/components/cards/custom_rating_label.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/customWidgets/custom_texts.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// One card for every Discover listing — rooms, restaurants, and experiences
/// share the same spine (image → title + rating → meta rows), so they use this
/// single widget instead of three near-identical ones. The variable parts are
/// optional slots:
///  - [badge]     a pill over the image (experiences' category tag);
///  - [chips]     cream amenity tags (rooms);
///  - the footer  driven by [priceLabel] / [primaryLabel] / [secondaryLabel]:
///     • priceLabel set        → divider + "$X / night" + a primary button (rooms);
///     • primary + secondary   → a two-button row (View Menu / Book Now — dining);
///     • primary only          → a single full-width button;
///     • none                  → no footer (experiences).
class CustomDiscoverCard extends StatelessWidget {
  final String imagePath;
  final String title;
  final double rating;
  final int reviews;

  /// `(iconPath, text)` meta rows, laid out in a wrap under the title.
  final List<(String, String)> meta;
  final VoidCallback onTap;

  final String? badge;
  final List<String> chips;

  final String? priceLabel;
  final String? primaryLabel;
  final VoidCallback? onPrimary;
  final String? secondaryLabel;
  final VoidCallback? onSecondary;

  const CustomDiscoverCard({
    required this.imagePath,
    required this.title,
    required this.rating,
    required this.reviews,
    required this.meta,
    required this.onTap,
    this.badge,
    this.chips = const [],
    this.priceLabel,
    this.primaryLabel,
    this.onPrimary,
    this.secondaryLabel,
    this.onSecondary,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final metaStyle = textStyle.labelSmall?.copyWith(
      fontWeight: FontWeight.w300,
      color: AppColors.graphite,
    );

    return InkWell(
      onTap: onTap,
      child: Card(
        clipBehavior: Clip.antiAlias,
        color: AppColors.white,
        shape: ContinuousRectangleBorder(
          borderRadius: BorderRadiusGeometry.circular(14),
        ),
        margin: const EdgeInsets.all(10),
        elevation: 1,
        child: SizedBox(
          width: 300,
          child: Column(
            spacing: 10,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Stack(
                children: [
                  CustomImage(
                    source: imagePath,
                    width: double.infinity,
                    height: 150,
                    fit: BoxFit.cover,
                  ),
                  if (badge != null)
                    Positioned(
                      top: 10,
                      right: 10,
                      child: PillContainer(
                        backgroundColor: AppColors.white88,
                        radius: 20,
                        child: Text(
                          badge!,
                          style: textStyle.labelSmall?.copyWith(
                            fontFamily: 'DM Sans',
                            fontWeight: FontWeight.w600,
                            color: AppColors.primary,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
              Padding(
                padding: const EdgeInsets.all(10),
                child: Column(
                  spacing: 10,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      spacing: 10,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Text(
                            title,
                            style: textStyle.labelLarge?.copyWith(
                              fontWeight: FontWeight.w600,
                              color: AppColors.inkBlack,
                            ),
                          ),
                        ),
                        CustomRatingLabel(rating: rating, reviews: reviews),
                      ],
                    ),
                    if (meta.isNotEmpty)
                      Wrap(
                        spacing: 10,
                        runSpacing: 10,
                        children: meta
                            .map(
                              (m) => RowTextComponent(
                                iconPath: m.$1,
                                iconColor: AppColors.graphite,
                                iconSize: 12,
                                spacing: 4,
                                text: m.$2,
                                textStyle: metaStyle,
                              ),
                            )
                            .toList(),
                      ),
                    if (chips.isNotEmpty)
                      Wrap(
                        spacing: 10,
                        runSpacing: 10,
                        children: chips
                            .map(
                              (label) => PillContainer(
                                backgroundColor: AppColors.pearlCream,
                                radius: 4,
                                child: Text(
                                  label,
                                  style: textStyle.labelSmall?.copyWith(
                                    fontFamily: 'DM Sans',
                                    color: AppColors.cocoaGold,
                                  ),
                                ),
                              ),
                            )
                            .toList(),
                      ),
                    ..._footer(textStyle),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  /// The optional bottom slot; empty when the card has no price and no buttons.
  List<Widget> _footer(TextTheme textStyle) {
    // Rooms: a divider, the nightly price, and a single Book button.
    if (priceLabel != null) {
      return [
        const Divider(color: AppColors.black06, height: 1),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            Text.rich(
              TextSpan(
                children: [
                  TextSpan(
                    text: priceLabel,
                    style: textStyle.titleMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                      color: AppColors.primary,
                    ),
                  ),
                  TextSpan(
                    text: ' / night',
                    style: textStyle.labelSmall?.copyWith(
                      fontFamily: 'DM Sans',
                      color: AppColors.taupeBrown,
                    ),
                  ),
                ],
              ),
            ),
            if (primaryLabel != null && onPrimary != null)
              CustomFilledButton(
                height: 50,
                backgroundColor: AppColors.lagoonTeal,
                foregroundColor: AppColors.white,
                onPressed: onPrimary,
                child: Text(primaryLabel!),
              ),
          ],
        ),
      ];
    }

    // Dining: a View Menu / Book Now pair, both filled.
    if (secondaryLabel != null && primaryLabel != null) {
      return [
        Row(
          spacing: 10,
          children: [
            Expanded(
              child: CustomFilledButton(
                width: double.infinity,
                height: 50,
                backgroundColor: AppColors.pearlCream,
                foregroundColor: AppColors.primary,
                onPressed: onSecondary,
                child: Text(secondaryLabel!),
              ),
            ),
            Expanded(
              child: CustomFilledButton(
                width: double.infinity,
                height: 50,
                backgroundColor: AppColors.lagoonTeal,
                foregroundColor: AppColors.white,
                onPressed: onPrimary,
                child: Text(primaryLabel!),
              ),
            ),
          ],
        ),
      ];
    }

    // A lone primary action.
    if (primaryLabel != null && onPrimary != null) {
      return [
        CustomFilledButton(
          width: double.infinity,
          height: 50,
          backgroundColor: AppColors.lagoonTeal,
          foregroundColor: AppColors.white,
          onPressed: onPrimary,
          child: Text(primaryLabel!),
        ),
      ];
    }

    // Experiences: no footer.
    return const [];
  }
}
