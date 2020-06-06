<?php

namespace Cuitcode\Paystack\Exceptions;

use Exception;

/**
 * SignatureVerification is thrown when the signature verification for
 * a webhook fails.
 */
class SignatureVerification extends Exception
{
    protected $httpBody;
    protected $sigHeader;

    /**
     * Creates a new SignatureVerification exception.
     *
     * @param string $message the exception message
     * @param null|string $httpBody the HTTP body as a string
     * @param null|string $sigHeader the `X-Paystack-Signature` HTTP header
     *
     * @return SignatureVerification
     */
    public static function factory(
        $message,
        $httpBody = null,
        $sigHeader = null
    ) {
        $instance = new static($message);
        $instance->setHttpBody($httpBody);
        $instance->setSigHeader($sigHeader);

        return $instance;
    }

    /**
     * Gets the HTTP body as a string.
     *
     * @return null|string
     */
    public function getHttpBody()
    {
        return $this->httpBody;
    }

    /**
     * Sets the HTTP body as a string.
     *
     * @param null|string $httpBody
     */
    public function setHttpBody($httpBody)
    {
        $this->httpBody = $httpBody;
    }

    /**
     * Gets the `Paystack-Signature` HTTP header.
     *
     * @return null|string
     */
    public function getSigHeader()
    {
        return $this->sigHeader;
    }

    /**
     * Sets the `Paystack-Signature` HTTP header.
     *
     * @param null|string $sigHeader
     */
    public function setSigHeader($sigHeader)
    {
        $this->sigHeader = $sigHeader;
    }
}