<?php

namespace ApacheSolrForTypo3\Solr\System\Configuration;

use InvalidArgumentException;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\Exception;

/**
 * TypoScript configuration object, used to read all TypoScript configuration.
 *
 * The TypoScriptConfiguration was introduced in order to be able to replace the old,
 * array based configuration with one configuration object.
 *
 * To read the configuration, you should use
 *
 * $configuration->getValueByPath
 *
 * or
 *
 * $configuration->isValidPath
 *
 * to check if an configuration path exists.
 *
 * To ensure Backwards compatibility the TypoScriptConfiguration object implements the
 * ArrayAccess interface (offsetGet,offsetExists,offsetUnset and offsetSet)
 *
 * This was only introduced to be backwards compatible in logTerm only "getValueByPath", "isValidPath" or
 * speaking methods for configuration settings should be used!
 *
 * @author Marc Bastian Heinrichs <mbh@mbh-software.de>
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 * @package TYPO3
 * @subpackage solr
 */
class TypoScriptConfiguration implements \ArrayAccess
{

    /**
     * Holds the solr configuration
     *
     * @var array
     */
    protected $configuration = array();

    /**
     * Holds the pageId in which context the configuration was parsed
     * (normally $GLOBALS['TSFE']->id)
     */
    protected $contextPageId = 0;

    /**
     * @param array $configuration
     * @param integer $contextPageId
     */
    public function __construct(array $configuration, $contextPageId = 0)
    {
        $this->configuration = $configuration;
        $this->contextPageId = $contextPageId;
    }

    /**
     * Checks if a value is 1, '1', 'true'
     * @param mixed $value
     * @return boolean
     */
    protected function getBool($value)
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Gets the value from a given TypoScript path.
     *
     * In the context of an frontend content element the path plugin.tx_solr is
     * merged recursive with overrule with the content element specific typoscript
     * settings, like plugin.tx_solr_PiResults_Results, and possible flex form settings
     * (depends on the solr plugin).
     *
     * Example: plugin.tx_solr.search.targetPage
     * returns $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['search.']['targetPage']
     *
     * @param string $path TypoScript path
     * @return array The TypoScript object defined by the given path
     * @throws InvalidArgumentException
     */
    public function getValueByPath($path)
    {
        if (!is_string($path)) {
            throw new InvalidArgumentException('Parameter $path is not a string',
                1325623321);
        }

        $pathExploded = explode('.', trim($path));
        $pathBranch = $this->configuration;

        $segmentCount = count($pathExploded);
        for ($i = 0; $i < $segmentCount; $i++) {
            $segment = $pathExploded[$i];

            if ($i == ($segmentCount - 1)) {
                $pathBranch = $pathBranch[$segment];
            } else {
                $pathBranch = $pathBranch[$segment . '.'];
            }
        }

        return $pathBranch;
    }

    /**
     * This method can be used to get  a configuration value by path if it exists or return a
     * default value when it does not exist.
     *
     * @param string $path
     * @param mixed $defaultValue
     * @return mixed
     */
    public function getValueByPathOrDefaultValue($path, $defaultValue)
    {
        $value = $this->getValueByPath($path);
        if (is_null($value)) {
            return $defaultValue;
        }

        return $value;
    }

    /**
     * Gets the parent TypoScript Object from a given TypoScript path.
     *
     * In the context of an frontend content element the path plugin.tx_solr is
     * merged recursive with overrule with the content element specific typoscript
     * settings, like plugin.tx_solr_PiResults_Results, and possible flex form settings
     * (depends on the solr plugin).
     *
     * Example: plugin.tx_solr.index.queue.tt_news.fields.content
     * returns $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['index.']['queue.']['tt_news.']['fields.']['content.']
     * which is a SOLR_CONTENT cObj.
     *
     * @param string $path TypoScript path
     * @return array The TypoScript object defined by the given path
     * @throws InvalidArgumentException
     */
    public function getObjectByPath($path)
    {
        if (!is_string($path)) {
            throw new InvalidArgumentException('Parameter $path is not a string',
                1325627243);
        }

        $pathExploded = explode('.', trim($path));
        // remove last object
        $lastPathSegment = array_pop($pathExploded);
        $pathBranch = $this->configuration;

        foreach ($pathExploded as $segment) {
            if (!array_key_exists($segment . '.', $pathBranch)) {
                throw new InvalidArgumentException(
                    'TypoScript object path "' . htmlspecialchars($path) . '" does not exist',
                    1325627264
                );
            }
            $pathBranch = $pathBranch[$segment . '.'];
        }

        return $pathBranch;
    }

    /**
     * Gets the parent TypoScript Object from a given TypoScript path and if not present return
     * the default value
     *
     * @see getObjectByPath
     * @param string $path
     * @param array $defaultValue
     * @return array
     */
    public function getObjectByPathOrDefault($path, array $defaultValue)
    {
        try {
            $object = $this->getObjectByPath($path);
        } catch (\InvalidArgumentException $e) {
            return $defaultValue;
        }

        if (!is_array($object)) {
            return $defaultValue;
        }

        return $object;
    }

    /**
     * Checks whether a given TypoScript path is valid.
     *
     * @param string $path TypoScript path
     * @return boolean TRUE if the path resolves, FALSE otherwise
     */
    public function isValidPath($path)
    {
        $isValidPath = false;

        $pathValue = $this->getValueByPath($path);
        if (!is_null($pathValue)) {
            $isValidPath = true;
        }

        return $isValidPath;
    }

    /**
     * Merges a configuration with another configuration a
     *
     * @param array $configurationToMerge
     * @return TypoScriptConfiguration
     */
    public function mergeSolrConfiguration(array $configurationToMerge)
    {
        ArrayUtility::mergeRecursiveWithOverrule(
            $this->configuration['plugin.']['tx_solr.'],
            $configurationToMerge
        );

        return $this;
    }

    /**
     * This method is used to allow the usage of the new configuration object with the array_key,
     * same to the previous configuration.
     *
     * isset($config['tx_solr']['configPath']);
     *
     *
     * @deprecated since 4.0, use TypoScriptConfiguration::isValidPath() instead, will be removed in version 5.0
     * introduced to track the old array style usage
     * @param  string $offset
     * @return boolean
     */
    public function offsetExists($offset)
    {
        GeneralUtility::logDeprecatedFunction();
        if (!isset($this->configuration['plugin.']['tx_solr.'])) {
            return false;
        }

        if (!is_array($this->configuration['plugin.']['tx_solr.'])) {
            return false;
        }

        return array_key_exists($offset, $this->configuration['plugin.']['tx_solr.']);
    }

    /**
     * This method is used to allow the usage of the new configuration object with the array_key,
     * same to the previous configuration.
     *
     * $config['tx_solr']['configPath'];
     *
     * @deprecated since 4.0, use TypoScriptConfiguration::getValueByPath() instead, will be removed in version 5.0
     * introduced to track the old array style usage
     * @param string $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        GeneralUtility::logDeprecatedFunction();

        if (!$this->offsetExists($offset)) {
            return null;
        }

        return $this->configuration['plugin.']['tx_solr.'][$offset];
    }

    /**
     * Throws an exception because the configuration should not be changed from outside.

     * @deprecated since 4.0 will be removed in version 5.0 introduced to track the old array style usage
     * @throws \Exception
     */
    public function offsetSet($offset, $value)
    {
        GeneralUtility::logDeprecatedFunction();

        throw new \Exception('The configuration is readonly');
    }

    /**
     * Throws an exception because the configuration options should not be unsetted from outside.
     *
     * @deprecated since 4.0 will be removed in version 5.0 introduced to track the old array style usage
     * @throws \Exception
     */
    public function offsetUnset($offset)
    {
        GeneralUtility::logDeprecatedFunction();

        throw new \Exception('The configuration is readonly');
    }


    /**
     * Returns the configured css file for a specific fileKey.
     *
     * plugin.tx_solr.cssFiles.<fileKey>
     *
     * @param string $fileKey
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getCssFileByFileKey($fileKey, $defaultIfEmpty = '')
    {
        $cssFileName =  $this->getValueByPathOrDefaultValue('plugin.tx_solr.cssFiles.' . $fileKey, $defaultIfEmpty);
        return (string) $cssFileName;
    }


    /**
     * Returns the fieldProcessingInstructions configuration array
     *
     * plugin.tx_solr.index.fieldProcessingInstructions.
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getIndexFieldProcessingInstructionsConfiguration(array $defaultIfEmpty = array())
    {
        $result = $this->getObjectByPathOrDefault('plugin.tx_solr.index.fieldProcessingInstructions.', $defaultIfEmpty);
        return $result;
    }

    /**
     * Returns the configured javascript file for a specific fileKey.
     *
     * plugin.tx_solr.javascriptFiles.<fileKey>
     *
     * @param string $fileKey
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getJavaScriptFileByFileKey($fileKey, $defaultIfEmpty = '')
    {
        $javaScriptFileName =  $this->getValueByPathOrDefaultValue('plugin.tx_solr.javascriptFiles.' . $fileKey, $defaultIfEmpty);
        return (string) $javaScriptFileName;
    }

    /**
     * Returns the configuration where to load the javascript
     *
     * plugin.tx_solr.javascriptFiles.loadIn
     *
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getJavaScriptLoadIn($defaultIfEmpty = 'footer')
    {
        $loadIn =  $this->getValueByPathOrDefaultValue('plugin.tx_solr.javascriptFiles.loadIn', $defaultIfEmpty);
        return (string) $loadIn;
    }

    /**
     * Returns the _LOCAL_LANG configuration from the TypoScript.
     *
     * plugin.tx_solr._LOCAL_LANG.
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getLocalLangConfiguration(array $defaultIfEmpty = array())
    {
        $result = $this->getObjectByPathOrDefault('plugin.tx_solr._LOCAL_LANG.', $defaultIfEmpty);
        return $result;
    }

    /**
     * Returns if query filters should be written to the log.
     *
     * plugin.tx_solr.logging.query.filters
     *
     * @param boolean $defaultIfEmpty
     * @return boolean
     */
    public function getLoggingQueryFilters($defaultIfEmpty = false)
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.logging.query.filters', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns if the querystring should be logged or not.
     *
     * plugin.tx_solr.logging.query.queryString
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getLoggingQueryQueryString($defaultIfEmpty = false)
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.logging.query.queryString', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns if the searchWords should be logged or not.
     *
     * plugin.tx_solr.logging.query.searchWords
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getLoggingQuerySearchWords($defaultIfEmpty = false)
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.logging.query.searchWords', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns if the rawGet requests should be logged or not.
     *
     * plugin.tx_solr.logging.query.rawGet
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getLoggingQueryRawGet($defaultIfEmpty = false)
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.logging.query.rawGet', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns if the rawPost requests should be logged or not.
     *
     * plugin.tx_solr.logging.query.rawPost
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getLoggingQueryRawPost($defaultIfEmpty = false)
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.logging.query.rawPost', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns if exceptions should be logged or not.
     *
     * plugin.tx_solr.logging.exceptions
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getLoggingExceptions($defaultIfEmpty = true)
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.logging.exceptions', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns if indexing operations should be logged or not.
     *
     * plugin.tx_solr.logging.indexing
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getLoggingIndexing($defaultIfEmpty = false)
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.logging.indexing', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns if a log message should be written when a page was indexed.
     *
     * plugin.tx_solr.logging.indexing.pageIndexed

     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getLoggingPageIndexed($defaultIfEmpty = false)
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.logging.indexing.pageIndexed', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns if a log message should be written when the TYPO3 search markers are missing in the page.
     *
     * plugin.tx_solr.logging.indexing.missingTypo3SearchMarkers

     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getLoggingMissingTypo3SearchMarkers($defaultIfEmpty = true)
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.logging.indexing.missingTypo3SearchMarkers', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Indicates if the debug mode is enabled or not.
     *
     * plugin.tx_solr.enableDebugMode

     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getEnabledDebugMode($defaultIfEmpty = false)
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.enableDebugMode', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Retrieves the complete search configuration
     *
     * plugin.tx_solr.search.
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getSearchConfiguration(array $defaultIfEmpty = array())
    {
        $result = $this->getObjectByPathOrDefault('plugin.tx_solr.search.', $defaultIfEmpty);
        return $result;
    }

    /**
     * Indicates if elevation should be used or not
     *
     * plugin.tx_solr.search.elevation
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchElevation($defaultIfEmpty = false)
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.elevation', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Indicates if elevated results should be marked
     *
     * plugin.tx_solr.search.elevation.markElevatedResults
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchElevationMarkElevatedResults($defaultIfEmpty = true)
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.elevation.markElevatedResults', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Indicates if elevation should be forced
     *
     *plugin.tx_solr.search.elevation.forceElevation
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchElevationForceElevation($defaultIfEmpty = true)
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.elevation.forceElevation', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Indicates if frequent searches should be show or not.
     *
     * plugin.tx_solr.search.frequentSearches
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchFrequentSearches($defaultIfEmpty = false)
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.frequentSearches', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns the sub configuration of the frequentSearches
     *
     * plugin.tx_solr.search.frequentSearches.
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getSearchFrequentSearchesConfiguration($defaultIfEmpty = array())
    {
        $result = $this->getObjectByPathOrDefault('plugin.tx_solr.search.frequentSearches.', $defaultIfEmpty);
        return $result;
    }

    /**
     * Retrieves the minimum font size that should be used for the frequentSearches.
     *
     * plugin.tx_solr.search.frequentSearches.minSize
     *
     * @param integer $defaultIfEmpty
     * @return integer
     */
    public function getSearchFrequentSearchesMinSize($defaultIfEmpty = 14)
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.frequentSearches.minSize', $defaultIfEmpty);
        return (int) $result;
    }

    /**
     * Retrieves the maximum font size that should be used for the frequentSearches.
     *
     * plugin.tx_solr.search.frequentSearches.minSize
     *
     * @param integer $defaultIfEmpty
     * @return integer
     */
    public function getSearchFrequentSearchesMaxSize($defaultIfEmpty = 32)
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.frequentSearches.maxSize', $defaultIfEmpty);
        return (int) $result;
    }

    /**
     * Indicates if frequent searches should be show or not.
     *
     * plugin.tx_solr.search.frequentSearches.useLowercaseKeywords
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchFrequentSearchesUseLowercaseKeywords($defaultIfEmpty = false)
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.frequentSearches.useLowercaseKeywords', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns the configuration if the search should be initialized with an empty query.
     *
     * plugin.tx_solr.search.initializeWithEmptyQuery
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchInitializeWithEmptyQuery($defaultIfEmpty = false)
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.initializeWithEmptyQuery', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns the configured initial query
     *
     * plugin.tx_solr.search.initializeWithQuery
     *
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getSearchInitializeWithQuery($defaultIfEmpty = '')
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.initializeWithQuery', $defaultIfEmpty);
        return (string) $result;
    }

    /**
     * Returns if the last searches should be displayed or not.
     *
     * plugin.tx_solr.search.lastSearches
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchLastSearches($defaultIfEmpty = false)
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.lastSearches', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns the lastSearch mode. "user" for user specific
     *
     * plugin.tx_solr.search.lastSearches.mode
     *
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getSearchLastSearchesMode($defaultIfEmpty = 'user')
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.lastSearches.mode', $defaultIfEmpty);
        return (string) $result;
    }

    /**
     * Returns the lastSearch limit
     *
     * plugin.tx_solr.search.lastSearches.limit
     *
     * @param int $defaultIfEmpty
     * @return int
     */
    public function getSearchLastSearchesLimit($defaultIfEmpty = 10)
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.lastSearches.limit', $defaultIfEmpty);
        return (int) $result;
    }

    /**
     * Returns the search results fieldProcessingInstructions configuration array
     *
     * plugin.tx_solr.search.results.fieldProcessingInstructions.
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getSearchResultsFieldProcessingInstructionsConfiguration(array $defaultIfEmpty = array())
    {
        $result = $this->getObjectByPathOrDefault('plugin.tx_solr.search.results.fieldProcessingInstructions.', $defaultIfEmpty);
        return $result;
    }

    /**
     * Returns the search results fieldRenderingInstructions configuration array
     *
     * plugin.tx_solr.search.results.fieldRenderingInstructions.
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getSearchResultsFieldRenderingInstructionsConfiguration(array $defaultIfEmpty = array())
    {
        $result = $this->getObjectByPathOrDefault('plugin.tx_solr.search.results.fieldRenderingInstructions.', $defaultIfEmpty);
        return $result;
    }

    /**
     * Indicates if the results of an initial empty query should be shown or not.
     *
     * plugin.tx_solr.search.showResultsOfInitialEmptyQuery
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchShowResultsOfInitialEmptyQuery($defaultIfEmpty = false)
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.showResultsOfInitialEmptyQuery', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Indicates if the results of an initial search query should be shown.
     *
     * plugin.tx_solr.search.showResultsOfInitialQuery
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchShowResultsOfInitialQuery($defaultIfEmpty = false)
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.showResultsOfInitialQuery', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Indicates if sorting was enabled or not.
     *
     * plugin.tx_solr.search.sorting
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchSorting($defaultIfEmpty = false)
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.sorting', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns the sorting options configurations.
     *
     * plugin.tx_solr.search.sorting.options.
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getSearchSortingOptionsConfiguration($defaultIfEmpty = array())
    {
        $result = $this->getObjectByPathOrDefault('plugin.tx_solr.search.sorting.options.', $defaultIfEmpty);
        return $result;
    }

    /**
     * Retrieves the sorting default order for a sort option.
     *
     * plugin.tx_solr.search.sorting.options.<sortOptionName>.defaultOrder
     *
     * or
     *
     * plugin.tx_solr.search.sorting.defaultOrder
     *
     *
     * @param string $sortOptionName
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getSearchSortingDefaultOrderBySortOptionName($sortOptionName = '', $defaultIfEmpty = 'asc')
    {
        $sortOrderSpecificPath = 'plugin.tx_solr.search.sorting.options.' . $sortOptionName . '.defaultOrder';
        $specificSortOrder = $this->getValueByPathOrDefaultValue($sortOrderSpecificPath, null);

        // if we have a concrete setting, use it
        if ($specificSortOrder !== null) {
            return $specificSortOrder;
        }

        // no specific setting, check common setting
        $commonPath = 'plugin.tx_solr.search.sorting.defaultOrder';
        $commonATagParamOrDefaultValue = $this->getValueByPathOrDefaultValue($commonPath, $defaultIfEmpty);
        return $commonATagParamOrDefaultValue;
    }


    /**
     * Returns the configured fixedOrder, if nothing configured defaultIfEmpty will be returned.
     *
     * plugin.tx_solr.search.sorting.options.<sortOptionName>.fixedOrder
     *
     * @param string $sortOptionName
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getSearchSortingFixedOrderBySortOptionName($sortOptionName = '', $defaultIfEmpty = '')
    {
        $fixedOrder = 'plugin.tx_solr.search.sorting.options.' . $sortOptionName . '.fixedOrder';
        return $this->getValueByPathOrDefaultValue($fixedOrder, $defaultIfEmpty);
    }

    /**
     * Returns the trusted fields configured for the search that do not need to be escaped.
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getSearchTrustedFieldsArray($defaultIfEmpty = array('url'))
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.trustedFields', '');

        if (trim($result) === '') {
            return $defaultIfEmpty;
        }

        return GeneralUtility::trimExplode(",", $result);
    }

    /**
     * Indicates if the plugin arguments should be kept in the search form for a second submission.
     *
     * plugin.tx_solr.search.keepExistingParametersForNewSearches
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchKeepExistingParametersForNewSearches($defaultIfEmpty = false)
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.keepExistingParametersForNewSearches', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns if an empty query is allowed on the query level.
     *
     * plugin.tx_solr.search.query.allowEmptyQuery
     *
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getSearchQueryAllowEmptyQuery($defaultIfEmpty = '')
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.query.allowEmptyQuery', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns the fieldProcessingInstructions configuration array
     *
     * plugin.tx_solr.search.query.filter.
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getSearchQueryFilterConfiguration(array $defaultIfEmpty = array())
    {
        $result = $this->getObjectByPathOrDefault('plugin.tx_solr.search.query.filter.', $defaultIfEmpty);
        return $result;
    }

    /**
     * Can be used to overwrite the filterConfiguration.
     *
     * plugin.tx_solr.search.query.filter.
     *
     * @param array $configuration
     */
    public function setSearchQueryFilterConfiguration(array $configuration)
    {
        $this->configuration['plugin.']['tx_solr.']['search.']['query.']['filter.'] = $configuration;
    }

    /**
     * Removes the pageSections filter setting.
     *
     * @return void
     */
    public function removeSearchQueryFilterForPageSections()
    {
        unset($this->configuration['plugin.']['tx_solr.']['search.']['query.']['filter.']['__pageSections']);
    }

    /**
     * Returns the configured queryFields from TypoScript
     *
     * plugin.tx_solr.search.query.queryFields
     *
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getSearchQueryQueryFields($defaultIfEmpty = '')
    {
        return $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.query.queryFields', $defaultIfEmpty);
    }


    /**
     * Returns the configured returnFields as array.
     *
     * plugin.tx_solr.search.query.returnFields
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getSearchQueryReturnFieldsAsArray($defaultIfEmpty = array())
    {
        $returnFields = $this->getValueByPath('plugin.tx_solr.search.query.returnFields');
        if (is_null($returnFields)) {
            return $defaultIfEmpty;
        }
        return GeneralUtility::trimExplode(',', $returnFields);
    }

    /**
     * Returns the configured target page for the search.
     * By default the contextPageId will be used
     *
     * plugin.tx_solr.search.targetPage
     *
     * @return integer
     */
    public function getSearchTargetPage()
    {
        $targetPage = (int) $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.targetPage', 0);
        if ($targetPage === 0) {
            // when no specific page was configured we use the contextPageId (which is usual $GLOBALS['TSFE']->id)
            $targetPage = $this->contextPageId;
        }


        return $targetPage;
    }

    /**
     * Retrieves the targetPage configuration.
     *
     * plugin.tx_solr.search.targetPage.
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getSearchTargetPageConfiguration(array $defaultIfEmpty = array())
    {
        $result = $this->getObjectByPathOrDefault('plugin.tx_solr.search.targetPage.', $defaultIfEmpty);
        return $result;
    }

    /**
     * Retrieves the pagebrowser configuration.
     *
     * plugin.tx_solr.search.results.pagebrowser.
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getSearchResultsPageBrowserConfiguration(array $defaultIfEmpty = array())
    {
        $result = $this->getObjectByPathOrDefault('plugin.tx_solr.search.results.pagebrowser.', $defaultIfEmpty);
        return $result;
    }

    /**
     * Returns the result highlighting fields.
     *
     * plugin.tx_solr.search.results.resultsHighlighting.highlightFields
     *
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getSearchResultsHighlightingFields($defaultIfEmpty = '')
    {
        return $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.results.resultsHighlighting.highlightFields', $defaultIfEmpty);
    }

    /**
     * Returns the result highlighting fields as array.
     *
     * plugin.tx_solr.search.results.resultsHighlighting.highlightFields
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getSearchResultsHighlightingFieldsAsArray($defaultIfEmpty = array())
    {
        $highlightingFields = $this->getSearchResultsHighlightingFields('');

        if ($highlightingFields === '') {
            return $defaultIfEmpty;
        }

        return GeneralUtility::trimExplode(',', $highlightingFields, true);
    }

    /**
     * Returns the fragmentSeparator for highlighted segments.
     *
     * plugin.tx_solr.search.results.resultsHighlighting.fragmentSeparator
     *
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getSearchResultsHighlightingFragmentSeparator($defaultIfEmpty = '[...]')
    {
        return $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.results.resultsHighlighting.fragmentSeparator', $defaultIfEmpty);
    }

    /**
     * Returns the number of results that should be shown per page.
     *
     * plugin.tx_solr.search.results.resultsPerPage
     *
     * @param int $defaultIfEmpty
     * @return int
     */
    public function getSearchResultsPerPage($defaultIfEmpty = 10)
    {
        return (int) $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.results.resultsPerPage', $defaultIfEmpty);
    }

    /**
     * Returns the available options for the per page switch.
     *
     * plugin.tx_solr.search.results.resultsPerPageSwitchOptions
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getSearchResultsPerPageSwitchOptionsAsArray($defaultIfEmpty = array())
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.results.resultsPerPageSwitchOptions', '');

        if (trim($result) === '') {
            return $defaultIfEmpty;
        }

        return GeneralUtility::intExplode(',', $result, true);
    }

    /**
     * Return if the fastVectorHighlighter should be used or not.
     *
     * plugin.tx_solr.search.results.resultsHighlighting.useFastVectorHighlighter
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchResultsHighlightingUseFastVectorHighlighter($defaultIfEmpty = false)
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.results.resultsHighlighting.useFastVectorHighlighter', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns the configured wrap for the resultHighlighting.
     *
     * plugin.tx_solr.search.results.resultsHighlighting.wrap
     *
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getSearchResultsHighlightingWrap($defaultIfEmpty = '')
    {
        return $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.results.resultsHighlighting.wrap', $defaultIfEmpty);
    }

    /**
     * Indicates if spellchecking is enabled or not.
     *
     * plugin.tx_solr.search.spellchecking
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchSpellchecking($defaultIfEmpty = false)
    {
        $isFacetingEnabled =  $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.spellchecking', $defaultIfEmpty);
        return $this->getBool($isFacetingEnabled);
    }

    /**
     * Returns the wrap that should be used for spellchecking
     *
     * plugin.tx_solr.search.spellchecking.wrap
     *
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getSearchSpellcheckingWrap($defaultIfEmpty = '')
    {
        return (string) $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.spellchecking.wrap', $defaultIfEmpty);
    }

    /**
     * Returns the numberOfSuggestionsToTry that should be used for the spellchecking.
     *
     * plugin.tx_solr.search.spellchecking.numberOfSuggestionsToTry
     *
     * @param integer $defaultIfEmpty
     * @return integer
     */
    public function getSearchSpellcheckingNumberOfSuggestionsToTry($defaultIfEmpty = 0)
    {
        return (int) $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.spellchecking.numberOfSuggestionsToTry', $defaultIfEmpty);
    }

    /**
     * Indicates if a second search should be fired from the spellchecking suggestion if no results could be found.
     *
     * plugin.tx_solr.search.spellchecking.searchUsingSpellCheckerSuggestion
     *
     * @param boolean $defaultIfEmpty
     * @return boolean
     */
    public function getSearchSpellcheckingSearchUsingSpellCheckerSuggestion($defaultIfEmpty = false)
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.spellchecking.searchUsingSpellCheckerSuggestion', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Indicates if faceting is enabled or not.
     *
     * plugin.tx_solr.search.faceting
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchFaceting($defaultIfEmpty = false)
    {
        $isFacetingEnabled =  $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.faceting', $defaultIfEmpty);
        return $this->getBool($isFacetingEnabled);
    }


    /**
     * Retrieves the facetLinkATagParams for a facet by facet name. If nothing specific is configured
     * the global facetLinkATagParams with be returned.
     *
     * plugin.tx_solr.search.faceting.facets.<facetName>.facetLinkATagParams
     *
     * or
     *
     * plugin.tx_solr.search.faceting.facetLinkATagParams
     *
     *
     * @param string $facetName
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getSearchFacetingFacetLinkATagParamsByName($facetName = '', $defaultIfEmpty = '')
    {
        $facetSpecificPath = 'plugin.tx_solr.search.faceting.facets.' . $facetName . '.facetLinkATagParams';
        $specificATagParam = $this->getValueByPathOrDefaultValue($facetSpecificPath, null);

            // if we have a concrete setting, use it
        if ($specificATagParam !== null) {
            return $specificATagParam;
        }

            // no specific setting, check common setting
        $commonPath = 'plugin.tx_solr.search.faceting.facetLinkATagParams';
        $commonATagParamOrDefaultValue = $this->getValueByPathOrDefaultValue($commonPath, $defaultIfEmpty);
        return $commonATagParamOrDefaultValue;
    }

    /**
     * Returns the test that should be used to remove a faceting link
     *
     * plugin.tx_solr.search.faceting.removeFacetLinkText
     *
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getSearchFacetingRemoveFacetLinkText($defaultIfEmpty = '@facetLabel: @facetText')
    {
        return (string) $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.faceting.removeFacetLinkText', $defaultIfEmpty);
    }

    /**
     * Retrieves the showEvenWhenEmpty for a facet by facet name. If nothing specific is configured
     * the global showEmptyFacets with be returned.
     *
     * plugin.tx_solr.search.faceting.facets.<facetName>.showEvenWhenEmpty
     *
     * or
     *
     * plugin.tx_solr.search.faceting.showEmptyFacets
     *
     *
     * @param string $facetName
     * @param boolean $defaultIfEmpty
     * @return boolean
     */
    public function getSearchFacetingShowEmptyFacetsByName($facetName = '', $defaultIfEmpty = false)
    {
        $facetSpecificPath = 'plugin.tx_solr.search.faceting.facets.' . $facetName . '.showEvenWhenEmpty';
        $specificShowWhenEmpty = $this->getValueByPathOrDefaultValue($facetSpecificPath, null);

        // if we have a concrete setting, use it
        if ($specificShowWhenEmpty !== null) {
            return $specificShowWhenEmpty;
        }

        // no specific setting, check common setting
        $commonPath = 'plugin.tx_solr.search.faceting.showEmptyFacets';
        $commonIfEmptyOrDefaultValue = $this->getValueByPathOrDefaultValue($commonPath, $defaultIfEmpty);
        return $commonIfEmptyOrDefaultValue;
    }

    /**
     * Returns the wrap for the faceting show all link
     *
     * plugin.tx_solr.search.faceting.showAllLink.wrap
     *
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getSearchFacetingShowAllLinkWrap($defaultIfEmpty = '')
    {
        return (string) $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.faceting.showAllLink.wrap', $defaultIfEmpty);
    }

    /**
     * Returns the link url parameters that should be added to a facet.
     *
     * plugin.tx_solr.search.faceting.facetLinkUrlParameters
     *
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getSearchFacetingFacetLinkUrlParameters($defaultIfEmpty = '')
    {
        $linkUrlParameters = trim($this->getValueByPathOrDefaultValue('plugin.tx_solr.search.faceting.facetLinkUrlParameters', $defaultIfEmpty));

        return $linkUrlParameters;
    }

    /**
     * Returns if the facetLinkUrlsParameters should be included in the reset link.
     *
     * plugin.tx_solr.search.faceting.facetLinkUrlParameters.useForFacetResetLinkUrl
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchFacetingFacetLinkUrlParametersUseForFacetResetLinkUrl($defaultIfEmpty = true)
    {
        $useForFacetResetLinkUrl =  $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.faceting.facetLinkUrlParameters.useForFacetResetLinkUrl', $defaultIfEmpty);
        return $this->getBool($useForFacetResetLinkUrl);
    }

    /**
     * Returns the link url parameters that should be added to a facet as array.
     *
     * plugin.tx_solr.search.faceting.facetLinkUrlParameters
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getSearchFacetingFacetLinkUrlParametersAsArray($defaultIfEmpty = array())
    {
        $linkUrlParameters = $this->getSearchFacetingFacetLinkUrlParameters();
        if ($linkUrlParameters === '') {
            return $defaultIfEmpty;
        }

        return GeneralUtility::explodeUrl2Array($linkUrlParameters);
    }

    /**
     * Return the configured minimumCount value for facets.
     *
     * plugin.tx_solr.search.faceting.minimumCount
     *
     * @param integer $defaultIfEmpty
     * @return integer
     */
    public function getSearchFacetingMinimumCount($defaultIfEmpty = 1)
    {
        return (int) $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.faceting.minimumCount', $defaultIfEmpty);
    }

    /**
     * Return the configured limit value for facets.
     *
     * plugin.tx_solr.search.faceting.limit
     *
     * @param integer $defaultIfEmpty
     * @return integer
     */
    public function getSearchFacetingLimit($defaultIfEmpty = 10)
    {
        return (int) $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.faceting.limit', $defaultIfEmpty);
    }

    /**
     * Returns if the singleFacetMode is active or not.
     *
     * plugin.tx_solr.search.faceting.singleFacetMode
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchFacetingSingleFacetMode($defaultIfEmpty = false)
    {
        $singleFacetMode =  $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.faceting.singleFacetMode', $defaultIfEmpty);
        return $this->getBool($singleFacetMode);
    }

    /**
     * Return the configured faceting sortBy value.
     *
     * plugin.tx_solr.search.faceting.sortBy
     *
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getSearchFacetingSortBy($defaultIfEmpty = '')
    {
        return $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.faceting.sortBy', $defaultIfEmpty);
    }


    /**
     * Returns if a facets should be kept on selection. Global faceting setting
     * can also be configured on facet level by using
     * (plugin.tx_solr.search.faceting.facets.<fieldName>.keepAllOptionsOnSelection)
     *
     * plugin.tx_solr.search.faceting.keepAllFacetsOnSelection
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchFacetingKeepAllFacetsOnSelection($defaultIfEmpty = false)
    {
        $keepAllOptionsOnSelection =  $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.faceting.keepAllFacetsOnSelection', $defaultIfEmpty);
        return $this->getBool($keepAllOptionsOnSelection);
    }


    /**
     * Returns the configured faceting configuration.
     *
     * plugin.tx_solr.search.faceting.facets
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getSearchFacetingFacets(array $defaultIfEmpty = array())
    {
        return $this->getObjectByPathOrDefault('plugin.tx_solr.search.faceting.facets.', $defaultIfEmpty);
    }

    /**
     * Returns the configuration of a single facet by facet name.
     *
     * plugin.tx_solr.search.faceting.facets.<facetName>
     *
     * @param string $facetName
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getSearchFacetingFacetByName($facetName, $defaultIfEmpty = array())
    {
        return $this->getObjectByPathOrDefault('plugin.tx_solr.search.faceting.facets.' . $facetName . '.', $defaultIfEmpty);
    }

    /**
     * Indicates if statistics is enabled or not.
     *
     * plugin.tx_solr.statistics
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getStatistics($defaultIfEmpty = false)
    {
        $isFacetingEnabled =  $this->getValueByPathOrDefaultValue('plugin.tx_solr.statistics', $defaultIfEmpty);
        return $this->getBool($isFacetingEnabled);
    }



    /**
     * Indicates to which length an ip should be anonymized in the statistics
     *
     * plugin.tx_solr.statistics.anonymizeIP
     *
     * @param integer $defaultIfEmpty
     * @return integer
     */
    public function getStatisticsAnonymizeIP($defaultIfEmpty = 0)
    {
        $anomizeToLength =  $this->getValueByPathOrDefaultValue('plugin.tx_solr.statistics.anonymizeIP', $defaultIfEmpty);
        return (int) $anomizeToLength;
    }

    /**
     * Indicates if suggestion is enabled or not.
     *
     * plugin.tx_solr.suggest
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSuggest($defaultIfEmpty = false)
    {
        $isSuggestionEnabled =  $this->getValueByPathOrDefaultValue('plugin.tx_solr.suggest', $defaultIfEmpty);
        return $this->getBool($isSuggestionEnabled);
    }

    /**
     * Indicates if https should be used for the suggest form.
     *
     * plugin.tx_solr.suggest.forceHttps
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSuggestForceHttps($defaultIfEmpty = false)
    {
        $isHttpsForced =  $this->getValueByPathOrDefaultValue('plugin.tx_solr.suggest.forceHttps', $defaultIfEmpty);
        return $this->getBool($isHttpsForced);
    }


    /**
     * Returns the configured template for a specific template fileKey.
     *
     * plugin.tx_solr.search.templateFiles.<fileKey>
     *
     * @param string $fileKey
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getTemplateByFileKey($fileKey, $defaultIfEmpty = '')
    {
        $templateFileName =  $this->getValueByPathOrDefaultValue('plugin.tx_solr.templateFiles.' . $fileKey, $defaultIfEmpty);
        return (string) $templateFileName;
    }

    /**
     * Returns the configuration of the crop view helper.
     *
     * plugin.tx_solr.viewHelpers.crop.
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getViewHelpersCropConfiguration(array $defaultIfEmpty = array())
    {
        $cropViewHelperConfiguration =  $this->getObjectByPathOrDefault('plugin.tx_solr.viewHelpers.crop.', $defaultIfEmpty);
        return $cropViewHelperConfiguration;
    }

    /**
     * Returns the configuration of the sorting view helper.
     *
     * plugin.tx_solr.viewHelpers.sortIndicator.
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getViewHelpersSortIndicatorConfiguration(array $defaultIfEmpty = array())
    {
        $sortingViewHelperConfiguration =  $this->getObjectByPathOrDefault('plugin.tx_solr.viewHelpers.sortIndicator.', $defaultIfEmpty);
        return $sortingViewHelperConfiguration;
    }
}
