<?php declare(strict_types=1);

/**
 * Test: Nette\Database\SqlPreprocessor literal parameter accumulation.
 */

use Nette\Database\Explorer;
use Nette\Database\SqlLiteral;
use Nette\Database\SqlPreprocessor;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

// Initialize compatibility aliases before loading Explorer.
class_exists(Nette\Database\Connection::class);
$preprocessor = new SqlPreprocessor(new Explorer(new Nette\Database\Drivers\PDO\SQLite\Driver('sqlite::memory:')));

test('Parameterless literals preserve surrounding parameters', function () use ($preprocessor) {
	foreach (['CURRENT_TIMESTAMP', ''] as $literal) {
		[$sql, $params] = $preprocessor->process(['SELECT ?, ?, ?', 'before', new SqlLiteral($literal), 'after']);
		Assert::same("SELECT ?, $literal, ?", $sql);
		Assert::same(['before', 'after'], $params);
	}
});


test('Nested literal parameters retain their order and normalize keys', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT ?, ?, ?, ?', 'before', new SqlLiteral('COALESCE(?, ?, ?)', [
		'first' => 'outer',
		10 => new SqlLiteral('NULLIF(?, ?)', ['inner' => 7, 'null' => null]),
		'last' => false,
	]), new SqlLiteral('?', ['next']), 'after']);
	Assert::same('SELECT ?, COALESCE(?, NULLIF(?, NULL), ?), ?, ?', $sql);
	Assert::same(['before', 'outer', 7, false, 'next', 'after'], $params);

	Assert::same(['SELECT ?', ['fresh']], $preprocessor->process(['SELECT ?', new SqlLiteral('?', ['fresh'])]));
	Assert::same(['before', 'outer', 7, false, 'next', 'after'], $params);
});


test('Nested literals inherit parameter and inline modes', function () use ($preprocessor) {
	$literal = new SqlLiteral('(? + ?)', [1.25, new SqlLiteral('?', [2])]);
	Assert::same(
		['expression(?, (? + ?), ?)', [true, 1.25, 2, 'tail']],
		$preprocessor->process(['expression(?, ?, ?)', true, $literal, 'tail'], true),
	);
	Assert::same(
		["expression('O''Reilly', (1.25 + 2), 0)", []],
		$preprocessor->process(['expression(?, ?, ?)', "O'Reilly", $literal, false]),
	);
});
