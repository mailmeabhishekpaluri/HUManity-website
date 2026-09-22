<?php
$apiDir=is_dir(__DIR__.'/deploy/backend')?__DIR__.'/deploy/backend/api/donations':dirname(__DIR__,2).'/backend/api/donations';
$files=glob($apiDir.'/*.php');
foreach($files as $file){token_get_all(file_get_contents($file),TOKEN_PARSE);}
echo 'PHP syntax passed: '.count($files)." changed files.\n";
