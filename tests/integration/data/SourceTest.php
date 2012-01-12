<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\tests\integration\data;

use Exception;
use lithium\data\Connections;
use lithium\tests\mocks\data\MockCompanies;
use lithium\tests\mocks\data\MockEmployees;

class SourceTest extends \lithium\test\Integration {

	protected $_connection = null;

	protected $_classes = array(
		'employees' => 'lithium\tests\mocks\data\MockEmployees',
		'companies' => 'lithium\tests\mocks\data\MockCompanies'
	);

	public $companiesData = array(
		array('name' => 'StuffMart', 'active' => true),
		array('name' => 'Ma \'n Pa\'s Data Warehousing & Bait Shop', 'active' => false)
	);

	/**
	 * @todo Make less dumb.
	 *
	 */
	public function setUp() {
		$this->_connection = Connections::get('test');

		if (strpos(get_class($this->_connection), 'CouchDb')) {
			$this->_loadViews();
		}

		if (strpos(get_class($this->_connection), 'MySql')) {
			$this->_loadSchema();
		}

		try {
			foreach (MockEmployees::all() as $employees) {
				$employees->delete();
			}
			foreach (MockCompanies::all() as $companies) {
				$companies->delete();
			}
		} catch (Exception $e) {}
	}

	protected function _loadViews() {
		Companies::create()->save();
	}

	protected function _loadSchema() {
		$sqlFile = LITHIUM_LIBRARY_PATH . '/lithium/tests/mocks/data/source/database/adapter/mysql_companies.sql';
		$sqls = file_get_contents($sqlFile);
		$db = Connections::get('test');
		foreach(explode(';', $sqls) as $sql) {
			if (trim($sql)) {
				$db->read($sql, array('return' => 'resource'));
			}
		}
	}

	/**
	 * @todo Make this less dumb.
	 */
	public function tearDown() {
		try {
			foreach (MockEmployees::all() as $employees) {
				$employees->delete();
			}
			foreach (MockCompanies::all() as $companies) {
				$companies->delete();
			}
		} catch (Exception $e) {
			$this->assertTrue(false, $e->getMessage());
		}
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
	public function testSingleReadWriteWithKey() {
		$key = MockCompanies::meta('key');
		$new = MockCompanies::create(array($key => 12345, 'name' => 'Acme, Inc.'));

		$result = $new->data();
		$expected = array($key => 12345, 'name' => 'Acme, Inc.');
		$this->assertEqual($expected[$key], $result[$key]);
		$this->assertEqual($expected['name'], $result['name']);

		$this->assertFalse($new->exists());
		$this->assertTrue($new->save());
		$this->assertTrue($new->exists());

		$existing = MockCompanies::find(12345);
		$result = $existing->data();
		$this->assertEqual($expected[$key], $result[$key]);
		$this->assertEqual($expected['name'], $result['name']);
		$this->assertTrue($existing->exists());

		$existing->name = 'Big Brother and the Holding Companies';
		$result = $existing->save();
		$this->assertTrue($result);

		$existing = MockCompanies::find(12345);
		$result = $existing->data();
		$expected['name'] = 'Big Brother and the Holding Companies';
		$this->assertEqual($expected[$key], $result[$key]);
		$this->assertEqual($expected['name'], $result['name']);

		$this->assertTrue($existing->delete());
	}

	public function testRewind() {
		$key = MockCompanies::meta('key');
		$new = MockCompanies::create(array($key => 12345, 'name' => 'Acme, Inc.'));

		$result = $new->data();
		$this->assertTrue($result !== null);
		$this->assertTrue($new->save());
		$this->assertTrue($new->exists());

		$result = MockCompanies::all(12345);
		$this->assertTrue($result !== null);

		$result = $result->rewind();
		$this->assertTrue($result !== null);
		$this->assertTrue(!is_string($result));
	}

	public function testFindFirstWithFieldsOption() {
		return;
		$key = MockCompanies::meta('key');
		$new = MockCompanies::create(array($key => 1111, 'name' => 'Test find first with fields.'));
		$result = $new->data();

		$expected = array($key => 1111, 'name' => 'Test find first with fields.');
		$this->assertEqual($expected['name'], $result['name']);
		$this->assertEqual($expected[$key], $result[$key]);
		$this->assertFalse($new->exists());
		$this->assertTrue($new->save());
		$this->assertTrue($new->exists());

		$result = MockCompanies::find('first', array('fields' => array('name')));
		$this->assertFalse(is_null($result));

		$this->skipIf(is_null($result), 'No result returned to test');
		$result = $result->data();
		$this->assertEqual($expected['name'], $result['name']);

		$this->assertTrue($new->delete());
	}

	public function testReadWriteMultiple() {
		$companies = array();
		$key = MockCompanies::meta('key');

		foreach ($this->companiesData as $data) {
			$companies[] = MockCompanies::create($data);
			$this->assertTrue(end($companies)->save());
			$this->assertTrue(end($companies)->{$key});
		}

		$this->assertIdentical(2, MockCompanies::count());
		$this->assertIdentical(1, MockCompanies::count(array('active' => true)));
		$this->assertIdentical(1, MockCompanies::count(array('active' => false)));
	
		$this->assertIdentical(0, MockCompanies::count(array('active' => null)));
		$all = MockCompanies::all();
		$this->assertIdentical(2, MockCompanies::count());

		$expected = count($this->companiesData);
		$this->assertEqual($expected, $all->count());
		$this->assertEqual($expected, count($all));

		$id = (string) $all->first()->{$key};
		$this->assertTrue(strlen($id) > 0);
		$this->assertTrue($all->data());

		foreach ($companies as $companies) {
			$this->assertTrue($companies->delete());
		}
		$this->assertIdentical(0, MockCompanies::count());
	}

	public function testEntityFields() {
		foreach ($this->companiesData as $data) {
			MockCompanies::create($data)->save();
		}
		$all = MockCompanies::all();

		$result = $all->first(function($doc) { return $doc->name == 'StuffMart'; });
		$this->assertEqual('StuffMart', $result->name);

		$result = $result->data();
		$this->assertEqual('StuffMart', $result['name']);

		//$result = $all->next();
		//$this->assertEqual('Ma \'n Pa\'s Data Warehousing & Bait Shop', $result->name);

		//$result = $result->data();
		//$this->assertEqual('Ma \'n Pa\'s Data Warehousing & Bait Shop', $result['name']);

		$this->assertNull($all->next());
	}

	/**
	 * Tests that a record can be created, saved, and subsequently re-read using a key
	 * auto-generated by the data source. Uses short-hand `find()` syntax which does not support
	 * compound keys.
	 *
	 * @return void
	 */
	public function testGetRecordByGeneratedId() {
		$key = MockCompanies::meta('key');
		$companies = MockCompanies::create(array('name' => 'Test MockCompanies'));
		$this->assertTrue($companies->save());

		$id = (string) $companies->{$key};
		$companiesCopy = MockCompanies::find($id)->data();
		$data = $companies->data();

		foreach ($data as $key => $value) {
			$this->assertTrue(isset($companiesCopy[$key]));
			$this->assertEqual($data[$key], $companiesCopy[$key]);
		}
	}

	/**
	 * Tests the default relationship information provided by the backend data source.
	 *
	 * @return void
	 */
	public function testDefaultRelationshipInfo() {
		$connection = $this->_connection;
		$message = "Relationships are not supported by this adapter.";
		$this->skipIf(!$connection::enabled('relationships'), $message);

		$this->assertEqual(array('Employees'), array_keys(MockCompanies::relations()));
		$this->assertEqual(array('Company'), array_keys(MockEmployees::relations()));

		$this->assertEqual(array('Employees'), MockCompanies::relations('hasMany'));
		$this->assertEqual(array('Company'), MockEmployees::relations('belongsTo'));

		$this->assertFalse(MockCompanies::relations('belongsTo'));
		$this->assertFalse(MockCompanies::relations('hasOne'));

		$this->assertFalse(MockEmployees::relations('hasMany'));
		$this->assertFalse(MockEmployees::relations('hasOne'));

		$result = MockCompanies::relations('Employees');

		$this->assertEqual('hasMany', $result->data('type'));
		$this->assertEqual($this->_classes['employees'], $result->data('to'));
	}

	public function testRelationshipQuerying() {
		$connection = $this->_connection;
		$message = "Relationships are not supported by this adapter.";
		$this->skipIf(!$connection::enabled('relationships'), $message);

		foreach ($this->companiesData as $data) {
			MockCompanies::create($data)->save();
		}
		$stuffMart = MockCompanies::findFirstByName('StuffMart');
		$maAndPas = MockCompanies::findFirstByName('Ma \'n Pa\'s Data Warehousing & Bait Shop');

		//$this->assertEqual($this->_classes['employees'], $stuffMart->employees->model());
		//$this->assertEqual($this->_classes['employees'], $maAndPas->employees->model());

		foreach (array('Mr. Smith', 'Mr. Jones', 'Mr. Brown') as $name) {
			//$stuffMart->employees[] = MockEmployees::create(compact('name'));
		}
		$expected = MockCompanies::key($stuffMart) + array(
			'name' => 'StuffMart', 'active' => true, 'employees' => array(
				array('name' => 'Mr. Smith'),
				array('name' => 'Mr. Jones'),
				array('name' => 'Mr. Brown')
			)
		);
		//$this->assertEqual($expected, $stuffMart->data());
		$this->assertTrue($stuffMart->save());
		//$this->assertEqual('Smith', $stuffMart->employees[0]->lastName());

		$stuffMartReloaded = MockCompanies::findFirstByName('StuffMart');
		//$this->assertEqual('Smith', $stuffMartReloaded->employees[0]->lastName());

		foreach (array('Ma', 'Pa') as $name) {
			//$maAndPas->employees[] = MockEmployees::create(compact('name'));
		}
		$maAndPas->save();
	}

	public function testAbstractTypeHandling() {
		$key = MockCompanies::meta('key');

		foreach ($this->companiesData as $data) {
			$companies[] = MockCompanies::create($data);
			$this->assertTrue(end($companies)->save());
			$this->assertTrue(end($companies)->{$key});
		}

		foreach (MockCompanies::all() as $companies) {
			$this->assertTrue($companies->delete());
		}
	}
}

?>