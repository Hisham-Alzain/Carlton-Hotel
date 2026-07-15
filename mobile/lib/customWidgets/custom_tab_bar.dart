import 'package:flutter/material.dart';

class CustomTabBar extends StatelessWidget {
  final TabController controller;
  final List<String> labels;

  const CustomTabBar({
    required this.controller,
    required this.labels,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return TabBar(
      controller: controller,
      isScrollable: true,
      tabAlignment: TabAlignment.start,
      padding: EdgeInsets.zero,
      labelPadding: const EdgeInsetsDirectional.only(end: 24),
      tabs: [for (final label in labels) Tab(text: label)],
    );
  }
}
