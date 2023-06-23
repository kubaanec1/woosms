<?php declare(strict_types=1);

namespace BulkGate\WooSms\Ajax\Test;

/**
 * @author Lukáš Piják 2023 TOPefekt s.r.o.
 * @link https://www.bulkgate.com/
 */

use Mockery;
use Tester\{Assert, TestCase};
use BulkGate\{Plugin\Settings\Synchronizer, Plugin\Settings\Settings, WooSms\Ajax\PluginSettingsChange};

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/.mock.php';

/**
 * @testCase
 */
class PluginSettingsChangeTest extends TestCase
{
	public function testToken(): void
	{
		$plugin_settings = new PluginSettingsChange($settings = Mockery::mock(Settings::class), $synchronize = Mockery::mock(Synchronizer::class));

		$settings->shouldReceive('load')->with('main:language')->once()->andReturn('en');
		$settings->shouldReceive('set')->with('main:dispatcher', '$cron$', ['type' => 'string'])->once();
		$settings->shouldReceive('set')->with('main:synchronization', '$all$', ['type' => 'string'])->once();
		$settings->shouldReceive('set')->with('main:language', '$en$', ['type' => 'string'])->once();
		$settings->shouldReceive('set')->with('main:language_mutation', '$0$', ['type' => 'bool'])->once();
		$settings->shouldReceive('set')->with('main:delete_db', '$0$', ['type' => 'bool'])->once();
		$synchronize->shouldReceive('synchronize')->with(true)->once();

		Assert::same([
			'data' => [
				'layout' => [
					'server' => [
						'application_settings' => [
							'dispatcher' => '$cron$',
							'synchronization' => '$all$',
							'language' => '$en$',
							'language_mutation' => '$0$',
							'delete_db' => '$0$',
						],
					],
				],
			],
		], $plugin_settings->run([
				'dispatcher' => 'cron',
				'synchronization' => 'all',
				'language' => 'en',
				'language_mutation' => 0,
				'delete_db' => 0,
				'invalid' => 'xxx'
		]));
	}


	public function testLanguageRedirect(): void
	{
		$plugin_settings = new PluginSettingsChange($settings = Mockery::mock(Settings::class), $synchronize = Mockery::mock(Synchronizer::class));

		$settings->shouldReceive('load')->with('main:language')->once()->andReturn('en');
		$settings->shouldReceive('set')->with('main:dispatcher', '$cron$', ['type' => 'string'])->once();
		$settings->shouldReceive('set')->with('main:synchronization', '$all$', ['type' => 'string'])->once();
		$settings->shouldReceive('set')->with('main:language', '$cs$', ['type' => 'string'])->once();
		$settings->shouldReceive('set')->with('main:language_mutation', '$0$', ['type' => 'bool'])->once();
		$settings->shouldReceive('set')->with('main:delete_db', '$0$', ['type' => 'bool'])->once();
		$synchronize->shouldReceive('synchronize')->with(true)->once();

		Assert::same(['redirect' => 'https://eshop.com//?bulkgate-redirect=dashboard'], $plugin_settings->run([
			'dispatcher' => 'cron',
			'synchronization' => 'all',
			'language' => 'cs',
			'language_mutation' => 0,
			'delete_db' => 0,
			'invalid' => 'xxx'
		]));
	}


	public function tearDown(): void
	{
		Mockery::close();
	}
}

(new PluginSettingsChangeTest())->run();
