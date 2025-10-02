<?php

/**
 * 500 Error Page - Internal Server Error
 */
require_once __DIR__ . '/error_handler.php';

$handler = ErrorHandler::getInstance();
$handler->showErrorPage(500, 'Internal Server Error', 'The server encountered an unexpected condition that prevented it from fulfilling the request.');
