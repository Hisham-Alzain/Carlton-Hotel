import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';

/// The white, hairline-bordered card the Home sections (active requests,
/// current bill) sit in.
class CustomHomeCard extends StatelessWidget {
  final Widget child;

  const CustomHomeCard({required this.child, super.key});

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.all(10),
      color: AppColors.white,
      surfaceTintColor: Colors.transparent,
      elevation: 1,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: const BorderSide(color: AppColors.black06),
      ),
      child: Padding(padding: const EdgeInsets.all(10), child: child),
    );
  }
}
