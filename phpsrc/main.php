<?php

// Main entry for testing PHP version of ApproximateCloneDetectingSuffixTree

require_once "JavaObjectInterface.php";
require_once "PhpToken.php";
require_once "SuffixTreeHashTable.php";
require_once "SuffixTree.php";
require_once "ApproximateCloneDetectingSuffixTree.php";

$word = [new PhpToken(1, 'T_STRING', 100, 'file.php', 'content')];
$tree = new ApproximateCloneDetectingSuffixTree($word);
