<?php

namespace HttpClient;

use ArrayAccess;
use Support\Collection;

class Response implements ArrayAccess
{
    /**
     * The raw response body received from the HTTP request.
     *
     * This is the unprocessed string content returned by the server.
     * It can be plain text, JSON, XML, or any other format depending on the response.
     *
     * @var string
     */
    protected string $body;

    /**
     * The headers returned with the HTTP response.
     *
     * This is an associative array where header names are keys and values are either strings or arrays
     * (in case of multiple headers with the same name).
     *
     * Example: ['Content-Type' => 'application/json']
     *
     * @var array
     */
    protected array $headers;

    /**
     * The HTTP status code of the response.
     *
     * Represents the HTTP status (e.g., 200 for OK, 404 for Not Found).
     * Useful for checking if the request was successful or if an error occurred.
     *
     * @var int
     */
    protected int $status;

    /**
     * Lazily decoded JSON version of the response body.
     *
     * This is populated only when JSON decoding is needed (via methods like offsetGet or json()).
     * It stores the result of json_decode to avoid decoding the body multiple times.
     *
     * @var mixed|null
     */
    protected mixed $decodedJson = null;

    /**
     * Response constructor.
     *
     * @param int $status HTTP status code.
     * @param array $headers Response headers.
     * @param string $body Raw response body.
     */
    public function __construct(int $status, array $headers, string $body)
    {
        $this->status = $status;
        $this->headers = $headers;
        $this->body = $body;
    }

    /**
     * Check if the given offset exists in the JSON-decoded response.
     *
     * @param mixed $offset Key to check.
     * @return bool True if key exists, false otherwise.
     */
    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->decoded());
    }

    /**
     * Get the value associated with the given offset from the JSON-decoded response.
     *
     * @param mixed $offset Key to retrieve.
     * @return mixed|null The value, or null if not found.
     */
    public function offsetGet($offset): mixed
    {
        return $this->decoded()[$offset] ?? null;
    }

    /**
     * Prevent setting values on the response (read-only).
     *
     * @param mixed $offset Key to set.
     * @param mixed $value Value to set.
     * @return void
     * @throws \LogicException Always throws to enforce immutability.
     */
    public function offsetSet($offset, $value): void
    {
        throw new \LogicException('Response is read-only');
    }

    /**
     * Prevent unsetting values on the response (read-only).
     *
     * @param mixed $offset Key to unset.
     * @return void
     * @throws \LogicException Always throws to enforce immutability.
     */
    public function offsetUnset($offset): void
    {
        throw new \LogicException('Response is read-only');
    }

    /**
     * Decode the response body as an associative array (cached).
     *
     * This method parses the JSON response body once and caches the result.
     * If decoding fails, it returns an empty array and avoids repeating the operation.
     *
     * @return array The decoded JSON as an array, or an empty array on failure.
     */
    protected function decoded(): array
    {
        // If we've already decoded and cached the result, return it
        if (is_array($this->decodedJson)) {
            return $this->decodedJson;
        }

        // Attempt to decode the response body as JSON (associative array)
        $this->decodedJson = json_decode($this->body, true);

        // If decoding fails, capture the error and fallback to an empty array
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Optional: log or handle the specific error message
            // error_log("JSON decode error: " . json_last_error_msg());

            $this->decodedJson = [];
        }

        return $this->decodedJson;
    }

    /**
     * Get the raw response body as a string.
     *
     * @return string The unprocessed response content.
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * Decode the response body as an associative array and return a key if provided.
     *
     * This method uses the cached decoded JSON for performance.
     *
     * @param string|null $key The key to retrieve from the decoded array, or null for the entire array.
     * @param mixed|null $default Default value to return if the key does not exist.
     * @return mixed The value of the key, the full array, or the default.
     */
    public function json($key = null, $default = null): mixed
    {
        $decoded = $this->decoded();

        if ($key === null) {
            return $decoded;
        }

        return $decoded[$key] ?? $default;
    }

    /**
     * Decode the response body as a standard object (stdClass).
     *
     * This bypasses the cached associative array for full object decoding.
     *
     * @return object The decoded JSON as an object, or empty object on failure.
     */
    public function object(): object
    {
        $decoded = json_decode($this->body);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return (object)[];
        }

        return $decoded;
    }

    /**
     * Convert the JSON-decoded response into a Collection instance.
     *
     * If a specific key is provided, its value will be extracted and passed
     * into the Collection. If no key is provided, the entire decoded array is used.
     *
     * @param string|null $key Optional key to retrieve from the decoded response.
     * @return Collection The resulting collection.
     */
    public function collect($key = null): Collection
    {
        $decoded = $this->decoded();

        if ($key !== null && array_key_exists($key, $decoded)) {
            $items = is_array($decoded[$key]) ? $decoded[$key] : [$decoded[$key]];
            return new Collection($items);
        }

        return new Collection($decoded);
    }

    /**
     * Get the response body as a stream resource.
     *
     * This method creates an in-memory stream and writes the response body into it,
     * allowing consumers to use stream functions like `fread`, `stream_get_contents`, etc.
     *
     * @return resource A readable stream containing the response body.
     */
    public function resource()
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $this->body);
        rewind($stream);

        return $stream;
    }

    /**
     * Get the HTTP status code for the response.
     *
     * @return int The response's HTTP status code (e.g. 200, 404, 500).
     */
    public function status(): int
    {
        return $this->status;
    }

    /**
     * Determine if the response was successful (2xx).
     *
     * @return bool True if the response status is between 200 and 299.
     */
    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * Determine if the response is a redirect (3xx).
     *
     * @return bool True if the response status is between 300 and 399.
     */
    public function redirect(): bool
    {
        return $this->status >= 300 && $this->status < 400;
    }

    /**
     * Determine if the response failed (4xx or 5xx).
     *
     * @return bool True if the status code is 400 or higher.
     */
    public function failed(): bool
    {
        return $this->status >= 400;
    }

    /**
     * Determine if the response is a client error (4xx).
     *
     * @return bool True if the status code is between 400 and 499.
     */
    public function clientError(): bool
    {
        return $this->status >= 400 && $this->status < 500;
    }

    /**
     * Get the value of a specific header (case-insensitive).
     *
     * @param string $header The header name.
     * @return string Header value or empty string if not found.
     */
    public function header($header): string
    {
        $normalized = strtolower($header);

        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === $normalized) {
                return $value;
            }
        }

        return '';
    }

    /**
     * Get all response headers.
     *
     * @return array Associative array of headers.
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Check if the response status is 200 OK.
     *
     * @return bool
     */
    public function ok(): bool
    {
        return $this->status === 200;
    }

    /**
     * Check if the response status is 201 Created.
     *
     * @return bool
     */
    public function created(): bool
    {
        return $this->status === 201;
    }

    /**
     * Check if the response status is 202 Accepted.
     *
     * @return bool
     */
    public function accepted(): bool
    {
        return $this->status === 202;
    }

    /**
     * Check if the response status is 204 No Content.
     *
     * @return bool
     */
    public function noContent(): bool
    {
        return $this->status === 204;
    }

    /**
     * Check if the response status is 301 Moved Permanently.
     *
     * @return bool
     */
    public function movedPermanently(): bool
    {
        return $this->status === 301;
    }

    /**
     * Check if the response status is 302 Found.
     *
     * @return bool
     */
    public function found(): bool
    {
        return $this->status === 302;
    }

    /**
     * Check if the response status is 400 Bad Request.
     *
     * @return bool
     */
    public function badRequest(): bool
    {
        return $this->status === 400;
    }

    /**
     * Check if the response status is 401 Unauthorized.
     *
     * @return bool
     */
    public function unauthorized(): bool
    {
        return $this->status === 401;
    }

    /**
     * Check if the response status is 402 Payment Required.
     *
     * @return bool
     */
    public function paymentRequired(): bool
    {
        return $this->status === 402;
    }

    /**
     * Check if the response status is 403 Forbidden.
     *
     * @return bool
     */
    public function forbidden(): bool
    {
        return $this->status === 403;
    }

    /**
     * Check if the response status is 404 Not Found.
     *
     * @return bool
     */
    public function notFound(): bool
    {
        return $this->status === 404;
    }

    /**
     * Check if the response status is 408 Request Timeout.
     *
     * @return bool
     */
    public function requestTimeout(): bool
    {
        return $this->status === 408;
    }

    /**
     * Check if the response status is 409 Conflict.
     *
     * @return bool
     */
    public function conflict(): bool
    {
        return $this->status === 409;
    }

    /**
     * Check if the response status is 422 Unprocessable Entity.
     *
     * @return bool
     */
    public function unprocessableEntity(): bool
    {
        return $this->status === 422;
    }

    /**
     * Check if the response status is 429 Too Many Requests.
     *
     * @return bool
     */
    public function tooManyRequests(): bool
    {
        return $this->status === 429;
    }

    /**
     * Check if the response status is 500 Internal Server Error.
     *
     * @return bool
     */
    public function serverError(): bool
    {
        return $this->status === 500;
    }

    /**
     * Immediately execute the given callback if the response failed (4xx or 5xx).
     *
     * Example:
     * $response->onError(function ($response) {
     *     logger('API failed', ['status' => $response->status()]);
     * });
     *
     * @param callable $callback Callback to execute, passed the current Response instance.
     * @return $this Fluent return for method chaining.
     */
    public function onError(callable $callback): static
    {
        if ($this->failed()) {
            $callback($this);
        }

        return $this;
    }

    /**
     * Throw an exception if a server or client error occurs.
     *
     * If a closure is provided, it will be called before the exception is thrown.
     * The closure will receive this Response instance.
     *
     * @param callable|null $callback Optional logic to run before the exception is thrown.
     * @return $this
     * @throws RequestException
     */
    public function throw(?callable $callback = null): static
    {
        if ($this->failed()) {
            if ($callback) {
                $callback($this);
            }

            throw new RequestException(
                "HTTP request failed with status {$this->status()}",
                $this->status(),
                $this
            );
        }

        return $this;
    }

    /**
     * Throw if the response has a client/server error and the given condition is true.
     *
     * @param bool|callable $condition
     * @return $this
     * @throws RequestException
     */
    public function throwIf(bool|callable $condition): static
    {
        $shouldThrow = is_callable($condition) ? $condition($this) : $condition;

        if ($this->failed() && $shouldThrow) {
            throw new RequestException("HTTP request failed with status {$this->status()}.", $this->status(), $this);
        }

        return $this;
    }

    /**
     * Throw if the response has a client/server error and the given condition is false.
     *
     * @param bool|callable $condition
     * @return $this
     * @throws RequestException
     */
    public function throwUnless(bool|callable $condition): static
    {
        $shouldThrow = is_callable($condition) ? $condition($this) : $condition;

        if ($this->failed() && !$shouldThrow) {
            throw new RequestException("HTTP request failed with status {$this->status()}.", $this->status(), $this);
        }

        return $this;
    }

    /**
     * Throw a RequestException if the response status matches the given code.
     *
     * @param int $code
     * @return $this
     * @throws RequestException
     */
    public function throwIfStatus(int $code): static
    {
        if ($this->status() === $code) {
            throw new RequestException("HTTP request returned status {$code}.", $code, $this);
        }

        return $this;
    }

    /**
     * Throw a RequestException unless the response status matches the given code.
     *
     * @param int $code
     * @return $this
     * @throws RequestException
     */
    public function throwUnlessStatus(int $code): static
    {
        if ($this->status() !== $code) {
            throw new RequestException("Expected status {$code} but received {$this->status()}.", $this->status(), $this);
        }

        return $this;
    }
}
