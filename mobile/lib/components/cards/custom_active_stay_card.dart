import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/customWidgets/custom_outlined_button.dart';
import 'package:carlton/customWidgets/custom_texts.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

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
    final TextTheme textStyle = Get.textTheme;

    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.white, width: 1.5),
      ),
      child: Column(
        children: [
          ColoredBox(
            color: AppColors.primary,
            child: IntrinsicHeight(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Flexible(
                    flex: 3,
                    child: Padding(
                      padding: const EdgeInsets.all(10),
                      child: Column(
                        spacing: 10,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Container(
                            height: 30,
                            alignment: Alignment.center,
                            decoration: BoxDecoration(
                              color: AppColors.sandBeige,
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              mainAxisSize: MainAxisSize.min,
                              spacing: 10,
                              children: [
                                SvgPicture.asset('assets/icons/bed.svg'),
                                Flexible(
                                  child: Text(
                                    '${stay.subtitle ?? stay.roomName}, Checked In',
                                    overflow: TextOverflow.ellipsis,
                                    style: textStyle.labelSmall?.copyWith(
                                      fontWeight: FontWeight.w700,
                                      color: AppColors.espressoBrown,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),

                          Text(
                            stay.roomName,
                            style: textStyle.titleMedium?.copyWith(
                              fontWeight: FontWeight.w600,
                              color: Colors.white,
                            ),
                          ),

                          RowTextComponent(
                            spacing: 10,
                            title: 'Checked in since',
                            text: stay.checkedInSince ?? '',
                            titleStyle: textStyle.labelSmall?.copyWith(
                              fontWeight: FontWeight.w300,
                              color: Colors.white,
                            ),
                            textStyle: textStyle.labelSmall?.copyWith(
                              fontWeight: FontWeight.w700,
                              color: Colors.white,
                            ),
                          ),

                          RowTextComponent(
                            spacing: 10,
                            title: 'Nights remaining',
                            text: '${stay.nightsRemaining}',
                            titleStyle: textStyle.labelSmall?.copyWith(
                              fontWeight: FontWeight.w300,
                              color: Colors.white,
                            ),
                            textStyle: textStyle.labelSmall?.copyWith(
                              fontWeight: FontWeight.w700,
                              color: Colors.white,
                            ),
                          ),

                          const Divider(
                            height: 1,
                            thickness: 1,
                            color: AppColors.cloudGrey,
                          ),

                          Row(
                            spacing: 10,
                            children: [
                              Expanded(
                                child: _dateBox(
                                  'CHECK-IN',
                                  stay.checkInLabel ?? '',
                                ),
                              ),
                              Expanded(
                                child: _dateBox(
                                  'CHECK-OUT',
                                  stay.checkOutLabel ?? '',
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),
                  Flexible(flex: 2, child: _photo(stay.imagePath!)),
                ],
              ),
            ),
          ),
          Container(
            width: Get.width,
            color: AppColors.featherGrey,
            padding: const EdgeInsets.all(10),
            child: Column(
              spacing: 10,
              children: [
                CustomOutlinedButton(
                  height: 50,
                  backgroundColor: AppColors.white,
                  foregroundColor: AppColors.primary,
                  onPressed: onRequestService,
                  borderColor: AppColors.white39,
                  borderWidth: 0.8,
                  child: Text(
                    'REQUEST SERVICE',
                    style: textStyle.labelSmall?.copyWith(
                      color: AppColors.primary,
                    ),
                  ),
                ),
                CustomOutlinedButton(
                  height: 50,
                  backgroundColor: AppColors.chalkGrey81,
                  foregroundColor: AppColors.primary,
                  onPressed: onExpressCheckout,
                  borderColor: AppColors.white39,
                  borderWidth: 0.8,
                  child: Text(
                    'EXPRESS CHECKOUT',
                    style: textStyle.labelSmall?.copyWith(
                      color: AppColors.primary,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _photo(String path) => Stack(
    fit: StackFit.expand,
    children: [
      CustomImage(path: 'assets/images/stay_room.png', fit: BoxFit.cover),
      const DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.centerLeft,
            end: Alignment.centerRight,
            colors: [AppColors.primary50, AppColors.primary00],
          ),
        ),
      ),
    ],
  );

  Widget _dateBox(String label, String value) {
    final TextTheme textStyle = Get.textTheme;

    final parts = value.split(', ');
    final primary = parts.isNotEmpty ? parts.first : value;
    final year = parts.length > 1 ? parts[1] : '';
    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: AppColors.petrolTeal,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: AppColors.espressoInk08, width: 1),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          Text(
            label,
            style: textStyle.labelSmall?.copyWith(
              fontSize: 8,
              fontFamily: 'DM Sans',
              fontWeight: FontWeight.w600,
              color: AppColors.antiqueGold,
            ),
          ),

          Text(
            primary,
            style: textStyle.labelLarge?.copyWith(
              fontFamily: 'DM Sans',
              fontWeight: FontWeight.w600,
              color: Colors.white,
            ),
          ),

          Text(
            year,
            style: textStyle.labelSmall?.copyWith(
              fontSize: 8,
              fontFamily: 'DM Sans',
              fontWeight: FontWeight.w600,
              color: AppColors.antiqueGold,
            ),
          ),
        ],
      ),
    );
  }
}
