<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Form
 * @subpackage Element
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

namespace Zend\Form\Element;

use Traversable;
use Zend\Form\Element;
use Zend\Form\ElementInterface;
use Zend\Form\Exception;
use Zend\Form\Fieldset;
use Zend\Form\FieldsetInterface;
use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;
use Zend\Stdlib\PriorityQueue;

/**
 * @category   Zend
 * @package    Zend_Form
 * @subpackage Element
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Collection extends Fieldset implements InputFilterProviderInterface
{
    /**
     * Default template placeholder
     */
    const DEFAULT_TEMPLATE_PLACEHOLDER = '__index__';

    /**
     * Element used in the collection
     *
     * @var ElementInterface
     */
    protected $targetElement;

    /**
     * Initial count of target element
     *
     * @var int
     */
    protected $count = 1;

    /**
     * Are new elements allowed to be added dynamically ?
     *
     * @var bool
     */
    protected $allowAdd = true;

    /**
     * Is the template generated ?
     *
     * @var bool
     */
    protected $shouldCreateTemplate = false;

    /**
     * Placeholder used in template content for making your life easier with JavaScript
     *
     * @var string
     */
    protected $templatePlaceholder = self::DEFAULT_TEMPLATE_PLACEHOLDER;


    /**
     * Accepted options for Collection:
     * - targetElement: an array or element used in the collection
     * - count: number of times the element is added initially
     * - allowAdd: if set to true, elements can be added to the form dynamically (using JavaScript)
     * - shouldCreateTemplate: if set to true, a template is generated (inside a <span>)
     * - templatePlaceholder: placeholder used in the data template
     *
     * @param array|\Traversable $options
     * @return Collection
     */
    public function setOptions($options)
    {
        parent::setOptions($options);

        if (isset($options['target_element'])) {
            $this->setTargetElement($options['target_element']);
        }

        if (isset($options['count'])) {
            $this->setCount($options['count']);
        }

        if (isset($options['allow_add'])) {
            $this->setAllowAdd($options['allow_add']);
        }

        if (isset($options['should_create_template'])) {
            $this->setShouldCreateTemplate($options['should_create_template']);
        }

        if (isset($options['template_placeholder'])) {
            $this->setTemplatePlaceholder($options['template_placeholder']);
        }

        return $this;
    }

    /**
     * Populate values
     *
     * @param array|\Traversable $data
     */
    public function populateValues($data)
    {
        $count = $this->count;

        if ($this->targetElement instanceof FieldsetInterface) {
            foreach ($data as $key => $value) {
                if ($count > 0) {
                    $this->fieldsets[$key]->populateValues($value);
                    unset($data[$key]);

                }

                $count--;
            }
        } else {
            foreach ($data as $key => $value) {
                if ($count > 0) {
                    $this->elements[$key]->setAttribute('value', $value);
                    unset($data[$key]);

                }

                $count--;
            }
        }

        // If there are still data, this means that elements or fieldsets were dynamically added. If allowed by the user, add them
        if (!empty($data) && $this->allowAdd) {
            foreach ($data as $key => $value) {
                $elementOrFieldset = $this->createNewTargetElementInstance();
                $elementOrFieldset->setName($key);

                if ($elementOrFieldset instanceof FieldsetInterface) {
                    $elementOrFieldset->populateValues($value);
                } else {
                    $elementOrFieldset->setAttribute('value', $value);
                }

                $this->add($elementOrFieldset);
            }
        }
    }

    /**
     * Set the initial count of target element
     *
     * @param $count
     * @return Collection
     */
    public function setCount($count)
    {
        $this->count = $count > 0 ? $count : 0;
        return $this;
    }

    /**
     * Get the initial count of target element
     *
     * @return int
     */
    public function getCount()
    {
        return $this->count;
    }

    /**
     * Set the target element
     *
     * @param ElementInterface|array|Traversable $elementOrFieldset
     * @return Collection
     * @throws \Zend\Form\Exception\InvalidArgumentException
     */
    public function setTargetElement($elementOrFieldset)
    {
        if (is_array($elementOrFieldset)
            || ($elementOrFieldset instanceof Traversable && !$elementOrFieldset instanceof ElementInterface)
        ) {
            $factory = $this->getFormFactory();
            $elementOrFieldset = $factory->create($elementOrFieldset);
        }

        if (!$elementOrFieldset instanceof ElementInterface) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s requires that $elementOrFieldset be an object implementing %s; received "%s"',
                __METHOD__,
                __NAMESPACE__ . '\ElementInterface',
                (is_object($elementOrFieldset) ? get_class($elementOrFieldset) : gettype($elementOrFieldset))
            ));
        }

        $this->targetElement = $elementOrFieldset;

        return $this;
    }

    /**
     * Get target element
     *
     * @return ElementInterface|null
     */
    public function getTargetElement()
    {
        return $this->targetElement;
    }

    /**
     * Get allow add
     *
     * @param bool $allowAdd
     * @return Collection
     */
    public function setAllowAdd($allowAdd)
    {
        $this->allowAdd = (bool)$allowAdd;
        return $this;
    }

    /**
     * Get allow add
     *
     * @return bool
     */
    public function getAllowAdd()
    {
        return $this->allowAdd;
    }

    /**
     * If set to true, a template prototype is automatically added to the form to ease the creation of dynamic elements through JavaScript
     *
     * @param bool $shouldCreateTemplate
     * @return Collection
     */
    public function setShouldCreateTemplate($shouldCreateTemplate)
    {
        $this->shouldCreateTemplate = (bool)$shouldCreateTemplate;

        // If it doesn't exist yet, create it
        if ($shouldCreateTemplate) {
            $this->addTemplateElement();
        }

        return $this;
    }

    /**
     * Get if the collection should create a template
     *
     * @return bool
     */
    public function shouldCreateTemplate()
    {
        return $this->shouldCreateTemplate;
    }

    /**
     * Set the placeholder used in the template generated to help create new elements in JavaScript
     *
     * @param string $templatePlaceholder
     * @return Collection
     */
    public function setTemplatePlaceholder($templatePlaceholder)
    {
        if (is_string($templatePlaceholder)) {
            $this->templatePlaceholder = $templatePlaceholder;
        }

        return $this;
    }

    /**
     * Get the template placeholder
     *
     * @return string
     */
    public function getTemplatePlaceholder()
    {
        return $this->templatePlaceholder;
    }

    /**
     * If both count and targetElement are set, add them to the fieldset
     *
     * @return void
     */
    protected function prepareCollection()
    {
        if ($this->targetElement !== null) {
            for ($i = 0 ; $i != $this->count ; ++$i) {
                $elementOrFieldset = $this->createNewTargetElementInstance();
                $elementOrFieldset->setName($i);

                $this->add($elementOrFieldset);
            }

            // If a template is wanted, we add a "dummy" element
            if ($this->shouldCreateTemplate) {
                $this->addTemplateElement();
            }
        }
    }

    /**
     * Add a "dummy" template element to be used with JavaScript
     *
     * @return Collection
     */
    protected function addTemplateElement()
    {
        if ($this->targetElement !== null && !$this->has($this->templatePlaceholder)) {
            $elementOrFieldset = $this->createNewTargetElementInstance();
            $elementOrFieldset->setName($this->templatePlaceholder);
            $this->add($elementOrFieldset);
        }

        return $this;
    }

    /**
     * Create a deep clone of a new target element
     */
    protected function createNewTargetElementInstance()
    {
        // If targetElement is a fieldset, make a deep clone of it
        if ($this->targetElement instanceof FieldsetInterface) {
            /** @var Fieldset $targetElement */
            $targetElement = clone $this->targetElement;
            $targetElement->iterator = new PriorityQueue();

            foreach ($targetElement->byName as $key => $value) {
                $value = clone $value;
                $targetElement->byName[$key] = $value;
                $targetElement->iterator->insert($value);

                if ($value instanceof FieldsetInterface) {
                    $targetElement->fieldsets[$key] = $value;
                } elseif ($value instanceof ElementInterface) {
                    $targetElement->elements[$key] = $value;
                }
            }

            return $targetElement;
        }

        return clone $this->targetElement;
    }

    /**
     * Should return an array specification compatible with
     * {@link Zend\InputFilter\Factory::createInputFilter()}.
     *
     * @return array
     */
    public function getInputFilterSpecification()
    {
        // Ignore any template
        if ($this->shouldCreateTemplate) {
            return array(
                $this->templatePlaceholder => array(
                    'required' => false
                )
            );
        }

        return array();
    }
}
