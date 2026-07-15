import 'package:carlton/customWidgets/custom_texts.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';

class CustomStayCard extends StatelessWidget {
  final String room;
  final String checkedInTime;
  final int nightsRemaining;
  final String imagePath;

  const CustomStayCard({
    required this.room,
    required this.checkedInTime,
    required this.nightsRemaining,
    required this.imagePath,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 116,

      width: double.infinity,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: AppColors.primary,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.white, width: 1.5),
      ),

      child: MediaQuery(
        data: MediaQuery.of(
          context,
        ).copyWith(textScaler: const TextScaler.linear(1.0)),
        child: Stack(
          children: [
            Positioned(
              right: 0,
              top: 0,
              bottom: 0,
              width: 144,
              child: Stack(
                fit: StackFit.expand,
                children: [
                  Image.asset(imagePath, fit: BoxFit.cover),
                  const DecoratedBox(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                        colors: [Color(0x80EDF1F2), Color(0x80347F87)],
                      ),
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                mainAxisSize: MainAxisSize.min,
                spacing: 4,
                children: [
                  _RoomPill(room: room),
                  Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: _InfoLine(
                      prefix: 'Checked in since ',
                      value: checkedInTime,
                    ),
                  ),
                  _InfoLine(
                    prefix: 'Nights remaining  ',
                    value: '$nightsRemaining',
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

class _RoomPill extends StatelessWidget {
  final String room;

  const _RoomPill({required this.room});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12),
      height: 27,
      decoration: BoxDecoration(
        color: AppColors.sandPillBg,
        borderRadius: BorderRadius.circular(8),
      ),
      child: RowTextComponent(
        path: 'assets/icons/bed.svg',
        iconSize: 16,
        spacing: 6,
        text: room,
        textStyle: const TextStyle(
          color: AppColors.sandPillText,
          fontWeight: FontWeight.bold,
          fontSize: 12,
        ),
      ),
    );
  }
}

class _InfoLine extends StatelessWidget {
  final String prefix;
  final String value;

  const _InfoLine({required this.prefix, required this.value});

  @override
  Widget build(BuildContext context) {
    return Text.rich(
      TextSpan(
        style: const TextStyle(
          color: Colors.white,
          fontSize: 11,
          fontWeight: FontWeight.w300,
          height: 1.4,
        ),
        children: [
          TextSpan(text: prefix),
          TextSpan(
            text: value,
            style: const TextStyle(fontWeight: FontWeight.bold),
          ),
        ],
      ),
      maxLines: 1,
      overflow: TextOverflow.ellipsis,
      softWrap: false,
    );
  }
}
