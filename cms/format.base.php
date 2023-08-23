<?php

$finder = PhpCsFixer\Finder::create()->in(#DIR#);
$config = new PhpCsFixer\Config();
$config->setUsingCache(false);

return $config->setRules(#RULES#)->setFinder($finder);