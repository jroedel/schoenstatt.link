<?php

/**
 * What laminas-session wrote into `$_SESSION`, and what a reader must recover from it.
 *
 * GENERATED FILE — regenerate with:
 *
 *     docker compose exec -T -u www-data app php test/Session/regenerate-session-surface.php
 *
 * and only while `laminas/laminas-session` is still installed.
 *
 * `session` is `serialize($_SESSION)` — the bytes PHP's session handler writes to storage,
 * so it is the real compatibility contract and not a paraphrase of one. `values` is what
 * the application has to get back out, written by hand.
 *
 * A visitor's cookie lives 30 days. Anything that cannot read these bytes signs that
 * visitor out for 30 days, and `App\Http\SessionListener` records what that looked like
 * the last time it happened: empty 200s on every ported route, 2026-08-07.
 *
 * @return array<string, array{session: string, values: array<string, mixed>}>
 */

declare(strict_types=1);

return array (
  'empty' => 
  array (
    'session' => 'a:2:{s:9:"__Laminas";a:2:{s:20:"_REQUEST_ACCESS_TIME";d:1790000000;s:6:"_VALID";a:1:{s:28:"Laminas\\Session\\Validator\\Id";s:8:"RECORDED";}}s:10:"_IMMUTABLE";b:1;}',
    'values' => 
    array (
    ),
  ),
  'flash: one message' => 
  array (
    'session' => 'a:3:{s:9:"__Laminas";a:2:{s:20:"_REQUEST_ACCESS_TIME";d:1790000000;s:14:"FlashMessenger";a:1:{s:11:"EXPIRE_HOPS";a:2:{s:4:"hops";i:1;s:2:"ts";d:1790000000;}}}s:14:"FlashMessenger";O:26:"Laminas\\Stdlib\\ArrayObject":4:{s:7:"storage";a:1:{s:7:"success";O:8:"SplQueue":3:{i:0;i:4;i:1;a:1:{i:0;s:18:"You are signed in.";}i:2;a:0:{}}}s:4:"flag";i:2;s:13:"iteratorClass";s:13:"ArrayIterator";s:19:"protectedProperties";a:4:{i:0;s:7:"storage";i:1;s:4:"flag";i:2;s:13:"iteratorClass";i:3;s:19:"protectedProperties";}}s:10:"_IMMUTABLE";b:1;}',
    'values' => 
    array (
      'success' => 
      array (
        0 => 'You are signed in.',
      ),
    ),
  ),
  'flash: every namespace' => 
  array (
    'session' => 'a:3:{s:9:"__Laminas";a:2:{s:20:"_REQUEST_ACCESS_TIME";d:1790000000;s:14:"FlashMessenger";a:1:{s:11:"EXPIRE_HOPS";a:2:{s:4:"hops";i:1;s:2:"ts";d:1790000000;}}}s:14:"FlashMessenger";O:26:"Laminas\\Stdlib\\ArrayObject":4:{s:7:"storage";a:5:{s:7:"default";O:8:"SplQueue":3:{i:0;i:4;i:1;a:1:{i:0;s:17:"a default message";}i:2;a:0:{}}s:7:"success";O:8:"SplQueue":3:{i:0;i:4;i:1;a:1:{i:0;s:17:"a success message";}i:2;a:0:{}}s:7:"warning";O:8:"SplQueue":3:{i:0;i:4;i:1;a:1:{i:0;s:17:"a warning message";}i:2;a:0:{}}s:5:"error";O:8:"SplQueue":3:{i:0;i:4;i:1;a:1:{i:0;s:15:"a error message";}i:2;a:0:{}}s:4:"info";O:8:"SplQueue":3:{i:0;i:4;i:1;a:1:{i:0;s:14:"a info message";}i:2;a:0:{}}}s:4:"flag";i:2;s:13:"iteratorClass";s:13:"ArrayIterator";s:19:"protectedProperties";a:4:{i:0;s:7:"storage";i:1;s:4:"flag";i:2;s:13:"iteratorClass";i:3;s:19:"protectedProperties";}}s:10:"_IMMUTABLE";b:1;}',
    'values' => 
    array (
      'default' => 
      array (
        0 => 'a default message',
      ),
      'success' => 
      array (
        0 => 'a success message',
      ),
      'warning' => 
      array (
        0 => 'a warning message',
      ),
      'error' => 
      array (
        0 => 'a error message',
      ),
      'info' => 
      array (
        0 => 'a info message',
      ),
    ),
  ),
  'flash: two in one namespace' => 
  array (
    'session' => 'a:3:{s:9:"__Laminas";a:2:{s:20:"_REQUEST_ACCESS_TIME";d:1790000000;s:14:"FlashMessenger";a:1:{s:11:"EXPIRE_HOPS";a:2:{s:4:"hops";i:1;s:2:"ts";d:1790000000;}}}s:14:"FlashMessenger";O:26:"Laminas\\Stdlib\\ArrayObject":4:{s:7:"storage";a:1:{s:7:"success";O:8:"SplQueue":3:{i:0;i:4;i:1;a:2:{i:0;s:18:"You are signed in.";i:1;s:13:"but not there";}i:2;a:0:{}}}s:4:"flag";i:2;s:13:"iteratorClass";s:13:"ArrayIterator";s:19:"protectedProperties";a:4:{i:0;s:7:"storage";i:1;s:4:"flag";i:2;s:13:"iteratorClass";i:3;s:19:"protectedProperties";}}s:10:"_IMMUTABLE";b:1;}',
    'values' => 
    array (
      'success' => 
      array (
        0 => 'You are signed in.',
        1 => 'but not there',
      ),
    ),
  ),
  'flash: a translatable message' => 
  array (
    'session' => 'a:3:{s:9:"__Laminas";a:2:{s:20:"_REQUEST_ACCESS_TIME";d:1790000000;s:14:"FlashMessenger";a:1:{s:11:"EXPIRE_HOPS";a:2:{s:4:"hops";i:1;s:2:"ts";d:1790000000;}}}s:14:"FlashMessenger";O:26:"Laminas\\Stdlib\\ArrayObject":4:{s:7:"storage";a:1:{s:5:"error";O:8:"SplQueue":3:{i:0;i:4;i:1;a:1:{i:0;O:35:"JTranslate\\I18n\\TranslatableMessage":3:{s:45:"' . "\0" . 'JTranslate\\I18n\\TranslatableMessage' . "\0" . 'template";s:9:"Not found";s:47:"' . "\0" . 'JTranslate\\I18n\\TranslatableMessage' . "\0" . 'parameters";a:0:{}s:47:"' . "\0" . 'JTranslate\\I18n\\TranslatableMessage' . "\0" . 'textDomain";s:5:"JUser";}}i:2;a:0:{}}}s:4:"flag";i:2;s:13:"iteratorClass";s:13:"ArrayIterator";s:19:"protectedProperties";a:4:{i:0;s:7:"storage";i:1;s:4:"flag";i:2;s:13:"iteratorClass";i:3;s:19:"protectedProperties";}}s:10:"_IMMUTABLE";b:1;}',
    'values' => 
    array (
      'error' => 
      array (
        0 => 'JTranslate\\I18n\\TranslatableMessage(Not found @ JUser)',
      ),
    ),
  ),
  'csrf: one token' => 
  array (
    'session' => 'a:3:{s:9:"__Laminas";a:2:{s:20:"_REQUEST_ACCESS_TIME";d:1790000000;s:32:"Laminas_Validator_Csrf_salt_csrf";a:1:{s:6:"EXPIRE";i:1790000300;}}s:32:"Laminas_Validator_Csrf_salt_csrf";O:26:"Laminas\\Stdlib\\ArrayObject":4:{s:7:"storage";a:2:{s:9:"tokenList";a:1:{s:6:"abc123";s:8:"deadbeef";}s:4:"hash";s:15:"deadbeef-abc123";}s:4:"flag";i:2;s:13:"iteratorClass";s:13:"ArrayIterator";s:19:"protectedProperties";a:4:{i:0;s:7:"storage";i:1;s:4:"flag";i:2;s:13:"iteratorClass";i:3;s:19:"protectedProperties";}}s:10:"_IMMUTABLE";b:1;}',
    'values' => 
    array (
      'tokenList' => 
      array (
        'abc123' => 'deadbeef',
      ),
      'hash' => 'deadbeef-abc123',
    ),
  ),
  'csrf: two tokens' => 
  array (
    'session' => 'a:3:{s:9:"__Laminas";a:2:{s:20:"_REQUEST_ACCESS_TIME";d:1790000000;s:32:"Laminas_Validator_Csrf_salt_csrf";a:1:{s:6:"EXPIRE";i:1790000300;}}s:32:"Laminas_Validator_Csrf_salt_csrf";O:26:"Laminas\\Stdlib\\ArrayObject":4:{s:7:"storage";a:2:{s:9:"tokenList";a:2:{s:6:"abc123";s:8:"deadbeef";s:6:"def456";s:8:"cafebabe";}s:4:"hash";s:15:"cafebabe-def456";}s:4:"flag";i:2;s:13:"iteratorClass";s:13:"ArrayIterator";s:19:"protectedProperties";a:4:{i:0;s:7:"storage";i:1;s:4:"flag";i:2;s:13:"iteratorClass";i:3;s:19:"protectedProperties";}}s:10:"_IMMUTABLE";b:1;}',
    'values' => 
    array (
      'tokenList' => 
      array (
        'abc123' => 'deadbeef',
        'def456' => 'cafebabe',
      ),
      'hash' => 'cafebabe-def456',
    ),
  ),
  'identity' => 
  array (
    'session' => 'a:3:{s:9:"__Laminas";a:1:{s:20:"_REQUEST_ACCESS_TIME";d:1790000000;}s:12:"Laminas_Auth";O:26:"Laminas\\Stdlib\\ArrayObject":4:{s:7:"storage";a:1:{s:7:"storage";i:42;}s:4:"flag";i:2;s:13:"iteratorClass";s:13:"ArrayIterator";s:19:"protectedProperties";a:4:{i:0;s:7:"storage";i:1;s:4:"flag";i:2;s:13:"iteratorClass";i:3;s:19:"protectedProperties";}}s:10:"_IMMUTABLE";b:1;}',
    'values' => 
    array (
      'storage' => 42,
    ),
  ),
  'juser: post-sign-in destination' => 
  array (
    'session' => 'a:3:{s:9:"__Laminas";a:1:{s:20:"_REQUEST_ACCESS_TIME";d:1790000000;}s:5:"JUser";O:26:"Laminas\\Stdlib\\ArrayObject":4:{s:7:"storage";a:1:{s:8:"redirect";s:9:"/en/admin";}s:4:"flag";i:2;s:13:"iteratorClass";s:13:"ArrayIterator";s:19:"protectedProperties";a:4:{i:0;s:7:"storage";i:1;s:4:"flag";i:2;s:13:"iteratorClass";i:3;s:19:"protectedProperties";}}s:10:"_IMMUTABLE";b:1;}',
    'values' => 
    array (
      'redirect' => '/en/admin',
    ),
  ),
  'juser: a freshly issued api token' => 
  array (
    'session' => 'a:3:{s:9:"__Laminas";a:1:{s:20:"_REQUEST_ACCESS_TIME";d:1790000000;}s:14:"JUser\\ApiToken";O:26:"Laminas\\Stdlib\\ArrayObject":4:{s:7:"storage";a:1:{s:5:"token";s:34:"eyJhbGciOiJIUzI1NiJ9.e30.signature";}s:4:"flag";i:2;s:13:"iteratorClass";s:13:"ArrayIterator";s:19:"protectedProperties";a:4:{i:0;s:7:"storage";i:1;s:4:"flag";i:2;s:13:"iteratorClass";i:3;s:19:"protectedProperties";}}s:10:"_IMMUTABLE";b:1;}',
    'values' => 
    array (
      'token' => 'eyJhbGciOiJIUzI1NiJ9.e30.signature',
    ),
  ),
  'two namespaces at once' => 
  array (
    'session' => 'a:4:{s:9:"__Laminas";a:2:{s:20:"_REQUEST_ACCESS_TIME";d:1790000000;s:14:"FlashMessenger";a:1:{s:11:"EXPIRE_HOPS";a:2:{s:4:"hops";i:1;s:2:"ts";d:1790000000;}}}s:12:"Laminas_Auth";O:26:"Laminas\\Stdlib\\ArrayObject":4:{s:7:"storage";a:1:{s:7:"storage";i:7;}s:4:"flag";i:2;s:13:"iteratorClass";s:13:"ArrayIterator";s:19:"protectedProperties";a:4:{i:0;s:7:"storage";i:1;s:4:"flag";i:2;s:13:"iteratorClass";i:3;s:19:"protectedProperties";}}s:14:"FlashMessenger";O:26:"Laminas\\Stdlib\\ArrayObject":4:{s:7:"storage";a:1:{s:4:"info";O:8:"SplQueue":3:{i:0;i:4;i:1;a:1:{i:0;s:12:"welcome back";}i:2;a:0:{}}}s:4:"flag";i:2;s:13:"iteratorClass";s:13:"ArrayIterator";s:19:"protectedProperties";a:4:{i:0;s:7:"storage";i:1;s:4:"flag";i:2;s:13:"iteratorClass";i:3;s:19:"protectedProperties";}}s:10:"_IMMUTABLE";b:1;}',
    'values' => 
    array (
      'storage' => 7,
      'info' => 
      array (
        0 => 'welcome back',
      ),
    ),
  ),
);
