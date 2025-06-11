<?php

namespace HttpClient;

use Closure;
use CURLFile;

class Client
{
    /**
     * URL template parameters for URI expansion.
     *
     * These parameters are used to replace placeholders in the URL
     * such as `{user}` or `{+endpoint}` before the request is sent.
     *
     * Example:
     *   ->withUrlParameters(['user' => 1])
     *   ->get('https://api.com/users/{user}');
     *
     * @var array<string, string>
     */
    protected array $urlParameters = [];

    /**
     * Query string parameters to be appended to the request URL.
     *
     * These parameters are appended to the final URL as a query string.
     * They are merged with any inline query passed during the request.
     *
     * Example:
     *   ->withQueryParameters(['page' => 1])
     *   ->get('https://api.com/posts');
     *
     * @var array<string, mixed>
     */
    protected array $queryParameters = [];

    /**
     * Raw request body set by the user.
     *
     * @var string|null
     */
    protected ?string $rawBody = null;

    /**
     * Files to attach in the request (for multipart/form-data).
     *
     * @var array
     */
    protected array $files = [];

    /**
     * Headers to be sent with the request.
     *
     * @var array
     */
    protected array $headers = [];

    /**
     * The HTTP authentication credentials and method.
     *
     * @var array|null [username, password, method]
     */
    protected ?array $auth = null;

    /**
     * Total request timeout in seconds.
     *
     * @var int|float|null
     */
    protected int|float|null $timeout = null;

    /**
     * Connection timeout in seconds.
     *
     * @var int|float|null
     */
    protected int|float|null $connectTimeout = null;

    /**
     * Retry configuration.
     *
     * @var array{
     *     times: int,
     *     delay: int,
     *     when: callable|null,
     *     throw: bool
 }|null
     */
    protected ?array $retryConfig = null;

    /**
     * The base URL to be prepended to all relative request URLs.
     *
     * @var string
     */
    protected string $baseUrl = '';

    /**
     * The body format for the request (e.g., 'json', 'form').
     *
     * @var string|null
     */
    protected ?string $bodyFormat = null;

    /**
     * Create a new HTTP client instance with default settings.
     */
    public function __construct()
    {
        // Set default request body format and content type to JSON
        $this->asJson();
    }

    /**
     * Set URI template variables for URL expansion.
     *
     * Placeholders like `{user}` or `{+base}` in the URL will be replaced
     * with their corresponding values from this array before sending the request.
     *
     * @param  array  $parameters  Associative array of template variables.
     * @return $this
     */
    public function withUrlParameters(array $parameters): static
    {
        $this->urlParameters = $parameters;
        return $this;
    }

    /**
     * Set the query string parameters for the request.
     *
     * These parameters will be appended to the request URL as a query string
     * and will override any previously set query parameters.
     *
     * @param  array  $parameters  Associative array of query string values.
     * @return $this
     */
    public function withQueryParameters(array $parameters): static
    {
        $this->queryParameters = $parameters;
        return $this;
    }

    /**
     * Configure the request to use application/x-www-form-urlencoded encoding.
     *
     * This format is commonly used for traditional HTML form submissions.
     * It sets the body format to 'form_params', ensuring the payload is encoded
     * as a URL-encoded query string, and applies the appropriate Content-Type header.
     *
     * Use this method when sending key-value form data without file uploads.
     *
     * @return $this Fluent return for chaining.
     */
    public function asForm(): static
    {
        return $this->bodyFormat('form_params')
            ->contentType('application/x-www-form-urlencoded');
    }

    /**
     * Set a raw request body and specify its Content-Type.
     *
     * This method gives you full control over the request payload and its MIME type.
     * It is useful when sending pre-encoded data like JSON, XML, or custom string payloads.
     * Automatically sets the body format to 'body', disabling form encoding or JSON encoding.
     *
     * Example:
     *   $client->withBody(json_encode($data), 'application/json')->post('/endpoint');
     *   $client->withBody('<xml></xml>', 'application/xml')->post('/xml-endpoint');
     *
     * @param string $content The raw string body to send.
     * @param string $contentType The MIME type for the request (default: 'application/json').
     * @return $this Fluent return for chaining.
     */
    public function withBody(string $content, string $contentType = 'application/json'): static
    {
        $this->bodyFormat('body');

        $this->rawBody = $content;

        $this->contentType($contentType);

        return $this;
    }

    /**
     * Specify that the request payload should be JSON-encoded.
     *
     * This sets the internal body format to 'json' and applies the appropriate
     * 'Content-Type' header. The request body will be encoded to JSON format
     * when the request is being prepared for dispatch.
     *
     * Unlike Laravel, this method does not automatically modify the 'Accept' header.
     *
     * @return $this Fluent return for chaining.
     */
    public function asJson(): static
    {
        return $this->bodyFormat('json')->contentType('application/json');
    }

    /**
     * Attach a file to the request payload.
     *
     * @param string|array $name The field name or an array of [name, contents, filename].
     * @param string|resource $contents The file contents or stream resource.
     * @param string|null $filename Optional filename to use.
     * @return $this
     */
    public function attach($name, $contents, $filename = null): static
    {
        if (is_array($name)) {
            [$name, $contents, $filename] = $name;
        }

        $this->files[] = [
            'name'     => $name,
            'contents' => $contents,
            'filename' => $filename,
        ];

        return $this;
    }

    /**
     * Attach multiple files to the request.
     *
     * @param array $files An array of files, each either in:
     *                     - [name, contents, filename] format
     *                     - ['name' => ..., 'contents' => ..., 'filename' => ...]
     * @return $this
     */
    public function attachMultiple(array $files): static
    {
        foreach ($files as $file) {
            if (is_array($file) && array_is_list($file)) {
                $this->attach(...$file); // [name, contents, filename]
            } elseif (is_array($file) && isset($file['name'], $file['contents'])) {
                $this->attach($file['name'], $file['contents'], $file['filename'] ?? null);
            }
        }

        return $this;
    }

    /**
     * Specify that the request should be sent as a multipart form-data request.
     *
     * This format is typically used for file uploads or when combining files with
     * regular form fields. Sets the body format to 'multipart', enabling the client
     * to structure the request payload accordingly.
     *
     * Note: You must use `attach()` or `attachMultiple()` to include files.
     * The Content-Type header will be handled automatically during request preparation.
     *
     * @return $this Fluent return for chaining.
     */
    public function asMultipart(): static
    {
        return $this->bodyFormat('multipart');
    }

    /**
     * Specify the request's content type.
     *
     * This sets the 'Content-Type' header for the request.
     *
     * @param string $contentType The MIME type (e.g., 'application/json').
     * @return $this
     */
    public function contentType(string $contentType): static
    {
        $this->headers['Content-Type'] = $contentType;
        return $this;
    }

    /**
     * Indicate that JSON should be returned by the server.
     *
     * This sets the 'Accept' header to 'application/json'.
     *
     * @return $this
     */
    public function acceptJson(): static
    {
        $this->headers['Accept'] = 'application/json';
        return $this;
    }

    /**
     * Indicate the type of content that should be returned by the server.
     *
     * This sets the 'Accept' header to a specific MIME type.
     *
     * @param string $contentType The desired MIME type (e.g., 'application/xml').
     * @return $this
     */
    public function accept(string $contentType): static
    {
        $this->headers['Accept'] = $contentType;
        return $this;
    }

    /**
     * Add or merge custom headers to the request.
     *
     * If keys overlap with existing headers, they will be overwritten.
     *
     * @param array $headers Associative array of headers.
     * @return $this
     */
    public function withHeaders(array $headers): static
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * Add a single header to the request.
     *
     * This is a convenience method that wraps withHeaders().
     *
     * @param string $name Header name.
     * @param mixed $value Header value.
     * @return $this
     */
    public function withHeader(string $name, mixed $value): static
    {
        return $this->withHeaders([$name => $value]);
    }

    /**
     * Replace all existing headers with the provided ones.
     *
     * This clears any previously set headers.
     *
     * @param array $headers Associative array of headers to set.
     * @return $this
     */
    public function replaceHeaders(array $headers): static
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * Use HTTP Basic authentication for the request.
     *
     * @param string $username
     * @param string $password
     * @return $this
     */
    public function withBasicAuth(string $username, string $password): static
    {
        $this->auth = [$username, $password, 'basic'];
        return $this;
    }

    /**
     * Use HTTP Digest authentication for the request.
     *
     * @param string $username
     * @param string $password
     * @return $this
     */
    public function withDigestAuth(string $username, string $password): static
    {
        $this->auth = [$username, $password, 'digest'];
        return $this;
    }

    /**
     * Use NTLM authentication for the request.
     *
     * @param string $username
     * @param string $password
     * @return $this
     */
    public function withNtlmAuth(string $username, string $password): static
    {
        $this->auth = [$username, $password, 'ntlm'];
        return $this;
    }

    /**
     * Specify an authorization token for the request.
     *
     * This sets the Authorization header using the given token and type.
     *
     * Example:
     * $client->withToken('abc123');            // Bearer token
     * $client->withToken('xyz456', 'Token');   // Token token
     *
     * @param string $token The token value.
     * @param string $type The token type (default: 'Bearer').
     * @return $this Fluent return for chaining.
     */
    public function withToken(string $token, string $type = 'Bearer'): static
    {
        $this->headers['Authorization'] = trim($type) . ' ' . trim($token);
        return $this;
    }

    /**
     * Specify the total timeout for the request (in seconds).
     *
     * This defines the maximum time the request is allowed to take,
     * including DNS resolution, connection, and data transfer.
     *
     * @param int|float $seconds
     * @return $this
     */
    public function timeout(int|float $seconds): static
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Specify the connection timeout for the request (in seconds).
     *
     * This controls how long to wait while establishing the initial TCP connection.
     *
     * @param int|float $seconds
     * @return $this
     */
    public function connectTimeout(int|float $seconds): static
    {
        $this->connectTimeout = $seconds;
        return $this;
    }

    /**
     * Specify the number of retry attempts and behavior for failed requests.
     *
     * This sets up a retry mechanism which can be customized via delay, condition, and failure behavior.
     *
     * Example:
     *   ->retry(3, 100) // Retry 3 times with 100ms delay
     *
     * @param array|int $times Number of attempts or [times, delay].
     * @param Closure|int $sleepMilliseconds Delay between retries (in ms).
     * @param callable|null $when Optional condition callback: fn($response, $exception) => bool
     * @param bool $throw Whether to throw if all retries fail (default: true)
     * @return $this
     */
    public function retry(array|int $times, Closure|int $sleepMilliseconds = 0, ?callable $when = null, bool $throw = true): static
    {
        if (is_array($times)) {
            [$attempts, $delay] = $times;
        } else {
            $attempts = $times;
            $delay = $sleepMilliseconds instanceof \Closure ? 0 : $sleepMilliseconds;
        }

        $this->retryConfig = [
            'times' => $attempts,
            'delay' => $delay,
            'when'  => $when,
            'throw' => $throw,
        ];

        return $this;
    }

    /**
     * Set the base URL for the request.
     *
     * This URL will be prepended to any relative URLs passed to methods like get(), post(), etc.
     *
     * Example:
     *   $client->baseUrl('https://api.example.com')->get('/users');
     *   // Resulting URL: https://api.example.com/users
     *
     * @param string $url The base URL (e.g., 'https://api.example.com').
     * @return $this Fluent return for chaining.
     */
    public function baseUrl(string $url): static
    {
        $this->baseUrl = $url;

        return $this;
    }

    /**
     * Specify the format used to encode the request body.
     *
     * Supported values:
     * - 'body'        Raw string (as-is)
     * - 'json'        JSON-encoded body (Content-Type: application/json)
     * - 'form_params' URL-encoded form (Content-Type: application/x-www-form-urlencoded)
     * - 'multipart'   Multipart/form-data (used for file uploads)
     *
     * This setting determines how the body payload will be encoded
     * and what Content-Type header will be applied automatically.
     *
     * Example:
     *   $client->bodyFormat('json')->post('/users', ['name' => 'John']);
     *
     * @param string $format The body format to use.
     * @return $this Fluent return for chaining.
     */
    public function bodyFormat(string $format): static
    {
        $this->bodyFormat = $format;
        return $this;
    }

    /**
     * Send a GET request.
     *
     * @param string $url The URL to request.
     * @param array|string|null $query Query parameters as array or query string.
     * @return Response
     */
    public function get(string $url, array|string|null $query = null): Response
    {
        return $this->send('GET', $url, func_num_args() === 1 ? [] : [
            'query' => $query,
        ]);
    }

    /**
     * Send a HEAD request.
     *
     * @param string $url The URL to request.
     * @param array|string|null $query Query parameters as array or query string.
     * @return Response
     */
    public function head(string $url, array|string|null $query = null): Response
    {
        return $this->send('HEAD', $url, func_num_args() === 1 ? [] : [
            'query' => $query,
        ]);
    }

    /**
     * Send a POST request.
     *
     * @param string $url The URL to post to.
     * @param array|\JsonSerializable $data The payload.
     * @return Response
     */
    public function post(string $url, array|\JsonSerializable $data = []): Response
    {
        return $this->send('POST', $url, [
            $this->bodyFormat => $data,
        ]);
    }

    /**
     * Send a PATCH request.
     *
     * @param string $url The URL to patch.
     * @param array|\JsonSerializable $data The payload.
     * @return Response
     */
    public function patch(string $url, array|\JsonSerializable $data = []): Response
    {
        return $this->send('PATCH', $url, [
            $this->bodyFormat => $data,
        ]);
    }

    /**
     * Send a PUT request.
     *
     * @param string $url The URL to put to.
     * @param array|\JsonSerializable $data The payload.
     * @return Response
     */
    public function put(string $url, array|\JsonSerializable $data = []): Response
    {
        return $this->send('PUT', $url, [
            $this->bodyFormat => $data,
        ]);
    }

    /**
     * Send a DELETE request.
     *
     * @param string $url The URL to delete.
     * @param array|\JsonSerializable $data The payload.
     * @return Response
     */
    public function delete(string $url, array|\JsonSerializable $data = []): Response
    {
        return $this->send('DELETE', $url, empty($data) ? [] : [
            $this->bodyFormat => $data,
        ]);
    }

    /**
     * Send the request to the given URL.
     *
     * @param  string  $method
     * @param  string  $url
     * @param  array  $options
     * @return Response
     */
    public function send(string $method, string $url, array $options = []): Response
    {
        $curlOptions = array_merge(
            [
                CURLOPT_URL => $this->buildFinalUrl($url, $options),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_HTTPHEADER => $this->formatHeaders(),
            ],
            $this->getMethodOptions($method),
            $this->prepareBodyOptions($options),
            $this->prepareAuthOptions(),
            $this->timeoutOptions()
        );

        return $this->executeWithRetries(function () use ($curlOptions) {
            $ch = curl_init();
            curl_setopt_array($ch, $curlOptions);

            $raw = curl_exec($ch);
            $error = curl_error($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

            curl_close($ch);

            if ($raw === false) {
                return new Response($status ?: 0, [], $error);
            }

            $rawHeaders = substr($raw, 0, $headerSize);
            $rawBody = substr($raw, $headerSize);
            $headers = $this->parseHeaders($rawHeaders);

            return new Response($status, $headers, $rawBody);
        });
    }

    /**
     * Build the final request URL by combining base URL, resolving URI placeholders,
     * and appending query parameters.
     *
     * Replaces tokens like {key} or {+key} using urlParameters, and merges queryParameters
     * with inline and request-specific queries.
     *
     * @param string $url The request URL (absolute or relative).
     * @param array &$options Request options array (may include 'query').
     * @return string The fully resolved and query-appended URL.
     */
    protected function buildFinalUrl(string $url, array &$options): string
    {
        $isAbsolute = str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
        $rawUrl = $isAbsolute ? $url : rtrim($this->baseUrl, '/') . '/' . ltrim($url, '/');

        foreach ($this->urlParameters as $key => $value) {
            $rawUrl = str_replace(
                ['{' . $key . '}', '{+' . $key . '}'],
                $value,
                $rawUrl
            );
        }

        $finalUrl = $rawUrl;

        if (str_contains($finalUrl, '?')) {
            [$cleanUrl, $queryFromUrl] = explode('?', $finalUrl, 2);
            $finalUrl = $cleanUrl;

            parse_str($queryFromUrl, $parsedFromUrl);

            $userQuery = $options['query'] ?? [];

            if (is_string($userQuery)) {
                parse_str($userQuery, $userQuery);
            } elseif (!is_array($userQuery)) {
                $userQuery = [];
            }

            $options['query'] = array_merge($parsedFromUrl, $userQuery);
        }

        $options['query'] = array_merge(
            $this->queryParameters,
            $options['query'] ?? []
        );

        if (!empty($options['query'])) {
            $finalUrl .= '?' . http_build_query($options['query']);
        }

        return $finalUrl;
    }

    /**
     * Format headers for cURL.
     *
     * @return array
     */
    protected function formatHeaders(): array
    {
        $formatted = [];

        foreach ($this->headers as $key => $value) {
            $formatted[] = "{$key}: {$value}";
        }

        return $formatted;
    }

    /**
     * Get method-specific cURL options based on the HTTP method.
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @return array cURL options specific to the method
     */
    protected function getMethodOptions(string $method): array
    {
        return match (strtoupper($method)) {
            'GET' => [
                CURLOPT_HTTPGET => true,
            ],
            'HEAD' => [
                CURLOPT_NOBODY => true,
                CURLOPT_CUSTOMREQUEST => 'HEAD',
            ],
            default => [
                CURLOPT_CUSTOMREQUEST => $method,
            ],
        };
    }

    /**
     * Prepare cURL options for the request body based on the selected format.
     *
     * @param array $options The original request options containing payload data.
     * @return array cURL options to merge with the main cURL configuration.
     */
    protected function prepareBodyOptions(array $options): array
    {
        if (!isset($options[$this->bodyFormat])) {
            return [];
        }

        $payload = $options[$this->bodyFormat];
        $curlOptions = [];

        switch ($this->bodyFormat) {
            case 'body':
                $curlOptions[CURLOPT_POSTFIELDS] = $this->rawBody;
                break;

            case 'json':
                $curlOptions[CURLOPT_POSTFIELDS] = json_encode($payload);
                break;

            case 'form_params':
                $curlOptions[CURLOPT_POSTFIELDS] = http_build_query($payload);
                break;

            case 'multipart':
                $multipart = [];

                foreach ($payload as $key => $value) {
                    $multipart[$key] = $value;
                }

                foreach ($this->files as $file) {
                    if (is_resource($file['contents'])) {
                        $tmpFile = tmpfile();
                        fwrite($tmpFile, stream_get_contents($file['contents']));
                        $meta = stream_get_meta_data($tmpFile);
                        $filePath = $meta['uri'];
                        $multipart[$file['name']] = new CURLFile($filePath, null, $file['filename']);
                    } elseif (file_exists($file['contents'])) {
                        $multipart[$file['name']] = new CURLFile($file['contents'], null, $file['filename']);
                    } else {
                        $tmpPath = tempnam(sys_get_temp_dir(), 'upload_');
                        file_put_contents($tmpPath, $file['contents']);
                        $multipart[$file['name']] = new \CURLFile($tmpPath, null, $file['filename']);
                    }
                }

                $curlOptions[CURLOPT_POSTFIELDS] = $multipart;
                break;
        }

        return $curlOptions;
    }

    /**
     * Prepare cURL options related to HTTP authentication.
     *
     * @return array The authentication-related cURL options.
     */
    protected function prepareAuthOptions(): array
    {
        if (!$this->auth) {
            return [];
        }

        [$username, $password, $method] = $this->auth;

        $options = [
            CURLOPT_USERPWD => "{$username}:{$password}",
        ];

        switch ($method) {
            case 'digest':
                $options[CURLOPT_HTTPAUTH] = CURLAUTH_DIGEST;
                break;
            case 'ntlm':
                $options[CURLOPT_HTTPAUTH] = CURLAUTH_NTLM;
                break;
            default:
                $options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
                break;
        }

        return $options;
    }

    /**
     * Get timeout-related cURL options.
     *
     * @return array Associative array of CURLOPT options, or empty if none set.
     */
    protected function timeoutOptions(): array
    {
        $options = [];

        if (!is_null($this->timeout)) {
            $options[CURLOPT_TIMEOUT] = $this->timeout;
        }

        if (!is_null($this->connectTimeout)) {
            $options[CURLOPT_CONNECTTIMEOUT] = $this->connectTimeout;
        }

        return $options;
    }

    /**
     * Execute the given request logic with retry support.
     *
     * This method uses the configured retry settings to repeat a request
     * based on a failure condition or exception, without ever throwing.
     *
     * @param callable $callback Function that returns a Response
     * @return Response
     */
    private function executeWithRetries(callable $callback): Response
    {
        $attempts = $this->retryConfig['times'] ?? 1;
        $delay = $this->retryConfig['delay'] ?? 0;
        $when = $this->retryConfig['when'] ?? null;

        $lastResponse = null;

        for ($i = 0; $i < $attempts; $i++) {
            try {
                $response = $callback();

                if (!$when || !$response->failed() || !$when($response, null)) {
                    return $response;
                }

                $lastResponse = $response;
            } catch (\Throwable $e) {
                $fallback = new Response(0, [], '');

                if (!$when || !$when($fallback, $e)) {
                    return $fallback;
                }

                $lastResponse = $fallback;
            }

            if ($delay > 0) {
                usleep($delay * 1000);
            }
        }

        return $lastResponse ?? new Response(0, [], '');
    }

    /**
     * Parse raw HTTP response headers into an associative array.
     *
     * This method processes the raw header string returned by cURL when
     * CURLOPT_HEADER is enabled. It extracts header key-value pairs and
     * excludes the HTTP status line.
     *
     * @param string $rawHeaders The raw header string from the cURL response.
     * @return array Associative array of headers.
     */
    private function parseHeaders(string $rawHeaders): array
    {
        $headers = [];

        foreach (explode("\r\n", trim($rawHeaders)) as $line) {
            if (str_starts_with(strtoupper($line), 'HTTP/')) {
                continue;
            }

            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }

        return $headers;
    }
}
