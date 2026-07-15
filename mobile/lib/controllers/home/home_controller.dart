import 'dart:developer';

import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/models/home_models.dart';
import 'package:flutter/widgets.dart';
import 'package:get/get.dart';
import 'package:video_player/video_player.dart';

/// Backs the homepage (Figma "homepage" 2089:861): the looping hero video plus
/// the demo room/restaurant listings. Demo-only — the CTAs just show snackbars.
class HomeController extends GetxController with WidgetsBindingObserver {
  final List<RoomItem> rooms = DemoData.rooms;
  final List<RestaurantItem> restaurants = DemoData.restaurants;

  late VideoPlayerController videoController;
  bool isVideoReady = false;

  // The hero video only decodes while it's actually watchable: on the Home
  // tab (the keep-alive shell would otherwise keep it playing on every tab)
  // and with the app in the foreground.
  bool _tabVisible = true;
  bool _appForeground = true;

  @override
  void onInit() {
    super.onInit();
    WidgetsBinding.instance.addObserver(this);
    // Prefer the bundled hotel promo clip; if it isn't in the bundle yet,
    // fall back to the demo network clip; failing both, the hero keeps its
    // poster image.
    videoController = VideoPlayerController.asset(DemoData.heroVideoAssetPath);
    _start(videoController).catchError((Object e) {
      log('Hero video: bundled asset unavailable ($e); trying network clip');
      // If the controller was closed during the failed attempt, onClose has
      // already disposed the player — creating the network one here would
      // leak a muted looping video that nothing ever disposes.
      if (isClosed) return;
      videoController.dispose();
      videoController = VideoPlayerController.networkUrl(
        Uri.parse(DemoData.heroVideoUrl),
      );
      _start(videoController).catchError((Object e) {
        log('Hero video: network clip failed ($e); keeping poster image');
      });
    });
  }

  Future<void> _start(VideoPlayerController vc) async {
    await vc.initialize();
    vc
      ..setLooping(true)
      ..setVolume(0);
    isVideoReady = true;
    _syncPlayback();
    update();
  }

  /// The user may switch tabs or background the app while the video is still
  /// initializing, so play/pause is always derived from current visibility
  /// rather than decided once at startup.
  void _syncPlayback() {
    if (!isVideoReady) return;
    if (_tabVisible && _appForeground) {
      videoController.play();
    } else {
      videoController.pause();
    }
  }

  /// Called by [MainController] when the shell switches tabs.
  void setTabVisible(bool visible) {
    _tabVisible = visible;
    _syncPlayback();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _appForeground = state == AppLifecycleState.resumed;
    _syncPlayback();
  }

  void bookNow() => CustomSnackbars.showInfo(message: 'Booking coming soon');

  void explore() => CustomSnackbars.showInfo(message: 'Explore coming soon');

  void discoverAll(String section) =>
      CustomSnackbars.showInfo(message: '$section — coming soon');

  @override
  void onClose() {
    WidgetsBinding.instance.removeObserver(this);
    videoController.dispose();
    super.onClose();
  }
}
