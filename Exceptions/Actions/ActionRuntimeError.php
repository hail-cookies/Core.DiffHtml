<?php namespace exface\Core\Exceptions\Actions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Exceptions\Actions\ActionExceptionTrait;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Exceptions\ActionExceptionInterface;

class ActionRuntimeError extends RuntimeException implements ActionExceptionInterface, ErrorExceptionInterface {
	
	use ActionExceptionTrait;
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Exceptions\ActionExceptionInterface::__construct()
	 */
	public function __construct (ActionInterface $action, $message, $alias = null, $previous = null) {
		parent::__construct($message, null, $previous);
		$this->set_alias($alias);
		$this->set_action($action);
	}
	
}
