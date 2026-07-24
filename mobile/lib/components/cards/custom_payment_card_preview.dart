import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

/// The gradient credit-card visual that heads the Payment card form. Purely
/// decorative — mirrors [name] / [expiry] as the user types. Matched to Figma:
/// a 160px 155° teal→primary gradient with a chip, masked number, and
/// cardholder / expiry labels, plus two faint decorative circles.
class CustomPaymentCardPreview extends StatelessWidget {
  final String name;
  final String expiry;
  final String number;

  const CustomPaymentCardPreview({
    this.name = '',
    this.expiry = '',
    this.number = '',
    super.key,
  });

  /// 16 slots grouped in fours: typed digits fill in, the rest stay as dots so
  /// the number appears on the card as the guest writes it.
  String get _displayNumber {
    final digits = number.replaceAll(RegExp(r'\D'), '');
    final buf = StringBuffer();
    for (var i = 0; i < 16; i++) {
      buf.write(i < digits.length ? digits[i] : '•');
      if (i % 4 == 3 && i != 15) buf.write('  ');
    }
    return buf.toString();
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 160,
      width: double.infinity,
      child: Stack(
        children: [
          const Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(
                // Figma card gradient is ~155° (nearly vertical): navy for the
                // top ~60%, fading to teal only near the bottom.
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [
                    AppColors.primary,
                    AppColors.marineTeal,
                    AppColors.lagoonTeal,
                  ],
                  stops: [0, 0.6, 1],
                ),
              ),
            ),
          ),
          Positioned(right: -30, top: -30, child: _circle(120, 0x0FFFFFFF)),
          Positioned(left: -20, bottom: -20, child: _circle(90, 0x0AFFFFFF)),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SvgPicture.asset(
                  'assets/icons/card_chip.svg',
                  width: 32,
                  height: 26,
                ),
                const Spacer(),
                Text(
                  _displayNumber,
                  style: const TextStyle(
                    fontFamily: 'DM Sans',
                    fontSize: 17,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 2,
                    color: AppColors.white90,
                  ),
                ),
                const Spacer(),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    _field(
                      'Cardholder',
                      name.isEmpty ? 'YOUR NAME' : name.toUpperCase(),
                    ),
                    _field('Expires', expiry.isEmpty ? 'MM/YY' : expiry),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _circle(double size, int color) => Container(
    width: size,
    height: size,
    decoration: BoxDecoration(color: Color(color), shape: BoxShape.circle),
  );

  Widget _field(String label, String value) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    mainAxisSize: MainAxisSize.min,
    children: [
      Text(
        label.toUpperCase(),
        style: const TextStyle(
          fontFamily: 'DM Sans',
          fontSize: 9,
          letterSpacing: 0.5,
          color: AppColors.white50,
        ),
      ),
      const SizedBox(height: 2),
      Text(
        value,
        style: const TextStyle(
          fontFamily: 'Plus Jakarta Sans',
          fontSize: 13,
          color: AppColors.white90,
        ),
      ),
    ],
  );
}
