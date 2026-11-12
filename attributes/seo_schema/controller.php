<?php

namespace Concrete\Package\SeoKit\Attribute\SeoSchema;

use SeoKit\JsonLd\Types\TypeRegistry;
use Concrete\Core\Error\ErrorList\ErrorList;
use Concrete\Core\Entity\Attribute\Value\Value\TextValue;
use Concrete\Core\Attribute\{DefaultController, FontAwesomeIconFormatter};

/**
 * Page attribute holding one schema type and its fields, stored as JSON (see TypeRegistry).
 */
class Controller extends DefaultController
{
    public $helpers = ['form'];

    protected function storedJson(): string
    {
        return is_object($this->attributeValue) ? (string) $this->attributeValue->getValue() : '';
    }

    public function getIconFormatter()
    {
        return new FontAwesomeIconFormatter('code');
    }

    public function createAttributeValue(mixed $value): ?TextValue
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $attributeValue = new TextValue();
        $attributeValue->setValue($value);

        return $attributeValue;
    }

    public function createAttributeValueFromRequest()
    {
        return $this->createAttributeValue(TypeRegistry::fromPost($this->post())['json']);
    }

    /**
     * Blank ("None") is valid; a chosen type must have its required fields.
     * Not called by the page attributes panel, which stores whatever it is sent; the form's own script warns
     * there, and TypeRegistry::nodes() refuses to output a type with a required field missing.
     */
    public function validateForm($data)
    {
        $errors = TypeRegistry::fromPost($data)['errors'];

        if ($errors === []) {
            return true;
        }

        $list = new ErrorList();

        foreach ($errors as $message) {
            $list->add($message);
        }

        return $list;
    }

    public function form(): void
    {
        $decoded = TypeRegistry::decode($this->storedJson());

        $this->set('base', substr($this->field('x'), 0, -3));
        $this->set('selected', $decoded[0] ?? null);
        $this->set('stored', $decoded === null ? [] : [$decoded[0] => $decoded[1]]);
    }

    public function composer(): void
    {
        $this->form();
    }

    public function getDisplayValue(): string
    {
        $decoded = TypeRegistry::decode($this->storedJson());

        return $decoded === null ? '' : h(t(TypeRegistry::all()[$decoded[0]]::label()));
    }
}
