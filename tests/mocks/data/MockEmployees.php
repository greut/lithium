<?php

namespace lithium\tests\mocks\data;

class MockEmployees extends \lithium\data\Model {
	public $belongsTo = array(
		'Company' => array(
			'key' => 'company_id',
			'to' => 'lithium\\tests\\mocks\\data\\MockCompanies',
		)
	);
	protected $_meta = array(
		'source' => 'employees',
		'connection' => 'test',
	);

	public function lastName($entity) {
		$name = explode(' ', $entity->name);
		return $name[1];
	}
}

?>