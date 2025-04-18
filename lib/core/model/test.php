<?php
/**
 * @author Alexander Rubtsov <RubtsovAV@gmail.com>
 * Date: 10.10.2017
 * Time: 8:25
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/memcached.php';

if ($_REQUEST['test_assert']) {
    assert(false, 'This is a test assert');
    die("If you do not see a warning message, then the assert is not working.");
}

$models = Model::findBy('fork_lift', [
    'min_height' => 0,
    ['model', 'LIKE', '4 Wheel %'],
]);
assert(count($models) == 7, 'findBy assert: The number of models must be 7');


$model = Model::firstOrFail('fork_lift', 2);
assert($model->getID() == 2, 'firstOrFail assert: The model ID must be 2');


try {
    $model = Model::firstOrFail('fork_lift', 100500);
    assert(false, 'firstOrFail assert: ModelNotFoundException expected');
} catch (ModelNotFoundException $ex) {
    // Expect exception
}


$model = Model::firstOrFail('fork_lift', 2);
$model->fill([
    'pic' => '',
    'pic_fn' => '0',
]);
assert($model->get('pic') === null, 'fill assert: "pic" must be a null');
assert($model->get('pic_fn') === null, 'fill assert: "pic_fn" must be a null');


$model = Model::firstOrFail('ware_loc', 1);
$sourceData = (array)$model->getData();
$model->fill([
    'row' => '00',
    'tn_packet_id' => '10',
]);
$model->update();

$actualModel = Model::firstOrFail('ware_loc', 1);
$diffData = array_diff_assoc((array)$model->getData(), (array)$actualModel->getData());
assert(count($diffData) == 0, 'Two models data must be equal');

$model->fill($sourceData);
$model->update();

$actualModel = Model::firstOrFail('ware_loc', 1);
$diffData = array_diff_assoc((array)$model->getData(), (array)$actualModel->getData());
assert(count($diffData) == 0, 'Two models data must be equal');


echo "Congratulations! All tests passed\n";