import 'package:carlton/models/pagination.dart';
import 'package:dio/dio.dart';
import 'package:get/get.dart';
import 'package:flutter/material.dart';

mixin PaginatedControllerMixin<T> on GetxController {
  // ── State ──────────────────────────────────────────────────────────────────
  final RxList<T> items = <T>[].obs;
  final RxBool loading = true.obs;
  final RxBool loadingMore = false.obs;
  final RxBool hasError = false.obs;

  Pagination _pagination = Pagination();
  bool get hasMore => _pagination.currentPage < _pagination.lastPage;
  DateTime? _lastScrollTrigger;

  late final ScrollController scrollController = ScrollController();

  // ── To be implemented by the controller ───────────────────────────────────
  Future<({List<T> items, Pagination pagination})?> fetchPage(
    int page,
    CancelToken cancelToken,
  );

  // ── Lifecycle ──────────────────────────────────────────────────────────────
  void initPagination(CancelToken cancelToken) {
    scrollController.addListener(() => _onScroll(cancelToken));
  }

  @override
  void onClose() {
    scrollController.dispose();
    super.onClose();
  }

  // ── Public API ─────────────────────────────────────────────────────────────
  Future<void> loadItems(CancelToken cancelToken) async {
    hasError.value = false;
    loading.value = true;

    final result = await fetchPage(1, cancelToken);

    if (result != null) {
      // ✅ Atomic replace — no flash, no empty frame
      _pagination = result.pagination;
      items.value = result.items;
    } else {
      if (items.isEmpty) hasError.value = true;
    }

    loading.value = false;
  }

  // ── Private ────────────────────────────────────────────────────────────────
  void _onScroll(CancelToken cancelToken) {
    // ✅ Debounce — ignore events within 300ms of the last trigger
    final now = DateTime.now();
    if (_lastScrollTrigger != null &&
        now.difference(_lastScrollTrigger!) <
            const Duration(milliseconds: 300)) {
      return;
    }

    final nearBottom =
        scrollController.position.pixels >=
        scrollController.position.maxScrollExtent - 200;

    if (nearBottom && hasMore && !loadingMore.value) {
      _lastScrollTrigger = now;
      _loadNextPage(cancelToken);
    }
  }

  Future<void> _loadNextPage(CancelToken cancelToken) async {
    if (loadingMore.value || !hasMore) return;
    loadingMore.value = true;
    await _fetchPage(_pagination.currentPage + 1, cancelToken);
    loadingMore.value = false;
  }

  Future<void> _fetchPage(int page, CancelToken cancelToken) async {
    final result = await fetchPage(page, cancelToken);
    if (result == null) {
      if (items.isEmpty) hasError.value = true;
      return;
    }
    _pagination = result.pagination;
    items.addAll(result.items);
  }
}
