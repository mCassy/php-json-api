<?php
/**
 * Author: Nil Portugués Calderó <contact@nilportugues.com>
 * Date: 11/27/15
 * Time: 11:40 PM.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace NilPortugues\Api\JsonApi\Application\Model;

use NilPortugues\Api\JsonApi\Domain\Model\Contracts\MappingRepository;
use NilPortugues\Api\JsonApi\Domain\Model\Errors\ErrorBag;
use NilPortugues\Api\JsonApi\Domain\Model\Errors\InvalidAttributeError;
use NilPortugues\Api\JsonApi\Domain\Model\Errors\InvalidTypeError;
use NilPortugues\Api\JsonApi\Domain\Model\Errors\MissingAttributesError;
use NilPortugues\Api\JsonApi\Domain\Model\Errors\MissingDataError;
use NilPortugues\Api\JsonApi\Domain\Model\Errors\MissingTypeError;
use NilPortugues\Api\JsonApi\JsonApiTransformer;
use NilPortugues\Api\JsonApi\Domain\Model\Exceptions\InputException;

/**
 * Class DataAssertions.
 */
class DataFormatAssertion
{
    /**
     * @var MappingRepository
     */
    private $mappingRepository;

    /**
     * DataInputValidations constructor.
     *
     * @param MappingRepository $mappingRepository
     */
    public function __construct(MappingRepository $mappingRepository)
    {
        $this->mappingRepository = $mappingRepository;
    }

    /**
     * @param array  $data
     * @param string $className
     */
    public function assert($data, $className)
    {
        $errorBag = new ErrorBag();

        $this->assertItIsArray($data, $errorBag);
        $this->assertItHasTypeMember($data, $errorBag);
        $this->assertItTypeMemberIsExpectedValue($data, $className, $errorBag);
        $this->assertItHasAttributeMember($data, $errorBag);
        $this->assertAttributesExists($data, $errorBag);

        if (count($errorBag) > 0) {
            throw new InputException($errorBag);
        }
    }

    /**
     * @param          $data
     * @param ErrorBag $errorBag
     *
     * @throws InputException
     */
    protected function assertItIsArray($data, ErrorBag $errorBag)
    {
        if (empty($data) || !is_array($data)) {
            $errorBag[] = new MissingDataError();
            throw new InputException($errorBag);
        }
    }

    /**
     * @param array    $data
     * @param ErrorBag $errorBag
     *
     * @throws InputException
     */
    protected function assertItHasTypeMember(array $data, ErrorBag $errorBag)
    {
        if (empty($data[JsonApiTransformer::TYPE_KEY]) || !is_string($data[JsonApiTransformer::TYPE_KEY])) {
            $errorBag[] = new MissingTypeError();
            throw new InputException($errorBag);
        }
    }

    /**
     * @param array    $data
     * @param          $className
     * @param ErrorBag $errorBag
     *
     * @throws InputException
     */
    protected function assertItTypeMemberIsExpectedValue(
        array $data,
        $className,
        ErrorBag $errorBag
    ) {
        $mapping = $this->mappingRepository->findByAlias($data[JsonApiTransformer::TYPE_KEY]);

        if (null === $mapping || $mapping->getClassName() !== $className) {
            $errorBag[] = new InvalidTypeError($data[JsonApiTransformer::TYPE_KEY]);
            throw new InputException($errorBag);
        }
    }

    /**
     * @param          $data
     * @param ErrorBag $errorBag
     *
     * @throws InputException
     */
    protected function assertItHasAttributeMember($data, ErrorBag $errorBag)
    {
        if (empty($data[JsonApiTransformer::ATTRIBUTES_KEY]) || !is_array($data[JsonApiTransformer::ATTRIBUTES_KEY])) {
            $errorBag[] = new MissingAttributesError();
            throw new InputException($errorBag);
        }
    }

    /**
     * @param array    $data
     * @param ErrorBag $errorBag
     *
     * @throws InputException
     */
    protected function assertAttributesExists(array $data, ErrorBag $errorBag)
    {
        $inputAttributes = array_keys($data[JsonApiTransformer::ATTRIBUTES_KEY]);
        $mapping = $this->mappingRepository->findByAlias($data[JsonApiTransformer::TYPE_KEY]);

        //Remove those aliased
        $aliasedKeys = $mapping->getAliasedProperties();
        foreach ($inputAttributes as $pos => $keyName) {
            if (true === in_array($keyName, $aliasedKeys)) {
                unset($inputAttributes[$pos]);
            }
        }

        $properties = array_diff($mapping->getProperties(), $mapping->getIdProperties());
        foreach ($inputAttributes as $pos => $keyName) {
            //Remove those that are using the original names.
            if (true === in_array($keyName, $properties, true)) {
                unset($inputAttributes[$pos]);
            }
        }

        $properties = array_map(function ($v) { return strtolower($v); }, $properties);
        //Remove if under_score field  matches an existed property.
        foreach ($inputAttributes as $pos => $keyName) {
            $keyName = strtolower(str_replace('_', '', ucwords($keyName, '_')));
            if (true === in_array($keyName, $properties)) {
                unset($inputAttributes[$pos]);
            }
        }

        //Remaining should be here.
        foreach ($inputAttributes as $property) {
            $errorBag[] = new InvalidAttributeError($property, $data[JsonApiTransformer::TYPE_KEY]);
        }
    }
}
