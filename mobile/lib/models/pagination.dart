class Pagination {
  int currentPage;
  int lastPage;
  int perPage;
  int total;
  String? nextPageUrl;

  Pagination({
    this.currentPage = 1,
    this.lastPage = 1,
    this.perPage = 0,
    this.total = 0,
    this.nextPageUrl,
  });

  factory Pagination.fromJson(Map<String, dynamic> json) {
    return Pagination(
      currentPage: json['current_page'] ?? 1,
      lastPage: json['last_page'] ?? 1,
      perPage: json['per_page'] ?? 0,
      total: json['total'] ?? 0,
      nextPageUrl: json['next_page_url'],
    );
  }
}
