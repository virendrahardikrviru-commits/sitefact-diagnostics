<?php
/**
 * Hook loader class.
 *
 * This is a simple service for registering WordPress hooks in a testable
 * way. Rather than calling add_action/add_filter directly, this allows
 * the plugin to register hooks and then execute them all at once.
 *
 * @package WPDoctor\Core
 */

namespace WPDoctor\Core;

/**
 * Class Loader
 *
 * @since 0.1.0
 */
class Loader {

	/**
	 * The array of actions registered with the loader.
	 *
	 * @var array
	 */
	private $actions = array();

	/**
	 * The array of filters registered with the loader.
	 *
	 * @var array
	 */
	private $filters = array();

	/**
	 * Add a new action to the collection to be registered with WordPress.
	 *
	 * @since 0.1.0
	 *
	 * @param string $hook             The name of the WordPress action hook.
	 * @param object $component        A reference to the instance of the object on which the action is defined.
	 * @param string $callback         The name of the function definition on the $component.
	 * @param int    $priority         Optional. The priority at which the function should be fired. Default 10.
	 * @param int    $accepted_args    Optional. The number of arguments that should be passed to the $callback. Default 1.
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
	}

	/**
	 * Add a new filter to the collection to be registered with WordPress.
	 *
	 * @since 0.1.0
	 *
	 * @param string $hook             The name of the WordPress filter hook.
	 * @param object $component        A reference to the instance of the object on which the filter is defined.
	 * @param string $callback         The name of the function definition on the $component.
	 * @param int    $priority         Optional. The priority at which the function should be fired. Default 10.
	 * @param int    $accepted_args    Optional. The number of arguments that should be passed to the $callback. Default 1.
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
	}

	/**
	 * Register all of the hooks with WordPress.
	 *
	 * @since 0.1.0
	 */
	public function run() {
		foreach ( $this->actions as $hook ) {
			add_action(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		foreach ( $this->filters as $hook ) {
			add_filter(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}
	}
}