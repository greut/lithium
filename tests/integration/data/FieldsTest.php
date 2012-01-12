<?php

namespace lithium\tests\integration\data;

use lithium\data\Connections;
use lithium\data\Entity;
use lithium\data\source\database\adapter\MySql;
use lithium\tests\mocks\data\MockEmployees;
use lithium\tests\mocks\data\MockCompanies;

class FieldsTest extends \lithium\test\Integration {
	public $db;

	public function setUp() {
		$sqlFile = LITHIUM_LIBRARY_PATH . '/lithium/tests/mocks/data/source/database/adapter/mysql_companies.sql';
		$sqls = file_get_contents($sqlFile);
		foreach(explode(';', $sqls) as $sql) {
			if (trim($sql)) {
				$this->db->read($sql, array('return' => 'resource'));
			}
		}
	}

	public function tearDown() {
		$this->db->read(
			'DROP TABLE IF EXISTS `employees`, `companies`;',
			array('return' => 'resource')
		);
	}

	public function skip() {
		$this->skipIf(!MySql::enabled(), 'MySQL Extension is not loaded');

		$dbConfig = Connections::get('test', array('config' => true));
		$hasDb = (isset($dbConfig['adapter']) && $dbConfig['adapter'] == 'MySql');
		$message = 'Test database is either unavailable, or not using a MySQL adapter';
		$this->skipIf(!$hasDb, $message);

		$this->db = new MySql($dbConfig);
	}

	public function testSingleField() {
		$new = MockCompanies::create(array('name' => 'Acme, Inc.'));
		$key = MockCompanies::meta('key');
		$new->save();
		$id = is_object($new->{$key}) ? (string) $new->{$key} : $new->{$key};

		$entity = MockCompanies::first($id);

		$this->assertTrue($entity instanceof Entity);
		$this->skipIf(!$entity instanceof Entity, 'Queried object is not an entity.');

		$expected = array(
			$key => $id, 'name' => 'Acme, Inc.', 'active' => null,
			'created' => null, 'modified' => null
		);
		$result = $entity->data();
		$this->assertEqual($expected, $result);

		$entity = MockCompanies::first(array(
			'conditions' => array($key => $id),
			'fields' => array($key)
		));

		$this->assertTrue($entity instanceof Entity);
		$this->skipIf(!$entity instanceof Entity, 'Queried object is not an entity.');

		$expected = array($key => $id);
		$result = $entity->data();
		$this->assertEqual($expected, $result);

		$entity = MockCompanies::find('first',array(
			'conditions' => array($key => $id),
			'fields' => array($key, 'name')
		));
		$this->assertTrue($entity instanceof Entity);
		$this->skipIf(!$entity instanceof Entity, 'Queried object is not an entity.');

		$entity->name = 'Acme, Incorporated';
		$result = $entity->save();
		$this->assertTrue($result);

		$entity = MockCompanies::find('first',array(
			'conditions' => array($key => $id),
			'fields' => array($key, 'name')
		));
		$this->assertEqual($entity->name, 'Acme, Incorporated');
		$new->delete();
	}

	function testFieldsWithJoins() {
		$new = MockCompanies::create(array('name' => 'Acme, Inc.'));
		$cKey = MockCompanies::meta('key');
		$result = $new->save();
		$cId = (string) $new->{$cKey};

		$this->skipIf(!$result, 'Could not save MockCompanies');

		$new = MockEmployees::create(array(
			'company_id' => $cId,
			'name' => 'John Doe'
		));
		$eKey = MockEmployees::meta('key');
		$result = $new->save();
		$this->skipIf(!$result, 'Could not save MockEmployee');
		$eId = (string) $new->{$eKey};

		$entity = MockEmployees::first(array(
			'with' => 'Company',
			'conditions' => array(
				'MockEmployees.id' => $eId,
			),
			'fields' => array(
				'Company' => array('id', 'name'),
				'MockEmployees' => array('id', 'name')
			)
		));
		$expected = array(
			'id' => $eId,
			'name' => 'John Doe',
			'company' => array(
				'id' => $cId,
				'name' => 'Acme, Inc.',
			),
		);
		$this->assertEqual($expected, $entity->data());

		$entity = MockCompanies::first(array(
			'with' => 'Employees',
			'conditions' => array(
				'MockCompanies.id' => $cId
			),
			'fields' => array(
				'MockCompanies' => array('id', 'name'),
				'Employees' => array('id', 'name'),
			)
		));
		$expected = array(
			'id' => $cId,
			'name' => 'Acme, Inc.',
			'employees' =>
			array (
				0 => array (
					'id' => $eId,
					'name' => 'John Doe'
				)
			)
		);
		$this->assertEqual($expected, $entity->data());
	}
}

?>