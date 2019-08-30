<?php
namespace Log\Job\DispatchStrategy;

use Doctrine\ORM\EntityManager;
use Omeka\Entity\Job;

class Synchronous extends \Omeka\Job\DispatchStrategy\Synchronous
{
    /**
     * Log status and message if job terminates with a fatal error
     *
     * @param Job $job
     * @param EntityManager $entityManager
     */
    public function handleFatalError(Job $job, EntityManager $entityManager)
    {
        $lastError = error_get_last();
        $errors = [E_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];
        if ($lastError && in_array($lastError['type'], $errors)) {
            $logger = $this->serviceLocator->get('Omeka\Logger');
            $logger->err(
                "Fatal error: {message}\nin {file} on line {line}", // @translate
                [
                    'message' => $lastError['message'],
                    'file' => $lastError['file'],
                    'line' => $lastError['line'],
                ]
            );

            $job->setStatus(Job::STATUS_ERROR);

            // Make sure we only flush this Job and nothing else
            $entityManager->clear();
            $entityManager->merge($job);
            $entityManager->flush();
        }
    }
}
