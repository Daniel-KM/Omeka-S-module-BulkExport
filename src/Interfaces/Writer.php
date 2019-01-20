<?php
namespace BulkExport\Interfaces;

use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * A writer returns metadata and files data.
 *
 * It can have a config (implements Configurable) and parameters (implements
 * Parametrizable).
 */
interface Writer extends \Iterator, \Countable
{
    /**
     * Writer constructor.
     *
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function __construct(ServiceLocatorInterface $services);

    /**
     * @var string
     */
    public function getLabel();

    /**
     * Check if the params of the writer are valid, for example the filepath.
     *
     * @return bool
     */
    public function isValid();

    /**
     * Get the last error message, in particular to know why writer is invalid.
     *
     * @return string
     */
    public function getLastErrorMessage();

    /**
     * List of fields used in the input, for example the first spreadsheet row.
     *
     * It allows to do the mapping in the user interface.
     *
     * Note that these available fields should not be the first output when
     * `rewind()` is called.
     *
     * @return array
     */
    public function getAvailableFields();

    /**
     * {@inheritDoc}
     * @see \Iterator::current()
     *
     * @return Entry
     */
    public function current();

    /**
     * Get the number of entries that will be read to be converted in resources.
     *
     * {@inheritDoc}
     * @see \Countable::count()
     */
    public function count();
}
