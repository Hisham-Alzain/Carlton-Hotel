class User {
  final int id;
  final String name;
  final String? email;
  final String phone;
  final String type;
  final String? photoPath;

  User({
    required this.id,
    required this.name,
    this.email,
    required this.phone,
    required this.type,
    this.photoPath,
  });

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      id: json['id'],
      name: json['full_name'],
      email: json['email'],
      phone: json['phone'],
      type: json['user_type'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'full_name': name,
      'email': email,
      'phone': phone,
      'user_type': type,
    };
  }

  User.empty()
    : id = -1,
      name = '',
      email = null,
      phone = '',
      type = '',
      photoPath = '';
}
