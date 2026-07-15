import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

/// One icon + label pair in a [CustomListingCard]'s meta area (e.g. "45 m²",
/// "King Bed", opening hours, location). [iconPath] is a tintable SVG asset.
class CardMeta {
  final String iconPath;
  final String text;

  const CardMeta(this.iconPath, this.text);
}

class CustomListingCard extends StatelessWidget {
  final String imagePath;
  final String title;
  final String subtitle;
  final List<CardMeta> meta;
  final bool metaInRow;

  final String? priceAmount;
  final VoidCallback? onTap;
  final double width;

  const CustomListingCard({
    required this.imagePath,
    required this.title,
    required this.subtitle,
    required this.meta,
    this.metaInRow = false,
    this.priceAmount,
    this.onTap,
    this.width = 278,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: Container(
        width: width,
        clipBehavior: Clip.antiAlias,
        decoration: const BoxDecoration(
          color: AppColors.greyField,
          boxShadow: [
            BoxShadow(
              color: Color(0x40D3D3D3),
              blurRadius: 1,
              offset: Offset(0, 1),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            CustomImage(
              path: imagePath,
              width: width,
              height: 208,
              fit: BoxFit.cover,
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(18.5, 18.5, 18.5, 21.5),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w500,
                      height: 23.15 / 16,
                      color: AppColors.primary,
                    ),
                  ),
                  Text(
                    subtitle,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 12.7,
                      fontWeight: FontWeight.w300,
                      height: 1.5,
                      color: AppColors.textMuted,
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.only(top: 6),
                    child: _meta(),
                  ),
                  if (priceAmount != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 11.5),
                      child: _price(),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _meta() {
    final rows = [
      for (final m in meta)
        Row(
          mainAxisSize: MainAxisSize.min,
          spacing: 4.5,
          children: [
            SvgPicture.asset(
              m.iconPath,
              width: 11.5,
              height: 11.5,
              colorFilter: const ColorFilter.mode(
                AppColors.textMuted,
                BlendMode.srcIn,
              ),
            ),
            Text(
              m.text,
              style: const TextStyle(
                fontSize: 12.4,
                fontWeight: FontWeight.w300,
                color: AppColors.textMuted,
              ),
            ),
          ],
        ),
    ];
    if (metaInRow) {
      return Wrap(spacing: 12, runSpacing: 4, children: rows);
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      spacing: 4,
      children: rows,
    );
  }

  /// "From **$580**/night" — the amount larger and teal, per Figma 2089:991.
  Widget _price() {
    return Text.rich(
      TextSpan(
        style: const TextStyle(
          fontSize: 10.4,
          fontWeight: FontWeight.w500,
          color: AppColors.textMuted,
        ),
        children: [
          const TextSpan(text: 'From '),
          TextSpan(
            text: priceAmount,
            style: const TextStyle(
              fontSize: 20.2,
              fontWeight: FontWeight.w400,
              color: AppColors.primary,
            ),
          ),
          const TextSpan(text: '/night', style: TextStyle(fontSize: 11.6)),
        ],
      ),
    );
  }
}
