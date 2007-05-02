<?php
/**
 * File containing the ezcWorkflowDefinition interface.
 *
 * @package Workflow
 * @version //autogen//
 * @copyright Copyright (C) 2005-2007 eZ systems as. All rights reserved.
 * @license http://ez.no/licenses/new_bsd New BSD License
 */

/**
 * Interface for workflow definition storage handlers.
 *
 * @package Workflow
 * @version //autogen//
 */
interface ezcWorkflowDefinition
{
    /**
     * Load a workflow definition by name.
     *
     * @param  string  $workflowName
     * @param  integer $workflowVersion
     * @return ezcWorkflow
     * @throws ezcWorkflowDefinitionException
     */
    public function loadByName( $workflowName, $workflowVersion = 0 );

    /**
     * Save a workflow definition to the database.
     *
     * @param  ezcWorkflow $workflow
     * @throws ezcWorkflowDefinitionException
     */
    public function save( ezcWorkflow $workflow );
}
?>
