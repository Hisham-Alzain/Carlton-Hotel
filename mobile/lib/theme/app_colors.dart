import 'package:flutter/material.dart';

/// Flat color palette for the app.
///
/// Constants are named after the *color*, not its usage, and grouped by hue
/// (dark -> light). Alpha variants sit directly beneath their opaque parent and
/// carry a `// @ N%` comment. `primary` is the one usage-named exception.
abstract class AppColors {
  // ── Primary ──
  static const Color primary = Color(0xFF08414D);
  static const Color primary90 = Color(0xE608414D); // @ 90%
  static const Color primary50 = Color(0x8008414D); // @ 50%
  static const Color primary08 = Color(0x1408414D); // @ 8%
  static const Color primary07 = Color(0x1208414D); // @ 7%
  static const Color primary06 = Color(0x0F08414D); // @ 6%
  static const Color primary00 = Color(0x0008414D); // @ 0%

  // ── Teal ──
  static const Color abyssTeal = Color(0xFF06262C);
  static const Color midnightTeal = Color(0xFF14454C);
  static const Color slateTeal = Color(0xFF1B3339);
  static const Color charcoalTeal = Color(0xFF223A40);
  static const Color petrolTeal = Color(0xFF22525C);
  static const Color pineTeal = Color(0xFF21545F);
  static const Color sageTeal = Color(0xFF375F67);
  static const Color marineTeal = Color(0xFF1C6B7A);
  static const Color oceanTeal = Color(0xFF2E7680);
  static const Color steelTeal70 = Color(0xB334727F); // @ 70%
  static const Color surfTeal50 = Color(0x80347F87); // @ 50%
  static const Color lagoonTeal = Color(0xFF2F7D8E);
  static const Color mistTeal = Color(0xFF93C2CD);

  // ── Gold / brown / sand ──
  static const Color bronzeGold = Color(0xFF8C6B30);
  static const Color cocoaGold = Color(0xFF604F30);
  static const Color walnutGold = Color(0xFF735E39);
  static const Color antiqueGold = Color(0xFFB8975A);
  static const Color antiqueGold56 = Color(0x8FB8975A); // @ 56%
  static const Color antiqueGold09 = Color(0x17B8975A); // @ 9%
  static const Color antiqueGold08 = Color(0x14B8975A); // @ 8%
  static const Color harvestGold46 = Color(0x75AE9567); // @ 46%
  static const Color espressoBrown = Color(0xFF453923);
  static const Color taupeBrown = Color(0xFF8C7B6E);
  static const Color stoneTaupe = Color(0xFFAEA091);
  static const Color linenTaupe30 = Color(0x4DB9AE99); // @ 30%
  static const Color sandBeige = Color(0xFFE0D8C9);
  static const Color cream = Color(0xFFF0EBE2);
  static const Color cream60 = Color(0x99F0EBE2); // @ 60%
  static const Color cream20 = Color(0x33F0EBE2); // @ 20%
  static const Color cream08 = Color(0x14F0EBE2); // @ 8%
  static const Color pearlCream = Color(0xFFF8F6F3);
  static const Color pearlCream65 = Color(0xA6F8F6F3); // @ 65%
  static const Color ivoryCream = Color(0xFFFDFAF4);
  static const Color oliveTaupe = Color(0xFF5C5548);

  // ── Ink / grey ──
  static const Color nearBlack = Color(0xFF0D0D0D);
  static const Color inkBlack = Color(0xFF141414);
  static const Color inkBlack88 = Color(0xE0141414); // @ 88%
  static const Color inkBlack50 = Color(0x80141414); // @ 50%
  static const Color espressoInk = Color(0xFF1C1714);
  static const Color espressoInk50 = Color(0x801C1714); // @ 50%
  static const Color espressoInk08 = Color(0x141A1814); // @ 8%
  static const Color coffeeInk64 = Color(0xA3191512); // @ 64%
  static const Color charcoal = Color(0xFF2A2A2A);
  static const Color graphite = Color(0xFF2C2C2C);
  static const Color slateGrey = Color(0xFF303030);
  static const Color ironGrey = Color(0xFF353535);
  static const Color dimGrey = Color(0xFF555555);
  static const Color ashGrey = Color(0xFF565656);
  static const Color steelGrey = Color(0xFF5D5D5D);
  static const Color mediumGrey = Color(0xFF6F6F6F);
  static const Color slateShadow04 = Color(0x0A6B6B6B); // @ 4%
  static const Color shadowGrey25 = Color(0x40888888); // @ 25%
  static const Color ashShadow20 = Color(0x33B3B3B3); // @ 20%
  static const Color silverGrey = Color(0xFFBDBDBD);
  static const Color pearlGrey = Color(0xFFCCCCCC);
  static const Color silverShadow25 = Color(0x40D3D3D3); // @ 25%
  static const Color mistGrey = Color(0xFFD4D4D4);
  static const Color fogGrey35 = Color(0x59D9D0D0); // @ 35%
  static const Color cloudGrey = Color(0xFFD9D9D9);
  static const Color cloudGrey48 = Color(0x7AD9D9D9); // @ 48%
  static const Color pebbleGrey73 = Color(0xBADDDDDD); // @ 73%
  static const Color pebbleGrey32 = Color(0x52DBDBDB); // @ 32%
  static const Color smokeGrey = Color(0xFFE0E0E0);
  static const Color linenGrey = Color(0xFFE5E5E5);
  static const Color platinumGrey90 = Color(0xE6ECECEC); // @ 90%
  static const Color platinumGrey56 = Color(0x8FECECEC); // @ 56%
  static const Color paperGrey92 = Color(0xEBEEEEEE); // @ 92%
  static const Color chalkGrey81 = Color(0xCECECECC); // @ 81%
  static const Color whisperGrey = Color(0xFFF2F2F2);
  static const Color featherGrey = Color(0xFFF3F3F3);
  static const Color frostGrey = Color(0xFFF6F6F6);
  static const Color snowGrey = Color(0xFFF7F7F7);
  static const Color snowGrey75 = Color(0xBFF7F7F7); // @ 75%
  static const Color ghostWhite = Color(0xFFFCFCFC);

  // ── Cool light ──
  static const Color iceBlue = Color(0xFFEDF1F2);
  static const Color iceBlue70 = Color(0xB3EDF1F2); // @ 70%
  static const Color iceBlue50 = Color(0x80EDF1F2); // @ 50%

  // ── Black overlays / scrims ──
  static const Color nightScrim90 = Color(0xE6030404); // @ 90%
  static const Color duskScrim83 = Color(0xD4030708); // @ 83%
  static const Color black70 = Color(0xB3000000); // @ 70%
  static const Color black10 = Color(0x1A000000); // @ 10%
  static const Color black08 = Color(0x14000000); // @ 8%
  static const Color black07 = Color(0x12000000); // @ 7%
  static const Color black06 = Color(0x0F000000); // @ 6%
  static const Color black05 = Color(0x0D000000); // @ 5%
  static const Color black04 = Color(0x0A000000); // @ 4%

  // ── White ──
  static const Color white = Color(0xFFFFFFFF);
  static const Color white92 = Color(0xEAFFFFFF); // @ 92%
  static const Color white90 = Color(0xE6FFFFFF); // @ 90%
  static const Color white88 = Color(0xE0FFFFFF); // @ 88%
  static const Color white73 = Color(0xBAFFFFFF); // @ 73%
  static const Color white50 = Color(0x80FFFFFF); // @ 50%
  static const Color white48 = Color(0x7AFFFFFF); // @ 48%
  static const Color white40 = Color(0x66FFFFFF); // @ 40%
  static const Color white39 = Color(0x63FFFFFF); // @ 39%
  static const Color white25 = Color(0x40FFFFFF); // @ 25%
  static const Color white10 = Color(0x1AFFFFFF); // @ 10%

  // ── Green ──
  static const Color forestGreen = Color(0xFF19541C);
  static const Color successGreen = Color(0xFF4CAF50);
  static const Color successGreen09 = Color(0x174CAF50); // @ 9%
  static const Color successGreen08 = Color(0x144CAF50); // @ 8%
  static const Color successGreen07 = Color(0x124CAF50); // @ 7%

  // ── Red ──
  static const Color brickRed = Color(0xFFC0392B);
  static const Color crimsonRed30 = Color(0x4DDC3C3C); // @ 30%
  static const Color crimsonRed10 = Color(0x19DC3C3C); // @ 10%
  static const Color crimsonRed08 = Color(0x14DC3C3C); // @ 8%
  static const Color salmonRed = Color(0xFFFC8181);
}
