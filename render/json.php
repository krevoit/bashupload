<?php

declare(strict_types=1);

# JSON renderer

header('Content-type: application/json;charset=utf-8');
include __DIR__ . "/../views/{$action}.json.phtml";
