<?php
/**
 * File containing the ezcWorkflowDefinitionStorageXml class.
 *
 * @package Workflow
 * @version //autogen//
 * @copyright Copyright (C) 2005-2007 eZ systems as. All rights reserved.
 * @license http://ez.no/licenses/new_bsd New BSD License
 */

/**
 * XML workflow definition storage handler.
 *
 * The definitions are stored inside the directory specified to the constructor with the name:
 * [workFlowName]_[workFlowVersion].xml where the name of the workflow has dots and spaces
 * replaced by '_'.
 *
 * @todo DTD for the XML file.
 * @package Workflow
 * @version //autogen//
 */
class ezcWorkflowDefinitionStorageXml implements ezcWorkflowDefinitionStorage
{
    /**
     * The directory that holds the XML files.
     *
     * @var string
     */
    protected $directory;

    /**
     * Constructs a new definition loader that loads definitions from $directory.
     *
     * $directory must contain the trailing '/'
     *
     * @param  string $directory The directory that holds the XML files.
     */
    public function __construct( $directory = '' )
    {
        $this->directory = $directory;
    }

    /**
     * Load a workflow definition by name.
     *
     * If the parameter $workflowVersion is omitted the most recent version is loaded.
     *
     * @param  string  $workflowName
     * @param  int $workflowVersion
     * @return ezcWorkflow
     * @throws ezcWorkflowDefinitionStorageException
     */
    public function loadByName( $workflowName, $workflowVersion = 0 )
    {
        if ( $workflowVersion == 0 )
        {
            // Load the latest version of the workflow definition by default.
            $workflowVersion = $this->getCurrentVersion( $workflowName );
        }

        $filename = $this->getFilename( $workflowName, $workflowVersion );

        // Load the document.
        $document = new DOMDocument;

        if ( is_readable( $filename ) )
        {
            libxml_use_internal_errors( true );

            $loaded = @$document->load( $filename );

            if ( $loaded === false )
            {
                $message = '';

                foreach ( libxml_get_errors() as $error )
                {
                    $message .= $error->message;
                }

                throw new ezcWorkflowDefinitionStorageException(
                  sprintf(
                    'Could not load workflow "%s" (version %d) from "%s".%s',

                    $workflowName,
                    $workflowVersion,
                    $filename,
                    $message != '' ? "\n" . $message : ''
                  )
                );
            }
        }
        else
        {
            throw new ezcWorkflowDefinitionStorageException(
              sprintf(
                'Could not read file "%s".',
                $filename
              )
            );
        }

        // Create node objects.
        $nodes = array();

        foreach ( $document->getElementsByTagName( 'node' ) as $node )
        {
            $id            = (int)$node->getAttribute( 'id' );
            $className     = 'ezcWorkflowNode' . $node->getAttribute( 'type' );
            $configuration = '';

            if ( class_exists( $className ) )
            {
                $configuration = call_user_func_array(
                  array( $className, 'configurationFromXML' ), array( $node )
                );
            }

            $nodes[$id] = new $className( $configuration );
            $nodes[$id]->setId( $id );

            if ( $className == 'ezcWorkflowNodeStart' )
            {
                $startNode = $nodes[$id];
            }

            else if ( $className == 'ezcWorkflowNodeEnd' &&
                      !isset( $defaultEndNode ) )
            {
                $defaultEndNode = $nodes[$id];
            }
        }

        // Connect node objects.
        foreach ( $document->getElementsByTagName( 'node' ) as $node )
        {
            $id        = (int)$node->getAttribute( 'id' );
            $className = 'ezcWorkflowNode' . $node->getAttribute( 'type' );

            foreach ( $node->getElementsByTagName( 'outNode' ) as $outNode )
            {
                $nodes[$id]->addOutNode( $nodes[(int)$outNode->getAttribute( 'id' )] );
            }

            if ( is_subclass_of( $className, 'ezcWorkflowNodeConditionalBranch' ) )
            {
                foreach ( $node->childNodes as $childNode )
                {
                    if ( $childNode instanceof DOMElement && $childNode->tagName == 'condition' )
                    {
                        $condition = $this->xmlToCondition( $childNode );

                        foreach ( $childNode->getElementsByTagName( 'outNode' ) as $outNode )
                        {
                            $nodes[$id]->addConditionalOutNode(
                              $condition,
                              $nodes[(int)$outNode->getAttribute( 'id' )]
                            );
                        }
                    }
                }
            }
        }

        // Create workflow object and add the node objects to it.
        $workflow = new ezcWorkflow( $workflowName, $startNode, $defaultEndNode );
        $workflow->definitionStorage = $this;
        $workflow->version = $workflowVersion;

        // Handle the variable handlers.
        foreach ( $document->getElementsByTagName( 'variableHandler' ) as $variableHandler )
        {
            $workflow->addVariableHandler(
              $variableHandler->getAttribute( 'variable' ),
              $variableHandler->getAttribute( 'class' )
            );
        }

        // Verify the loaded workflow.
        $workflow->verify();

        return $workflow;
    }

    /**
     * Save a workflow definition.
     *
     * @param  ezcWorkflow $workflow
     * @throws ezcWorkflowDefinitionStorageException
     */
    public function save( ezcWorkflow $workflow )
    {
        $workflowVersion = $this->getCurrentVersion( $workflow->name ) + 1;
        $filename = $this->getFilename( $workflow->name, $workflowVersion );

        $document = new DOMDocument( '1.0', 'UTF-8' );
        $document->formatOutput = true;

        $root = $document->createElement( 'workflow' );
        $document->appendChild( $root );

        $root->setAttribute( 'name', $workflow->name );
        $root->setAttribute( 'version', $workflowVersion );

        $nodes    = $workflow->nodes;
        $numNodes = count( $nodes );

        // Workaround for foreach() bug in PHP 5.2.1.
        $keys = array_keys( $nodes );

        for ( $i = 0; $i < $numNodes; $i++ )
        {
            $id        = $keys[$i];
            $node      = $nodes[$id];
            $nodeClass = get_class( $node );

            $xmlNode = $document->createElement( 'node' );
            $xmlNode->setAttribute( 'id', $id );
            $xmlNode->setAttribute(
              'type',
              str_replace( 'ezcWorkflowNode', '', get_class( $node ) )
            );

            $node->configurationtoXML( $xmlNode );
            $root->appendChild( $xmlNode );

            foreach ( $node->getOutNodes() as $outNode )
            {
                foreach ( $nodes as $outNodeId => $_node )
                {
                    if ( $_node === $outNode )
                    {
                        break;
                    }
                }

                $xmlOutNode = $document->createElement( 'outNode' );
                $xmlOutNode->setAttribute( 'id', $outNodeId );

                if ( is_subclass_of( $nodeClass, 'ezcWorkflowNodeConditionalBranch' ) &&
                      $condition = $node->getCondition( $outNode ) )
                {
                    $xmlCondition = self::conditionToXml(
                      $condition,
                      $document
                    );

                    $xmlCondition->appendChild( $xmlOutNode );
                    $xmlNode->appendChild( $xmlCondition );
                }
                else
                {
                    $xmlNode->appendChild( $xmlOutNode );
                }
            }
        }

        foreach ( $workflow->getVariableHandlers() as $variable => $class )
        {
            $variableHandler = $root->appendChild(
              $document->createElement( 'variableHandler' )
            );

            $variableHandler->setAttribute( 'variable', $variable );
            $variableHandler->setAttribute( 'class', $class );
        }

        file_put_contents( $filename, $document->saveXML() );
    }

    /**
     * "Convert" an ezcWorkflowCondition object into an DOMElement object.
     *
     * @param  ezcWorkflowCondition $condition
     * @param  DOMDocument $document
     * @return DOMElement
     */
    public static function conditionToXml( ezcWorkflowCondition $condition, DOMDocument $document )
    {
        $xmlCondition = $document->createElement( 'condition' );

        $conditionClass = get_class( $condition );
        $conditionType  = str_replace( 'ezcWorkflowCondition', '', $conditionClass );

        $xmlCondition->setAttribute( 'type', $conditionType );

        switch ( $conditionClass )
        {
            case 'ezcWorkflowConditionVariable': {
                $xmlCondition->setAttribute( 'name', $condition->getVariableName() );

                $xmlCondition->appendChild(
                  self::conditionToXml( $condition->getCondition(), $document )
                );
            }
            break;

            case 'ezcWorkflowConditionVariables': {
                list( $variableNameA, $variableNameB ) = $condition->getVariableNames();

                $xmlCondition->setAttribute( 'a', $variableNameA );
                $xmlCondition->setAttribute( 'b', $variableNameB );

                $xmlCondition->appendChild(
                  self::conditionToXml( $condition->getCondition(), $document )
                );
            }
            break;

            case 'ezcWorkflowConditionAnd':
            case 'ezcWorkflowConditionOr':
            case 'ezcWorkflowConditionXor': {
                foreach ( $condition->getConditions() as $childCondition )
                {
                    $xmlCondition->appendChild(
                      self::conditionToXml( $childCondition, $document )
                    );
                }
            }
            break;

            case 'ezcWorkflowConditionNot': {
                $xmlCondition->appendChild(
                  self::conditionToXml( $condition->getCondition(), $document )
                );
            }
            break;

            case 'ezcWorkflowConditionIsEqual':
            case 'ezcWorkflowConditionIsEqualOrGreaterThan':
            case 'ezcWorkflowConditionIsEqualOrLessThan':
            case 'ezcWorkflowConditionIsGreaterThan':
            case 'ezcWorkflowConditionIsLessThan':
            case 'ezcWorkflowConditionIsNotEqual': {
                $xmlCondition->setAttribute( 'value', $condition->getValue() );
            }
            break;
        }

        return $xmlCondition;
    }

    /**
     * "Convert" an DOMElement object into an ezcWorkflowCondition object.
     *
     * @param  DOMElement $element
     * @return ezcWorkflowCondition
     */
    public static function xmlToCondition( DOMElement $element )
    {
        $class = 'ezcWorkflowCondition' . $element->getAttribute( 'type' );

        switch ( $class )
        {
            case 'ezcWorkflowConditionVariable': {
                return new $class(
                  $element->getAttribute( 'name' ),
                  self::xmlToCondition( $element->childNodes->item( 1 ) )
                );
            }
            break;

            case 'ezcWorkflowConditionVariables': {
                return new $class(
                  $element->getAttribute( 'a' ),
                  $element->getAttribute( 'b' ),
                  self::xmlToCondition( $element->childNodes->item( 1 ) )
                );
            }
            break;

            case 'ezcWorkflowConditionAnd':
            case 'ezcWorkflowConditionOr':
            case 'ezcWorkflowConditionXor': {
                $conditions = array();

                foreach ( $element->childNodes as $childNode )
                {
                    if ( $childNode instanceof DOMElement && $childNode->tagName == 'condition' )
                    {
                        $conditions[] = self::xmlToCondition( $childNode );
                    }
                }

                return new $class( $conditions );
            }
            break;

            case 'ezcWorkflowConditionNot': {
                return new $class( self::xmlToCondition( $element->childNodes->item( 1 ) ) );
            }
            break;

            case 'ezcWorkflowConditionIsEqual':
            case 'ezcWorkflowConditionIsEqualOrGreaterThan':
            case 'ezcWorkflowConditionIsEqualOrLessThan':
            case 'ezcWorkflowConditionIsGreaterThan':
            case 'ezcWorkflowConditionIsLessThan':
            case 'ezcWorkflowConditionIsNotEqual': {
                return new $class( $element->getAttribute( 'value' ) );
            }
            break;

            default: {
                return new $class;
            }
            break;
        }
    }

    /**
     * "Convert" a PHP variable into an DOMElement object.
     *
     * @param  mixed $variable
     * @param  DOMDocument $document
     * @return DOMElement
     */
    public static function variableToXml( $variable, DOMDocument $document )
    {
        if ( is_array( $variable ) )
        {
            $xmlResult = $document->createElement( 'array' );

            foreach ( $variable as $key => $value )
            {
                $element = $document->createElement( 'element' );
                $element->setAttribute( 'key', $key );
                $element->appendChild( self::variableToXml( $value, $document ) );

                $xmlResult->appendChild( $element );
            }
        }

        if ( is_object( $variable ) )
        {
            $xmlResult = $document->createElement( 'object' );
            $xmlResult->setAttribute( 'class', get_class( $variable ) );
        }

        if ( is_null( $variable ) )
        {
            $xmlResult = $document->createElement( 'null' );
        }

        if ( is_scalar( $variable ) )
        {
            $type = gettype( $variable );

            if ( is_bool( $variable ) )
            {
                $variable = $variable === true ? 'true' : 'false';
            }

            $xmlResult = $document->createElement( $type, $variable );
        }

        return $xmlResult;
    }

    /**
     * "Convert" an DOMElement object into a PHP variable.
     *
     * @param  DOMElement $element
     * @return mixed
     */
    public static function xmlToVariable( DOMElement $element )
    {
        $variable = null;

        switch ( $element->tagName )
        {
            case 'array': {
                $variable = array();

                foreach ( $element->getElementsByTagName( 'element' ) as $element )
                {
                    $variable[(string)$element->getAttribute ('key') ] = self::xmlToVariable( $element->childNodes->item( 1 ) );
                }
            }
            break;

            case 'object': {
                $className = $element->getAttribute( 'class' );

                if ( $element->hasChildNodes() )
                {
                    $arguments       = $element->childNodes->item( 1 )->childNodes;
                    $constructorArgs = array();

                    foreach ( $arguments as $argument )
                    {
                        if ( $argument instanceof DOMElement )
                        {
                            $constructorArgs[] = self::xmlToVariable( $argument );
                        }
                    }

                    $class = new ReflectionClass( $className );

                    $variable = $class->newInstanceArgs( $constructorArgs );
                    
                }
                else
                {
                    $variable = new $className;
                }
            }
            break;

            case 'boolean': {
                $variable = $element->nodeValue == 'true' ? true : false;
            }
            break;

            case 'integer':
            case 'double':
            case 'string': {
                $variable = $element->nodeValue;

                settype( $variable, $element->tagName );
            }
        }

        return $variable;
    }

    /**
     * Returns the current version number for a given workflow name.
     *
     * @param  string $workflowName
     * @return integer
     */
    protected function getCurrentVersion( $workflowName )
    {
        $workflowName = $this->getFilesystemWorkflowName( $workflowName );
        $files = glob( $this->directory . $workflowName . '_*.xml' );

        if ( !empty( $files ) )
        {
            return (int)str_replace(
              array(
                $this->directory . $workflowName . '_',
                '.xml'
              ),
              '',
              $files[count( $files ) - 1]
            );
        }
        else
        {
            return 0;
        }
    }

    /**
     * Returns the filename with path for given workflow name and version.
     *
     * The name of the workflow file is of the format [workFlowName]_[workFlowVersion].xml
     *
     * @param  string  $workflowName
     * @param  int $workflowVersion
     * @return string
     */
    protected function getFilename( $workflowName, $workflowVersion )
    {
        return sprintf(
          '%s%s_%d.xml',

          $this->directory,
          $this->getFilesystemWorkflowName( $workflowName ),
          $workflowVersion
        );
    }

    /**
     * Returns a safe filesystem name for a given workflow.
     *
     * This method replaces whitespace and '.' with '_'.
     *
     * @param  string $workflowName
     * @return string
     */
    protected function getFilesystemWorkflowName( $workflowName )
    {
        return preg_replace( '#[^\w.]#', '_', $workflowName );
    }

}
?>
