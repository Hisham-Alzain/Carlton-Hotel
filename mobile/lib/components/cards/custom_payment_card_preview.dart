import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

/// The gradient credit-card visual that heads the Payment card form. Purely
/// decorative — mirrors [name] / [expiry] as the user types. Matched to Figma:
/// a 160px 155° teal→primary gradient with a chip, masked number, and
/// cardholder / expiry labels, plus two faint decorative circles.
class CustomPaymentCardPreview extends StatelessWidget {
  final String name;
  final String expiry;

  const CustomPaymentCardPreview({this.name = '', this.expiry = '', super.key});

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
                    Color(0xFF08414D),
                    Color(0xFF1C6B7A),
                    Color(0xFF2F7D8E),
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
                const Text(
                  '••••  ••••  ••••  ••••',
                  style: TextStyle(
                    fontFamily: 'DM Sans',
                    fontSize: 17,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 2,
                    color: Color(0xE6FFFFFF),
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
          color: Color(0x80FFFFFF),
        ),
      ),
      const SizedBox(height: 2),
      Text(
        value,
        style: const TextStyle(
          fontFamily: 'Plus Jakarta Sans',
          fontSize: 13,
          color: Color(0xE6FFFFFF),
        ),
      ),
    ],
  );
}
