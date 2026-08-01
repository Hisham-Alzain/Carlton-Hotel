import 'package:flutter/material.dart';
import 'package:flutter_svg/svg.dart';

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

class SpinningIconIndicator extends StatefulWidget {
  final String assetPath;
  final Color? color;
  final double size;
  final Duration duration;

  const SpinningIconIndicator({
    super.key,
    this.assetPath = 'assets/icons/white_logo.svg',
    this.color,
    this.size = 50,
    this.duration = const Duration(milliseconds: 1200),
  });

  @override
  State<SpinningIconIndicator> createState() => _SpinningIconIndicatorState();
}

class _SpinningIconIndicatorState extends State<SpinningIconIndicator>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(vsync: this, duration: widget.duration)
      ..repeat(); // linear, indeterminate spin
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return RotationTransition(
      turns: _controller, // 0 -> 1 = one full rotation per cycle
      child: SvgPicture.asset(
        widget.assetPath,
        width: widget.size,
        height: widget.size,
        colorFilter: widget.color != null
            ? ColorFilter.mode(widget.color!, BlendMode.srcIn)
            : null,
      ),
    );
  }
}
