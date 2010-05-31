<?php
/**
 * Utility class for helper unit test functions
 */

$menuItemHelper = dirname(__FILE__).'/../../plugins/ioMenuPlugin/test/fixtures/project/lib/test/unitHelper.php';
if (!file_exists($menuItemHelper))
{
  throw new Exception('Cannot find ioMenuPlugin unit helper. Are submodules updated?');
}
require_once ($menuItemHelper);


// clears the menu database and then creates a test tree
function create_doctrine_test_tree(lime_test $t)
{
  $t->info('### Creating test tree.');

  // create the root
  $rt = create_root('rt');

  $pt1 = new ioDoctrineMenuItem();
  $pt1->name = 'pt1';
  $pt1->save();
  $pt1->getNode()->insertAsLastChildOf($rt);
  $rt->refresh();

  $pt2 = new ioDoctrineMenuItem();
  $pt2->name = 'pt2';
  $pt2->save();
  $pt2->getNode()->insertAsLastChildOf($rt);
  $rt->refresh();

  $ch1 = new ioDoctrineMenuItem();
  $ch1->name = 'ch11';
  $ch1->save();
  $ch1->getNode()->insertAsLastChildOf($pt1);
  $pt1->refresh();

  $rt->refresh();
  $pt1->refresh();
  $pt2->refresh();
  $ch1->refresh();

  return array(
    'rt' => $rt,
    'pt1' => $pt1,
    'pt2' => $pt2,
    'ch1' => $ch1,
  );
}

// prints the test doctrine tree to the view
function print_doctrine_test_tree(lime_test $t)
{
  $t->info('      Menu Structure   ');
  $t->info('               rt      ');
  $t->info('             /    \    ');
  $t->info('          pt1      pt2 ');
  $t->info('           |           ');
  $t->info('          ch1          ');
}

// creates a root menu entry and optionally clears all of the menu records
function create_root($name, $clearData = true)
{
  if ($clearData)
  {
    Doctrine_Core::getTable('ioDoctrineMenuItem')->createQuery()->delete()->execute();
  }

  $rt = new ioDoctrineMenuItem();
  $rt->name = 'rt';
  $rt->save();
  Doctrine_Core::getTable('ioDoctrineMenuItem')->getTree()->createRoot($rt);

  return $rt;
}

// used in 2.1.* to run a few basic checks on the root menu item
function basic_root_menu_check(ioDoctrineMenuItem $rt, lime_test $t)
{
  $t->is($rt->getName(), 'Root li', '->getName() returns the correct value from the menu.');
  $t->is($rt->getLabel(), 'sympal', '->getLabel() returns the correct value from the menu.');
  $t->is($rt->getRoute(), 'http://www.sympalphp.org', '->getRoute() returns the correct value from the menu.');
  $t->is($rt->getAttributes(), 'class="root" id="sympal_menu"', '->getAttributes() returns the string representation of the attributes.');
  $t->is($rt->getRequiresAuth(), 1, '->getRequiresAuth() returns the correct value from the menu.');
  $t->is($rt->getRequiresNoAuth(), 0, '->getRequiresNoAuth() returns the correct value from the menu.');

  $permissions = $rt->Permissions;
  $t->is(count($permissions), 2, '->Permissions matches two items');
  $t->is($permissions[0]->name, 'c1', 'The c1 permission was properly set');
  $t->is($permissions[1]->name, 'c2', 'The c2 permission was properly set');
}

// used in 2.2.* to run checks on the integrity of the whole tree
function complex_root_menu_check(ioDoctrineMenuItem $rt, lime_test $t, $count)
{
  $children = $rt->getNode()->getChildren();

  $t->info('    2.'.$count.'.1 - Test the top-level menu integrity');
    $t->is(count($children), 2, '->getNode()->getChildren() on rt returns 2 children');
    $t->is($children[0]->name, 'Parent 1', '  The first child is pt1');
    $t->is($children[1]->name, 'Parent 2', '  The second child is pt2');
    $t->is($children[0]->getAttributes(), 'class="parent1"', 'The attributes were correctly set on Parent 1');

    $parent1 = $children[0];
    $parent2 = $children[1];

  $t->info('    2.'.$count.'.2 - Test the second-level menu integrity under pt1');
    $children = $parent1->getNode()->getChildren();
    $t->is(count($children), 3, '->getNode()->getChildren() on pt1 returns 3 children');
    $t->is($children[0]->name, 'Child 1', '  The first child is ch1');
    $t->is($children[1]->name, 'Child 2', '  The second child is ch2');
    $t->is($children[2]->name, 'Child 3', '  The third child is ch3');
    $t->is(count($children[1]->Permissions), 2, '  ch2 was given the proper permissions.');

    $t->is($children[0]->getNode()->getChildren(), false, '->getNode()->getChildren() on ch1 returns false');
    $t->is($children[1]->getNode()->getChildren(), false, '->getNode()->getChildren() on ch2 returns false');
    $t->is($children[2]->getNode()->getChildren(), false, '->getNode()->getChildren() on ch3 returns false');

  $t->info('    2.'.$count.'.3 - Test the second and third-level menu integrity under pt2');
    $children = $parent2->getNode()->getChildren();
    $t->is(count($children), 1, '->getNode()->getChildren() on pt2 returns 1 child');
    $t->is($children[0]->name, 'Child 4', '  The first child is ch4');
    $children = $children[0]->getNode()->getChildren();
    $t->is(count($children), 1, '->getNode()->getChildren() on ch4 returns 1 child');
    $t->is($children[0]->name, 'Grandchild 1', '  The first child is gc1');
}

// prints a message about how long a persist operation took
function persist_menu(lime_test $t, ioDoctrineMenuItem $rt, ioMenuItem $menu)
{
  $timer = new sfTimer();
  $rt->persistFromMenuArray($menu->toArray());
  $timer->addTime();
  $rt->refresh(true);
  $t->info(sprintf(
    '### Menu took %s to persist (%s nodes/min)',
    round($timer->getElapsedTime(), 4),
    floor(8 * 60 / $timer->getElapsedTime())
  ));
}

// tests the number of total nodes and nodes at each level
function test_total_nodes(lime_test $t, $total, $perLevelTotal = null)
{
  $t->info('    Checking node totals...');

  $nodeTotal = Doctrine_Query::create()
    ->from('ioDoctrineMenuItem m')
    ->count();
  $t->is($nodeTotal, $total, 'The total number of nodes is '.$total);

  if ($perLevelTotal !== null)
  {
    foreach ($perLevelTotal as $level => $levelTotal)
    {
      $total = Doctrine_Query::create()
        ->from('ioDoctrineMenuItem m')
        ->where('m.level = ?', $level)
        ->count();

      $t->is($total, $levelTotal, sprintf('The node count at level %s is %s', $level, $levelTotal));
    }
  }
}

// checks for correct lft, rgt values to see if the true was corrupted
function root_sanity_check(lime_test $t, ioDoctrineMenuItem $rt)
{
  $t->info('    Testing for correct lft, rgt values');
  $nodeTotal = Doctrine_Query::create()
    ->from('ioDoctrineMenuItem m')
    ->count();

  $t->is($rt->lft, 1, 'rt.lft = 1');
  $rgt = $nodeTotal * 2;
  $t->is($rt->rgt, $rgt, 'rt.rgt = '.$rgt);

  $children = $rt->getNode()->getChildren();
  $childRgt = 1; // fake the previous sibling
  foreach ($children as $child)
  {
    $child->refresh(); // just to be sure
    $t->is($child->lft, $childRgt + 1, 'The child node lft value is rgt+1 of its previous sibling');
    $childRgt = $child->rgt;
  }
  $t->is($child->rgt, $rgt - 1, 'The final child.rgt value is one less than the parent.rgt value.');
}

// checks the ordering of children. The 3rd arg is an array of child indexs it should drill into
// check_child_ordering($t, $rt, array(0), array('Child 1', 'Child 2', 'Child 3', 'Child 5'));
function check_child_ordering(lime_test $t, ioDoctrineMenuItem $rt, $path, array $ordering)
{
  $menu = $rt;
  foreach ($path as $part)
  {
    $children = $menu->getNode()->getChildren();
    $menu = $children[$part];
  }

  $childNameArray = array();
  foreach ($menu->getNode()->getChildren() as $child)
  {
    $childNameArray[] = $child->getName();
  }

  $t->is($childNameArray, $ordering, 'The children are ordered correctly: ', implode(',', $childNameArray));
}