<?php
namespace NamelessCoder\Fluid\Core\ViewHelper;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use NamelessCoder\Fluid\Core\Compiler\TemplateCompiler;
use NamelessCoder\Fluid\Core\Parser;
use NamelessCoder\Fluid\Core\Parser\SyntaxTree\NodeInterface;
use NamelessCoder\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;
use NamelessCoder\Fluid\Core\Rendering\RenderingContextInterface;
use NamelessCoder\Fluid\Core\Variables\VariableProviderInterface;

/**
 * The abstract base class for all view helpers.
 *
 * @api
 */
abstract class AbstractViewHelper implements ViewHelperInterface {

	/**
	 * Stores all \NamelessCoder\Fluid\ArgumentDefinition instances
	 * @var ArgumentDefinition[]
	 */
	protected $argumentDefinitions = array();

	/**
	 * Cache of argument definitions; the key is the ViewHelper class name, and the
	 * value is the array of argument definitions.
	 *
	 * In our benchmarks, this cache leads to a 40% improvement when using a certain
	 * ViewHelper class many times throughout the rendering process.
	 * @var array
	 */
	static private $argumentDefinitionCache = array();

	/**
	 * Current view helper node
	 * @var ViewHelperNode
	 */
	protected $viewHelperNode;

	/**
	 * Arguments array.
	 * @var array
	 * @api
	 */
	protected $arguments = array();

	/**
	 * Arguments array.
	 * @var NodeInterface[] array
	 * @api
	 */
	protected $childNodes = array();

	/**
	 * Current variable container reference.
	 * @var VariableProviderInterface
	 * @api
	 */
	protected $templateVariableContainer;

	/**
	 * @var RenderingContextInterface
	 */
	protected $renderingContext;

	/**
	 * @var \Closure
	 */
	protected $renderChildrenClosure = NULL;

	/**
	 * ViewHelper Variable Container
	 * @var ViewHelperVariableContainer
	 * @api
	 */
	protected $viewHelperVariableContainer;

	/**
	 * Specifies whether the escaping interceptors should be disabled or enabled for the result of renderChildren() calls within this ViewHelper
	 * @see isChildrenEscapingEnabled()
	 *
	 * Note: If this is NULL the value of $this->escapingInterceptorEnabled is considered for backwards compatibility
	 *
	 * @var boolean
	 * @api
	 */
	protected $escapeChildren = NULL;

	/**
	 * Specifies whether the escaping interceptors should be disabled or enabled for the render-result of this ViewHelper
	 * @see isOutputEscapingEnabled()
	 *
	 * @var boolean
	 * @api
	 */
	protected $escapeOutput = NULL;

	/**
	 * @param array $arguments
	 * @return void
	 */
	public function setArguments(array $arguments) {
		$this->arguments = $arguments;
	}

	/**
	 * @param RenderingContextInterface $renderingContext
	 * @return void
	 */
	public function setRenderingContext(RenderingContextInterface $renderingContext) {
		$this->renderingContext = $renderingContext;
		$this->templateVariableContainer = $renderingContext->getVariableProvider();
		$this->viewHelperVariableContainer = $renderingContext->getViewHelperVariableContainer();
	}

	/**
	 * Returns whether the escaping interceptors should be disabled or enabled for the result of renderChildren() calls within this ViewHelper
	 *
	 * Note: This method is no public API, use $this->escapeChildren instead!
	 *
	 * @return boolean
	 */
	public function isChildrenEscapingEnabled() {
		return $this->escapeChildren !== FALSE;
	}

	/**
	 * Returns whether the escaping interceptors should be disabled or enabled for the render-result of this ViewHelper
	 *
	 * Note: This method is no public API, use $this->escapeChildren instead!
	 *
	 * @return boolean
	 */
	public function isOutputEscapingEnabled() {
		return $this->escapeOutput !== FALSE;
	}

	/**
	 * Register a new argument. Call this method from your ViewHelper subclass
	 * inside the initializeArguments() method.
	 *
	 * @param string $name Name of the argument
	 * @param string $type Type of the argument
	 * @param string $description Description of the argument
	 * @param boolean $required If TRUE, argument is required. Defaults to FALSE.
	 * @param mixed $defaultValue Default value of argument
	 * @return \NamelessCoder\Fluid\Core\ViewHelper\AbstractViewHelper $this, to allow chaining.
	 * @throws Exception
	 * @api
	 */
	protected function registerArgument($name, $type, $description, $required = FALSE, $defaultValue = NULL) {
		if (array_key_exists($name, $this->argumentDefinitions)) {
			throw new Exception('Argument "' . $name . '" has already been defined, thus it should not be defined again.', 1253036401);
		}
		$this->argumentDefinitions[$name] = new ArgumentDefinition($name, $type, $description, $required, $defaultValue);
		return $this;
	}

	/**
	 * Overrides a registered argument. Call this method from your ViewHelper subclass
	 * inside the initializeArguments() method if you want to override a previously registered argument.
	 * @see registerArgument()
	 *
	 * @param string $name Name of the argument
	 * @param string $type Type of the argument
	 * @param string $description Description of the argument
	 * @param boolean $required If TRUE, argument is required. Defaults to FALSE.
	 * @param mixed $defaultValue Default value of argument
	 * @return \NamelessCoder\Fluid\Core\ViewHelper\AbstractViewHelper $this, to allow chaining.
	 * @throws Exception
	 * @api
	 */
	protected function overrideArgument($name, $type, $description, $required = FALSE, $defaultValue = NULL) {
		if (!array_key_exists($name, $this->argumentDefinitions)) {
			throw new Exception('Argument "' . $name . '" has not been defined, thus it can\'t be overridden.', 1279212461);
		}
		$this->argumentDefinitions[$name] = new ArgumentDefinition($name, $type, $description, $required, $defaultValue);
		return $this;
	}

	/**
	 * Sets all needed attributes needed for the rendering. Called by the
	 * framework. Populates $this->viewHelperNode.
	 * This is PURELY INTERNAL! Never override this method!!
	 *
	 * @param ViewHelperNode $node View Helper node to be set.
	 * @return void
	 */
	public function setViewHelperNode(ViewHelperNode $node) {
		$this->viewHelperNode = $node;
	}

	/**
	 * Sets all needed attributes needed for the rendering. Called by the
	 * framework. Populates $this->viewHelperNode.
	 * This is PURELY INTERNAL! Never override this method!!
	 *
	 * @param NodeInterface[] $childNodes
	 * @return void
	 */
	public function setChildNodes(array $childNodes) {
		$this->childNodes = $childNodes;
	}

	/**
	 * Called when being inside a cached template.
	 *
	 * @param \Closure $renderChildrenClosure
	 * @return void
	 */
	public function setRenderChildrenClosure(\Closure $renderChildrenClosure) {
		$this->renderChildrenClosure = $renderChildrenClosure;
	}

	/**
	 * Initialize the arguments of the ViewHelper, and call the render() method of the ViewHelper.
	 *
	 * @return string the rendered ViewHelper.
	 */
	public function initializeArgumentsAndRender() {
		$this->validateArguments();
		$this->initialize();

		return $this->callRenderMethod();
	}

	/**
	 * Call the render() method and handle errors.
	 *
	 * @return string the rendered ViewHelper
	 * @throws Exception
	 */
	protected function callRenderMethod() {
		return $this->render();
	}

	/**
	 * Initializes the view helper before invoking the render method.
	 *
	 * Override this method to solve tasks before the view helper content is rendered.
	 *
	 * @return void
	 * @api
	 */
	public function initialize() {
	}

	/**
	 * Helper method which triggers the rendering of everything between the
	 * opening and the closing tag.
	 *
	 * @return mixed The finally rendered child nodes.
	 * @api
	 */
	public function renderChildren() {
		if ($this->renderChildrenClosure !== NULL) {
			$closure = $this->renderChildrenClosure;
			return $closure();
		}
		return $this->viewHelperNode->evaluateChildNodes($this->renderingContext);
	}

	/**
	 * Helper which is mostly needed when calling renderStatic() from within
	 * render().
	 *
	 * No public API yet.
	 *
	 * @return \Closure
	 */
	protected function buildRenderChildrenClosure() {
		$self = $this;
		return function() use ($self) {
			return $self->renderChildren();
		};
	}

	/**
	 * Initialize all arguments and return them
	 *
	 * @return ArgumentDefinition[]
	 */
	public function prepareArguments() {
		$thisClassName = get_class($this);
		if (isset(self::$argumentDefinitionCache[$thisClassName])) {
			$this->argumentDefinitions = self::$argumentDefinitionCache[$thisClassName];
		} else {
			$this->initializeArguments();
			self::$argumentDefinitionCache[$thisClassName] = $this->argumentDefinitions;
		}
		return $this->argumentDefinitions;
	}

	/**
	 * Validate arguments, and throw exception if arguments do not validate.
	 *
	 * @return void
	 * @throws \InvalidArgumentException
	 */
	public function validateArguments() {
		$argumentDefinitions = $this->prepareArguments();
		foreach ($argumentDefinitions as $argumentName => $registeredArgument) {
			if ($this->hasArgument($argumentName)) {
				$value = $this->arguments[$argumentName];
				$type = $registeredArgument->getType();
				if ($value !== $registeredArgument->getDefaultValue() && $type !== 'mixed') {
					$givenType = is_object($value) ? get_class($value) : gettype($value);
					$errorException = new \InvalidArgumentException(
						'The argument "' . $argumentName . '" was registered with type "' . $type . '", but is of type "' .
						$givenType . '" in view helper "' . get_class($this) . '".',
						1256475113
					);
					if ($type === 'object') {
						if (!is_object($value)) {
							throw $errorException;
						}
					} elseif ($type === 'array' && !is_array($value)) {
						if (!$value instanceof \ArrayAccess && !$value instanceof \Traversable) {
							throw $errorException;
						}
					} elseif ($type === 'string') {
						if (is_object($value) && !method_exists($value, '__toString')) {
							throw $errorException;
						}
					} elseif ($type === 'boolean' && !is_bool($value)) {
						throw $errorException;
					} elseif (class_exists($type) && $value !== NULL && !$value instanceof $type) {
						throw $errorException;
					} elseif (is_object($value) && !is_a($value, $type, TRUE)) {
						throw $errorException;
					}
				}
			}
		}
	}

	/**
	 * Initialize all arguments. You need to override this method and call
	 * $this->registerArgument(...) inside this method, to register all your arguments.
	 *
	 * @return void
	 * @api
	 */
	public function initializeArguments() {
	}

	/**
	 * Render method you need to implement for your custom view helper.
	 *
	 * @return string rendered string, view helper specific
	 * @api
	 */
	public function render() {
		return $this->renderChildren();
	}

	/**
	 * Tests if the given $argumentName is set, and not NULL.
	 *
	 * @param string $argumentName
	 * @return boolean TRUE if $argumentName is found, FALSE otherwise
	 * @api
	 */
	protected function hasArgument($argumentName) {
		return isset($this->arguments[$argumentName]) && $this->arguments[$argumentName] !== NULL;
	}

	/**
	 * You only should override this method *when you absolutely know what you
	 * are doing*, and really want to influence the generated PHP code during
	 * template compilation directly.
	 *
	 * @param string $argumentsName
	 * @param string $closureName
	 * @param string $initializationPhpCode
	 * @param ViewHelperNode $node
	 * @param TemplateCompiler $compiler
	 * @return string
	 */
	public function compile($argumentsName, $closureName, &$initializationPhpCode, ViewHelperNode $node, TemplateCompiler $compiler) {
		return sprintf(
			'%s::renderStatic(%s, %s, $renderingContext)',
			get_class($this),
			$argumentsName,
			$closureName
		);
	}

	/**
	 * Default implementation of static rendering; useful API method if your ViewHelper
	 * when compiled is able to render itself statically to increase performance. This
	 * default implementation will simply delegate to the ViewHelperInvoker.
	 *
	 * @param array $arguments
	 * @param \Closure $renderChildrenClosure
	 * @param RenderingContextInterface $renderingContext
	 * @return mixed
	 */
	static public function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext) {
		$viewHelperClassName = get_called_class();
		return $renderingContext->getViewHelperResolver()->resolveViewHelperInvoker($viewHelperClassName)
			->invoke($viewHelperClassName, $arguments, $renderingContext, $renderChildrenClosure);
	}

	/**
	 * Save the associated ViewHelper node in a static public class variable.
	 * called directly after the ViewHelper was built.
	 *
	 * @param ViewHelperNode $node
	 * @param TextNode[] $arguments
	 * @param VariableProviderInterface $variableContainer
	 * @return void
	 */
	static public function postParseEvent(ViewHelperNode $node, array $arguments, VariableProviderInterface $variableContainer) {
	}

	/**
	 * Resets the ViewHelper state.
	 *
	 * Overwrite this method if you need to get a clean state of your ViewHelper.
	 *
	 * @return void
	 */
	public function resetState() {
	}

}
