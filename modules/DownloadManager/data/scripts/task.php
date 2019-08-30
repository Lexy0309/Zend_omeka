<?php
/**
 * Prepare the application to process a cron task.
 *
 * A cron task is a standard Omeka job that is not managed inside Omeka.
 *
 * By construction, no control of the user is done. It is from the task.
 * Nevertheless, the process is checked and must be a system one, not a web one.
 * The class must be a cron one.
 *
 * @todo Use the true Zend console routing system.
 * @todo Manage the server url for absolute links (currently via a setting).
 */
namespace DownloadManager\Job;

use Log\Stdlib\PsrMessage;
use Omeka\Entity\User;

require dirname(dirname(dirname(dirname(__DIR__)))) . '/bootstrap.php';

$application = \Omeka\Mvc\Application::init(require OMEKA_PATH . '/application/config/application.config.php');
$services = $application->getServiceManager();
/** @var \Zend\Log\Logger $logger */
$logger = $services->get('Omeka\Logger');
$translator = $services->get('MvcTranslator');

if (php_sapi_name() !== 'cli') {
    $message = new PsrMessage(
        'The script "{script}" must be run from the command line.', // @translate
        ['script' => __FILE__]
    );
    $logger->err($message->getMessage(), $message->getContext());
    $message->setTranslator($translator);
    exit($message . PHP_EOL);
}

$shortopts = 'h:t:u:b::';
$longopts = ['help', 'task:', 'user-id:', 'base-path::'];
$options = getopt($shortopts, $longopts);

foreach ($options as $key => $value) switch ($key) {
    case 't':
    case 'task':
        $taskName = $value;
        break;
    case 'u':
    case 'user-id':
        $userId = $value;
        break;
    case 'b':
    case 'base-path':
        $basePath = $value;
        break;
    case 'h':
    case 'help':
        $message = new PsrMessage(
            'Required options: -t --task / -u --user; ' . PHP_EOL . 'Optional option: -b --base-path' // @translate
        );
        $message->setTranslator($translator);
        echo $message . PHP_EOL;
        exit();
}

if (empty($taskName)) {
    $message = new PsrMessage(
        'The task name must be set and exist.' // @translate
    );
    $message->setTranslator($translator);
    exit($message . PHP_EOL);
}

// TODO Use a plugin manager.
$omekaModulesPath = OMEKA_PATH . '/modules';
$modulePaths = array_values(array_filter(array_diff(scandir($omekaModulesPath), ['.', '..']), function ($file) use ($omekaModulesPath) {
    return is_dir($omekaModulesPath . '/' . $file);
}));
foreach ($modulePaths as $modulePath) {
    $filepath = $omekaModulesPath . '/' . $modulePath . '/src/Job/' . $taskName . '.php';
    if (file_exists($filepath) && filesize($filepath) && is_readable($filepath)) {
        include_once $filepath;
        $taskClass = $modulePath . '\\Job\\' . $taskName;
        if (class_exists($taskClass)) {
            $job = new \Omeka\Entity\Job;
            $task = new $taskClass($job, $services);
            break;
        }
    }
}

if (empty($task)) {
    $message = new PsrMessage(
        'The task "{task_name}" should be set and exist.', // @translate
        ['task_name' => $taskName]
    );
    $message->setTranslator($translator);
    exit($message . PHP_EOL);
}

if (empty($userId)) {
    $message = new PsrMessage(
        'The user id must be set and exist.' // @translate
    );
    $message->setTranslator($translator);
    exit($message . PHP_EOL);
}

$entityManager = $services->get('Omeka\EntityManager');
$user = $entityManager->find(User::class, $userId);
if (empty($user)) {
    $message = new PsrMessage(
        'The user #{user_id} is set for the cron task "{task_name}", but doesn’t exist.', // @translate
        ['user_id' => $userId, 'task_name' => $taskName]
    );
    $logger->err($message->getMessage(), $message->getContext());
    exit($message . PHP_EOL);
}

if (!empty($basePath)) {
    $services->get('ViewHelperManager')->get('BasePath')->setBasePath($basePath);
}

$services->get('Omeka\AuthenticationService')->getStorage()->write($user);

$referenceIdProcessor = new \Zend\Log\Processor\ReferenceId();
$referenceIdProcessor->setReferenceId('Task: ' . $taskName);
$logger->addProcessor($referenceIdProcessor);

$userIdProcessor = new \Log\Processor\UserId($user);
$logger->addProcessor($userIdProcessor);

// Finalize the task.
$job->setOwner($user);
$job->setClass($taskClass);

// TODO Log fatal errors.
// @see \Omeka\Job\DispatchStrategy::handleFatalError();
// @link https://stackoverflow.com/questions/1900208/php-custom-error-handler-handling-parse-fatal-errors#7313887

try {
    $logger->info('Task: Start');
    $task->perform();
    $logger->info('Task: End');
} catch (\Exception $e) {
    $logger->err(new PsrMessage('Task: Error: {exception}', ['exception' => $e]));
}
