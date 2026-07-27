import 'package:flutter/material.dart';

class CustomProgressIndicator extends StatelessWidget {
  /// Completed fraction, 0..1. Null renders the indeterminate animation.
  final double? progress;

  const CustomProgressIndicator({super.key, this.progress});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(10, 20, 10, 20),
      child: SizedBox(
        height: 10,
        width: 30,
        child: LinearProgressIndicator(
          value: progress,
          // color: AppColors.primaryColor,
          // backgroundColor: AppColors.grey3,
        ),
      ),
    );
  }
}

class CustomIndicator extends StatelessWidget {
  /// Completed fraction, 0..1. Null renders the indeterminate animation.
  final double? progress;

  const CustomIndicator({super.key, this.progress});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 5,
      width: 150,
      child: LinearProgressIndicator(
        value: progress,
        // color: AppColors.primaryColor,
      ),
    );
  }
}
