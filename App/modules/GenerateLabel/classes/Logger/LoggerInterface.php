<?php
namespace label\Logger;
/**
 * {license_notice}
 *
 * @copyright   {copyright}
 * @license     {license_link}
 */
interface LoggerInterface
{
    /**
     * @param string $message
     * @return void
     */
    public function logInfo($message);

    /**
     * @param string $message
     * @return void
     */
    public function logError($message);
}
