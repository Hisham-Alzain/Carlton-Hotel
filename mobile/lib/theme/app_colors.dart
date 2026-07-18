import 'package:flutter/material.dart';

/// The single app-wide palette. Names are semantic and screen-agnostic —
/// no per-screen prefixes and no duplicate values, so every screen pulls
/// from the same small set of named colors.
abstract class AppColors {
  // ── Brand ────────────────────────────────────────────────────────────
  static const Color primary = Color(0xFF08414D);
  static const Color primaryTileBg = Color(0x0F08414D); // primary @ 6%
  static const Color primaryButtonBg = Color(0xE608414D); // primary @ 90%
  static const Color teal = Color(0xFF2F7D8E); // filled CTAs
  static const Color tealSoft = Color(0xFF375F67); // gradient pair of primary
  static const Color tealGlow = Color(0xFF2E7680); // splash radial center
  static const Color tealDeep = Color(0xFF06262C); // splash radial edge
  static const Color tealRing = Color(0xFF1B3339); // ring around check badge
  static const Color gold = Color(0xFFB8975A); // links, In-Progress status
  static const Color goldBadge = Color(0x8FB8975A); // gold @ 56%
  static const Color logoGradient = Color(0xFF93C2CD); // logo gradient

  // ── Surfaces ─────────────────────────────────────────────────────────
  static const Color background = Color(0xFFFCFCFC);
  static const Color surface = Colors.white;
  static const Color cardBg = Color(0xFFF3F3F3);
  static const Color cardBorder = Color(0xFFE5E5E5);
  static const Color bottomNavBg = Color(0xFFF7F7F7);
  static const Color cream = Color(0xFFF0EBE2); // filled inputs on dark
  static const Color creamOverlay = Color(0x14F0EBE2); // cream @ 8%
  static const Color creamBorder = Color(0x33F0EBE2); // cream @ 20%
  static const Color creamText = Color(0x99F0EBE2); // cream @ 60%
  static const Color greyField = Color(0xFFF3F3F3); // light input pill
  static const Color disabled = Color(0xFFBDBDBD);
  static const Color heroGlow = Color(
    0xFFF2F2F2,
  ); // soft CTA glow on hero cards

  // Sand (room pill, chip borders)
  static const Color sandPillBg = Color(0xFFE0D8C9);
  static const Color sandPillText = Color(0xFF453923);
  static const Color sandBorder = Color(0x4DB9AE99); // sand @ 30%

  // ── Text ─────────────────────────────────────────────────────────────
  static const Color textPrimary = Color(0xFF303030);
  static const Color textSecondary = Color(0xFF565656);
  static const Color textTertiary = Color(0xFF6F6F6F);
  static const Color textMuted = Color(0xFF8C7B6E);
  static const Color tileSubtitle = Color(0xFFAEA091);
  static const Color navLabel = Color(0xFF141414);
  static const Color menuText = Color(0xFF2A2A2A);
  static const Color ink = Color(0xFF1C1714); // text on cream fields
  static const Color inkHint = Color(0x801C1714); // ink @ 50%
  static const Color inkSoft = Color(0xE0141414); // navLabel @ 88%
  static const Color inkSoftHint = Color(0x80141414); // navLabel @ 50%
  static const Color textTealDark = Color(0xFF223A40);
  static const Color textOnDark = Color(0xBFF7F7F7); // light text @ 75%
  static const Color textOnDarkFaint = Color(0x80FFFFFF); // white @ 50%
  static const Color error = Color(0xFFFC8181);

  // ── Borders / overlays on dark backgrounds ───────────────────────────
  static const Color white40 = Color(0x66FFFFFF);
  static const Color white10 = Color(0x1AFFFFFF);
  static const Color borderDark = Color(0xFF353535);
  static const Color panelDark = Color(0xA3191512); // rgba(25,21,18,.64)
  static const Color scrimTop = Color(0xD4030708); // rgba(3,7,8,.83)
  static const Color scrimBottom = Color(0xE6030404); // rgba(3,4,4,.9)
  static const Color bottomNavBarShadow = Color(0x33B3B3B3);
  static const Color cardShadow = Color(0x40D3D3D3);

  // ── Progress / status ────────────────────────────────────────────────
  static const Color progressTrack = Color(0xFFEDF1F2);
  static const Color statusProgressBg = Color(0x17B8975A); // gold @ 9%
  static const Color statusProgressIconBg = Color(0x14B8975A); // gold @ 8%
  static const Color statusConfirmedBg = Color(0x174CAF50);
  static const Color statusConfirmedIconBg = Color(0x144CAF50);
  static const Color statusConfirmedText = Color(0xFF4CAF50);

  // ── Buttons ──────────────────────────────────────────────────────────
  static const Color neutralButtonBg = Color(0xE6ECECEC);
  static const Color neutralButtonText = Color(0xFF0D0D0D);
  static const Color outlinedButtonText = Color(0xFF21545F);
  static const Color outlinedButtonBorder = Color(0x63FFFFFF);
}
