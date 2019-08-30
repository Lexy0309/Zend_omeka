<?php
namespace Log\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;
use Log\Stdlib\PsrMessage;

class LogRepresentation extends AbstractEntityRepresentation
{
    public function getControllerName()
    {
        return 'log';
    }

    public function getJsonLdType()
    {
        return 'o:Log';
    }

    public function getJsonLd()
    {
        $owner = $this->owner();
        if ($owner) {
            $owner = $owner->getReference();
        }

        $job = $this->job();
        if ($job) {
            $job = $job->getReference();
        }

        // TODO Find the schema for log severity. See https://tools.ietf.org/html/rfc3164.

        $created = [
            '@value' => $this->getDateTime($this->created()),
            '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
        ];

        return [
            'o:reference' => $this->reference(),
            'o:severity' => $this->severity(),
            'o:message' => $this->message(),
            'o:context' => $this->context(),
            'o:created' => $created,
            'o:owner' => $owner,
            'o:job' => $job,
        ];
    }

    public function reference()
    {
        return $this->resource->getReference();
    }

    public function severity()
    {
        return $this->resource->getSeverity();
    }

    public function severityLabel()
    {
        $severities = [
            \Zend\Log\Logger::EMERG => 'emergency', // @translate
            \Zend\Log\Logger::ALERT => 'alert', // @translate
            \Zend\Log\Logger::CRIT => 'critical', // @translate
            \Zend\Log\Logger::ERR => 'error', // @translate
            \Zend\Log\Logger::WARN => 'warning', // @translate
            \Zend\Log\Logger::NOTICE => 'notice', // @translate
            \Zend\Log\Logger::INFO => 'info', // @translate
            \Zend\Log\Logger::DEBUG => 'debug', // @translate
        ];
        $severity = $this->severity();
        return isset($severities[$severity])
            ? $severities[$severity]
            : $severity;
    }

    /**
     * @return PsrMessage
     */
    public function message()
    {
        $translator = $this->getServiceLocator()->get('MvcTranslator');
        $message = $this->resource->getMessage();
        $context = $this->resource->getContext() ?: [];
        $psrMessage = new PsrMessage($message, $context);
        $psrMessage->setTranslator($translator);
        return $psrMessage;
    }

    public function created()
    {
        return $this->resource->getCreated();
    }

    public function owner()
    {
        $owner = $this->resource->getOwner();
        return $owner
            ? $this->getAdapter('users')->getRepresentation($owner)
            : null;
    }

    public function job()
    {
        $job = $this->resource->getJob();
        return $job
            ? $this->getAdapter('jobs')->getRepresentation($job)
            : null;
    }
}
