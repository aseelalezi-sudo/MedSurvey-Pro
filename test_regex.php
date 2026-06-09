<?php
$pattern = '/(?:^|\s)\\\\!\s*(?:system|exec|shell|sh)/i';
$testStr1 = 'CREATE TABLE test (id int); \! system';
$testStr2 = 'CREATE TABLE test (id int); \! sh';

if (preg_match($pattern, $testStr1)) echo "MATCHED 1\n"; else echo "FAILED 1\n";
if (preg_match($pattern, $testStr2)) echo "MATCHED 2\n"; else echo "FAILED 2\n";
