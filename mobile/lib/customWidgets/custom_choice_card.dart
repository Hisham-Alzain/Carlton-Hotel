import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

class CustomChoiceCard extends StatelessWidget {
  final IconData? icon;
  final String? iconPath;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  const CustomChoiceCard({
    required this.title,
    required this.subtitle,
    required this.onTap,
    this.icon,
    this.iconPath,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: Container(
        padding: const EdgeInsets.all(21),
        decoration: BoxDecoration(
          color: const Color(0x14F0EBE2),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: const Color(0x33F0EBE2)),
        ),
        child: Row(
          children: [
            Container(
              width: 44,
              height: 44,
              alignment: Alignment.center,

              margin: const EdgeInsetsDirectional.only(end: 16),
              decoration: BoxDecoration(
                color: const Color(0x8FB8975A),
                borderRadius: BorderRadius.circular(10),
              ),
              child: iconPath != null
                  ? SvgPicture.asset(iconPath!, width: 22, height: 22)
                  : Icon(icon, color: Colors.white, size: 22),
            ),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                spacing: 3,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 15,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  Text(
                    subtitle,
                    style: const TextStyle(
                      color: Color(0x99F0EBE2), // .6 alpha
                      fontSize: 12,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right, color: Colors.white, size: 20),
          ],
        ),
      ),
    );
  }
}
