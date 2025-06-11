# HTTP Client Documentation

## 📘 Introduction

This HTTP Client is a minimalist, fluent PHP library for making HTTP requests using native cURL under the hood. Designed to provide a simple and elegant API, it helps you send requests, handle responses, inspect headers, manipulate data formats, and optionally trigger errors — all without depending on heavy HTTP stacks.

Whether you are integrating with third-party APIs or building internal services, this client gives you complete control over your HTTP interactions while maintaining readability and testability in your code.

---

## 💾 Installation

Install via Composer:

```bash
composer require samluvanda/http-client
```

This package requires:

- PHP 8.0 or later
- The `curl` PHP extension
- [`samluvanda/collection`](https://github.com/samluvanda/collection) for fluent JSON access

---

## 🚀 Making Requests

The core of every request starts with an instance of `HttpClient\Client`. It provides a fluent API to build and send HTTP requests.

```php
use HttpClient\Client;

$client = new Client();

$response = $client
    ->withHeaders(['Accept' => 'application/json'])
    ->get('https://api.example.com/books');
```

Supported HTTP verbs:

```php
$client->get($url, $query);
$client->head($url, $query);
$client->post($url, $data);
$client->put($url, $data);
$client->patch($url, $data);
$client->delete($url, $data);
```

Each method returns a `Response` object with various utilities to help interpret or inspect the outcome.

---

## 📦 The Response Object

The `Response` class encapsulates the server's reply and gives you flexible access to its content.

### 📖 Reading the Response

- `body()` – Get the raw response string.
- `json($key = null, $default = null)` – Decode JSON and optionally access a specific key.
- `object()` – Get the response as a PHP object.
- `collect($key = null)` – Convert JSON to a Collection for fluent chaining.
- `resource()` – Get the raw cURL resource (advanced).
- `status()` – Get the HTTP status code.

### 🚦 Response State Helpers

- `successful()` – True if status is between 200 and 299.
- `redirect()` – True if status is a redirect (3xx).
- `failed()` – True if status is 400 or higher.
- `clientError()` – True if status is in the 400 range.
- `serverError()` – True if status is 500 or above.

### 🧾 Headers

- `header($key)` – Get a specific response header.
- `headers()` – Get all response headers as an associative array.

### 🧰 ArrayAccess Support

The `Response` object implements PHP’s `ArrayAccess` interface. This means you can access JSON keys directly as if it were an array:

```php
$userId = $response['user']['id'];
```

This provides concise and readable access to nested API data.

### 🧾 Status Code Specific Methods

To simplify status checks, these helper methods are available:

```php
$response->ok();                   // 200
$response->created();             // 201
$response->accepted();            // 202
$response->noContent();           // 204
$response->movedPermanently();    // 301
$response->found();               // 302
$response->badRequest();          // 400
$response->unauthorized();        // 401
$response->paymentRequired();     // 402
$response->forbidden();           // 403
$response->notFound();            // 404
$response->requestTimeout();      // 408
$response->conflict();            // 409
$response->unprocessableEntity(); // 422
$response->tooManyRequests();     // 429
$response->serverError();         // 500
```

---

## 🧩 URI Templates

To help build dynamic URLs without manually concatenating strings, the client provides the `withUrlParameters` method. This allows you to define named placeholders and their corresponding values, which are automatically substituted into the URL before the request is sent.

```php
$response = $client->withUrlParameters([
    'host' => 'https://api.example.com',
    'resource' => 'users',
    'id' => 42,
])->get('{+host}/{resource}/{id}');
```

---

## 📝 Request Data

### 📦 JSON Requests (Default)

By default, `post()`, `put()`, and `patch()` methods send data as JSON:

```php
$response = $client->post('https://example.com/data', [
    'title' => 'Hello World',
    'type' => 'article',
]);
```

### 🔎 GET Request Query Parameters

When making GET requests, it's common to include extra data in the form of query parameters—such as filters, search terms, or pagination values. This client provides two ways to handle that elegantly.

#### 🟠 Inline Parameters

The most straightforward method is to pass query parameters as the second argument to the `get()` method:

```php
$response = $client->get('https://example.com/users', [
    'name' => 'Alex',
    'page' => 1,
]);
```

This results in the request being sent to:

```
https://example.com/users?name=Alex&page=1
```

#### 🟠 Fluent `withQueryParameters()` Method

For more readable, reusable request flows, you can define query parameters using `withQueryParameters()` before making the call:

```php
$response = $client->retry(3, 100)->withQueryParameters([
    'name' => 'Alex',
    'page' => 1,
])->get('https://example.com/users');
```

This approach allows you to define the query structure once and combine it with other configuration like retry behavior, custom headers, or timeouts. If the URL already contains a query string, the provided parameters will be merged cleanly, with your defined values taking precedence.

### 🧾 Form URL Encoded Requests

To send `application/x-www-form-urlencoded` data, use `asForm()`:

```php
$response = $client
    ->asForm()
    ->post('https://example.com/login', [
        'username' => 'john',
        'password' => 'secret',
    ]);
```

### 🧬 Sending Raw Request Bodies

Use `withBody()` when you want full control of the request body:

```php
$response = $client
    ->withBody('<xml><tag>value</tag></xml>', 'application/xml')
    ->post('https://api.example.com/xml');
```

### 📎 Multi-Part Requests

When uploading files or sending multipart form data, the client provides a convenient and fluent interface.

#### 🖼 Attaching a Single File with `attach()`

You can attach a single file to your request using the `attach()` method. The first argument is the field name, and the second is the full file path on disk:

```php
$response = $client
    ->attach('photo', '/path/to/image.jpg')
    ->post('https://example.com/upload');
```

#### 📂 Attaching Multiple Files with `attachMultiple()`

For use cases that require sending multiple files in a single request, you may use `attachMultiple()`. This method accepts an associative array where the keys are the field names and the values are file paths:

```php
$response = $client
    ->attachMultiple([
        'profile' => '/path/to/profile.jpg',
        'resume' => '/path/to/resume.pdf',
    ])
    ->post('https://example.com/submit');
```

Each file will be sent using the correct `multipart/form-data` content type.

These methods make it easy to manage file uploads and are especially useful for APIs that handle media or document submissions.

---

## 🎩 Headers

The HTTP client gives you full control over the request headers, allowing you to customize them in a variety of ways.

### 🔧 Setting Headers with `withHeaders()`

Use `withHeaders()` to merge custom headers into your request. If any headers already exist, they will be overwritten by the new ones:

```php
$response = $client->withHeaders([
    'X-First' => 'foo',
    'X-Second' => 'bar'
])->post('https://example.com/users', [
    'name' => 'Alex',
]);
```

### 🎯 Setting a Single Header with `withHeader()`

You can also set a single header using `withHeader()`, which is a shortcut for `withHeaders()` with one key-value pair:

```php
$response = $client->withHeader('X-Custom-Header', 'value')
                  ->post('https://example.com/data');
```

### 🔁 Replacing All Headers with `replaceHeaders()`

If you want to discard any previously set headers and apply a clean set, use `replaceHeaders()`:

```php
$response = $client->replaceHeaders([
    'X-Fresh' => 'reset',
])->get('https://example.com/clear');
```

### 🧾 Content Type with `contentType()`

To define the `Content-Type` header explicitly (which tells the server the format of the request body), use the `contentType()` method:

```php
$response = $client->contentType('application/json')
                  ->post('https://example.com/json', ['key' => 'value']);
```

### 📦 Accepting Specific Response Types

You can tell the server what kind of content you expect in the response by using `accept()` or `acceptJson()`:

```php
$response = $client->accept('application/xml')
                  ->get('https://example.com/data.xml');

$response = $client->acceptJson()
                  ->get('https://example.com/data.json');
```

- `accept()` sets the `Accept` header to the MIME type you provide.
- `acceptJson()` is a shorthand for `accept('application/json')`.

These fluent methods allow you to expressively and efficiently configure all aspects of request headers for any HTTP call.

---

## 🔐 Authentication

This client supports several authentication strategies:

```php
$client->withBasicAuth('user', 'pass');
$client->withDigestAuth('user', 'pass');
$client->withNtlmAuth('user', 'pass');
$client->withToken('your-access-token'); // Bearer
```

---

## ⏱ Timeout Configuration

Set global or connection-specific timeouts (in seconds):

```php
$client->timeout(15);          // Total request time
$client->connectTimeout(5);    // Time to establish connection
```

---

## 🔁 Retries

Automatically retry a request if it fails. You can specify attempts and delay:

```php
$client->retry(3, 200); // Retry up to 3 times, with 200ms between
```

---

## 🛑 Error Handling

The response object provides a robust set of methods for detecting and responding to HTTP errors in a clean and fluent manner.

### 🔍 Introspective Error Checks

These methods allow you to programmatically determine how the request was handled:

```php
$response->successful();   // True if status code is between 200 and 299
$response->failed();       // True if status code is 400 or above
$response->clientError();  // True if status code is between 400 and 499
$response->serverError();  // True if status code is 500 or above
```

### ⚡ Responding to Errors Dynamically

You can register a callback to be executed only when an error (client or server) occurs using `onError()`:

```php
$response->onError(function ($res) {
    logger('Request failed with status ' . $res->status());
});
```

This method provides a clean hook for side effects like logging or reporting failures.

### 🚨 Conditional Exception Throwing

The response object gives you fine-grained control over throwing exceptions when needed:

```php
$response->throw(); // Throws if client or server error occurred

$response->throwIf($condition);
$response->throwUnless($condition);

$response->throwIf(fn ($res) => $res->status() === 403);
$response->throwUnless(fn ($res) => $res->successful());
```

You can also throw based on specific status codes:

```php
$response->throwIfStatus(404);
$response->throwUnlessStatus(200);
```

These methods allow you to centralize error control and reduce repetitive boilerplate around response checks.

---

## 🔗 Chaining Requests

The HTTP client is designed with fluent method chaining in mind. This means that you can build, send, and handle responses in a seamless, expressive manner — all in one readable chain.

### 🧬 Why Chain?

Chaining methods eliminates the need for temporary variables and scattered logic. It helps condense request setup, error handling, and response parsing into a single, intuitive line of code.

### ✨ Example

```php
return $client->post('https://api.example.com/users', [
        'name' => 'Alex',
    ])
    ->throw()
    ->json();
```

This single line does the following:

1. Sends a POST request with JSON payload.
2. Automatically throws an exception if an error response is received.
3. Parses and returns the JSON response body.

### 🔄 Fluent Flow

You can chain any combination of supported request modifiers (headers, timeouts, retries, etc.) to keep your code consistent and elegant:

```php
$data = $client->withHeaders(['Authorization' => 'Bearer token'])
               ->retry(3, 200)
               ->timeout(10)
               ->post('https://api.example.com/data', ['key' => 'value'])
               ->throw()
               ->json();
```

Each method returns the instance itself (`$this`), making fluent composition natural and readable.

Chaining enhances clarity and expressiveness in your code — and once you try it, it's hard to go back!

---

Happy coding! 🚀