<?php
declare(strict_types=1);
use App\Laminas\ServiceBridge;
use App\Sion\CommentPredicates;
use App\Sion\EntityShow;
require_once __DIR__ . '/vendor/autoload.php';
$b = new ServiceBridge(require __DIR__ . '/config/application.config.php');
$d = (new EntityShow($b, new CommentPredicates($b)))->load('association', 1);
$e = $d->entity;
foreach (['kind','childAssociations','nationalOrganizations','roles','assignments'] as $k) {
    $v = $e[$k] ?? '(absent)';
    printf("%-24s %s\n", $k, is_array($v) ? 'array(' . count($v) . ')' : var_export($v, true));
}
