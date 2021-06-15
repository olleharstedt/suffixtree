<?php

// Main entry for testing PHP version of ApproximateCloneDetectingSuffixTree

//error_reporting(E_ALL);
//ini_set('display_errors', '1');

require_once "JavaObjectInterface.php";
require_once "JavaObject.php";
require_once "PhpToken.php";
require_once "Sentinel.php";
require_once "SuffixTreeHashTable.php";
require_once "SuffixTree.php";
require_once "ApproximateCloneDetectingSuffixTree.php";

$word = [
    new PhpToken(1, 'T_STRING', 100, 'file.php', 'content'),
    new Sentinel()
];
$tree = new ApproximateCloneDetectingSuffixTree($word);
