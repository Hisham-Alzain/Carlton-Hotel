import 'package:flutter/material.dart';

class CustomProgressIndicator extends StatelessWidget {
  final double? value;

  const CustomProgressIndicator({super.key, this.value});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(10, 20, 10, 20),
      child: SizedBox(
        height: 10,
        width: 30,
        child: LinearProgressIndicator(
          value: value,
          // color: AppColors.primaryColor,
          // backgroundColor: AppColors.grey3,
        ),
      ),
    );
  }
}

class CustomIndicator extends StatelessWidget {
  final double? value;

  const CustomIndicator({super.key, this.value});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 5,
      width: 150,
      child: LinearProgressIndicator(
        value: value,
        // color: AppColors.primaryColor,
      ),
    );
  }
}
