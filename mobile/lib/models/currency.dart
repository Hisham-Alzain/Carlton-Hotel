class Currency {
  final String name;
  final String value;
  final String symbol;

  Currency({required this.name, required this.value, required this.symbol});

  @override
  String toString() {
    return name;
  }
}
