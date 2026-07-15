import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_outlined_button.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

const _kFontFamily = 'Plus Jakarta Sans';

class CustomHeroCard extends StatelessWidget {
  final Widget background;
  final String location;

  final List<InlineSpan> titleSpans;
  final String subtitle;
  final String primaryLabel;
  final String secondaryLabel;
  final VoidCallback? onPrimary;
  final VoidCallback? onSecondary;
  final double height;

  const CustomHeroCard({
    required this.background,
    required this.location,
    required this.titleSpans,
    required this.subtitle,
    this.primaryLabel = 'Book Now',
    this.secondaryLabel = 'Explore',
    this.onPrimary,
    this.onSecondary,
    this.height = 463,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 20),
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(borderRadius: BorderRadius.circular(20)),
      child: Stack(
        children: [
          Positioned.fill(child: background),
          const Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [Color(0x00000000), Color(0xB3000000)],
                  stops: [0.35, 1],
                ),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(20),
            child: ConstrainedBox(
              constraints: BoxConstraints(minHeight: height - 40),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.end,
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                spacing: 18,
                children: [_caption(), _ctas()],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _caption() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      spacing: 10,
      children: [
        Padding(
          // Figma's location→title gap is 13, title→subtitle is 10: the
          // Column's spacing covers 10, this padding tops up the extra 3.
          padding: const EdgeInsets.only(bottom: 3),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            spacing: 8,
            children: [
              SvgPicture.asset(
                'assets/icons/pin.svg',
                width: 10.7,
                height: 10.7,
                colorFilter: const ColorFilter.mode(
                  Colors.white,
                  BlendMode.srcIn,
                ),
              ),
              Text(
                location.toUpperCase(),
                style: const TextStyle(
                  fontSize: 9.7,
                  letterSpacing: 2.43,
                  color: Colors.white,
                ),
              ),
            ],
          ),
        ),
        Text.rich(
          TextSpan(
            style: const TextStyle(
              fontSize: 29.8,
              fontWeight: FontWeight.w300,
              height: 36 / 29.8,
              color: Colors.white,
            ),
            children: titleSpans,
          ),
        ),
        Text(
          subtitle,
          style: const TextStyle(
            fontFamily: 'Cormorant',
            fontSize: 14,
            height: 21.45 / 14,
            color: Colors.white,
          ),
        ),
      ],
    );
  }

  Widget _ctas() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      spacing: 12,
      children: [
        DecoratedBox(
          decoration: const BoxDecoration(
            boxShadow: [
              BoxShadow(
                color: Color(0xFFD4D4D4),
                blurRadius: 4,
                offset: Offset(0, 4),
              ),
            ],
          ),
          child: CustomFilledButton(
            height: 52,
            width: double.infinity,
            backgroundColor: AppColors.primaryButtonBg,
            foregroundColor: Colors.white,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(8),
            ),
            textStyle: const TextStyle(
              fontFamily: _kFontFamily,
              fontSize: 13,
              fontWeight: FontWeight.bold,
              letterSpacing: 1.3,
            ),
            onPressed: onPrimary,
            child: Text(primaryLabel.toUpperCase()),
          ),
        ),
        CustomOutlinedButton(
          height: 52,
          width: double.infinity,
          backgroundColor: const Color(0x1FFFFFFF),
          borderColor: const Color(0x63FFFFFF),
          borderWidth: 0.8,
          foregroundColor: AppColors.primary,
          elevation: 0,
          onPressed: onSecondary,
          child: Text(
            secondaryLabel.toUpperCase(),
            style: const TextStyle(
              fontFamily: _kFontFamily,
              fontSize: 13,
              fontWeight: FontWeight.w500,
              letterSpacing: 1.3,
            ),
          ),
        ),
      ],
    );
  }
}
