<?php

require_once dirname(__FILE__).'/../../bootstrap/functional.php';
require_once $_SERVER['SYMFONY'].'/vendor/lime/lime.php';
require_once sfConfig::get('sf_lib_dir').'/test/unitHelper.php';

$t = new lime_test(7);

$manager = new ioDoctrineMenuManager();

$cacheDir = '/tmp/doctrine_menu';
sfToolkit::clearDirectory($cacheDir);
$cache = new sfFileCache(array('cache_dir' => $cacheDir));

$t->info('1 - Test the basic getters and setters.');
  $t->is($manager->getCacheDriver(), null, 'The cache driver is null by default.');
  $manager->setCacheDriver($cache);
  $t->is(get_class($manager->getCacheDriver()), 'sfFileCache', 'The cache driver was set correctly.');

$t->info('2 - Retrieve a menu from the manager');
  create_doctrine_test_tree($t);
  $cacheKey = md5('Root li');

  $t->info('  2.1 - Retrieve a menu, no cache is set at first.');
  $menu = $manager->getMenu('Root li');
  $t->is(get_class($menu), 'ioMenuItem', '->getMenu() retrieves the correct ioMenuItem object');
  $t->is($menu->getName(), 'Root li', '->getMenu() retrieves the correct ioMenuItem object');

  $t->info('  2.2 - Check that the cache has now been set');
  $t->is($cache->has($cacheKey), true, 'The cache was set to the cache key.');
  $cached = unserialize($manager->getCache($cacheKey));
  $t->is($cached['name'], 'Root li', 'The proper cache was set');

  $t->info('  2.3 - Mutate the cache and see that fetching the menu retrieves from the cache.');
  $cached['route'] = 'http://www.sympalphp.org';
  $cache->set($cacheKey, serialize($cached));

  $menu = $manager->getMenu('Root li');
  $t->is($menu->getRoute(), 'http://www.sympalphp.org', 'The manager correctly retrieves from the cache.');
  