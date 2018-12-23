<?php
namespace BulkImport\View\Helper;

use Omeka\View\Helper\Api;
use Zend\I18n\View\Helper\Translate;
use Zend\View\Helper\AbstractHelper;

class AutomapFields extends AbstractHelper
{
    /**
     * @var array
     */
    protected $map;

    /**
     * @var Api
     */
    protected $api;

    /**
     * @var Translate
     */
    protected $translate;

    /**
     * @param array $map
     * @param Api $api
     * @param Translate $translate
     */
    public function __construct(array $map, Api $api, Translate $translate)
    {
        $this->map = $map;
        $this->api = $api;
        $this->translate = $translate;
    }

    /**
     * Automap a list of field names with a standard Omeka metadata names.
     *
     * This is a simplified version of the full automap of an old version of the
     * module CSVImport. it returns all the input fields.
     * User mappings are replaced by the file /data/mappings/fields_to_metadata.php.
     * @see \CSVImport\Mvc\Controller\Plugin\AutomapHeadersToMetadata
     *
     * @param array $fields
     * @param array $options Associative array of options:
     * - map (array)
     * - check_names_alone (boolean)
     * @return array Associative array of all fields with their metadata names,
     * or null.
     */
    public function __invoke($fields, array $options = [])
    {
        $automaps = [];

        $defaultOptions = [
            'map' => [],
            'check_names_alone' => true,
            'resource_type' => null,
        ];
        $options += $defaultOptions;
        $this->map = array_merge($this->map, $options['map']);
        unset($options['map']);

        $fields = $this->cleanStrings($fields);

        // Prepare the standard lists to check against.
        $lists = [];
        $automapLists = [];

        // Prepare the list of names and labels one time to speed up process.
        $propertyLists = $this->listTerms();

        // The automap list is the file mapping combined with itself, with a
        // lower case version.
        $automapList = [];
        if ($this->map) {
            $automapList = $this->checkAutomapList($this->map, $propertyLists['names']);
            $automapLists['base'] = array_combine(
                array_keys($automapList),
                array_keys($automapList)
            );
            $automapLists['lower_base'] = array_map('strtolower', $automapLists['base']);
            if ($automapLists['base'] === $automapLists['lower_base']) {
                unset($automapLists['base']);
            }
        }

        // Because some terms and labels are not standardized (foaf:givenName is
        // not foaf:givenname), the process must be done case sensitive first.
        $lists['names'] = array_combine(
            array_keys($propertyLists['names']),
            array_keys($propertyLists['names'])
        );
        $lists['lower_names'] = array_map('strtolower', $lists['names']);
        $lists['labels'] = array_combine(
            array_keys($propertyLists['names']),
            array_keys($propertyLists['labels'])
        );
        $lists['lower_labels'] = array_map('strtolower', $lists['labels']);

        // Check names alone, like "Title", for "dcterms:title".
        $checkNamesAlone = !empty($options['check_names_alone']);
        if ($checkNamesAlone) {
            $lists['local_names'] = array_map(function ($v) {
                $w = explode(':', $v);
                return end($w);
            }, $lists['names']);
            $lists['lower_local_names'] = array_map('strtolower', $lists['local_names']);
            $lists['local_labels'] = array_map(function ($v) {
                $w = explode(':', $v);
                return end($w);
            }, $lists['labels']);
            $lists['lower_local_labels'] = array_map('strtolower', $lists['local_labels']);
        }

        foreach ($fields as $index => $field) {
            $lowerField = strtolower($field);
            // Check first with the specific auto-mapping list.
            foreach ($automapLists as $listName => $list) {
                $toSearch = strpos($listName, 'lower_') === 0 ? $lowerField : $field;
                $found = array_search($toSearch, $list, true);
                if ($found) {
                    // The automap list is used to keep the sensitive value.
                    $automaps[$index] = $automapList[$found];
                    continue 2;
                }
            }

            // Check strict term name, like "dcterms:title", sensitively then
            // insensitively, then term label like "Dublin Core : Title"
            // sensitively then insensitively too. Because all the lists contain
            // the same keys in the same order, the process can be done in one
            // step.
            foreach ($lists as $listName => $list) {
                $toSearch = strpos($listName, 'lower_') === 0 ? $lowerField : $field;
                $found = array_search($toSearch, $list, true);
                if ($found) {
                    $property = $propertyLists['names'][$found];
                    $automaps[$index] = $property;
                    continue 2;
                }
            }

            // Return all input fields in the same order.
            $automaps[$index] = null;
        }

        return $automaps;
    }

    /**
     * Return the list of properties by names and labels.
     *
     * @return array Associative array of term names and term labels as key
     * (ex: "dcterms:title" and "Dublin Core : Title") in two subarrays ("names"
     * "labels", and properties as value.
     * Note: Some terms are badly standardized (in foaf, the label "Given name"
     * matches "foaf:givenName" and "foaf:givenname"), so, in that case, the
     * index is added to the label, except the first property.
     */
    protected function listTerms()
    {
        $result = [];
        $vocabularies = $this->api()->search('vocabularies')->getContent();
        foreach ($vocabularies as $vocabulary) {
            $properties = $vocabulary->properties();
            if (empty($properties)) {
                continue;
            }
            foreach ($properties as $property) {
                $result['names'][$property->term()] = $property->term();
                $name = $vocabulary->label() .  ':' . $property->label();
                if (isset($result['labels'][$name])) {
                    $result['labels'][$vocabulary->label() . ':' . $property->label() . ' (#' . $property->id() . ')'] = $property->term();
                } else {
                    $result['labels'][$vocabulary->label() . ':' . $property->label()] = $property->term();
                }
            }
        }
        return $result;
    }

    /**
     * Clean and trim all whitespace, and remove spaces around colon.
     *
     * It fixes whitespaces added by some spreadsheets before or after a colon.
     *
     * @param array $strings
     * @return array
     */
    protected function cleanStrings(array $strings)
    {
        return array_map(function($string) {
            return preg_replace('~\s*:\s*~', ':', $this->cleanUnicode($string));
        }, $strings);
    }

    /**
     * Clean and trim all whitespace, included the unicode ones.
     *
     * @param string $string
     * @return string
     */
    protected function cleanUnicode($string)
    {
        return trim(preg_replace('/[\h\v\s[:blank:][:space:]]+/u', ' ', $string));
    }

    /**
     * Clean the automap list to remove old properties.
     *
     * @param array $automapList
     * @param array $propertyList
     * @return array
     */
    protected function checkAutomapList($automapList, $propertyList)
    {
        $result = $automapList;
        foreach ($automapList as $name => $value) {
            if (empty($value)) {
                unset($result[$name]);
                continue;
            }
            $isProperty = preg_match('/^[a-z0-9_-]+:[a-z0-9_-]+$/i', $value);
            if ($isProperty) {
                if (empty($propertyList[$value])) {
                    unset($result[$name]);
                } else {
                    $result[$name] = $propertyList[$value];
                }
            }
        }
        return $result;
    }

    protected function api()
    {
        return $this->api;
    }
}
