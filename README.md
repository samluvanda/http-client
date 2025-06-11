# HTTP Client

## 📘 Introduction

A lightweight, fluent PHP HTTP client built entirely with cURL — no dependencies, no bloat. This library gives you complete control over requests and responses while maintaining readability and simplicity in your codebase.

Designed for developers who value minimalism, precision, and testability in API communication.

---

## 🚀 Why Use This Client?

- ✨ Fluent, expressive syntax
- 💡 Full control over headers, body, query params, and more
- ⚙️ Built-in retry, timeout, and error handling mechanisms
- 📦 Smart JSON parsing and collection-style access
- 🔎 Rich status code and response helpers
- 🔐 Supports all major auth schemes (Basic, Digest, Bearer, NTLM)
- 🧵 Pure cURL — zero third-party HTTP layers

---

## 💾 Installation

```bash
composer require samluvanda/http-client
```

**Requirements:**

- PHP 8.0+
- `curl` PHP extension
- [`samluvanda/collection`](https://github.com/samluvanda/collection)

---

## 🧪 Quick Example

```php
use HttpClient\Client;

$client = new Client();

$response = $client
    ->withHeaders(['Authorization' => 'Bearer token'])
    ->withQueryParameters(['search' => 'books'])
    ->get('https://example.com/api/items');

if ($response->ok()) {
    print_r($response->json());
} else {
    echo "Request failed with status: " . $response->status();
}
```

You may also handle errors explicitly using fluent methods:

```php
$response->throwIf(fn($res) => $res->status() >= 400);
```

---

## 📚 Full Documentation

See [`docs/DOCUMENTATION.md`](docs/DOCUMENTATION.md) for comprehensive usage instructions, supported methods, chaining examples, and advanced features like file uploads and raw body sending.

---

## ✅ Minimum Requirements

- PHP >= 8.0
- cURL extension
- `samluvanda/collection` for JSON and array-like access

---

## 🤝 Contributing

Contributions, bug reports, and feature suggestions are welcome!

- Read the [CONTRIBUTING.md](CONTRIBUTING.md) guide
- Submit issues or PRs via [GitHub](https://github.com/samluvanda/http-client)

---

## 💖 Sponsor This Project

If you find this HTTP client useful, consider showing your support!

☕ Buy me a coffee, fuel my late-night coding sessions, or just say thanks  
📬 [s_luvanda@hotmail.com](mailto:s_luvanda@hotmail.com)  
🌍 [github.com/samluvanda](https://github.com/samluvanda)

Every star, share, and sponsor keeps this project alive and evolving! 🚀✨

---

## 📄 License

This project is open-sourced under the [MIT license](LICENSE).
