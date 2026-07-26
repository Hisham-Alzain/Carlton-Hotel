import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';

enum ServiceRequestStatus {
  inProgress(
    label: 'In Progress',
    iconPath: 'assets/icons/orangeclock.svg',
    textColor: AppColors.antiqueGold,
    bgColor: AppColors.antiqueGold09,
    iconBgColor: AppColors.antiqueGold08,
  ),
  confirmed(
    label: 'Confirmed',
    iconPath: 'assets/icons/greenclock.svg',
    textColor: AppColors.successGreen,
    bgColor: AppColors.successGreen09,
    iconBgColor: AppColors.successGreen08,
  );

  const ServiceRequestStatus({
    required this.label,
    required this.iconPath,
    required this.textColor,
    required this.bgColor,
    required this.iconBgColor,
  });

  final String label;
  final String iconPath;
  final Color textColor;
  final Color bgColor;
  final Color iconBgColor;
}

class ServiceRequest {
  final String title;
  final String detail;
  final ServiceRequestStatus status;

  const ServiceRequest({
    required this.title,
    required this.detail,
    required this.status,
  });
}
