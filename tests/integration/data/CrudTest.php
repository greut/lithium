<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\tests\integration\data;

use lithium\data\Connections;
use lithium\tests\mocks\data\MockCompanies;

class CrudTest extends \lithium\test\Integration {

	protected $_connection = null;

	public $companyData = array(
		array('name' => 'StuffMart', 'active' => true),
		array('name' => 'Ma \'n Pa\'s Data Warehousing & Bait Shop', 'active' => false)
	);

	public function setUp() {
		$sqlFile = LITHIUM_LIBRARY_PATH . '/lithium/tests/mocks/data/source/database/adapter/mysql_companies.sql';
		$sqls = file_get_contents($sqlFile);
		$db = Connections::get('test');
		foreach(explode(';', $sqls) as $sql) {
			if (trim($sql)) {
				$db->read($sql, array('return' => 'resource'));
			}
		}
	}

	public function tearDown() {
		$db = Connections::get('test');
		$db->read(
			'DROP TABLE IF EXISTS `employees`, `companies`;',
			array('return' => 'resource')
		);
	}


	/**
	 * Skip the test if no test database connection available.
	 *
	 * @return void
	 */
	public function skip() {
		$isAvailable = (
			Connections::get('test', array('config' => true)) &&
			Connections::get('test')->isConnected(array('autoConnect' => true))
		);
		$this->skipIf(!$isAvailable, "No test connection available.");
	}

	/**
	 * Tests that a single record with a manually specified primary key can be created, persisted
	 * to an arbitrary data store, re-read and updated.
	 *
	 * @return void
	 */
	public function testCreate() {
		$this->assertIdentical(0, MockCompanies::count());

		$new = MockCompanies::create(array('name' => 'Acme, Inc.', 'active' => true));
		$expected = array('name' => 'Acme, Inc.', 'active' => true);
		$result = $new->data();
		$this->assertEqual($expected, $result);

		$this->assertEqual(
			array(false, true, true),
			array($new->exists(), $new->save(), $new->exists())
		);
		$this->assertIdentical(1, MockCompanies::count());
	}

	public function testRead() {
		$existing = $this->_existing();

		foreach (MockCompanies::key($existing) as $val) {
			$this->assertTrue($val);
		}
		$this->assertEqual('Acme, Inc.', $existing->name);
		$this->assertTrue($existing->active);
		$this->assertTrue($existing->exists());
	}

	public function testUpdate() {
		$existing = $this->_existing();
		$this->assertEqual($existing->name, 'Acme, Inc.');
		$existing->name = 'Big Brother and the Holding Company';
		$result = $existing->save();
		$this->assertTrue($result);

		$existing = MockCompanies::first();
		foreach (MockCompanies::key($existing) as $val) {
			$this->assertTrue($val);
		}
		$this->assertTrue($existing->active);
		$this->assertEqual('Big Brother and the Holding Company', $existing->name);
	}

	public function testDelete() {
		$existing = $this->_existing();
		$this->assertTrue($existing->exists());
		$this->assertTrue($existing->delete());
		$this->assertNull(MockCompanies::first(array('conditions' => MockCompanies::key($existing))));
		$this->assertIdentical(0, MockCompanies::count());
	}

	public function testCrudMulti() {
		$large  = MockCompanies::create(array('name' => 'BigBoxMart', 'active' => true));
		$medium = MockCompanies::create(array('name' => 'Acme, Inc.', 'active' => true));
		$small  = MockCompanies::create(array('name' => 'Ma & Pa\'s', 'active' => true));

		foreach (array('large', 'medium', 'small') as $key) {
			$this->assertFalse(${$key}->exists());
			$this->assertTrue(${$key}->save());
			$this->assertTrue(${$key}->exists());
		}
		$this->assertEqual(3, MockCompanies::count());

		$all = MockCompanies::all();
		$this->assertEqual(3, $all->count());

		$match = 'BigBoxMart';
		$filter = function($entity) use (&$match) { return $entity->name == $match; };

		foreach (array('BigBoxMart', 'Acme, Inc.', 'Ma & Pa\'s') as $match) {
			$this->assertTrue($all->first($filter)->exists());
		}
		$this->assertEqual(array(true, true, true), array_values($all->delete()));
		$this->assertEqual(0, MockCompanies::count());
	}

	public function testUpdateWithNewProperties() {
		$this->_existing();
		$new = MockCompanies::find('first', array('fields' => array('id', 'name', 'active')));
		$expected = array('id' => 1, 'name' => 'Acme, Inc.', 'active' => true);
		$result = $new->data();
		$this->assertEqual($expected, $result);

		/* MySQL: won't support that
		$new->foo = 'bar';
		$expected = array('id' => 1, 'name' => 'Acme, Inc.', 'active' => true, 'foo' => 'bar');
		$result = $new->data();
		$this->assertEqual($expected, $result);

		$this->assertTrue($new->save());
		$updated = MockCompanies::find((string) $new->_id);
		$expected = 'bar';
		$result = $updated->foo;
		$this->assertEqual($expected, $result);
		*/
	}

	protected function _existing() {
		$new = MockCompanies::create(array('name' => 'Acme, Inc.', 'active' => true));
		$new->save();
		return $new;
	}
}

?>