<?php

namespace Zet\AntiSpam;

use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Forms\Container;
use Nette\Forms\Controls\BaseControl;
use Nette\Http\Request;
use Nette\Http\Session;
use Nette\Utils\Html;

/**
 * Class AntiSpamControl
 *
 * @author  Zechy <email@zechy.cz>
 * @package Zet\AntiSpam
 */
class AntiSpamControl extends BaseControl {
	
	# --------------------------------------------------------------------
	# Registration
	# --------------------------------------------------------------------
	/**
	 * @param array   $configuration
	 * @param Session $session
	 * @param Request $request
	 */
	public static function register(array $configuration, Session $session, \Nette\Http\IRequest $request) {
		$class = __CLASS__;
		
		Container::extensionMethod("addAntiSpam", function(
			Container $container, $name, $lockTime = null, $resendTime = null
		) use ($class, $configuration, $session, $request) {
			/** @var AntiSpamControl $control */
			$control = new $class($configuration, $session, $request, $name);
			if($lockTime !== null) $control->setLockTime($lockTime);
			if($resendTime !== null) $control->setResendTime($resendTime);
			
			$container->addComponent($control, $name, key($container->getComponents()));
			
			return $control;
		});
	}
	
	# --------------------------------------------------------------------
	# Control definition
	# --------------------------------------------------------------------
	/**
	 * @var array
	 */
	private $configuration = [
		"lockTime" => null,
		"resendTime" => null,
		"numbers" => [],
		"question" => null,
		"translate" => false
	];
	
	/**
	 * @var HiddenFields
	 */
	private $hiddenFields;
	
	/**
	 * @var QuestionGenerator
	 */
	private $question;
	
	/**
	 * @var Validator
	 */
	private $validator;
	
	/**
	 * AntiSpamControl constructor.
	 *
	 * @param array   $configuration
	 * @param Session $session
	 * @param Request $request
	 * @param string  $name
	 */
	public function __construct(array $configuration, Session $session, Request $request, $name) {
		parent::__construct($name);
		
		$this->configuration = $configuration;
		$this->validator = new Validator($request);
		$this->monitor(Presenter::class);
	}
	
	/**
	 * @param Form $parent
	 */
	protected function attached($parent): void {
		parent::attached($parent);
		
		if($parent instanceof Presenter) {
			$this->validator->setSession($parent->getSession());
		}
		
		$this->hiddenFields = new HiddenFields();
		$translator = $this->configuration["translate"] ? $this->getTranslator() : null;
		$this->question = new QuestionGenerator(
			$this->configuration["numbers"], $this->configuration["question"], $translator
		);
		
		/*$self = $this;
		$form->onAnchor[] = function() use ($form, $self) {
			if(!$form->isSubmitted()) {
				$self->validator->setQuestionResult($self->question->getResult());
				$self->validator->setLockTime($self->configuration["lockTime"]);
			}
		};*/
	}
	
	/**
	 * @param int $lockTime
	 * @return AntiSpamControl
	 */
	public function setLockTime($lockTime) {
		$this->configuration["lockTime"] = $lockTime;
		
		return $this;
	}
	
	/**
	 * @param int $resendTime
	 * @return AntiSpamControl
	 */
	public function setResendTime($resendTime) {
		$this->configuration["resendTime"] = $resendTime;
		
		return $this;
	}
	
	/**
	 * @param array $numbers
	 * @return AntiSpamControl
	 */
	public function setNumbers(array $numbers) {
		$this->configuration["numbers"] = $numbers;
		
		return $this;
	}
	
	/**
	 * @param string $question
	 * @return AntiSpamControl
	 */
	public function setQuestion($question) {
		$this->configuration["question"] = $question;
		
		return $this;
	}
	
	/**
	 * @return HiddenFields
	 */
	public function getHiddenFields() {
		return $this->hiddenFields;
	}
	
	/**
	 * @return QuestionGenerator
	 */
	public function getQuestionGenerator() {
		return $this->question;
	}
	
	/**
	 * @return Html
	 */
	public function getControl() {
		$element = parent::getControl();
		
		$this->validator->setHtmlName($this->getHtmlName());
		$this->validator->setHtmlId($this->getForm()->getElementPrototype()->getAttribute("id"));
		
		$this->hiddenFields->setHtmlName($this->getHtmlName());
		$this->hiddenFields->setHtmlId($this->getForm()->getElementPrototype()->getAttribute("id"));
		
		$this->question->setHtmlName($this->getHtmlName());
		$this->question->setHtmlId($this->getForm()->getElementPrototype()->getAttribute("id"));
		
		$element->setName("div");
		$element->addHtml($this->hiddenFields->getControls());
		$element->addHtml($this->question->getQuestion());
		
		$this->validator->setQuestionResult($this->question->getResult());
		$this->validator->setLockTime($this->configuration["lockTime"]);
		
		return $element;
	}
	
	/**
	 * @param null $caption
	 * @return \Nette\Utils\Html|string
	 */
	public function getLabel($caption = null) {
		return "";
	}
	
	/**
	 * @return mixed
	 */
	public function getValue() {
		$this->hiddenFields->setHtmlName($this->getHtmlName());
		$this->question->setHtmlName($this->getHtmlName());
		$this->validator->setHtmlName($this->getHtmlName());
		$this->validator->setHtmlId($this->getForm()->getElementPrototype()->getAttribute("id"));
		
		$this->validator->setHtmlName($this->getHtmlName());
		
		$this->validator->setFormMethod($this->form->getMethod());
		$this->validator->setHiddenInputs($this->hiddenFields->getInputs());
		$this->validator->setQuestionInput($this->question->getQuestionName());
		
		$validation = $this->validator->validateForm();
		if($validation) {
			$this->validator->setQuestionResult($this->question->getResult());
			$this->validator->setResendTime($this->configuration["resendTime"]);
		}
		
		return $validation;
	}
	
	/**
	 * @return int
	 */
	public function getError():?string {
		return $this->validator->getError();
	}
}
