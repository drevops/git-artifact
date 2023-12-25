<?php
/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */

use DrevOps\Robo\ArtefactTrait;
use Robo\Tasks;

/**
 * Class RoboFile.
 */
class RoboFile extends Tasks
{

    use ArtefactTrait {
        ArtefactTrait::__construct as private __artifactConstruct;
    }

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->__artifactConstruct();
    }
}
