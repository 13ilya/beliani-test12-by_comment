<?php

$CFG_LABEL->set('root_dir', dirname(dirname(__FILE__)));


$label_config = new \label\Config(ROOT_MOD_DIR);
$label_config->setRegistry($CFG_LABEL)
    ->setLogDirectory("logs");

