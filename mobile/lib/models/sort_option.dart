class SortOption {
  final String key;
  final String value;

  SortOption({required this.key, required this.value});

  SortOption.empty() : key = '', value = '';

  @override
  String toString() {
    return value;
  }
}
