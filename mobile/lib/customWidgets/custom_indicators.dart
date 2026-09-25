import 'dart:math' as math;
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/svg.dart';

// --- Stylized Linear Progress Indicators ---

class CustomProgressIndicator extends StatelessWidget {
  final double? progress;
  final Color? color;
  final Color? backgroundColor;

  const CustomProgressIndicator({
    super.key,
    this.progress,
    this.color,
    this.backgroundColor,
  });

  @override
  Widget build(BuildContext context) {
    final activeColor = color ?? AppColors.primary;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 20),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(10),
        child: SizedBox(
          height: 8,
          width: 120,
          child: LinearProgressIndicator(
            value: progress,
            color: activeColor,
            backgroundColor:
                backgroundColor ?? activeColor.withValues(alpha: 0.15),
          ),
        ),
      ),
    );
  }
}

class CustomIndicator extends StatelessWidget {
  final double? progress;
  final Color? color;

  const CustomIndicator({super.key, this.progress, this.color});

  @override
  Widget build(BuildContext context) {
    final activeColor = color ?? AppColors.primary;
    return ClipRRect(
      borderRadius: BorderRadius.circular(6),
      child: SizedBox(
        height: 6,
        width: 150,
        child: LinearProgressIndicator(
          value: progress,
          color: activeColor,
          backgroundColor: activeColor.withValues(alpha: 0.15),
        ),
      ),
    );
  }
}

// --- High-End Realistic Hovering Logo Loader ---

class LogoLoadingIndicator extends StatefulWidget {
  final String assetPath;
  final double size;
  final Color? color;
  final Color shadowColor;
  final Duration duration;

  const LogoLoadingIndicator({
    super.key,
    this.assetPath = 'assets/icons/white_logo.svg',
    this.size = 80,
    this.color,
    this.shadowColor = AppColors.primary,
    this.duration = const Duration(milliseconds: 2000),
  });

  @override
  State<LogoLoadingIndicator> createState() => _LogoLoadingIndicatorState();
}

class _LogoLoadingIndicatorState extends State<LogoLoadingIndicator>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<double> _scaleAnimation;
  late final Animation<double> _liftAnimation;
  late final Animation<double> _shadowOpacityAnimation;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(vsync: this, duration: widget.duration)
      ..repeat();

    // Scale breathing animation
    _scaleAnimation = TweenSequence<double>([
      TweenSequenceItem(
        tween: Tween(
          begin: 0.92,
          end: 1.08,
        ).chain(CurveTween(curve: Curves.easeInOutCubic)),
        weight: 50,
      ),
      TweenSequenceItem(
        tween: Tween(
          begin: 1.08,
          end: 0.92,
        ).chain(CurveTween(curve: Curves.easeInOutCubic)),
        weight: 50,
      ),
    ]).animate(_controller);

    // Vertical Y-axis lift (physically lifting off the surface)
    _liftAnimation = TweenSequence<double>([
      TweenSequenceItem(
        tween: Tween(
          begin: 0.0,
          end: -12.0,
        ).chain(CurveTween(curve: Curves.easeInOutCubic)),
        weight: 50,
      ),
      TweenSequenceItem(
        tween: Tween(
          begin: -12.0,
          end: 0.0,
        ).chain(CurveTween(curve: Curves.easeInOutCubic)),
        weight: 50,
      ),
    ]).animate(_controller);

    // Shadow opacity diminishes as logo moves higher
    _shadowOpacityAnimation = TweenSequence<double>([
      TweenSequenceItem(
        tween: Tween(
          begin: 0.38,
          end: 0.12,
        ).chain(CurveTween(curve: Curves.easeInOutCubic)),
        weight: 50,
      ),
      TweenSequenceItem(
        tween: Tween(
          begin: 0.12,
          end: 0.38,
        ).chain(CurveTween(curve: Curves.easeInOutCubic)),
        weight: 50,
      ),
    ]).animate(_controller);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _controller,
      child: SvgPicture.asset(
        widget.assetPath,
        width: widget.size,
        height: widget.size,
        colorFilter: widget.color != null
            ? ColorFilter.mode(widget.color!, BlendMode.srcIn)
            : null,
      ),
      builder: (context, child) {
        final scale = _scaleAnimation.value;
        final liftY = _liftAnimation.value;
        final shadowOpacity = _shadowOpacityAnimation.value;

        return SizedBox(
          width: widget.size * 1.3,
          height: widget.size * 1.5,
          child: Stack(
            alignment: Alignment.center,
            children: [
              // Realistic Floor Shadow
              Positioned(
                bottom: widget.size * 0.08,
                child: Transform.scale(
                  scaleX: scale * 0.85,
                  child: Container(
                    width: widget.size * 0.65,
                    height: widget.size * 0.15,
                    // BoxShape offers only `rectangle` and `circle`. An ellipse
                    // is a rectangle whose corner radii are half its own width
                    // and height — at which point the straight edges vanish.
                    // (Setting `shape` *and* `borderRadius` together asserts, so
                    // this relies on the default `BoxShape.rectangle`.)
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.all(
                        Radius.elliptical(
                          widget.size * 0.325,
                          widget.size * 0.075,
                        ),
                      ),
                      boxShadow: [
                        BoxShadow(
                          color: widget.shadowColor.withValues(
                            alpha: shadowOpacity,
                          ),
                          blurRadius: 16 * scale,
                          spreadRadius: 2,
                        ),
                      ],
                    ),
                  ),
                ),
              ),

              // Hovering & Rotating SVG Logo
              Transform.translate(
                offset: Offset(0, liftY),
                child: Transform.scale(
                  scale: scale,
                  child: Transform.rotate(
                    angle: _controller.value * 2 * math.pi,
                    child: child,
                  ),
                ),
              ),
            ],
          ),
        );
      },
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
      ..repeat();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return RotationTransition(
      turns: _controller,
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
