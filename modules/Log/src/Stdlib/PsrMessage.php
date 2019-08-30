<?php

namespace Log\Stdlib;

use Zend\I18n\Translator\TranslatorAwareTrait;

/**
 * Manage a message with a context
 *
 * This is a copy of Message, except the constructor, that requires an array.
 *
 * @see \Omeka\Stdlib\Message
 */
class PsrMessage implements \JsonSerializable, PsrInterpolateInterface
{
    use PsrInterpolateTrait;
    use TranslatorAwareTrait;

    /**
     * @var string
     */
    protected $message;

    /**
     * @var array
     */
    protected $context;

    /**
     * @var bool
     */
    protected $escapeHtml = true;

    /**
     * Set the message string and its context. The plural is not managed.
     *
     * @param string $message
     * @param array $context
     */
    public function __construct($message, array $context = [])
    {
        $this->message = $message;
        $this->context = $context ?: [];
    }

    /**
     * Get the message string.
     *
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Get the message context.
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * Does this message have context?
     *
     * @return bool
     */
    public function hasContext()
    {
        return (bool) $this->context;
    }

    /**
     * Get the message arguments for compatibility purpose.
     *
     * @deprecated Use hasContext() instead.
     */
    public function getArgs()
    {
        return $this->getContext();
    }

    /**
     * Does this message have arguments? For compatibility purpose.
     *
     * @deprecated Use hasContext() instead.
     * @return bool
     */
    public function hasArgs()
    {
        return $this->hasContext();
    }

    public function setEscapeHtml($escapeHtml)
    {
        $this->escapeHtml = (bool) $escapeHtml;
    }

    public function escapeHtml()
    {
        return $this->escapeHtml;
    }

    public function __toString()
    {
        return $this->isTranslatorEnabled()
            ? $this->translate()
            : $this->interpolate($this->getMessage(), $this->getContext());
    }

    public function translate()
    {
        return $this->hasTranslator()
            ? $this->interpolate($this->translator->translate($this->getMessage()), $this->getContext())
            : $this->interpolate($this->getMessage(), $this->getContext());
    }

    public function jsonSerialize()
    {
        return (string) $this;
    }
}
