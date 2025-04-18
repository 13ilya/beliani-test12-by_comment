<?php

/**
 * Download files
 */

require_once 'connect.php';
require_once 'util.php';
require_once 'HTTP/Download.php';
require_once 'lib/Insurance.php';

$result = '';

if (requestVar('issue_doc_id')) {
    $id = (int) requestVar('issue_doc_id');
    $result = $dbr->getRow("SELECT `hash` AS `data`, `name` FROM issue_pic WHERE id = '$id'");
} 

if (PEAR::isError($result)) {
    print_r($result);
    exit();
}

$result->data = get_file_path($result->data);
if ( ! $result->data) {
    die('no doc');
}

$ext = array_pop(explode('.', $result->name));

switch (strtolower($ext)) {
    case 'htm':
    case 'html':
        $type = 'text/html';
        break;
    case 'pdf':
        $type = 'application/pdf';
        break;
    case 'doc':
    case 'docx':
        $type = 'application/doc';
        break;
    case 'rtf':
        $type = 'application/rtf';
        break;
    case 'cvs':
    case 'xls':
        $type = 'application/excel';
        break;
    case 'jpeg':
    case 'jpg':
        break;
    case 'xls':
        $type = 'application/vnd.ms-excel';
        break;
    case 'bmp':
        $type = 'image/bmp';
        break;
    case 'mp3':
        $type = 'application/mp3';
        break;
    case 'txt':
        $type = 'application/txt';
        break;
    case 'png':
        break;
    default: 
        $type = 'application/octet-stream';
} // case type

$params = [
    'Data' => $result->data,
    'ContentType' => $type,
    'ContentDisposition' => [HTTP_DOWNLOAD_INLINE, $result->name],
];

HTTP_Download::staticSend($params);
