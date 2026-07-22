import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/customWidgets/custom_outlined_button.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

class CustomActiveStayCard extends StatelessWidget {
  final Stay stay;
  final VoidCallback onRequestService;
  final VoidCallback onExpressCheckout;

  const CustomActiveStayCard({
    required this.stay,
    required this.onRequestService,
    required this.onExpressCheckout,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.white, width: 1.48),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          ColoredBox(
            color: AppColors.primary,
            child: stay.imagePath == null
                ? _info()
                : Stack(
                    children: [
                      Padding(
                        padding: const EdgeInsets.only(right: 135),
                        child: _info(),
                      ),
                      Positioned(
                        top: 0,
                        right: 0,
                        bottom: 0,
                        width: 135,
                        child: _photo(stay.imagePath!),
                      ),
                    ],
                  ),
          ),
          Container(
            color: AppColors.cardBg,
            padding: const EdgeInsets.fromLTRB(18, 24, 18, 24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              spacing: 11,
              children: [
                _action(
                  'REQUEST SERVICE',
                  background: AppColors.surface,
                  onPressed: onRequestService,
                ),
                _action(
                  'EXPRESS CHECKOUT',
                  background: AppColors.neutralButtonBg,
                  onPressed: onExpressCheckout,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _info() => Padding(
    padding: const EdgeInsets.all(16),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          height: 27,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: AppColors.sandPillBg,
            borderRadius: BorderRadius.circular(8),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            mainAxisSize: MainAxisSize.min,
            spacing: 6,
            children: [
              SvgPicture.asset('assets/icons/bed.svg', width: 16, height: 16),
              Flexible(
                child: Text(
                  '${stay.subtitle ?? stay.roomName}, Checked In',
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontFamily: 'Plus Jakarta Sans',
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    color: AppColors.sandPillText,
                  ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 6),
        Text(
          stay.roomName,
          style: const TextStyle(
            fontFamily: 'Plus Jakarta Sans',
            fontSize: 16,
            fontWeight: FontWeight.w600,
            color: Colors.white,
          ),
        ),
        const SizedBox(height: 26),
        Text.rich(
          TextSpan(
            style: const TextStyle(
              fontFamily: 'Plus Jakarta Sans',
              fontSize: 11,
              fontWeight: FontWeight.w300,
              color: Colors.white,
            ),
            children: [
              const TextSpan(text: 'Checked in since '),
              TextSpan(
                text: stay.checkedInSince ?? '',
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
            ],
          ),
        ),
        const SizedBox(height: 2),
        Text.rich(
          TextSpan(
            style: const TextStyle(
              fontFamily: 'Plus Jakarta Sans',
              fontSize: 11,
              fontWeight: FontWeight.w300,
              color: Colors.white,
            ),
            children: [
              const TextSpan(text: 'Nights remaining '),
              TextSpan(
                text: '${stay.nightsRemaining ?? 0}',
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
            ],
          ),
        ),
        const SizedBox(height: 8),
        const Divider(height: 1, thickness: 1, color: AppColors.white10),
        const SizedBox(height: 8),
        Row(
          spacing: 9,
          children: [
            Expanded(child: _dateBox('CHECK-IN', stay.checkInLabel ?? '')),
            Expanded(child: _dateBox('CHECK-OUT', stay.checkOutLabel ?? '')),
          ],
        ),
      ],
    ),
  );

  Widget _photo(String path) => Stack(
    fit: StackFit.expand,
    children: [
      CustomImage(path: path, fit: BoxFit.cover),
      const DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.centerLeft,
            end: Alignment.centerRight,
            colors: [Color(0x8008414D), Color(0x0008414D)],
          ),
        ),
      ),
    ],
  );

  Widget _dateBox(String label, String value) {
    final parts = value.split(', ');
    final primary = parts.isNotEmpty ? parts.first : value;
    final year = parts.length > 1 ? parts[1] : '';
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: AppColors.tealDateBox,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: AppColors.tealDateBoxBorder, width: 0.88),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            label,
            style: const TextStyle(
              fontFamily: 'DM Sans',
              fontSize: 9,
              fontWeight: FontWeight.w600,
              letterSpacing: 0.8,
              color: AppColors.gold,
            ),
          ),
          const SizedBox(height: 3),
          Text(
            primary,
            style: const TextStyle(
              fontFamily: 'DM Sans',
              fontSize: 14,
              fontWeight: FontWeight.w600,
              color: Colors.white,
            ),
          ),
          if (year.isNotEmpty)
            Text(
              year,
              style: const TextStyle(
                fontFamily: 'DM Sans',
                fontSize: 9,
                color: AppColors.textOnDark,
              ),
            ),
        ],
      ),
    );
  }

  Widget _action(
    String label, {
    required Color background,
    required VoidCallback onPressed,
  }) => CustomOutlinedButton(
    width: double.infinity,
    height: 48,
    backgroundColor: background,
    borderColor: AppColors.outlinedButtonBorder,
    borderWidth: 0.8,
    onPressed: onPressed,
    child: Text(
      label,
      style: const TextStyle(
        fontFamily: 'Plus Jakarta Sans',
        fontSize: 10,
        fontWeight: FontWeight.w500,
        letterSpacing: 1,
        color: AppColors.primary,
      ),
    ),
  );
}
