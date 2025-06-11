<?php

namespace HttpClient;

use Exception;

class RequestException extends Exception
{
    /**
     * The HTTP response that caused the exception.
     *
     * @var Response|null
     */
    public ?Response $response = null;

    /**
     * Create a new RequestException instance.
     *
     * @param string $message
     * @param int $code
     * @param Response|null $response
     */
    public function __construct(string $message, int $code = 0, ?Response $response = null)
    {
        parent::__construct($message, $code);
        $this->response = $response;
    }
}
